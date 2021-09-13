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
use Psr\Log\LoggerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;


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
     * Constructor
     *
     * @param Context $context
     * @param LoggerInterface $logger
     * @param PageFactory $resultPageFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param Cart $cart
     * @param Registry $registry
     */
    public function __construct(Context $context, LoggerInterface $logger, PageFactory $resultPageFactory, OrderRepositoryInterface $orderRepository, Cart $cart, Registry $registry)
    {
        $this->resultPageFactory = $resultPageFactory;
        $this->logger = $logger;
        $this->orderRepository = $orderRepository;
        $this->cart = $cart;
        $this->registry = $registry;
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
        $order = $this->orderRepository->get((int)$order_id);

        $order->load($order_id);

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
        $order->delete();
        $this->registry->unregister('isSecureArea');

        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setUrl('/checkout/cart/');
        return $resultRedirect;
    }
}
