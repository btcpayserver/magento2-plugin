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
use Magento\Framework\UrlInterface;
use Magento\Framework\Data\Form\FormKey;

class ApiKeyComment implements CommentInterface
{
    private $urlBuilder;

    private $scopeConfig;

    private $formKey;

    public function __construct(UrlInterface $urlBuilder, ScopeConfigInterface $scopeConfig, FormKey $formKey)
    {
        $this->scopeConfig = $scopeConfig;
        $this->urlBuilder = $urlBuilder;
        $this->formKey = $formKey;
    }

    public function getCommentText($elementValue)
    {
        // TODO build a dynamic link to get the API key with the needed permissions: https://docs.btcpayserver.org/API/Greenfield/v1/#tag/Authorization/paths/~1api-keys~1authorize/get

        $magentoRootDomain = $this->scopeConfig->getValue('web/secure/base_url', 'store', 0);
        $magentoRootDomain = parse_url($magentoRootDomain, PHP_URL_HOST);
        $magentoRootDomain = str_replace(['http://', 'https://'], '', $magentoRootDomain);
        $magentoRootDomain = rtrim($magentoRootDomain, '/');

        $redirectToUrlAfterCreation = $this->urlBuilder->getUrl('btcpay/api/save', ['form_key' => $this->formKey->getFormKey()]);

        $applicationIdentifier = 'magento2';

        $baseUrl = $this->scopeConfig->getValue('payment/btcpay/btcpay_base_url');
        if ($baseUrl) {
            $authorizeUrl = \BTCPayServer\Client\ApiKey::getAuthorizeUrl($baseUrl, \Storefront\BTCPay\Helper\Data::REQUIRED_API_PERMISSIONS, 'Magento 2 @ ' . $magentoRootDomain, true, true, $redirectToUrlAfterCreation, $applicationIdentifier);
            $r = '<a target="_blank" href="' . $authorizeUrl . '">Generate API key</a>, but be sure to save any changes first.';
        } else {


            $r = 'Make sure you configure the <strong>VTCPay Base Url</strong> above';
        }
        return $r;
    }
}
