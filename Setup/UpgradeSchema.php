<?php
declare(strict_types=1);

namespace Storefront\BTCPay\Setup;

use Magento\Framework\App\Config\ConfigResource\ConfigInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Status;
use Magento\Sales\Model\Order\StatusFactory;
use Magento\Sales\Model\ResourceModel\Order\Status as StatusResource;
use Magento\Sales\Model\ResourceModel\Order\StatusFactory as StatusResourceFactory;

use Storefront\BTCPay\Model\OrderStatuses;

class UpgradeSchema implements UpgradeSchemaInterface
{


    /**
     * Status Factory
     *
     * @var StatusFactory
     */
    protected $statusFactory;
    /**
     * Status Resource Factory
     *
     * @var StatusResourceFactory
     */
    protected $statusResourceFactory;


    /**
     * @var ConfigInterface
     */
    private $configResource;

    public function __construct(ConfigInterface $configResource, StatusFactory $statusFactory, StatusResourceFactory $statusResourceFactory)
    {
        $this->configResource = $configResource;
        $this->statusFactory = $statusFactory;
        $this->statusResourceFactory = $statusResourceFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        if (version_compare($context->getVersion(), "2.0.0", "<")) {
            // Add new column
            $setup->getConnection()->addColumn('btcpay_invoices', 'btcpay_store_id', [
                'type' => Table::TYPE_TEXT,
                'default' => false,
                'comment' => 'BTC Pay Server Store ID',
                'after' => 'id'
            ]);

            // Update invoice status. Since the v1, they now start with a capital letter.
            $setup->getConnection()->query('update btcpay_invoices set status = CONCAT(UPPER(SUBSTRING(status,1,1)),LOWER(SUBSTRING(status,2)))');

            // Add new order statuses and assign them to states
            $this->addNewStatusToState(Order::STATE_PENDING_PAYMENT, ['status' => OrderStatuses::STATUS_CODE_PENDING_PAYMENT, 'label' => OrderStatuses::STATUS_LABEL_PENDING_PAYMENT]);
            $this->addNewStatusToState(Order::STATE_PAYMENT_REVIEW, ['status' => OrderStatuses::STATUS_CODE_INVALID, 'label' => OrderStatuses::STATUS_LABEL_INVALID]);
            $this->addNewStatusToState(Order::STATE_PAYMENT_REVIEW, ['status' => OrderStatuses::STATUS_CODE_UNDERPAID, 'label' => OrderStatuses::STATUS_LABEL_UNDERPAID]);
            $this->addNewStatusToState(Order::STATE_PROCESSING, ['status' => OrderStatuses::STATUS_CODE_PAID_CORRECTLY, 'label' => OrderStatuses::STATUS_LABEL_PAID_CORRECTLY]);
            $this->addNewStatusToState(Order::STATE_PAYMENT_REVIEW, ['status' => OrderStatuses::STATUS_CODE_OVERPAID, 'label' => OrderStatuses::STATUS_LABEL_OVERPAID]);

            // Alter btc_invoices(oder_id) to be compatible
            $setup->getConnection()->query("ALTER TABLE btcpay_invoices CHANGE order_id order_id INT(10) UNSIGNED NOT NULL COMMENT 'Order ID'");
            // Add foreign key from btcpay_invoices to sales_order table for the order entity ID. On delete restrict + On update cascade.
            $setup->getConnection()->query('ALTER TABLE btcpay_invoices ADD CONSTRAINT BTCPAY_INVOICES_ORDER_ID_SALES_ORDER_ID FOREIGN KEY (order_id) REFERENCES sales_order(entity_id) ON DELETE RESTRICT ON UPDATE CASCADE');

            // Add magento_store_id column to table btcpay_invoice, and a foreign key to the store table

            $setup->getConnection()->query("ALTER TABLE btcpay_invoices ADD magento_store_id SMALLINT(5) UNSIGNED NOT NULL, ADD CONSTRAINT BTCPAY_INVOICES_MAGENTO_STORE_ID_STORE_ID FOREIGN KEY (magento_store_id) REFERENCES store(store_id) ON DELETE RESTRICT ON UPDATE CASCADE");
        }

        if (version_compare($context->getVersion(), "2.0.3", "<")) {
            $this->createWebhookDeliveriesTable($setup);
        }

        $setup->endSetup();
    }

    /**
     * Stores processed BTCPay webhook delivery IDs so replayed or concurrent
     * deliveries do not change the invoice or order a second time.
     */
    private function createWebhookDeliveriesTable(SchemaSetupInterface $setup): void
    {
        $connection = $setup->getConnection();
        $tableName = $setup->getTable('btcpay_webhook_deliveries');

        if ($connection->isTableExists($tableName)) {
            return;
        }

        $table = $connection->newTable($tableName)
            ->addColumn('id', Table::TYPE_INTEGER, null, [
                'identity' => true,
                'nullable' => false,
                'primary' => true,
                'unsigned' => true,
            ], 'ID')
            ->addColumn('delivery_id', Table::TYPE_TEXT, 255, [
                'nullable' => false,
            ], 'BTCPay Delivery ID')
            ->addColumn('invoice_id', Table::TYPE_TEXT, 255, [
                'nullable' => true,
            ], 'BTCPay Invoice ID')
            ->addColumn('event_type', Table::TYPE_TEXT, 64, [
                'nullable' => true,
            ], 'Webhook Event Type')
            ->addColumn('created_at', Table::TYPE_TIMESTAMP, null, [
                'nullable' => false,
                'default' => Table::TIMESTAMP_INIT,
            ], 'Created At')
            ->addIndex(
                $setup->getIdxName('btcpay_webhook_deliveries', ['delivery_id']),
                ['delivery_id'],
                ['type' => AdapterInterface::INDEX_TYPE_UNIQUE]
            )
            ->addIndex(
                $setup->getIdxName('btcpay_webhook_deliveries', ['created_at']),
                ['created_at'],
                ['type' => AdapterInterface::INDEX_TYPE_INDEX]
            )
            ->setComment('Processed BTCPay webhook deliveries');

        $connection->createTable($table);
    }

    protected function addNewStatusToState($state, $statusData): void
    {
        /** @var StatusResource $statusResource */
        $statusResource = $this->statusResourceFactory->create();
        /** @var Status $status */
        $status = $this->statusFactory->create();
        $status->setData($statusData);
        try {
            $statusResource->save($status);
        } catch (AlreadyExistsException $exception) {
            return;
        }
        $status->assignState($state, false, true);
    }
}
