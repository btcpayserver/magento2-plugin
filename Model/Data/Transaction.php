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

namespace Storefront\BTCPay\Model\Data;

use Storefront\BTCPay\Api\Data\TransactionInterface;

class Transaction extends \Magento\Framework\Api\AbstractExtensibleObject implements TransactionInterface
{

    /**
     * Get transaction_id
     * @return string|null
     */
    public function getTransactionId()
    {
        return $this->_get(self::TRANSACTION_ID);
    }

    /**
     * Set transaction_id
     * @param string $transactionId
     * @return \Storefront\BTCPay\Api\Data\TransactionInterface
     */
    public function setTransactionId($transactionId)
    {
        return $this->setData(self::TRANSACTION_ID, $transactionId);
    }

    /**
     * Get transaction_status
     * @return string|null
     */
    public function getTransactionStatus()
    {
        return $this->_get(self::TRANSACTION_STATUS);
    }

    /**
     * Set transaction_status
     * @param string $transactionStatus
     * @return \Storefront\BTCPay\Api\Data\TransactionInterface
     */
    public function setTransactionStatus($transactionStatus)
    {
        return $this->setData(self::TRANSACTION_STATUS, $transactionStatus);
    }

    /**
     * Retrieve existing extension attributes object or create a new one.
     * @return \Storefront\BTCPay\Api\Data\TransactionExtensionInterface|null
     */
    public function getExtensionAttributes()
    {
        return $this->_getExtensionAttributes();
    }

    /**
     * Set an extension attributes object.
     * @param \Storefront\BTCPay\Api\Data\TransactionExtensionInterface $extensionAttributes
     * @return $this
     */
    public function setExtensionAttributes(
        \Storefront\BTCPay\Api\Data\TransactionExtensionInterface $extensionAttributes
    ) {
        return $this->_setExtensionAttributes($extensionAttributes);
    }
}
