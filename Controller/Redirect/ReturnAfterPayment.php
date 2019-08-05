<?php

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

class ReturnAfterPayment extends Action {
    protected $resultPageFactory;
    private $logger;
    /**
     * @var BTCPayService
     */
    private $btcPayService;
    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var
     */
    private $checkoutSession;
    /**
     * @var UrlInterface
     */
    private $url;

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
    public function __construct(Context $context, LoggerInterface $logger, PageFactory $resultPageFactory, BTCPayService $btcPayService, OrderRepositoryInterface $orderRepository, \Magento\Checkout\Model\Session $checkoutSession, UrlInterface $url) {
        $this->resultPageFactory = $resultPageFactory;
        $this->logger = $logger;
        $this->btcPayService = $btcPayService;
        $this->orderRepository = $orderRepository;
        $this->checkoutSession = $checkoutSession;
        $this->url = $url;
        parent:: __construct($context);
    }

    /**
     * Execute view action
     *
     * @return ResultInterface
     */
    public function execute() {
        $request = $this->getRequest();
        $orderId = $request->getParam('orderId');
        $hash = $request->getParam('hash');

        $order = null;
        $valid = false;

        $resultRedirect = $this->resultRedirectFactory->create();
        try {
            $order = $this->orderRepository->get($orderId);
            $correctHash = $this->btcPayService->getOrderHash($order);

            if ($hash === $correctHash) {
                $valid = true;
                // TODO log something?
            }

        } catch (NoSuchEntityException $ex) {
            // Order not found
        }

        if ($order && $valid) {
            $this->checkoutSession->setLastQuoteId($order->getQuoteId());
            $this->checkoutSession->setLastSuccessQuoteId($order->getQuoteId());
            $this->checkoutSession->setLastOrderId($order->getId());
            $this->checkoutSession->setLastRealOrderId($order->getIncrementId());

            $resultRedirect->setUrl($order->getStore()->getUrl('checkout/onepage/success'));
        } else {
            $resultRedirect->setUrl($this->url->getUrl('checkout/cart/'));
        }

        return $resultRedirect;
    }
}
