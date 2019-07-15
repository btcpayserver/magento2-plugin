<?php

namespace Storefront\BTCPayServer\Setup;

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

class InstallSchema implements InstallSchemaInterface {
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context) {
        $installer = $setup;
        $installer->startSetup();

        // TODO Maybe add a Magento Admin grid so we can see the transaction data?
        $table = $installer->getConnection()->newTable($installer->getTable('btcpayserver_transactions'))->addColumn('id', Table::TYPE_INTEGER, null, [
            'identity' => true,
            'nullable' => false,
            'primary' => true,
            'unsigned' => true
        ], 'ID')->addColumn('order_id', Table::TYPE_TEXT, 255, ['nullable' => false], 'Order ID')->addColumn('transaction_id', Table::TYPE_TEXT, 255, ['nullable' => false], 'Transaction ID')->addColumn('transaction_status', Table::TYPE_TEXT, 255, ['nullable' => false], 'Transaction Status')->addColumn('created_at', Table::TYPE_TIMESTAMP, 255, [
            'nullable' => false,
            'default' => Table::TIMESTAMP_INIT_UPDATE
        ], 'Date Added');
        $installer->getConnection()->createTable($table);


        $table = $installer->getConnection()->newTable($installer->getTable('btcpayserver_ipns'))->addColumn('id', Table::TYPE_INTEGER, null, [
            'identity' => true,
            'nullable' => false,
            'primary' => true,
            'unsigned' => true
        ], 'ID')->addColumn('order_id', Table::TYPE_TEXT, 255, ['nullable' => false], 'Order ID')->addColumn('transaction_id', Table::TYPE_TEXT, 255, ['nullable' => false], 'Transaction ID')->addColumn('transaction_status', Table::TYPE_TEXT, 255, ['nullable' => false], 'Transaction Status')->addColumn('transaction_data', Table::TYPE_TEXT, 4096, ['nullable' => false], 'Transaction Data')->addColumn('created_at', Table::TYPE_TIMESTAMP, 255, [
            'nullable' => false,
            'default' => Table::TIMESTAMP_INIT_UPDATE
        ], 'Date Added');
        $installer->getConnection()->createTable($table);


        $installer->endSetup();
    }
}
