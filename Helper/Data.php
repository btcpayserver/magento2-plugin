<?php
declare(strict_types=1);


namespace Storefront\BTCPay\Helper;


use Magento\Framework\App\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;
use Storefront\BTCPay\Model\BTCPay\Exception\CannotCreateWebhook;
use Storefront\BTCPay\Model\BTCPay\Exception\ForbiddenException;

class Data
{

    const REQUIRED_API_PERMISSIONS = [
        'btcpay.store.canviewinvoices',
        'btcpay.store.cancreateinvoice',
        'btcpay.store.webhooks.canmodifywebhooks',
        'btcpay.store.canviewstoresettings'
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

    public function __construct(\Storefront\BTCPay\Model\BTCPay\BTCPayService $BTCPayService, ScopeConfigInterface $scopeConfig, \Magento\Framework\App\CacheInterface $cache, LoggerInterface $logger)
    {
        $this->btcPayService = $BTCPayService;
        $this->logger = $logger;
        $this->cache = $cache;
        $this->scopeConfig = $scopeConfig;
    }

    public function getWebhookSecret(): ?string
    {
        $this->scopeConfig->getValue('payment/btcpay/webhook_secret', ScopeInterface::SCOPE_STORE, 0);
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

            $myPermissions = $this->btcPayService->getApiKeyPermissions($magentoStoreId);
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

                    $btcPayStoreId = $this->btcPayService->getBtcPayStore($magentoStoreId);

                    if ($btcPayStoreId) {

                        if ($this->checkWebhook($magentoStoreId, true)) {
                            // There are no errors...

                            // TODO check if the store has any actual payment methods we can use. The store may still be misconfigured (i.e. no wallet is configured). To check this, we need a new API call, but we don't have it yet.

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
                            $errors[] = __('Your API key does not have the %1 permission. Please add it for this key.', $missingPermission);
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

    private function checkWebhook(int $magentoStoreId, bool $autoCreateIfNeeded): bool
    {
        try {
            $webhookData = $this->btcPayService->getWebhookForStore($magentoStoreId);
        } catch (ForbiddenException $e) {
            // Bad configuration
            return false;
        }

        if ($webhookData === null) {
            if ($autoCreateIfNeeded) {
                try {
                    //TODO: create webhook
/*                                        $this->btcPayService->createWebhook($magentoStoreId);*/
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

            // TODO delete the webhook and create a new one with the required data...
            return false;

        }

    }


}
