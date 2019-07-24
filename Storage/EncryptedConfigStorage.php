<?php
/**
 * ConfigStorage
 *
 * @copyright Copyright © 2019 Storefront bvba. All rights reserved.
 * @author    info@storefront.be
 */

namespace Storefront\BTCPay\Storage;


use BTCPayServer\Storage\KeyInterface;
use BTCPayServer\Storage\StorageInterface;

class EncryptedConfigStorage implements StorageInterface {


    /**
     * @var \Magento\Framework\Encryption\EncryptorInterface
     */
    private $encryptor;

    /**
     * @var \Magento\Framework\App\Config\ValueFactory
     */
    private $configValueFactory;

    /**
     * @var \Magento\Framework\App\Config\ConfigResource\ConfigInterface
     */
    private $configResource;

    public function __construct(\Magento\Framework\Encryption\EncryptorInterface $encryptor, \Magento\Framework\App\Config\ConfigResource\ConfigInterface $configResource, \Magento\Framework\App\Config\ValueFactory $configValueFactory) {
        $this->configValueFactory = $configValueFactory;
        $this->encryptor = $encryptor;
        $this->configResource = $configResource;
    }

    private function getConfigKey($id){
        return 'btcpay/keys/' .$id;
    }

    /**
     * @param KeyInterface $key
     */
    public function persist(\BTCPayServer\KeyInterface $key) {
        $unencrypted = serialize($key);
        $encrypted = $this->encryptor->encrypt($unencrypted);

        $configKey = $this->getConfigKey($key->getId());

        $this->configResource->saveConfig($configKey, $encrypted);
    }

    /**
     * @param string $id
     *
     * @return KeyInterface
     */
    public function load($id) {
        $configKey = $this->getConfigKey($id);

        $encrypted = $this->getConfigWithoutCache($configKey);
        $decrypted = $this->encryptor->decrypt($encrypted);

        $key = unserialize($decrypted);

        return $key;
    }

    private function getConfigWithoutCache($path) {
        /* @var $dataCollection \Magento\Config\Model\ResourceModel\Config\Data\Collection */
        $dataCollection = $this->configValueFactory->create()->getCollection();
        $dataCollection->addFieldToFilter('path', ['eq' => $path]);

        $config = [];

        foreach ($dataCollection as $row) {
            $config[$row->getPath()] = $row->getValue();
        }

        return $config;
    }


}