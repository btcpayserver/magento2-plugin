<?php
declare(strict_types=1);

namespace Storefront\BTCPay\Setup\Patch\Data;

use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Encrypts API keys that were stored in plaintext before encryption at rest
 * was introduced. Values already in Magento's encrypted format are skipped,
 * so the patch is safe to run more than once.
 */
class EncryptApiKey implements DataPatchInterface
{
    private const CONFIG_PATH = 'payment/btcpay/api_key';

    /**
     * Matches Magento ciphertext: "<key version>:<cipher version>:<payload>".
     */
    private const ENCRYPTED_PATTERN = '/^\d+:\d+:/';

    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @var EncryptorInterface
     */
    private $encryptor;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        EncryptorInterface $encryptor
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->encryptor = $encryptor;
    }

    /**
     * {@inheritdoc}
     */
    public function apply()
    {
        $connection = $this->moduleDataSetup->getConnection();
        $table = $this->moduleDataSetup->getTable('core_config_data');

        $select = $connection->select()
            ->from($table, ['config_id', 'value'])
            ->where('path = ?', self::CONFIG_PATH);

        foreach ($connection->fetchAll($select) as $row) {
            $value = (string)$row['value'];

            if ($value === '' || preg_match(self::ENCRYPTED_PATTERN, $value) === 1) {
                continue;
            }

            $connection->update(
                $table,
                ['value' => $this->encryptor->encrypt($value)],
                ['config_id = ?' => (int)$row['config_id']]
            );
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases()
    {
        return [];
    }
}
