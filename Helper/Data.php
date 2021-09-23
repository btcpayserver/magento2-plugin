<?php
declare(strict_types=1);

namespace Storefront\BTCPay\Helper;

use Magento\Framework\App\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Storefront\BTCPay\Model\BTCPay\Exception\CannotCreateWebhook;
use Storefront\BTCPay\Model\BTCPay\Exception\ForbiddenException;

class Data
{
    const REQUIRED_API_PERMISSIONS = [
        'btcpay.store.canviewinvoices',
        'btcpay.store.cancreateinvoice',
        'btcpay.store.webhooks.canmodifywebhooks',
        'btcpay.store.canviewstoresettings',
        'btcpay.store.canmodifyinvoices'
    ];

    /**
     * @var \Storefront\BTCPay\Model\BTCPay\BTCPayService
     */
    private $btcPayService;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var \Magento\Framework\App\CacheInterface
     */
    private $cache;
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var StoreRepositoryInterface $storeRepository
     */
    private $storeRepository;

    /**
     * @var StoreManagerInterface $storeManager
     */
    private $storeManager;

    public function __construct(\Storefront\BTCPay\Model\BTCPay\BTCPayService $BTCPayService, ScopeConfigInterface $scopeConfig, \Magento\Framework\App\CacheInterface $cache, LoggerInterface $logger, StoreRepositoryInterface $storeRepository, StoreManagerInterface $storeManager)
    {
        $this->btcPayService = $BTCPayService;
        $this->logger = $logger;
        $this->cache = $cache;
        $this->scopeConfig = $scopeConfig;
        $this->storeRepository = $storeRepository;
        $this->storeManager = $storeManager;
    }

    public function getWebhookSecret(): ?string
    {
        return $this->scopeConfig->getValue('payment/btcpay/webhook_secret', ScopeInterface::SCOPE_STORE, 0);
    }

