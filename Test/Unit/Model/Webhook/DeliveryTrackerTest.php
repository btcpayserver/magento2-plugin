<?php
declare(strict_types=1);

namespace Storefront\BTCPay\Test\Unit\Model\Webhook;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Storefront\BTCPay\Model\Webhook\DeliveryTracker;

class DeliveryTrackerTest extends TestCase
{
    private const TABLE = 'btcpay_webhook_deliveries';

    /**
     * @var AdapterInterface
     */
    private $adapter;

    /**
     * @var DeliveryTracker
     */
    private $tracker;

    protected function setUp(): void
    {
        $this->adapter = $this->createMock(AdapterInterface::class);
        $this->adapter->method('getTableName')->willReturn(self::TABLE);

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($this->adapter);

        $this->tracker = new DeliveryTracker(
            $resource,
            $this->createMock(LoggerInterface::class)
        );
    }

    public function testClaimInsertsDelivery(): void
    {
        $this->adapter->expects(self::once())->method('delete');
        $this->adapter->expects(self::once())
            ->method('insert')
            ->with(self::TABLE, self::callback(function (array $data): bool {
                return $data['delivery_id'] === 'delivery-1'
                    && $data['invoice_id'] === 'invoice-1'
                    && $data['event_type'] === 'InvoiceSettled'
                    && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $data['created_at']) === 1;
            }));

        self::assertTrue($this->tracker->claim('delivery-1', 'invoice-1', 'InvoiceSettled'));
    }

    public function testDuplicateDeliveryIsNotClaimed(): void
    {
        $this->adapter->method('delete');
        $this->adapter->method('insert')->willThrowException(new \Exception('Duplicate entry'));
        $this->mockFetchOne('1');

        self::assertFalse($this->tracker->claim('delivery-1', 'invoice-1', 'InvoiceSettled'));
    }

    public function testClaimFailureIsRethrownWhenDeliveryIsNotStored(): void
    {
        $this->adapter->method('delete');
        $this->adapter->method('insert')->willThrowException(new \Exception('Table missing'));
        $this->mockFetchOne(false);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Table missing');

        $this->tracker->claim('delivery-1', 'invoice-1', 'InvoiceSettled');
    }

    public function testReleaseDeletesDelivery(): void
    {
        $this->adapter->expects(self::once())
            ->method('delete')
            ->with(self::TABLE, ['delivery_id = ?' => 'delivery-1']);

        $this->tracker->release('delivery-1');
    }

    public function testIsClaimedReturnsTrueWhenRowExists(): void
    {
        $this->mockFetchOne('1');

        self::assertTrue($this->tracker->isClaimed('delivery-1'));
    }

    public function testIsClaimedReturnsFalseWhenRowIsMissing(): void
    {
        $this->mockFetchOne(false);

        self::assertFalse($this->tracker->isClaimed('delivery-1'));
    }

    /**
     * @param mixed $value
     */
    private function mockFetchOne($value): void
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $select->method('limit')->willReturnSelf();

        $this->adapter->method('select')->willReturn($select);
        $this->adapter->method('fetchOne')->willReturn($value);
    }
}
