<?php

namespace Storefront\BTCPay\Controller\Redirect;

use Magento\Checkout\Model\Session;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\OrderRepository;
use Psr\Log\LoggerInterface;
use Storefront\BTCPay\Model\BTCPay\InvoiceService;

class ForwardToPayment extends Action {

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var CookieManagerInterface
     */
    private $cookieManager;
    /**
     * @var CookieMetadataFactory
     */
    private $cookieMetadataFactory;
    /**
     * @var SessionManagerInterface
     */
    private $sessionManager;

    /**
     * @var \Storefront\BTCPay\Model\BTCPay\InvoiceService
     */
    private $invoiceService;
    /**
     * @var Session
     */
    private $checkoutSession;


    public function __construct(Context $context, Session $checkoutSession, CookieManagerInterface $cookieManager, CookieMetadataFactory $cookieMetadataFactory, SessionManagerInterface $sessionManager, \Storefront\BTCPay\Model\BTCPay\InvoiceService $invoiceService, CustomerSession $customerSession) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
        $this->cookieManager = $cookieManager;
        $this->sessionManager = $sessionManager;
        $this->invoiceService = $invoiceService;
        $this->customerSession = $customerSession;
    }

//    public function __construct(RedirectInterface $redirect, ResponseInterface $response, OrderRepository $orderRepository, , ) {
//
//        $this->redirect = $redirect;
//        $this->response = $response;
//        $this->orderRepository = $orderRepository;
//    }


    private function setCookie($name, $value, $duration) {
        $path = $this->sessionManager->getCookiePath();
        $domain = $this->sessionManager->getCookieDomain();

        $metadata = $this->cookieMetadataFactory->createPublicCookieMetadata()->setDuration($duration)->setPath($path)->setDomain($domain);

        $this->cookieManager->setPublicCookie($name, $value, $metadata);
    }


    public function execute() {
        $order = $this->checkoutSession->getLastRealOrder();
        $resultRedirect = $this->resultRedirectFactory->create();

        if ($order) {
            $btcpayInvoice = $this->invoiceService->createInvoice($order);
            $invoiceId = $btcpayInvoice->getId();

            if ($invoiceId) {
                if (!$this->customerSession->isLoggedIn()) {
                    // Set cookies for the order/returns page
                    $duration = 30 * 24 * 60 * 60;
                    $this->setCookie('oar_order_id', $order->getIncrementId(), $duration);
                    $this->setCookie('oar_billing_lastname', $order->getBillingAddress()->getLastName(), $duration);
                    $this->setCookie('oar_email', $order->getCustomerEmail(), $duration);
                }
                $invoiceUrl = $btcpayInvoice->getUrl();
                $resultRedirect->setUrl($invoiceUrl);
            } else {
                throw new \RuntimeException('Could not create the transaction in BTCPay Server');
            }
        } else {
            $resultRedirect->setUrl($order->getStore()->getUrl('checkout/cart'));
        }
        return $resultRedirect;
    }

}
