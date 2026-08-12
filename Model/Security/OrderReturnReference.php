<?php
declare(strict_types=1);

namespace Storefront\BTCPay\Model\Security;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Sales\Model\Order;

class OrderReturnReference
{
    private const SESSION_KEY = 'btcpay_return_reference';
    private const LIFETIME_SECONDS = 5 * 60 * 60;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    public function __construct(CheckoutSession $checkoutSession)
    {
        $this->checkoutSession = $checkoutSession;
    }

    public function create(Order $order): string
    {
        $reference = bin2hex(random_bytes(32));
        $this->checkoutSession->setData(self::SESSION_KEY, [
            'hash' => hash('sha256', $reference),
            'expires' => time() + self::LIFETIME_SECONDS,
            'order_id' => (int)$order->getId(),
            'order_increment_id' => (string)$order->getIncrementId(),
            'invoice_id' => '',
        ]);

        return $reference;
    }

    public function bindInvoice(string $invoiceId): bool
    {
        $state = $this->getState();
        if ($state === null || $invoiceId === '') {
            return false;
        }

        $state['invoice_id'] = $invoiceId;
        $this->checkoutSession->setData(self::SESSION_KEY, $state);

        return true;
    }

    public function resolve(string $reference): ?array
    {
        if (preg_match('/^[a-f0-9]{64}$/D', $reference) !== 1) {
            return null;
        }

        $state = $this->getState();
        if ($state === null
            || !isset(
                $state['hash'],
                $state['expires'],
                $state['order_id'],
                $state['order_increment_id'],
                $state['invoice_id']
            )
            || !is_string($state['hash'])
            || preg_match('/^[a-f0-9]{64}$/D', $state['hash']) !== 1
            || !is_numeric($state['expires'])
            || (int)$state['expires'] <= time()
            || !is_numeric($state['order_id'])
            || (int)$state['order_id'] <= 0
            || !is_scalar($state['order_increment_id'])
            || (string)$state['order_increment_id'] === ''
            || !is_scalar($state['invoice_id'])
            || (string)$state['invoice_id'] === ''
            || !hash_equals($state['hash'], hash('sha256', $reference))
        ) {
            return null;
        }

        return [
            'order_id' => (int)$state['order_id'],
            'order_increment_id' => (string)$state['order_increment_id'],
            'invoice_id' => (string)$state['invoice_id'],
        ];
    }

    public function consume(): void
    {
        $this->checkoutSession->unsetData(self::SESSION_KEY);
    }

    private function getState(): ?array
    {
        $state = $this->checkoutSession->getData(self::SESSION_KEY);

        return is_array($state) ? $state : null;
    }
}
