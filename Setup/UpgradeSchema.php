<?php
declare(strict_types=1);

namespace Storefront\BTCPay\Setup;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Store\Model\Store;

class UpgradeSchema implements UpgradeSchemaInterface
{

    /**
     * @var \Magento\Framework\App\Config\ConfigResource\ConfigInterface
     */
    private $configResource;

    public function __construct(\Magento\Framework\App\Config\ConfigResource\ConfigInterface $configResource)
    {
        $this->configResource = $configResource;
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
        }

        $setup->endSetup();

    }
}
