<?php
declare(strict_types=1);

namespace Storefront\BTCPay\Controller\Adminhtml\Order;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Storefront\BTCPay\Model\BTCPay\BTCPayService;

use Psr\Log\LoggerInterface;

class Mark extends Action
{

    /**
     * @var BTCPayService $btcPayService
     */
    protected $btcPayService;

    /**
     * @var LoggerInterface $logger
     */
    protected $logger;

    public function __construct(Context $context, BTCPayService $btcPayService, LoggerInterface $logger)
    {
        parent::__construct($context);
        $this->btcPayService = $btcPayService;
        $this->logger = $logger;
    }

    public function execute(): Redirect
    {

        $orderId = $this->getRequest()->getParam('order_id');
        $markBtcPayInvoiceAs = $this->getRequest()->getParam('mark');

        try {
            $this->btcPayService->markBtcPayInvoice($orderId, $markBtcPayInvoiceAs);
            $this->messageManager->addSuccessMessage(__('Marked BTCPay Server Invoice as %1 successfully', $markBtcPayInvoiceAs));

        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Could not mark BTCPay Server Invoice as %1 ', $markBtcPayInvoiceAs));
        }

        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setUrl($this->_url->getUrl('sales/order/view/', ['order_id' => $orderId]));
        return $resultRedirect;

    }
}
