<?php
declare(strict_types=1);

namespace Storefront\BTCPay\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Checkout\Model\Session;
use Storefront\BTCPay\Model\BTCPay\BTCPayService;
use Magento\Store\Model\StoreManagerInterface;

class CheckOrderStatus implements ObserverInterface
{
    /**
     * @var RedirectInterface $redirect
     */
    private $redirect;

    /**
     * @var Session $checkoutSession
     */
    private $checkoutSession;

    /**
     * @var BTCPayService $btcService
     */
    private $btcService;

    /**
     * @var StoreManagerInterface $storeManager
     */
    private $storeManager;

    public function __construct(RedirectInterface $redirect, Session $checkoutSession, BTCPayService $btcService, StoreManagerInterface $storeManager)
    {
        $this->redirect = $redirect;
        $this->checkoutSession = $checkoutSession;
        $this->btcService = $btcService;
        $this->storeManager = $storeManager;
    }

    public function execute(Observer $observer)
    {
        $order = $this->checkoutSession->getLastRealOrder();
        $orderIdArr[] = $order->getIncrementId();

        if (count($order->getData()) > 0) {

            $paymentMethod = $order->getPayment()->getMethod();
            if ($paymentMethod === 'btcpay') {

                $currentStoreId = (int)$this->storeManager->getStore()->getId();
                //Check Order Status
                $invoices = $this->btcService->getInvoicesByOrderIds($currentStoreId, $orderIdArr);

                $invoices = $invoices->all();
                if (count($invoices) !== 0) {
                    $invoice = $invoices[0];

                    $status = $invoice->getStatus();

                    if ($status === 'New' || $status === 'Expired') {

                        //Only cancel when no other open invoices for the same orderId
                        $btcpayInvoices = $this->btcService->getInvoicesByOrderIds($currentStoreId, [$order->getIncrementId()]);
                        $isEverythingNew = true;
                        foreach ($btcpayInvoices->all() as $invoice) {
                            if (!$invoice->isNew()) {
                                $isEverythingNew = false;
                                break;
                            }
                        }

                        if ($isEverythingNew) {
                            //Cancel Order
                            $order->cancel();
                            $order->addCommentToStatusHistory(__('The customer left the payment page. Not paid.'));
                            $order->save();

                            //Restore Quote
                            $this->checkoutSession->restoreQuote();
                        }
                    }
                }
            }
        }
    }
}
