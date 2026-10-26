<?php
declare(strict_types=1);

namespace Storefront\BTCPay\Model\Webhook;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Psr\Log\LoggerInterface;

/**
 * Tracks BTCPay webhook deliveries that have already been processed.
 *
 * A delivery is claimed before it is processed, so concurrent or replayed
 * requests carrying the same delivery ID cannot change the invoice or order a
 * second time. If processing fails, the claim is released so a later retry can
 * process the delivery.
 */
class DeliveryTracker
{
    private const TABLE = 'btcpay_webhook_deliveries';

    /**
     * Deliveries older than this are pruned. Replaying an event after this
     * window is still safe because invoice status changes are conditional.
     */
    private const RETENTION_DAYS = 30;

    /**
     * @var AdapterInterface
     */
    private $db;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(ResourceConnection $resource, LoggerInterface $logger)
    {
        $this->db = $resource->getConnection();
        $this->logger = $logger;
    }

    /**
     * Atomically claim a delivery ID.
     *
     * @return bool True when this request owns the delivery, false when it was
     *              already claimed by an earlier request.
     * @throws \Exception When the claim fails for a reason other than a
     *                    duplicate delivery ID.
     */
    public function claim(string $deliveryId, ?string $invoiceId, ?string $eventType): bool
    {
        $table = $this->db->getTableName(self::TABLE);
        $this->prune($table);

        try {
            $this->db->insert($table, [
                'delivery_id' => $deliveryId,
                'invoice_id' => $invoiceId,
                'event_type' => $eventType,
                'created_at' => gmdate('Y-m-d H:i:s'),
            ]);

            return true;
        } catch (\Exception $e) {
            if ($this->isClaimed($deliveryId)) {
                return false;
            }

            throw $e;
        }
    }

    /**
     * Release a claim after processing failed so the delivery can be retried.
     */
    public function release(string $deliveryId): void
    {
        $table = $this->db->getTableName(self::TABLE);
        $this->db->delete($table, ['delivery_id = ?' => $deliveryId]);
    }

    public function isClaimed(string $deliveryId): bool
    {
        $table = $this->db->getTableName(self::TABLE);
        $select = $this->db->select()
            ->from($table, ['id'])
            ->where('delivery_id = ?', $deliveryId)
            ->limit(1);

        return (bool)$this->db->fetchOne($select);
    }

    private function prune(string $table): void
    {
        $cutoff = gmdate('Y-m-d H:i:s', time() - self::RETENTION_DAYS * 86400);

        try {
            $this->db->delete($table, ['created_at < ?' => $cutoff]);
        } catch (\Throwable $e) {
            // Pruning is best-effort and must never block webhook processing.
            $this->logger->warning(
                'Could not prune processed BTCPay webhook deliveries: ' . $e->getMessage()
            );
        }
    }
}
