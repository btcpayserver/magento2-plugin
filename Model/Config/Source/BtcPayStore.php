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

namespace Storefront\BTCPay\Model\Config\Source;

class BtcPayStore implements \Magento\Framework\Data\OptionSourceInterface
{

    /**
     * @var \Storefront\BTCPay\Model\BTCPay\BTCPayService
     */
    private $btcPayService;

    public function __construct(\Storefront\BTCPay\Model\BTCPay\BTCPayService $btcPayService)
    {
        $this->btcPayService = $btcPayService;
    }

    public function toOptionArray()
    {
        $r = [];

        $magentoStoreId = $this->btcPayService->getCurrentMagentoStoreId();

        $baseUrl = $this->btcPayService->getBtcPayServerBaseUrl($magentoStoreId);
        $apiKey = $this->btcPayService->getApiKey($magentoStoreId);
        $stores = $this->btcPayService->getAllBtcPayStores($baseUrl, $apiKey);

        $r[] = '';
        if ($stores) {
            foreach ($stores as $store) {
                $r[$store['id']] = $store['name'];
            }
        }

        return $r;
    }
}
