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
namespace Storefront\BTCPay\Ui\Component\Listing\Column;


use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;

class BtcPayInvoice extends \Magento\Ui\Component\Listing\Columns\Column {

    /**
     * @var \Storefront\BTCPay\Model\BTCPay\BTCPayService
     */
    private $btcPayService;

    public function __construct(ContextInterface $context, UiComponentFactory $uiComponentFactory, \Storefront\BTCPay\Model\BTCPay\BTCPayService $BTCPayService, array $components = [], array $data = []) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
        $this->btcPayService = $BTCPayService;
    }

    /**
     * Prepare Data Source
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource) {
        if (isset($dataSource['data']['items'])) {
            $fieldName = $this->getData('name');
            foreach ($dataSource['data']['items'] as & $item) {
                if (isset($item[$fieldName])) {
                    $invoiceId = $item[$fieldName];
                    $magentoStoreId = $item['magento_store_id'];
                    $url = $this->btcPayService->getInvoiceDetailUrl((int)$magentoStoreId, $invoiceId);
                    $html = '<a href="' . $url . '" target="_blank">';
                    $html .= $invoiceId;
                    $html .= '</a>';
                    $item[$fieldName] = $html;
                }
            }
        }

        return $dataSource;
    }

}
