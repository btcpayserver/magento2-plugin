<?php
declare(strict_types=1);

namespace Storefront\BTCPay\Controller\Cart;

use Exception;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\Checkout\Model\Cart;
use Magento\Framework\Registry;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\Data\OrderStatusHistoryInterface;


class Restore extends Action
{

    protected $resultPageFactory;
    private $logger;

    /**
     * @var OrderRepositoryInterface $orderRepository
     */
    private $orderRepository;

    /**
     * @var Cart $cart
     */
    private $cart;

    /**
     * @var Registry $registry
     */
    private $registry;

    /**
     * @var OrderManagementInterface $orderManager
     */
    private $orderManager;

    /**
     * @var OrderStatusHistoryInterface $orderStatusHistory
     */
    private $orderStatusHistory;

    /**
     * Constructor
     *
     * @param Context $context
     * @param LoggerInterface $logger
     * @param PageFactory $resultPageFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param Cart $cart
     * @param Registry $registry
     * @param OrderManagementInterface $orderManager
     */
    public function __construct(Context $context, LoggerInterface $logger, PageFactory $resultPageFactory, OrderRepositoryInterface $orderRepository, Cart $cart, Registry $registry, OrderManagementInterface $orderManager, OrderStatusHistoryInterface $orderStatusHistory)
    {
        $this->resultPageFactory = $resultPageFactory;
        $this->logger = $logger;
        $this->orderRepository = $orderRepository;
        $this->cart = $cart;
        $this->registry = $registry;
        $this->orderManager = $orderManager;
        $this->orderStatusHistory;
        parent:: __construct($context);
    }

    /**
     * Execute view action
     *
     * @return ResultInterface
     */
    public function execute()
    {
        $order_id = $this->getRequest()->getParam('order_id');
        try {
            $order = $this->orderRepository->get((int)$order_id);
            $items = $order->getItemsCollection();
            foreach ($items as $item) {
                try {
                    $options = $item->getProductOptions();
                    $product = $item->getProduct();
                    if (isset($options['info_buyRequest'])) {
                        $options['info_buyRequest']['qty'] = $item['qty_ordered'];
                        $this->cart->addProduct($product, $options['info_buyRequest']);

                    } else {
                        $this->cart->addOrderItem($product);
                    }
                } catch (Exception $e) {
                    $this->logger->critical($e);
                }
            }

            $this->cart->save();

            $this->registry->register('isSecureArea', 'true');

            //Cancel the abandoned order and add comment
            $order->cancel();
            $order->addCommentToStatusHistory(__('The customer has left the payment page and has returned to the shop. The invoice is expired.'));
            $order->save();

            $this->registry->unregister('isSecureArea');


        } catch (\Exception $e) {
            $this->logger->error($e);
        }
        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setUrl('/checkout/cart/');
        return $resultRedirect;
    }
}
