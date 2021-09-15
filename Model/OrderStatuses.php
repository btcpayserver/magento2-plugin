<?php
declare(strict_types=1);

namespace Storefront\BTCPay\Model;


class OrderStatuses extends \Magento\Framework\Model\AbstractModel
{

    const STATUS_CODE_INVALID = 'btcpay_invalid';
    const STATUS_LABEL_INVALID = 'Waiting for payment confirmation';

    const STATUS_CODE_UNDERPAID = 'btcpay_underpaid';
    const STATUS_LABEL_UNDERPAID = 'Underpaid with BTCPay';

    const STATUS_CODE_PAID_CORRECTLY = 'btcpay_paid_correctly';
    const STATUS_LABEL_PAID_CORRECTLY = 'Paid using BTCPay';

    const STATUS_CODE_OVERPAID = 'btcpay_overpaid';
    const STATUS_LABEL_OVERPAID = 'Overpaid with BTCPay';

    const STATUS_CODE_PENDING_PAYMENT ='btcpay_pending_payment';
    const STATUS_LABEL_PENDING_PAYMENT ='Pending BTCPay payment';


}
