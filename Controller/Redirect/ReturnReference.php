<?php
declare(strict_types=1);

namespace Storefront\BTCPay\Controller\Redirect;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Psr\Log\LoggerInterface;
use Storefront\BTCPay\Model\BTCPay\BTCPayService;
use Storefront\BTCPay\Model\Security\OrderReturnReference;

class ReturnReference extends Action
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var BTCPayService
     */
    private $btcPayService;

    /**
     * @var OrderFactory
     */
    private $orderFactory;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var UrlInterface
     */
    private $url;

    /**
     * @var OrderReturnReference
     */
    private $orderReturnReference;

    public function __construct(
        Context $context,
        LoggerInterface $logger,
        BTCPayService $btcPayService,
        OrderFactory $orderFactory,
        CheckoutSession $checkoutSession,
        UrlInterface $url,
        OrderReturnReference $orderReturnReference
    ) {
        parent::__construct($context);
        $this->logger = $logger;
        $this->btcPayService = $btcPayService;
        $this->orderFactory = $orderFactory;
        $this->checkoutSession = $checkoutSession;
        $this->url = $url;
        $this->orderReturnReference = $orderReturnReference;
    }

    public function execute(): ResultInterface
    {
        $reference = $this->getRequest()->getParam('reference');
        if (!is_scalar($reference)) {
            return $this->redirectToCart();
        }

        $state = $this->orderReturnReference->resolve((string)$reference);
        if ($state === null) {
            return $this->redirectToCart();
        }

        try {
            $order = $this->orderFactory->create()->load($state['order_id']);
            if (!$order->getId()
                || !hash_equals($state['order_increment_id'], (string)$order->getIncrementId())
                || !$this->checkoutSessionOwnsOrder($order)
            ) {
                return $this->redirectToCart();
            }

            $invoice = $this->btcPayService->getVerifiedInvoiceForOrder(
                $order,
                $state['invoice_id']
            );
            if (!$invoice) {
                return $this->redirectToCart();
            }

            if ($invoice->isProcessing() || $invoice->isSettled()) {
                $this->orderReturnReference->consume();
                $this->checkoutSession->setLastQuoteId($order->getQuoteId());
                $this->checkoutSession->setLastSuccessQuoteId($order->getQuoteId());
                $this->checkoutSession->setLastOrderId($order->getId());
                $this->checkoutSession->setLastRealOrderId($order->getIncrementId());

                return $this->resultRedirectFactory->create()->setUrl(
                    $order->getStore()->getUrl('checkout/onepage/success')
                );
            }

            if ($invoice->isExpired()) {
                $this->orderReturnReference->consume();

                return $this->resultRedirectFactory->create()->setUrl(
                    $order->getStore()->getUrl('btcpay/cart/restore', [
                        'order_id' => $order->getId(),
                        'invoice_id' => $state['invoice_id'],
                        'hash' => $this->btcPayService->getOrderHash($order),
                        '_secure' => true,
                    ])
                );
            }
        } catch (\Throwable $exception) {
            $this->logger->error($exception);
        }

        return $this->redirectToCart();
    }

    private function checkoutSessionOwnsOrder(Order $order): bool
    {
        return (string)$this->checkoutSession->getLastOrderId() === (string)$order->getId()
            && (string)$this->checkoutSession->getLastRealOrderId()
                === (string)$order->getIncrementId();
    }

    private function redirectToCart(): ResultInterface
    {
        return $this->resultRedirectFactory->create()->setUrl(
            $this->url->getUrl('checkout/cart/')
        );
    }
}
