<?php
declare(strict_types=1);

namespace Storefront\BTCPay\Test\Unit\Model\Security;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\TestCase;
use Storefront\BTCPay\Model\Security\OrderReturnReference;

class OrderReturnReferenceTest extends TestCase
{
    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var OrderReturnReference
     */
    private $reference;

    protected function setUp(): void
    {
        $this->checkoutSession = new class extends CheckoutSession {
            /**
             * @var array
             */
            private $sessionData = [];

            public function __construct()
            {
            }

            public function setData($key, $value = null)
            {
                $this->sessionData[$key] = $value;

                return $this;
            }

            public function getData($key = '', $clear = false)
            {
                $value = $key === '' ? $this->sessionData : ($this->sessionData[$key] ?? null);
                if ($clear && $key !== '') {
                    unset($this->sessionData[$key]);
                }

                return $value;
            }

            public function unsetData($key = null)
            {
                if ($key === null) {
                    $this->sessionData = [];
                } else {
                    unset($this->sessionData[$key]);
                }

                return $this;
            }
        };
        $this->reference = new OrderReturnReference($this->checkoutSession);
    }

    public function testReferenceIsRandomBoundAndConsumable(): void
    {
        $order = $this->createMock(Order::class);
        $order->method('getId')->willReturn(42);
        $order->method('getIncrementId')->willReturn('000000042');

        $value = $this->reference->create($order);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $value);
        self::assertNull($this->reference->resolve($value));
        self::assertTrue($this->reference->bindInvoice('invoice-1'));
        self::assertSame([
            'order_id' => 42,
            'order_increment_id' => '000000042',
            'invoice_id' => 'invoice-1',
        ], $this->reference->resolve($value));
        self::assertNull($this->reference->resolve(str_repeat('a', 64)));

        $this->reference->consume();
        self::assertNull($this->reference->resolve($value));
    }

    public function testExpiredReferenceIsRejected(): void
    {
        $value = str_repeat('b', 64);
        $this->checkoutSession->setData('btcpay_return_reference', [
            'hash' => hash('sha256', $value),
            'expires' => time() - 1,
            'order_id' => 42,
            'order_increment_id' => '000000042',
            'invoice_id' => 'invoice-1',
        ]);

        self::assertNull($this->reference->resolve($value));
    }

    public function testMalformedReferenceIsRejected(): void
    {
        self::assertNull($this->reference->resolve('not-a-reference'));
        self::assertFalse($this->reference->bindInvoice(''));
    }
}