    public function getInstallationErrors(int $magentoStoreId, bool $useCache): array
    {
        $cacheKey = 'BTCPAY_INSTALLATION_ERRORS_STORE_' . $magentoStoreId;
        $errors = false;
        if ($useCache) {
            $errors = $this->cache->load($cacheKey);
            if ($errors !== false) {
                $errors = \json_decode($errors, true);
            }
        }

        if ($errors === false) {
            $secret = $this->btcPayService->getWebhookSecret($magentoStoreId);

            $errors = [];

            $myPermissions = $this->btcPayService->getApiKeyPermissions('default', 0);
            $permissionsSeparator = ':';

            $specificStores = [];

            if ($myPermissions) {
                foreach ($myPermissions as $permission) {
                    $parts = explode($permissionsSeparator, $permission);
                    if (count($parts) === 1) {
                        // This is not a store-specific permission
                    } elseif (count($parts) === 2) {
                        // Store-specific permission
                        $btcPayStoreId = $parts[1];
                        if (!in_array($btcPayStoreId, $specificStores, true)) {
                            $specificStores[] = $btcPayStoreId;
                        }
                    } else {
                        throw new \Storefront\BTCPay\Model\BTCPay\Exception\InvalidPermissionFormat($permission);
                    }
                }

                $neededPermissions = [];
                if (count($specificStores) === 0) {
                    // The user does not have any store-specific permissions, so he can access all stores.
                    $neededPermissions = self::REQUIRED_API_PERMISSIONS;
                } else {
                    // The user has store-specific permissions, so these should all be present for each store
                    foreach ($specificStores as $specificStore) {
                        foreach (self::REQUIRED_API_PERMISSIONS as $essentialPermission) {
                            $neededPermissions[] = $essentialPermission . $permissionsSeparator . $specificStore;
                        }
                    }
                }

                sort($myPermissions);
                sort($neededPermissions);

                if ($myPermissions === $neededPermissions) {
                    // Permissions are exact


                    $this->btcPayService->removeDeletedBtcPayStores();

                    $btcPayStoreId = $this->btcPayService->getBtcPayStore($magentoStoreId);

                    if ($btcPayStoreId) {
                        if ($this->installWebhookIfNeeded($magentoStoreId, true)) {
                            $paymentMethods = $this->btcPayService->getBtcPayStorePaymentMethods($btcPayStoreId);

                            if (count($paymentMethods) === 0) {
                                $errors[] = __('Please configure a payment method in BTCPay Server for this BTCPay store.');
                            } else {
                                $enabledPaymentMethod = false;
                                foreach ($paymentMethods as $paymentMethod) {
                                    if ($paymentMethod->isEnabled()) {
                                        $enabledPaymentMethod = true;
                                    }

                                    if (!$enabledPaymentMethod) {
                                        $errors[] = __('No enabled payment method for this BTCPay store in BTCPay Server.');
                                    } else {
                                        // No errors
                                    }
                                }
                            }
                        } else {
                            $errors[] = __('Could not install the webhook in BTCPay Server for this Magento installation.');
                        }
                    } else {
                        $errors[] = __('Please select a BTCPay Server Store to use.');
                    }
                } else {
                    // You either have too many permissions or too few!
                    $missingPermissions = array_diff($neededPermissions, $myPermissions);
                    $superfluousPermissions = array_diff($myPermissions, $neededPermissions);

                    if (count($missingPermissions)) {
                        foreach ($missingPermissions as $missingPermission) {
                            $errors[] = __('Your API key does not have the %1 permission. Please regenerate the API key.', $missingPermission);
                        }
                    }
                    if (count($superfluousPermissions)) {
                        foreach ($superfluousPermissions as $superfluousPermission) {
                            $errors[] = __('Your API key has the %1 permission, but we don\'t need it. Please use an API key that has the exact permissions for increased security.', '<span style="font-family: monospace; background: #EEE; padding: 2px 4px; display: inline-block">' . $superfluousPermission . '</span>');
                        }
                    }
                }

                if ($useCache) {
                    $this->cache->save(\json_encode($errors, JSON_THROW_ON_ERROR), $cacheKey, [Config::CACHE_TAG], 15 * 60);
                }
            } else {
                $errors[] = __('No permissions, please check if your API key is valid.');
            }
        }

        return $errors;
    }

    public function installWebhookIfNeeded(int $magentoStoreId, bool $autoCreateIfNeeded): bool
    {

        try {
            $apiKey = $this->btcPayService->getApiKey('default', 0);
            if ($apiKey) {
                $btcPayStoreId = $this->btcPayService->getBtcPayStore($magentoStoreId);
                if ($btcPayStoreId) {

                    $webhookData = $this->btcPayService->getWebhooksForStore($magentoStoreId, $btcPayStoreId, $apiKey);


                    if (!$webhookData) {
                        if ($autoCreateIfNeeded) {
                            try {
                                $webhook = $this->btcPayService->createWebhook($magentoStoreId, $apiKey);
                                if (!$webhook) {
                                    return false;
                                }
                                return true;
                            } catch (CannotCreateWebhook $e) {
                                $this->logger->error($e);
                                return false;
                            }
                        } else {
                            return false;
                        }
                    } else {

                        // Example: {
                        //  "id": "8kR8zG81EERX59FGav5WWo",
                        //  "enabled": true,
                        //  "automaticRedelivery": true,
                        //  "url": "http:\/\/mybtcpay.com\/admin\/V1\/btcpay\/webhook\/key\/8c7982460d83d57fb3e351ade2335aa88c42f5654eeee282a4e70e5751422dab\/",
                        //  "authorizedEvents": {
                        //    "everything": true,
                        //    "specificEvents": []
                        //  }
                        //}

                        if ($webhookData['enabled'] === true) {
                            if ($webhookData['automaticRedelivery'] === true) {
                                if ($webhookData['authorizedEvents']['everything'] === true) {
                                    $url = $this->btcPayService->getWebhookUrl($magentoStoreId);
                                    if ($webhookData['url'] === $url) {
                                        return true;
                                    }
                                }
                            }
                        }

                        return false;
                    }
                }


            }
            return false;
        } catch (ForbiddenException $e) {
            // Bad configuration
            return false;
        }

    }

