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
namespace Storefront\BTCPay\Setup;

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

class InstallSchema implements InstallSchemaInterface {



    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context) {
        $installer = $setup;
        $installer->startSetup();

        $connection = $installer->getConnection();
        $table = $connection->newTable($installer->getTable('btcpay_invoices'));

        $table->addColumn('id', Table::TYPE_INTEGER, null, [
            'identity' => true,
            'nullable' => false,
            'primary' => true,
            'unsigned' => true
        ], 'ID');
        $table->addColumn('order_id', Table::TYPE_TEXT, 255, ['nullable' => false], 'Order ID');
        $table->addColumn('invoice_id', Table::TYPE_TEXT, 255, ['nullable' => false], 'BTCPay Invoice ID');
        $table->addColumn('status', Table::TYPE_TEXT, 255, ['nullable' => false], 'Payment Status');
        $table->addColumn('created_at', Table::TYPE_TIMESTAMP, null, [
            'nullable' => false,
            'default' => Table::TIMESTAMP_INIT
        ], 'Created At');
        $table->addColumn('updated_at', Table::TYPE_TIMESTAMP, null, [
            'nullable' => false,
            'default' => Table::TIMESTAMP_INIT_UPDATE
        ], 'Updated At');

        $connection->createTable($table);

        // TODO foreign key from btcpay_invoices to sales_order table for the order entity ID

        $installer->endSetup();
    }


}
