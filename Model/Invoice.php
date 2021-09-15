<?php
declare(strict_types=1);
/**
 * Integrates BTCPay Server with Magento 2 for online payments
 * @copyright Copyright © 2019-2021 Storefront bv. All rights reserved.
 * @author    Wouter Samaey - wouter.samaey@storefront.be
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

namespace Storefront\BTCPay\Model;

use Magento\Framework\Api\DataObjectHelper;
use Storefront\BTCPay\Api\Data\InvoiceInterface;
use Storefront\BTCPay\Api\Data\InvoiceInterfaceFactory;

class Invoice extends \Magento\Framework\Model\AbstractModel
{
    protected $invoiceDataFactory;

    protected $dataObjectHelper;

    protected $_eventPrefix = 'storefront_btcpay_invoice';

    /**
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param InvoiceInterfaceFactory $invoiceDataFactory
     * @param DataObjectHelper $dataObjectHelper
     * @param \Storefront\BTCPay\Model\ResourceModel\Invoice $resource
     * @param \Storefront\BTCPay\Model\ResourceModel\Invoice\Collection $resourceCollection
     * @param array $data
     */
    public function __construct(\Magento\Framework\Model\Context $context, \Magento\Framework\Registry $registry, InvoiceInterfaceFactory $invoiceDataFactory, DataObjectHelper $dataObjectHelper, \Storefront\BTCPay\Model\ResourceModel\Invoice $resource, \Storefront\BTCPay\Model\ResourceModel\Invoice\Collection $resourceCollection, array $data = [])
    {
        $this->invoiceDataFactory = $invoiceDataFactory;
        $this->dataObjectHelper = $dataObjectHelper;
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    /**
     * Retrieve invoice model with invoice data
     * @return InvoiceInterface
     */
    public function getDataModel()
    {
        $invoiceData = $this->getData();

        $invoiceDataObject = $this->invoiceDataFactory->create();
        $this->dataObjectHelper->populateWithArray($invoiceDataObject, $invoiceData, InvoiceInterface::class);

        return $invoiceDataObject;
    }
}
