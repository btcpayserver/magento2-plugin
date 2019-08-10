<?php
/**
 * Order
 *
 * @copyright Copyright © 2019 Storefront bvba. All rights reserved.
 * @author    info@storefront.be
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
                    // TODO store ID is hard coded since we don't have multi-BTCPay Server support
                    $storeId = 0;
                    $url = $this->btcPayService->getInvoiceDetailUrl($storeId, $invoiceId);
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