<?php
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

namespace Storefront\BTCPay\Api\Data;

interface InvoiceInterface extends \Magento\Framework\Api\ExtensibleDataInterface
{

    const INVOICE_STATUS = 'status';
    const INVOICE_ID = 'invoice_id';

    /**
     * Get invoice_id
     * @return string|null
     */
    public function getInvoiceId();

    /**
     * Set invoice ID
     * @param string $invoiceId
     * @return \Storefront\BTCPay\Api\Data\InvoiceInterface
     */
    public function setInvoiceId($invoiceId);

    /**
     * Get status
     * @return string|null
     */
    public function getStatus();

    /**
     * Set status
     * @param string $status
     * @return \Storefront\BTCPay\Api\Data\InvoiceInterface
     */
    public function setStatus($sStatus);

    /**
     * Retrieve existing extension attributes object or create a new one.
     * @return \Storefront\BTCPay\Api\Data\InvoiceExtensionInterface|null
     */
    public function getExtensionAttributes();

    /**
     * Set an extension attributes object.
     * @param \Storefront\BTCPay\Api\Data\InvoiceExtensionInterface $extensionAttributes
     * @return $this
     */
    public function setExtensionAttributes(
        \Storefront\BTCPay\Api\Data\InvoiceExtensionInterface $extensionAttributes
    );
}
