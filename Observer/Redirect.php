<?php

namespace Storefront\BTCPay\Observer;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\OrderRepository;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\ResourceConnection;
use Storefront\BTCPay\Model\BTCPay\Item;
use Magento\Customer\Model\Session as CustomerSession;

class Redirect implements ObserverInterface {

    private $redirect;
    private $response;
    private $orderRepository;

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

    public function __construct(RedirectInterface $redirect, ResponseInterface $response, OrderRepository $orderRepository, CustomerSession $customerSession, CookieManagerInterface $cookieManager, CookieMetadataFactory $cookieMetadataFactory, SessionManagerInterface $sessionManager, \Storefront\BTCPay\Model\BTCPay\InvoiceService $invoiceService) {

        $this->redirect = $redirect;
        $this->response = $response;
        $this->orderRepository = $orderRepository;
        $this->customerSession = $customerSession;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
        $this->cookieManager = $cookieManager;
        $this->sessionManager = $sessionManager;
        $this->invoiceService = $invoiceService;
    }



    private function setCookie($name, $value, $duration) {
        $path = $this->sessionManager->getCookiePath();
        $domain = $this->sessionManager->getCookieDomain();

        $metadata = $this->cookieMetadataFactory->createPublicCookieMetadata()->setDuration($duration)->setPath($path)->setDomain($domain);

        $this->cookieManager->setPublicCookie($name, $value, $metadata);
    }


    public function execute(Observer $observer) {
        $orderIds = $observer->getEvent()->getOrderIds();
        $orderId = $orderIds[0];
        $order = $this->orderRepository->get($orderId);

        if ($order->getPayment()->getMethodInstance()->getCode() === \Storefront\BTCPay\Model\BTCPay::PAYMENT_METHOD_CODE) {

            $newStatus = $this->getStoreConfig('payment/btcpay/new_status', $order->getStoreId());

            $order->setState('new');
            if ($newStatus) {
                $order->setStatus($newStatus);
            } else {
                $order->setStatus('new'); // TODO can we avoid hard coded status here?
            }

            $order->save();

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

                die('TEST');

                $this->redirect->redirect($this->response, $invoiceUrl);
            } else {
                throw new \RuntimeException('Could not create the transaction in BTCPay Server');
            }
        }
    }

}
