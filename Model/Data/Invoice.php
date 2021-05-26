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
namespace Storefront\BTCPay\Model\Data;

use Storefront\BTCPay\Api\Data\InvoiceInterface;

class Invoice extends \Magento\Framework\Api\AbstractExtensibleObject implements InvoiceInterface
{

    /**
     * Get invoice_id
     * @return string|null
     */
    public function getInvoiceId()
    {
        return $this->_get(self::INVOICE_ID);
    }

    /**
     * Set invoice_id
     * @param string $invoiceId
     * @return \Storefront\BTCPay\Api\Data\InvoiceInterface
     */
    public function setInvoiceId($invoiceId)
    {
        return $this->setData(self::INVOICE_ID, $invoiceId);
    }

    /**
     * Get invoice_status
     * @return string|null
     */
    public function getStatus()
    {
        return $this->_get(self::INVOICE_STATUS);
    }

    /**
     * Set invoice_status
     * @param string $invoiceStatus
     * @return \Storefront\BTCPay\Api\Data\InvoiceInterface
     */
    public function setStatus($invoiceStatus)
    {
        return $this->setData(self::INVOICE_STATUS, $invoiceStatus);
    }

    /**
     * Retrieve existing extension attributes object or create a new one.
     * @return \Storefront\BTCPay\Api\Data\InvoiceExtensionInterface|null
     */
    public function getExtensionAttributes()
    {
        return $this->_getExtensionAttributes();
    }

    /**
     * Set an extension attributes object.
     * @param \Storefront\BTCPay\Api\Data\InvoiceExtensionInterface $extensionAttributes
     * @return $this
     */
    public function setExtensionAttributes(
        \Storefront\BTCPay\Api\Data\InvoiceExtensionInterface $extensionAttributes
    ) {
        return $this->_setExtensionAttributes($extensionAttributes);
    }
}
