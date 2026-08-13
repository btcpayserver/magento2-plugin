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
use Storefront\BTCPay\Helper\Data;

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

    /**
     * @var Data $helper
     */
    private $helper;

    public function __construct(Context $context, BTCPayService $btcService, StoreManagerInterface $storeManager, LoggerInterface $logger, Config $configResource, ReinitableConfigInterface $reinitableConfig, Data $helper)
    {
        parent::__construct($context);
        $this->btcService = $btcService;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
        $this->configResource = $configResource;
        $this->reinitableConfig = $reinitableConfig;
        $this->helper = $helper;
    }

    public function execute()
    {
        $magentoStoreId = 0;
        $apiKey = $this->getRequest()->getParam('apiKey');
        $givenSecret = $this->getRequest()->getParam('secret');

        try {
            $baseUrlParam = $this->getRequest()->getParam('base_url');
            if ($baseUrlParam === null) {
                $baseUrl = $this->btcService->getBtcPayServerBaseUrl();
                $secret = $this->btcService->hashSecret($magentoStoreId);
            } elseif (is_string($baseUrlParam)) {
                $baseUrl = $this->btcService->normalizeBtcPayServerBaseUrl($baseUrlParam);
                $secret = $this->btcService->hashApiKeyPairingSecret($magentoStoreId, $baseUrl);
            } else {
                return $this->getResponse()
                    ->setHttpResponseCode(403)
                    ->setBody((string)__('Forbidden.'));
            }
        } catch (\InvalidArgumentException $e) {
            return $this->getResponse()
                ->setHttpResponseCode(403)
                ->setBody((string)__('Forbidden.'));
        }

        if (!is_string($givenSecret) || !hash_equals($secret, $givenSecret)) {
            return $this->getResponse()
                ->setHttpResponseCode(403)
                ->setBody((string)__('Forbidden.'));
        }

        try {
            if (!is_string($apiKey) || $apiKey === '' || !$baseUrl) {
                throw new \InvalidArgumentException('Missing API key or BTCPay Server URL.');
            }

            // Validate the API key and store access before changing Magento configuration.
            $client = new \BTCPayServer\Client\ApiKey($baseUrl, $apiKey);
            $client->getCurrent();
            $allBtcStores = $this->btcService->getAllBtcPayStores($baseUrl, $apiKey);

            if ($baseUrlParam !== null) {
                $this->configResource->saveConfig('payment/btcpay/btcpay_base_url', $baseUrl);
            }
            $this->configResource->saveConfig('payment/btcpay/api_key', $apiKey);

            $magentoStoreViewsToConfigure = [];
            if ($allBtcStores && count($allBtcStores) === 1) {
                $btcStoreId = $allBtcStores[0]['id'];
                if ($btcStoreId) {
                    $magentoStoreViewsToConfigure = $this->helper->getAllMagentoStoreViewIds();
                    foreach ($magentoStoreViewsToConfigure as $magentoStoreView) {
                        $this->configResource->saveConfig(
                            'payment/btcpay/btcpay_store_id',
                            $btcStoreId,
                            'stores',
                            $magentoStoreView
                        );
                    }
                }
            }

            // Webhook installation must read the newly saved URL, key and store IDs.
            $this->reinitableConfig->reinit();
        } catch (\Throwable $e) {
            $this->logger->error($e);
            return $this->getResponse()
                ->setHttpResponseCode(500)
                ->setBody((string)__('Something went wrong. Please try again.'));
        }

        $webhookSetupFailed = false;
        foreach ($magentoStoreViewsToConfigure as $magentoStoreView) {
            try {
                if (!$this->helper->installWebhookIfNeeded((int)$magentoStoreView, true)) {
                    $webhookSetupFailed = true;
                }
            } catch (\Throwable $e) {
                $webhookSetupFailed = true;
                $this->logger->warning('BTCPay API key saved, but automatic webhook setup failed.', [
                    'exception' => $e,
                ]);
            }
        }

        $message = __('The API key was saved successfully. You can close this window now.');
        if ($webhookSetupFailed) {
            $message = __(
                'The API key was saved successfully. Automatic webhook setup was not completed. '
                . 'Please review the BTCPay configuration on the Payment Methods page. '
                . 'You can close this window now.'
            );
        }

        return $this->getResponse()->setBody((string)$message);
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
