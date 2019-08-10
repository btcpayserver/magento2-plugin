<?php
/**
 * Order
 *
 * @copyright Copyright © 2019 Storefront bvba. All rights reserved.
 * @author    info@storefront.be
 */

namespace Storefront\BTCPay\Ui\Component\Listing\Column;


class Order extends \Magento\Ui\Component\Listing\Columns\Column {

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
                    $orderId = $item['order_id'];
                    $orderIncrementId = $item['order_increment_id'];
                    $url = $this->context->getUrl('sales/order/view', ['order_id' => $orderId]);
                    $html = '<a href="' . $url . '">';
                    $html .= $orderIncrementId;
                    $html .= '</a>';
                    $item[$fieldName] = $html;
                }
            }
        }

        return $dataSource;
    }

}