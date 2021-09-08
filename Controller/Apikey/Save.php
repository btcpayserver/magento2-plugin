<?php
declare(strict_types=1);

namespace Storefront\BTCPay\Controller\Apikey;

use Magento\Config\Model\ResourceModel\Config;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Storefront\BTCPay\Model\BTCPay\BTCPayService;

class Save extends Action implements CsrfAwareActionInterface
{
    /**
     * @var BTCPayService $btcService
     */
    private $btcService;

    /**
     * @var StoreManagerInterface $storeManager
     */
    private $storeManager;

    /**
     * @var LoggerInterface $logger
     */
    private $logger;

    /**
     * @var Config $configResource
     */
    private $configResource;

    /**
     * @var ReinitableConfigInterface $reinitableConfig
     */
    private $reinitableConfig;

    public function __construct(Context $context, BTCPayService $btcService, StoreManagerInterface $storeManager, LoggerInterface $logger, Config $configResource, ReinitableConfigInterface $reinitableConfig)
    {
        parent::__construct($context);
        $this->btcService = $btcService;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
        $this->configResource = $configResource;
        $this->reinitableConfig = $reinitableConfig;
    }

    public function execute()
    {
        $magentoStoreId = $this->btcService->getCurrentMagentoStoreId();

        $apiKey = $this->getRequest()->getParam('apiKey');

        $givenSecret = $this->getRequest()->getParam('secret');

        $secret = $this->btcService->hashSecret($magentoStoreId);
        if ($givenSecret === $secret) {
            try {
                $baseUrl = $this->btcService->getBtcPayServerBaseUrl($magentoStoreId);

                // Safety check
                $client = new \BTCPayServer\Client\ApiKey($baseUrl, $apiKey);
                $client->getCurrent();

                // Save API key to config settings
                $this->configResource->saveConfig('payment/btcpay/api_key', $apiKey, 'stores', $magentoStoreId);

                // When only 1 BTCStore, save immediately
                $btcStores = $this->btcService->getStores($magentoStoreId);
                if ($btcStores && count($btcStores) === 1) {
                    $btcStoreId = $btcStores[0]['id'];
                    if ($btcStoreId) {
                        $this->configResource->saveConfig('payment/btcpay/btcpay_store_id', $btcStoreId, 'stores', $magentoStoreId);
                    }
                }

                //Clear config cache.
                $this->reinitableConfig->reinit();

                echo ___('exact_online.connection_succeeded_close_window', 'Success! You can now close this window.');
            } catch (\Exception $e) {
                $this->logger->error($e);
                echo ___('exact_online.connection_failed_close_window', 'Something went wrong. Please try again.');
            }
        } else {
            echo ___('exact_online.connection_access_denied_close_window', 'Forbidden.');
        }
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
