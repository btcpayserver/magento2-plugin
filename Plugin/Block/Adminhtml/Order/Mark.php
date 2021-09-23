<?php
declare(strict_types=1);

namespace Storefront\BTCPay\Plugin\Block\Adminhtml\Order;

use Magento\Sales\Block\Adminhtml\Order\View;
use BTCPayServer\Result\Invoice as BTCPayServerInvoice;

class Mark
{
    public const URL_PATH_MARK = 'btcpay/order/mark';


    public function beforeGetLayout(View $view)
    {

        //TODO add conditions to show and hide these buttons

        $orderId = $view->getOrderId();

        $settledUrl = $view->getUrl(static::URL_PATH_MARK, [
            'order_id' => $orderId, 'mark' => BTCPayServerInvoice::STATUS_SETTLED
        ]);

        $invalidUrl = $view->getUrl(static::URL_PATH_MARK, [
            'order_id' => $orderId, 'mark' => BTCPayServerInvoice::STATUS_INVALID
        ]);

        $settledMessage = __('Are you sure you want to mark this BTCPay Invoice as settled?');

        $invalidMessage = __('Are you sure you want to mark this BTCPayInvoice as invalid?');

        $view->addButton(
            'mark_settled',
            [
                'label' => __('Mark as Settled'),
                'class' => '',
                'onclick' => "confirmSetLocation('{$settledMessage}', '{$settledUrl}')"
            ]
        );

        $view->addButton(
            'mark_invalid',
            [
                'label' => __('Mark as Invalid'),
                'class' => '',
                'onclick' => "confirmSetLocation('{$invalidMessage}', '{$invalidUrl}')"
            ]
        );
    }

}
