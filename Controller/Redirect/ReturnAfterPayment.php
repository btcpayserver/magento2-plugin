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
        $resultRedirect = $this->resultRedirectFactory->create();

        if (!is_scalar($orderId)
            || !ctype_digit((string)$orderId)
            || !is_scalar($btcPayInvoiceId)
            || (string)$btcPayInvoiceId === ''
            || !is_scalar($hash)
            || (string)$hash === ''
        ) {
            return $resultRedirect->setUrl($this->url->getUrl('checkout/cart/'));
        }

        try {
            $order = $this->orderFactory->create()->load((int)$orderId);
            if (!$order->getId()) {
                return $resultRedirect->setUrl($this->url->getUrl('checkout/cart/'));
            }

            if (!$this->checkoutSessionOwnsOrder($order)) {
                return $resultRedirect->setUrl($this->url->getUrl('checkout/cart/'));
            }

            $hash = (string)$hash;
            $validCurrentHash = $this->btcPayService->isValidOrderHash($order, $hash);
            $validLegacyHash = !$validCurrentHash
                && $this->btcPayService->isValidLegacyOrderHash($order, $hash);
            if (!$validCurrentHash && !$validLegacyHash) {
                return $resultRedirect->setUrl($this->url->getUrl('checkout/cart/'));
            }

            $invoice = $this->btcPayService->getVerifiedInvoiceForOrder(
                $order,
                (string)$btcPayInvoiceId
            );
            if (!$invoice) {
                return $resultRedirect->setUrl($this->url->getUrl('checkout/cart/'));
            }

            if ($invoice->isProcessing() || $invoice->isSettled()) {
                $this->checkoutSession->setLastQuoteId($order->getQuoteId());
                $this->checkoutSession->setLastSuccessQuoteId($order->getQuoteId());
                $this->checkoutSession->setLastOrderId($order->getId());
                $this->checkoutSession->setLastRealOrderId($order->getIncrementId());
                return $resultRedirect->setUrl(
                    $order->getStore()->getUrl('checkout/onepage/success')
                );
            }

            if ($invoice->isExpired()) {
                return $resultRedirect->setUrl($order->getStore()->getUrl(
                    'btcpay/cart/restore',
                    [
                        'order_id' => $order->getId(),
                        'invoice_id' => (string)$btcPayInvoiceId,
                        'hash' => $validCurrentHash
                            ? $hash
                            : $this->btcPayService->getOrderHash($order),
                        '_secure' => true,
                    ]
                ));
            }
        } catch (\Throwable $exception) {
            $this->logger->error($exception);
        }

        return $resultRedirect->setUrl($this->url->getUrl('checkout/cart/'));

        //TODO: if order is placed manually put payment url in order confirmation mail
    }

    private function checkoutSessionOwnsOrder(\Magento\Sales\Model\Order $order): bool
    {
        return (string)$this->checkoutSession->getLastOrderId() === (string)$order->getId()
            && (string)$this->checkoutSession->getLastRealOrderId()
                === (string)$order->getIncrementId();
    }
}
