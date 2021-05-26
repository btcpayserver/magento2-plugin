<?php
declare(strict_types=1);

namespace Storefront\BTCPay\Controller\Redirect;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use Storefront\BTCPay\Model\BTCPay\BTCPayService;
use Storefront\BTCPay\Model\Invoice;

class ReturnAfterPayment extends Action
{
    /**
     * @var PageFactory
     */
    protected $resultPageFactory;
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var BTCPayService
     */
    private $btcPayService;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $checkoutSession;
    /**
     * @var UrlInterface
     */
    private $url;
    /**
     * @var \Magento\Sales\Api\Data\OrderInterfaceFactory
     */
    private $orderFactory;

    /**
     * Constructor
     *
     * @param Context $context
     * @param LoggerInterface $logger
     * @param PageFactory $resultPageFactory
     * @param BTCPayService $btcPayService
     * @param OrderRepositoryInterface $orderRepository
     * @param \Magento\Checkout\Model\Session $checkoutSession
     */
    public function __construct(Context $context, LoggerInterface $logger, PageFactory $resultPageFactory, BTCPayService $btcPayService, \Magento\Sales\Api\Data\OrderInterfaceFactory $orderFactory, \Magento\Checkout\Model\Session $checkoutSession, UrlInterface $url)
    {
        $this->resultPageFactory = $resultPageFactory;
        $this->logger = $logger;
        $this->btcPayService = $btcPayService;
        $this->orderFactory = $orderFactory;
        $this->checkoutSession = $checkoutSession;
        $this->url = $url;
        parent:: __construct($context);
    }

    /**
     * Execute view action
     *
     * @return ResultInterface
     * @throws NoSuchEntityException
     */
    public function execute()
    {
        $request = $this->getRequest();
        $orderIncrementId = $request->getParam('orderId');
        $btcPayInvoiceId = $request->getParam('invoiceId');
        $hash = $request->getParam('hash');

        $valid = false;
        $isInvoiceExpired = false;

        $resultRedirect = $this->resultRedirectFactory->create();

        $order = $this->orderFactory->create()->loadByIncrementId($orderIncrementId);
        if ($order->getId()) {
            $correctHash = $this->btcPayService->getOrderHash($order);

            if ($hash === $correctHash) {
                $valid = true;

                $magentoStoreId = (int)$order->getStoreId();
                $btcPayStoreId = $this->btcPayService->getBtcPayStore($magentoStoreId);

                $invoice = $this->btcPayService->getInvoice($btcPayInvoiceId, $btcPayStoreId, $magentoStoreId);
                $isInvoiceExpired = $invoice['status'] === Invoice::STATUS_EXPIRED;
                $isInvoicePaid = $invoice['status'] === Invoice::STATUS_PAID;

                // TODO log something?
            }
        } else {
            // Order cannot be found
            $order = null;
        }

        if ($order && $valid) {
            if ($isInvoicePaid) {
                $this->checkoutSession->setLastQuoteId($order->getQuoteId());
                $this->checkoutSession->setLastSuccessQuoteId($order->getQuoteId());
                $this->checkoutSession->setLastOrderId($order->getId());
                $this->checkoutSession->setLastRealOrderId($order->getIncrementId());

                $resultRedirect->setUrl($order->getStore()->getUrl('checkout/onepage/success'));
            } elseif ($isInvoiceExpired) {
                // TODO Restore the contents of the shopping cart so the customer can buy again. Start using \Storefront\BTCPay\Controller\Cart\Restore for this
                // TODO Cancel the abandoned order + create a setting for this behaviour
                $resultRedirect->setUrl($this->url->getUrl('checkout/cart/'));
            } else {
                // TODO Sending the customer here is not ideal if he/she has not paid.
                $resultRedirect->setUrl($this->url->getUrl('btcpay/payment/waiting'));
            }
        } else {
            $resultRedirect->setUrl($this->url->getUrl('checkout/cart/'));
        }

        return $resultRedirect;
    }
}
