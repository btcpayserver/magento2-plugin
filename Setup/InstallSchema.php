<?php

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

        $installer->endSetup();
    }


}
