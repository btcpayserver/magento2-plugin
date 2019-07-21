<?php
/**
 * Integrates BTCPay Server with Magento 2 for online payments
 * Copyright (C) 2019  Storefront BVBA
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

class TransactionActions extends \Magento\Ui\Component\Listing\Columns\Column
{

    const URL_PATH_EDIT = 'storefront_btcpay/transaction/edit';
    const URL_PATH_DELETE = 'storefront_btcpay/transaction/delete';
    const URL_PATH_DETAILS = 'storefront_btcpay/transaction/details';
    protected $urlBuilder;

    /**
     * @param \Magento\Framework\View\Element\UiComponent\ContextInterface $context
     * @param \Magento\Framework\View\Element\UiComponentFactory $uiComponentFactory
     * @param \Magento\Framework\UrlInterface $urlBuilder
     * @param array $components
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\UiComponent\ContextInterface $context,
        \Magento\Framework\View\Element\UiComponentFactory $uiComponentFactory,
        \Magento\Framework\UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        $this->urlBuilder = $urlBuilder;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * Prepare Data Source
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {
                if (isset($item['transaction_id'])) {
                    $item[$this->getData('name')] = [
                        // TODO add poll for update
//                        'edit' => [
//                            'href' => $this->urlBuilder->getUrl(
//                                static::URL_PATH_EDIT,
//                                [
//                                    'transaction_id' => $item['transaction_id']
//                                ]
//                            ),
//                            'label' => __('Edit')
//                        ],
//                        'delete' => [
//                            'href' => $this->urlBuilder->getUrl(
//                                static::URL_PATH_DELETE,
//                                [
//                                    'transaction_id' => $item['transaction_id']
//                                ]
//                            ),
//                            'label' => __('Delete'),
//                            'confirm' => [
//                                'title' => __('Delete "${ $.$data.title }"'),
//                                'message' => __('Are you sure you wan\'t to delete a "${ $.$data.title }" record?')
//                            ]
//                        ]
                    ];
                }
            }
        }
        
        return $dataSource;
    }
}
