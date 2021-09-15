<?php
declare(strict_types=1);

namespace Storefront\BTCPay\Setup;

use Magento\Framework\App\Config\ConfigResource\ConfigInterface;
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
            $this->addNewStatusToState(Order::STATE_PAYMENT_REVIEW, ['status' => OrderStatuses::STATUS_CODE_FEE_TOO_LOW, 'label' => OrderStatuses::STATUS_LABEL_FEE_TOO_LOW]);
            $this->addNewStatusToState(Order::STATE_PAYMENT_REVIEW, ['status' => OrderStatuses::STATUS_CODE_UNDERPAID, 'label' => OrderStatuses::STATUS_LABEL_UNDERPAID]);
            $this->addNewStatusToState(Order::STATE_PROCESSING, ['status' => OrderStatuses::STATUS_CODE_PAID_CORRECTLY, 'label' => OrderStatuses::STATUS_LABEL_PAID_CORRECTLY]);
            $this->addNewStatusToState(Order::STATE_PAYMENT_REVIEW, ['status' => OrderStatuses::STATUS_CODE_OVERPAID, 'label' => OrderStatuses::STATUS_LABEL_OVERPAID]);
        }

        $setup->endSetup();

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
        $status->assignState($state, false, false);
    }
}
