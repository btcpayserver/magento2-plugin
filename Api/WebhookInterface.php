<?php
declare(strict_types=1);

namespace Storefront\BTCPay\Api;

interface WebhookInterface {

    /**
     * Process
     * @return bool
     */
    public function process(): bool;


}
