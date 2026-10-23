<?php
declare(strict_types=1);

namespace Storefront\BTCPay\Test\Unit\Model\BTCPay;

use Magento\Config\Model\ResourceModel\Config\Data\Collection;
use Magento\Config\Model\ResourceModel\Config\Data\CollectionFactory;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Config\ValueFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Transaction;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;
use Magento\Framework\Url;
use Magento\Sales\Model\OrderRepository;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\StoresConfig;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Storefront\BTCPay\Model\BTCPay\BTCPayService;

class PairingTokenTest extends TestCase
{
    /**
     * @var string|null
     */
    private $storedValue;

    /**
     * @var AdapterInterface
     */
    private $adapter;

    /**
     * @var BTCPayService
     */
    private $service;

    protected function setUp(): void
    {
        $this->adapter = $this->createMock(AdapterInterface::class);

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($this->adapter);

        $collection = $this->createMock(Collection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('getIterator')->willReturnCallback(function () {
            if ($this->storedValue === null) {
                return new \ArrayIterator([]);
            }

            $row = new class($this->storedValue) {
                private $value;

                public function __construct(string $value)
                {
                    $this->value = $value;
                }

                public function getPath(): string
                {
                    return BTCPayService::CONFIG_PAIRING_TOKEN;
                }

                public function getValue(): string
                {
                    return $this->value;
                }
            };

            return new \ArrayIterator([$row]);
        });

        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        $configWriter = $this->createMock(WriterInterface::class);
        $configWriter->method('save')->willReturnCallback(function ($path, $value) {
            $this->storedValue = (string)$value;
        });

        $this->service = new BTCPayService(
            $resource,
            $this->createMock(ScopeConfigInterface::class),
            $this->createMock(OrderRepository::class),
            $this->createMock(Transaction::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(Url::class),
            $this->createMock(StoreManagerInterface::class),
            $configWriter,
            $this->createMock(ValueFactory::class),
            $collectionFactory,
            $this->createMock(RequestInterface::class),
            $this->createMock(StoresConfig::class),
            $this->createMock(ReinitableConfigInterface::class),
            $this->createMock(PriceHelper::class),
            $this->createMock(EncryptorInterface::class)
        );
    }

    public function testCreatedTokenHasExpectedFormat(): void
    {
        $token = $this->service->createPairingToken('https://btcpay.example/');

        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
        self::assertMatchesRegularExpression(
            '/^[a-f0-9]{64}:\d+:https%3A%2F%2Fbtcpay\.example%2F$/',
            (string)$this->storedValue
        );
    }

    public function testCorrectTokenIsConsumed(): void
    {
        $token = $this->service->createPairingToken('https://btcpay.example/');

        $this->adapter->expects(self::once())->method('delete')->willReturn(1);
        self::assertTrue($this->service->consumePairingToken($token, 'https://btcpay.example/'));
    }

    public function testNullBaseUrlRoundTrip(): void
    {
        $token = $this->service->createPairingToken(null);

        $this->adapter->expects(self::once())->method('delete')->willReturn(1);
        self::assertTrue($this->service->consumePairingToken($token, null));
    }

    public function testWrongTokenIsRejected(): void
    {
        $this->service->createPairingToken('https://btcpay.example/');

        $this->adapter->expects(self::never())->method('delete');
        self::assertFalse(
            $this->service->consumePairingToken('wrong-token', 'https://btcpay.example/')
        );
    }

    public function testWrongBaseUrlIsRejected(): void
    {
        $token = $this->service->createPairingToken('https://btcpay.example/');

        $this->adapter->expects(self::once())->method('delete')->willReturn(1);
        self::assertFalse(
            $this->service->consumePairingToken($token, 'https://attacker.example/')
        );
    }

    public function testExpiredTokenIsRejected(): void
    {
        $token = 'expired-token';
        $this->storedValue = implode(':', [
            hash('sha256', $token),
            (string)(time() - BTCPayService::PAIRING_TOKEN_TTL - 1),
            rawurlencode('https://btcpay.example/'),
        ]);

        $this->adapter->expects(self::once())->method('delete')->willReturn(1);
        self::assertFalse(
            $this->service->consumePairingToken($token, 'https://btcpay.example/')
        );
    }

    public function testMissingStoredTokenIsRejected(): void
    {
        $this->adapter->expects(self::never())->method('delete');
        self::assertFalse(
            $this->service->consumePairingToken('any-token', 'https://btcpay.example/')
        );
    }

    public function testLegacyAdminBoundRecordIsRejected(): void
    {
        $token = 'legacy-token';
        $this->storedValue = implode(':', [
            hash('sha256', $token),
            (string)time(),
            '7',
            rawurlencode('https://btcpay.example/'),
        ]);

        $this->adapter->expects(self::once())->method('delete')->willReturn(1);
        self::assertFalse(
            $this->service->consumePairingToken($token, 'https://btcpay.example/')
        );
    }
}
