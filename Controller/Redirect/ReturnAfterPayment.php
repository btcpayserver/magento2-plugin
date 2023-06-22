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
use Magento\Sales\Model\OrderFactory;
use Storefront\BTCPay\Controller\Cart\Restore;

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
     * @var OrderFactory
     */
    private $orderFactory;

    /**
     * @var Restore $cartRestorer
     */
    private $cartRestorer;

    /**
     * Constructor
     *
     * @param Context $context
     * @param LoggerInterface $logger
     * @param PageFactory $resultPageFactory
     * @param BTCPayService $btcPayService
     * @param OrderRepositoryInterface $orderRepository
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param Restore $cartRestorer
     */
    public function __construct(Context $context, LoggerInterface $logger, PageFactory $resultPageFactory, BTCPayService $btcPayService, OrderFactory $orderFactory, \Magento\Checkout\Model\Session $checkoutSession, UrlInterface $url, Restore $cartRestorer)
    {
        $this->resultPageFactory = $resultPageFactory;
        $this->logger = $logger;
        $this->btcPayService = $btcPayService;
        $this->orderFactory = $orderFactory;
        $this->checkoutSession = $checkoutSession;
        $this->url = $url;
        $this->cartRestorer = $cartRestorer;
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
        $orderId = $request->getParam('orderId');
        $btcPayInvoiceId = $request->getParam('invoiceId');
        $hash = $request->getParam('hash');

        $isInvoiceProcessing = false;
        $valid = false;
        $isInvoiceExpired = false;

        $resultRedirect = $this->resultRedirectFactory->create();

        $order = $this->orderFactory->create()->load($orderId);
        if ($order->getId()) {
            $correctHash = $this->btcPayService->getOrderHash($order);

            if ($hash === $correctHash) {
                $valid = true;

                $magentoStoreId = (int)$order->getStoreId();
                $btcPayStoreId = $this->btcPayService->getBtcPayStore($magentoStoreId);

                $invoice = $this->btcPayService->getInvoice($btcPayInvoiceId, $btcPayStoreId, $magentoStoreId);
                $isInvoiceExpired = $invoice->isExpired();
                $isInvoiceProcessing = $invoice->isProcessing();
                $isInvoiceSettled = $invoice->isSettled();
            }
        } else {
            // Order cannot be found
            $order = null;
        }

        if ($order && $valid) {
            if ($isInvoiceProcessing || $isInvoiceSettled) {
                $this->checkoutSession->setLastQuoteId($order->getQuoteId());
                $this->checkoutSession->setLastSuccessQuoteId($order->getQuoteId());
                $this->checkoutSession->setLastOrderId($order->getId());
                $this->checkoutSession->setLastRealOrderId($order->getIncrementId());
                $resultRedirect->setUrl($order->getStore()->getUrl('checkout/onepage/success'));
            }
            if ($isInvoiceExpired) {
                $resultRedirect->setUrl($this->url->getUrl('btcpay/cart/restore', ['order_id' => $orderId]));
            }
        } else {
            $resultRedirect->setUrl($this->url->getUrl('checkout/cart/'));
        }

        return $resultRedirect;

        //TODO: if order is placed manually put payment url in order confirmation mail
    }
}
