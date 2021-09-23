<?php
declare(strict_types=1);
/**
 * Integrates BTCPay Server with Magento 2 for online payments
 * Copyright (C) 2019  Storefront BVBA
 *
 * This file is part of Storefront/BTCPay.
 *
 * Storefront/BTCPay is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace Storefront\BTCPay\Controller\Adminhtml\Invoice;

use Psr\Log\LoggerInterface;
use Storefront\BTCPay\Model\BTCPay\BTCPayService;

class Update extends \Magento\Backend\App\Action
{

    protected $resultPageFactory;
    /**
     * @var BTCPayService
     */
    private $btcPayService;
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Constructor
     *
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     */
    public function __construct(\Magento\Backend\App\Action\Context $context, \Magento\Framework\View\Result\PageFactory $resultPageFactory, BTCPayService $BTCPayService, LoggerInterface $logger)
    {
        $this->resultPageFactory = $resultPageFactory;
        $this->btcPayService = $BTCPayService;
        $this->logger = $logger;
        parent::__construct($context);
    }

    /**
     * Index action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $invoiceId = $this->getRequest()->getParam('invoice_id');
        $btcPayStoreId = $this->getRequest()->getParam('btcpay_store_id');

        if ($invoiceId) {
            try {
                $btcPayInvoiceId = $this->btcPayService->getBtcPayInvoiceIdFromMagentoId((int)$invoiceId);
                if ($btcPayInvoiceId !== null) {
                    $order = $this->btcPayService->updateInvoice($btcPayStoreId, $btcPayInvoiceId);
                    if ($order) {
                        $this->messageManager->addSuccessMessage(__('Updated BTCPay Server Invoice %1 successfully', $btcPayInvoiceId));
                    } else {
                        $this->messageManager->addSuccessMessage(__('BTCPay Server Invoice %1 hasn\'t changed.', $btcPayInvoiceId));
                    }
                } else {
                    $this->messageManager->addErrorMessage(__('Could not find BTCPay Server Invoice with ID %1 in Magento', $invoiceId));
                }
            } catch (\Exception $ex) {
                $this->logger->error($ex);
                $this->messageManager->addErrorMessage(__('Could not update BTCPay Server Invoice %1: %2', $btcPayInvoiceId, $ex->getMessage()));
            }
        }
        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setUrl($this->_url->getUrl('*/*/'));
        return $resultRedirect;
    }
}
