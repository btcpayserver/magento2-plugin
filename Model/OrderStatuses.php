<?php
declare(strict_types=1);

namespace Storefront\BTCPay\Model;


class OrderStatuses extends \Magento\Framework\Model\AbstractModel
{

    const STATUS_CODE_FEE_TOO_LOW = 'btcpay_fee_too_low';
    const STATUS_LABEL_FEE_TOO_LOW = 'Waiting for payment confirmation';

    const STATUS_CODE_UNDERPAID = 'btcpay_underpaid';
    const STATUS_LABEL_UNDERPAID = 'Underpaid with BTCPay';

    const STATUS_CODE_PAID_CORRECTLY = 'btcpay_paid_correclty';
    const STATUS_LABEL_PAID_CORRECTLY = 'Paid using BTCPay';

    const STATUS_CODE_OVERPAID = 'btcpay_overpaid';
    const STATUS_LABEL_OVERPAID = 'Overpaid with BTCPay';

    public function __construct(Context $context, \Magento\Framework\Registry $registry, ResourceModel\AbstractResource $resource = null, \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null, array $data = [])
    {
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

}
