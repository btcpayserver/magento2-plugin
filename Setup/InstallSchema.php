<?php

namespace Storefront\BTCPay\Setup;

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Storefront\BTCPay\Helper\Data;
use Storefront\BTCPay\Storage\EncryptedConfigStorage;

class InstallSchema implements InstallSchemaInterface {


    /**
     * @var Data
     */
    private $helper;

    public function __construct(Data $helper) {
        $this->helper = $helper;
    }


    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context) {
        $installer = $setup;
        $installer->startSetup();

        $connection = $installer->getConnection();
        $table = $connection->newTable($installer->getTable('btcpay_transactions'));

        $table->addColumn('id', Table::TYPE_INTEGER, null, [
            'identity' => true,
            'nullable' => false,
            'primary' => true,
            'unsigned' => true
        ], 'ID');
        $table->addColumn('order_id', Table::TYPE_TEXT, 255, ['nullable' => false], 'Order ID');
        $table->addColumn('transaction_id', Table::TYPE_TEXT, 255, ['nullable' => false], 'Transaction ID');
        $table->addColumn('status', Table::TYPE_TEXT, 255, ['nullable' => false], 'Transaction Status');
        $table->addColumn('created_at', Table::TYPE_TIMESTAMP, null, [
            'nullable' => false,
            'default' => Table::TIMESTAMP_INIT
        ], 'Created At');
        $table->addColumn('updated_at', Table::TYPE_TIMESTAMP, null, [
            'nullable' => false,
            'default' => Table::TIMESTAMP_INIT_UPDATE
        ], 'Updated At');

        $connection->createTable($table);


        // Generate the keys
        $this->helper->generateKeys();


        $installer->endSetup();
    }


}
