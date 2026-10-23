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

class WebhookSecretTest extends TestCase
{
    /**
     * @var string|null
     */
    private $storedValue;

    /**
     * @var WriterInterface
     */
    private $configWriter;

    /**
     * @var EncryptorInterface
     */
    private $encryptor;

    /**
     * @var BTCPayService
     */
    private $service;

    protected function setUp(): void
    {
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
                    return BTCPayService::CONFIG_WEBHOOK_SECRET;
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

        $this->configWriter = $this->createMock(WriterInterface::class);
        $this->configWriter->method('save')->willReturnCallback(function ($path, $value) {
            $this->storedValue = (string)$value;
        });

        $this->encryptor = $this->createMock(EncryptorInterface::class);

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($this->createMock(AdapterInterface::class));

        $this->service = new BTCPayService(
            $resource,
            $this->createMock(ScopeConfigInterface::class),
            $this->createMock(OrderRepository::class),
            $this->createMock(Transaction::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(Url::class),
            $this->createMock(StoreManagerInterface::class),
            $this->configWriter,
            $this->createMock(ValueFactory::class),
            $collectionFactory,
            $this->createMock(RequestInterface::class),
            $this->createMock(StoresConfig::class),
            $this->createMock(ReinitableConfigInterface::class),
            $this->createMock(PriceHelper::class),
            $this->encryptor
        );
    }

    public function testLegacyPlaintextSecretIsReturnedUnchanged(): void
    {
        $this->storedValue = str_repeat('a', 64);
        $this->encryptor->expects(self::never())->method('decrypt');
        $this->configWriter->expects(self::never())->method('save');

        self::assertSame($this->storedValue, $this->service->getWebhookSecret(0));
    }

    public function testEncryptedSecretIsDecrypted(): void
    {
        $this->storedValue = '0:3:encrypted-value';
        $this->encryptor->expects(self::once())
            ->method('decrypt')
            ->with($this->storedValue)
            ->willReturn(str_repeat('b', 64));
        $this->configWriter->expects(self::never())->method('save');

        self::assertSame(str_repeat('b', 64), $this->service->getWebhookSecret(0));
    }

    public function testMissingSecretIsCreatedAndStoredEncrypted(): void
    {
        $this->storedValue = null;
        $this->encryptor->expects(self::once())
            ->method('encrypt')
            ->willReturn('0:3:encrypted-value');

        $secret = $this->service->getWebhookSecret(0);

        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', (string)$secret);
        self::assertSame('0:3:encrypted-value', $this->storedValue);
    }

    public function testUndecryptableSecretReturnsNullWithoutOverwriting(): void
    {
        $this->storedValue = '0:3:broken-value';
        $this->encryptor->method('decrypt')->willThrowException(new \RuntimeException('Cannot decrypt'));
        $this->configWriter->expects(self::never())->method('save');

        self::assertNull($this->service->getWebhookSecret(0));
    }

    public function testStoredSecretIsNullWhenMissing(): void
    {
        $this->storedValue = null;

        self::assertNull($this->service->getStoredWebhookSecret());
    }

    public function testStoredSecretReturnsLegacyPlaintext(): void
    {
        $this->storedValue = str_repeat('c', 64);

        self::assertSame($this->storedValue, $this->service->getStoredWebhookSecret());
    }

    public function testStoredSecretIsDecrypted(): void
    {
        $this->storedValue = '0:3:encrypted-value';
        $this->encryptor->method('decrypt')->willReturn('decrypted-secret');

        self::assertSame('decrypted-secret', $this->service->getStoredWebhookSecret());
    }
}
