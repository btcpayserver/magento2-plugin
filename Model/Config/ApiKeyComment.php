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
use Magento\Framework\View\Element\AbstractBlock;

class ApiKeyComment extends AbstractBlock implements CommentInterface
{
    public function getCommentText($elementValue)
    {
        // TODO build a dynamic link to get the API key with the needed permissions: https://docs.btcpayserver.org/API/Greenfield/v1/#tag/Authorization/paths/~1api-keys~1authorize/get

        $magentoRootDomain = $this->_scopeConfig->getValue('web/secure/base_url', 'store', 0);
        $magentoRootDomain = parse_url($magentoRootDomain, PHP_URL_HOST);
        $magentoRootDomain = str_replace(['http://', 'https://'], '', $magentoRootDomain);
        $magentoRootDomain = rtrim($magentoRootDomain, '/');

        $redirectToUrlAfterCreation = '';
        $applicationIdentifier = 'magento2';
        $apiKeyClient = \BTCPayServer\Client\ApiKey::getAuthorizeUrl($baseUrl, \Storefront\BTCPay\Helper\Data::REQUIRED_API_PERMISSIONS, 'Magento 2 @ ' . $magentoRootDomain, true, true, $redirectToUrlAfterCreation, $applicationIdentifier);

        $r = 'To get this token:
<ol class="note">
<li>Log in to BTCPay Server</li>
<li>Go to "My Account" (person icon on the top right)</li>
<li>Go to "API Keys Tokens"</li>
<li>Click "Generate new key"</li>
<li>Enter any label you like, for example "Magento"</li>
<li>Select the following permissions:
    <ul>
        <li>View invoices</li>
        <li>Create an invoice</li>
        <li>Modify stores webhooks</li>
    </ul>
</li>
<li>Confirm by clicking "Generate API Key"</li>
<li>You can now get your API key by clicking on "Reveal" and copying the key to clipboard</li>
<li>Enter the API key in the field above</li>
</ol>';

        //$url = $this->_urlBuilder->getUrl('dynamic/dynamic/dynamic');
        return $r;
    }
}