    public function getApiKeyInfo($scope, $scopeId)
    {
        $apiCreated = true;
        $apiKeyInfo = [];
        $apiKey = $this->btcPayService->getApiKey($scope, $scopeId);
        if (!$apiKey) {
            $apiKey = '<span style="color: red">' . __('No API key yet.') . '</span>';
            $apiCreated = false;
        }
        $generateUrl = $this->getGenerateApiKeyUrl($scopeId);
        $apiKeyInfo['api_created'] = $apiCreated;
        $apiKeyInfo['api_key'] = $apiKey;
        $apiKeyInfo['generate_url'] = $generateUrl;
        return $apiKeyInfo;
    }

    public function getAllMagentoStoreViews()
    {
        $stores = $this->storeManager->getStores();
        return $stores;
    }

    public function getAllMagentoStoreViewIds()
    {
        $storeIds = [];
        $stores = $this->getAllMagentoStoreViews();

        foreach ($stores as $store) {
            $storeId = $store->getId();
            $storeIds[] = $storeId;
        }
        return $storeIds;
    }

    public function getGenerateApiKeyUrl(int $magentoStoreId)
    {
        $magentoRootDomain = $this->scopeConfig->getValue('web/secure/base_url', 'default', 0);
        $magentoRootDomain = parse_url($magentoRootDomain, PHP_URL_HOST);
        $magentoRootDomain = str_replace(['http://', 'https://'], '', $magentoRootDomain);
        $magentoRootDomain = rtrim($magentoRootDomain, '/');

        $redirectToUrlAfterCreation = $this->btcPayService->getReceiveApikeyUrl($magentoStoreId);

        $applicationIdentifier = 'magento2';
        $baseUrl = $this->btcPayService->getBtcPayServerBaseUrl();

        $authorizeUrl = \BTCPayServer\Client\ApiKey::getAuthorizeUrl($baseUrl, \Storefront\BTCPay\Helper\Data::REQUIRED_API_PERMISSIONS, 'Magento 2 @ ' . $magentoRootDomain, true, false, $redirectToUrlAfterCreation, $applicationIdentifier);

        return $authorizeUrl;
    }

    public function isBtcPayBaseUrlSet(): bool
    {
        if ($this->btcPayService->getBtcPayServerBaseUrl()) {
            return true;
        }
        return false;
    }

    public function getAllBtcPayStoresAssociative($magentoStoreId): array
    {
        $baseUrl = $this->btcPayService->getBtcPayServerBaseUrl();
        $apiKey = $this->btcPayService->getApiKey('default', 0);
        return $this->btcPayService->getAllBtcPayStoresAssociative($baseUrl, $apiKey);
    }

    public function getSelectedBtcPayStoreForMagentoStore(int $magentoStoreId)
    {
        return $this->btcPayService->getBtcPayStore($magentoStoreId);
    }

    public function deleteWebhookIfNeeded(int $storeId, string $apiKey, string $btcPayStoreId)
    {
        $webhook = $this->btcPayService->getWebhooksForStore($storeId, $btcPayStoreId, $apiKey);
        if ($webhook) {
            $webhookId = $webhook['id'];
            $deleted = $this->btcPayService->deleteWebhook($storeId, $btcPayStoreId, $webhookId, $apiKey);
        }
        return true;
    }

    public function deleteWebhooksIfNeeded($storeIds, $apiKey, $btcPayStoreId)
    {
        foreach ($storeIds as $storeId) {
            $this->deleteWebhookIfNeeded((int)$storeId, $apiKey, $btcPayStoreId);
        }
        return true;
    }

    public function getCurrentStoreId()
    {
        return $this->btcPayService->getCurrentMagentoStoreId();
    }

    public function getApiKey($scope, $scopeId)
    {
        return $this->btcPayService->getApiKey($scope, $scopeId);
    }

}
