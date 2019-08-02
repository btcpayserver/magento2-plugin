<?php

namespace Storefront\BTCPay\Controller\Cart;

use Exception;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\Sales\Model\Order;
use Magento\Checkout\Model\Cart;
use Magento\Framework\Registry;
use Psr\Log\LoggerInterface;

class Restore extends Action {

    protected $resultPageFactory;
    private $logger;

    /**
     * Constructor
     *
     * @param Context $context
     * @param LoggerInterface $logger
     * @param PageFactory $resultPageFactory
     */
    public function __construct(Context $context, LoggerInterface $logger, PageFactory $resultPageFactory
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->logger = $logger;
        parent:: __construct($context);
    }

    /**
     * Execute view action
     *
     * @return ResultInterface
     */
    public function execute() {
        $order_id = $this->getRequest()->getParam('order_id');
        // TODO is this the order ID or the increment ID?
        // TODO remove ObjectManager
        $_objectManager = ObjectManager::getInstance();

        $order = $this->_objectManager->create(Order::class);

        $order->load($order_id);

        $cart = $_objectManager->get(Cart::class);

        $items = $order->getItemsCollection();
        foreach ($items as $item) {
            try {
                $options = $item->getProductOptions();
                $product = $item->getProduct();
                if (isset($options['info_buyRequest'])) {
                    $options['info_buyRequest']['qty'] = $item['qty_ordered'];

                    $cart->addProduct($product, $options['info_buyRequest']);

                } else {
                    $cart->addOrderItem($product);
                }
            } catch (Exception $e) {
                $this->logger->critical($e);
            }
        }

        $cart->save();
        $registry = $_objectManager->get(Registry::class);

        $registry->register('isSecureArea', 'true');
        $order->delete();
        $registry->unregister('isSecureArea');

        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setUrl('/checkout/cart/');
        return $resultRedirect;
    }
}
