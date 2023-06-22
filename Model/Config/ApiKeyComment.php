<?php
declare(strict_types=1);
/**
 * Integrates BTCPay Server with Magento 2 for online payments
 * @copyright Copyright © 2019-2021 Storefront bv. All rights reserved.
 * @author    Wouter Samaey - wouter.samaey@storefront.be
 *
 * This file is part of Storefront/BTCPay.
 *
 * Storefront/BTCPay is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace Storefront\BTCPay\Model\Config;

use Magento\Config\Model\Config\CommentInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Url;
use Magento\Store\Model\StoreManagerInterface;
use Storefront\BTCPay\Model\BTCPay\BTCPayService;

class ApiKeyComment implements CommentInterface
{
    private $storeManager;

    private $btcPayService;

    private $urlBuilder;

    private $scopeConfig;

    private $formKey;

    public function __construct(Url $urlBuilder, ScopeConfigInterface $scopeConfig, FormKey $formKey, BTCPayService $btcPayService, StoreManagerInterface $storeManager)
    {
        $this->scopeConfig = $scopeConfig;
        $this->urlBuilder = $urlBuilder;
        $this->formKey = $formKey;
        $this->btcPayService = $btcPayService;
        $this->storeManager = $storeManager;
    }

    public function getCommentText($elementValue)
    {
        $r = '';
        $magentoStoreId = $this->btcPayService->getCurrentMagentoStoreId();
        $apiKey = $this->btcPayService->getApiKey('default', 0);
        if (!$apiKey) {
            $magentoRootDomain = $this->scopeConfig->getValue('web/secure/base_url', 'store', 0);
            $magentoRootDomain = parse_url($magentoRootDomain, PHP_URL_HOST);
            $magentoRootDomain = str_replace(['http://', 'https://'], '', $magentoRootDomain);
            $magentoRootDomain = rtrim($magentoRootDomain, '/');

            $redirectToUrlAfterCreation = $this->btcPayService->getReceiveApikeyUrl($magentoStoreId);

            $applicationIdentifier = 'magento2';

            $baseUrl = $this->btcPayService->getBtcPayServerBaseUrl();
            if ($baseUrl) {
                $authorizeUrl = \BTCPayServer\Client\ApiKey::getAuthorizeUrl($baseUrl, \Storefront\BTCPay\Helper\Data::REQUIRED_API_PERMISSIONS, 'Magento 2 @ ' . $magentoRootDomain, true, true, $redirectToUrlAfterCreation, $applicationIdentifier);
                $r = '<a target="_blank" href="' . $authorizeUrl . '">Generate API key</a>, but be sure to save any changes first.';
            } else {
                $r = 'Make sure you configure the <strong>BTCPay Base Url</strong> above';
            }
        }
        return $r;
    }
}
