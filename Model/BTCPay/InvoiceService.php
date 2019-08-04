<?php

namespace Storefront\BTCPay\Model\BTCPay;

use Bitpay\Token;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Transaction;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderRepository;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Storefront\BTCPay\Storage\EncryptedConfigStorage;

class InvoiceService {

    CONST KEY_PUBLIC = 'btcpay.pub';
    CONST KEY_PRIVATE = 'btcpay.priv';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var UrlInterface
     */
    private $url;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var AdapterInterface
     */
    private $db;

    /**
     * @var \Magento\Framework\HTTP\ZendClientFactory
     */
    private $httpClientFactory;

    /**
     * @var \Magento\Framework\App\Config\ConfigResource\ConfigInterface
     */
    private $configResource;
    /**
     * @var EncryptedConfigStorage
     */
    private $encryptedConfigStorage;
    /**
     * @var OrderRepository
     */
    private $orderRepository;

    /**
     * @var Transaction
     */
    private $transaction;
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(ResourceConnection $resource, EncryptedConfigStorage $encryptedConfigStorage, \Magento\Framework\App\Config\ConfigResource\ConfigInterface $configResource, StoreManagerInterface $storeManager, UrlInterface $url, \Magento\Framework\HTTP\ZendClientFactory $httpClientFactory, ScopeConfigInterface $scopeConfig, OrderRepository $orderRepository, Transaction $transaction, LoggerInterface $logger) {
        $this->httpClientFactory = $httpClientFactory;
        $this->scopeConfig = $scopeConfig;
        $this->url = $url;
        $this->storeManager = $storeManager;
        $this->db = $resource->getConnection();
        $this->configResource = $configResource;
        $this->encryptedConfigStorage = $encryptedConfigStorage;
        $this->orderRepository = $orderRepository;
        $this->transaction = $transaction;
        $this->logger = $logger;
    }


    /**
     * @param $storeId
     * @param bool $loadToken
     * @return \BitPay\Client\Client
     * @throws \BitPay\Client\BitpayException
     */
    private function getClient($storeId, $loadToken = true) {
        $client = new \BitPay\Client\Client();


        $privateKey = $this->getPrivateKey();
        $publicKey = $this->getPublicKey();
        $client->setPrivateKey($privateKey);
        $client->setPublicKey($publicKey);

        $host = $this->getHost($storeId);
        $port = $this->getPort($storeId);
        $network = new \Bitpay\Network\Customnet($host, $port);
        $client->setNetwork($network);

        $adapter = new \BitPay\Client\Adapter\CurlAdapter();
        $client->setAdapter($adapter);

        if ($loadToken) {
            $token = $this->getTokenOrRegenerate($storeId);
            $token->setFacade('merchant');
            $client->setToken($token);
        }

        return $client;
    }

    /**
     * @param $storeId
     * @param null $pairingCode New pairing code to set, or if empty load the pairing code entered in Magento config
     * @return Token
     * @throws \BitPay\Client\BitpayException
     */
    public function pair($storeId, $pairingCode = null) {

        if ($pairingCode === null) {
            $pairingCode = $this->getPairingCode($storeId);
        } else {
            $this->setPairingCode($pairingCode);
        }

        /**
         * Start by creating a PrivateKey object
         */
        $privateKey = new \BitPay\PrivateKey(self::KEY_PRIVATE);

        // Generate a random number
        $privateKey->generate();

        // Once we have a private key, a public key is created from it.
        $publicKey = new \BitPay\PublicKey(self::KEY_PUBLIC);

        // Inject the private key into the public key
        $publicKey->setPrivateKey($privateKey);

        // Generate the public key
        $publicKey->generate();

        $this->encryptedConfigStorage->persist($privateKey);
        $this->encryptedConfigStorage->persist($publicKey);

        $client = $this->getClient($storeId, false);


        /**
         * Currently this part is required, however future versions of the PHP SDK will
         * be refactor and this part may become obsolete.
         */
        $sin = \BitPay\SinKey::create()->setPublicKey($publicKey)->generate();
        /**** end ****/

        $baseUrl = $this->getStoreConfig('web/unsecure/base_url', $storeId);
        $baseUrl = str_replace('http://', '', $baseUrl);
        $baseUrl = str_replace('https://', '', $baseUrl);
        $baseUrl = trim($baseUrl, ' /');

        $token = $client->createToken([
            'pairingCode' => $pairingCode,
            'label' => $baseUrl . ' (Magento 2 Storefront_BTCPay, ' . date('Y-m-d H:i:s') . ')',
            'id' => (string)$sin,
        ]);

        $this->configResource->saveConfig('payment/btcpay/pairing_code', $pairingCode);
        $this->configResource->saveConfig('payment/btcpay/token', $token->getToken());

        $client->setToken($token);
        // TODO test the new token somehow?

        //$x = $client->getPayouts();

        return $token;
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @return \BitPay\Invoice
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function createInvoice(\Magento\Sales\Model\Order $order) {
        $storeId = $order->getStoreId();
        $orderId = $order->getId();

        $client = $this->getClient($storeId);

        $btcpayInvoice = new \BitPay\Invoice();

        $ba = $order->getBillingAddress();

        $buyer = new \BitPay\Buyer();
        $buyer->setFirstName($order->getCustomerFirstname());
        $buyer->setLastName($order->getCustomerLastname());
        $buyer->setCountry($ba->getCountryId());
        $buyer->setState($ba->getRegionCode());
        $buyer->setAddress($ba->getStreet());
        $buyer->setAgreedToTOSandPP(true);
        $buyer->setCity($ba->getCity());
        $buyer->setPhone($ba->getTelephone());
        $buyer->setZip($ba->getPostcode());
        $buyer->setEmail($order->getCustomerEmail());

        // TODO what does this notify field to exactly? BTCPay never emails customers directly.
        $buyer->setNotify(true);

        // Add the buyers info to invoice
        $btcpayInvoice->setBuyer($buyer);

        $item = new \BitPay\Item();
        $item->setCode($order->getIncrementId());
        // TODO the description "Order #%1" is hard coded and not in the locale of the customer.
        $item->setDescription('Order #' . $order->getIncrementId());
        $item->setPrice($order->getGrandTotal());
        $item->setQuantity(1);
        $item->setPhysical(!$order->getIsVirtual());

        $btcpayInvoice->setItem($item);

        /**
         * BTCPayServer supports multiple different currencies. Most shopping cart applications
         * and applications in general have defined set of currencies that can be used.
         * Setting this to one of the supported currencies will create an invoice using
         * the exchange rate for that currency.
         *
         * @see https://docs.btcpayserver.org/faq-and-common-issues/faq-general#which-cryptocurrencies-are-supported-in-btcpay for supported currencies
         */
        $btcpayInvoice->setCurrency(new \BitPay\Currency($order->getOrderCurrencyCode()));

        // Configure the rest of the invoice
        $ipnUrl = $this->storeManager->getStore()->getBaseUrl() . 'rest/V1/btcpay/ipn';

        $btcpayInvoice->setOrderId($order->getIncrementId());
        $btcpayInvoice->setNotificationUrl($ipnUrl);

        // If we use this, the IPN notifications will be emailed to this address.
        //$invoice->setNotificationEmail();

        // When using extended notifications, the JSON is different and we get a lot more (too many even) notifications. Not needed.
        $btcpayInvoice->setExtendedNotifications(false);

        $orderHash = $this->getOrderHash($order);
        $returnUrl = $order->getStore()->getUrl('btcpay/redirect/returnafterpayment', [
            'orderId' => $order->getId(),
            'hash' => $orderHash,
            'invoiceId' => $btcpayInvoice->getId(),
            '_secure' => true
        ]);
        $btcpayInvoice->setRedirectUrl($returnUrl);

        $client->createInvoice($btcpayInvoice);

        $tableName = $this->db->getTableName('btcpay_invoices');
        $this->db->insert($tableName, [
            'order_id' => $orderId,
            'invoice_id' => $btcpayInvoice->getId(),
            'status' => 'new'
        ]);

        return $btcpayInvoice;
    }


    /**
     * @param string $invoiceId
     * @return Order|null
     * @throws InputException
     * @throws NoSuchEntityException
     * @throws \BitPay\Client\BitpayException
     */
    public function updateInvoice(string $invoiceId): ?Order {
        $tableName = $this->db->getTableName('btcpay_invoices');
        $select = $this->db->select()->from($tableName)->where('invoice_id = ?', $invoiceId)->limit(1);

        $row = $this->db->fetchRow($select);
        if ($row) {

            $orderId = $row['order_id'];
            /* @var $order \Magento\Sales\Model\Order */
            $order = $this->orderRepository->get($orderId);

            $storeId = $order->getStoreId();

            $client = $this->getClient($storeId);
            $invoice = $client->getInvoice($invoiceId);

            if ($order->getIncrementId() !== $invoice->getOrderId()) {
                throw new RuntimeException('The supplied order "' . $orderId . '"" does not match BTCPay Invoice "' . $invoiceId . '"". Cannot process BTCPay Server IPN.');
            }

            $invoiceStatus = $invoice->getStatus();

            // TODO refactor to use the model instead of direct SQL reading
            $where = $this->db->quoteInto('order_id = ?', $orderId) . ' and ' . $this->db->quoteInto('invoice_id = ?', $invoiceId);
            $rowsChanged = $this->db->update($tableName, ['status' => $invoiceStatus], $where);

            switch ($invoiceStatus) {
                case \Storefront\BTCPay\Model\Invoice::STATUS_PAID:
                    // 1) Payments have been made to the invoice for the requested amount but the invoice has not been confirmed yet. We also don't know if the amount is enough.
                    $paidNotConfirmedStatus = $this->getStoreConfig('payment/btcpay/payment_paid_status', $storeId);

                    $order->addStatusHistoryComment('Payment underway, but not sure about the amount and also not confirmed yet', $paidNotConfirmedStatus);
                    $order->save();
                    break;
                case \Storefront\BTCPay\Model\Invoice::STATUS_CONFIRMED:

                    // 2) Paid and confirmed (happens before completed and transitions to it quickly)

                    // TODO maybe add the transation ID in the comment or something like that?

                    $confirmedStatus = $this->getStoreConfig('payment/btcpay/payment_confirmed_status', $storeId);
                    $order->addStatusHistoryComment('Payment confirmed, but not completed yet', $confirmedStatus);

                    $order->save();
                    break;
                case \Storefront\BTCPay\Model\Invoice::STATUS_COMPLETE:
                    // 3) Paid, confirmed and settled. Final!
                    $completedStatus = $this->getStoreConfig('payment/btcpay/payment_completed_status', $storeId);
                    if ($order->canInvoice()) {
                        if ($completedStatus) {
                            $order->addStatusHistoryComment('Payment completed', $completedStatus);
                        } else {
                            $order->addStatusHistoryComment('Payment completed');
                        }
                        $invoice = $order->prepareInvoice();
                        $invoice->setState(\Magento\Sales\Model\Order\Invoice::STATE_PAID);
                        $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE);
                        $invoice->register();

                        // TODO we really need to save the invoice first as we are saving it again in this invoice? Leaving it out for now.
                        //$invoice->save();

                        $invoiceSave = $this->transaction->addObject($invoice)->addObject($invoice->getOrder());
                        $invoiceSave->save();
                    }
                    break;
                case \Storefront\BTCPay\Model\Invoice::STATUS_INVALID:
                    $order->addStatusHistoryComment('Failed to confirm the order. The order will automatically update when the status changes.');
                    $order->save();
                    break;
                case \Storefront\BTCPay\Model\Invoice::STATUS_EXPIRED:
                    // Invoice expired - let's do nothing?
                default:
                    $order->addStatusHistoryComment('Invoice status: ' . $invoiceStatus);
                    $this->logger->exception('Unknown invoice state "' . $invoiceStatus . '" for invoice "' . $invoiceId . '"');
                    break;
            }
//
//                case 'invoice_refundComplete':
//                    // Full refund
//
//                    $order->addStatusHistoryComment('Refund received through BTCPay Server.');
//                    $order->setState(Order::STATE_CLOSED)->setStatus(Order::STATE_CLOSED);
//
//                    $order->save();
//
//                    break;
//
//                // TODO what about partial refunds, partial payments and overpayment?
            return $order;

        } else {
            // No invoice record found
            return null;
        }
    }

    public function updateIncompleteInvoices() {
        // TODO refactor to use the Invoice model instead of direct SQL reading
        $tableName = $this->db->getTableName('btcpay_invoices');
        $select = $this->db->select()->from($tableName)->where('status != ?', 'completed')->limit(1);

        $r = 0;

        $rows = $this->db->fetchAll($select);

        foreach ($rows as $row) {
            $invoiceId = $row['invoice_id'];
            $this->updateInvoice($invoiceId);
            $r++;
        }
        return $r;
    }


//    public function getInvoiceURL() {
//        $data = json_decode($this->invoiceData, true);
//        return $data['data']['url'] ?? false;
//    }

//    public function updateBuyersEmail($invoice_result, $buyers_email) {
//        $invoice_result = json_decode($invoice_result, false);
//
//        $token = $this->getPairingCode();
//
//        $update_fields = new stdClass();
//        $update_fields->token = $token;
//        $update_fields->buyerProvidedEmail = $buyers_email;
//        $update_fields->invoiceId = $invoice_result->data->id;
//        $update_fields = json_encode($update_fields);
//        // TODO replace with Zend HTTP Client
//        $ch = curl_init();
//        curl_setopt($ch, CURLOPT_URL, 'https://' . $this->item->getBuyerInvoiceEndpoint());
//        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
//        curl_setopt($ch, CURLOPT_POST, 1);
//        curl_setopt($ch, CURLOPT_POSTFIELDS, $update_fields);
//        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//        $result = curl_exec($ch);
//        curl_close($ch);
//        return $result;
//    }
//
//    public function updateBuyerCurrency($invoice_result, $buyer_currency) {
//        $invoice_result = json_decode($invoice_result);
//
//        $update_fields = new stdClass();
//        $update_fields->token = $this->item->item_params->token;
//        $update_fields->buyerSelectedInvoiceCurrency = $buyer_currency;
//        $update_fields->invoiceId = $invoice_result->data->id;
//        $update_fields = json_encode($update_fields);
//        // TODO replace with Zend HTTP Client
//        $ch = curl_init();
//        curl_setopt($ch, CURLOPT_URL, 'https://' . $this->item->getBuyerInvoiceEndpoint());
//        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
//        curl_setopt($ch, CURLOPT_POST, 1);
//        curl_setopt($ch, CURLOPT_POSTFIELDS, $update_fields);
//        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//        $result = curl_exec($ch);
//        curl_close($ch);
//        return $result;
//    }
//
//    /**
//     * @return string
//     */
//    public function getBuyerInvoiceEndpoint(): string {
//        return $this->host . '/invoiceData/setBuyerSelectedInvoiceCurrency';
//    }

    public function getStoreConfig($path, $storeId): ?string {
        $r = $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
        return $r;
    }

    private function getPairingCode(int $storeId): ?string {
        $r = $this->getStoreConfig('payment/btcpay/pairing_code', $storeId);
        return $r;
    }

    /**
     * @param int $storeId
     * @return \Storefront\BTCPay\Model\BTCPay\Token
     * @throws \BitPay\Client\BitpayException
     */
    private function getTokenOrRegenerate(int $storeId): Token {
        $tokenString = $this->getStoreConfig('payment/btcpay/token', $storeId);

        if (!$tokenString) {
            $tokenString = $this->pair($storeId);
        }
        $token = new Token();
        $token->setToken($tokenString);

        return $token;
    }

    private function getHost($storeId) {
        $r = $this->getStoreConfig('payment/btcpay/host', $storeId);
        return $r;
    }

    public function getInvoiceDetailUrl(int $storeId, string $invoiceId) {
        $host = $this->getHost($storeId);
        $r = 'https://' . $host . '/invoices/' . $invoiceId;
        return $r;
    }


    /**
     * @param $pairingCode
     * @return bool
     */
    public function setPairingCode($pairingCode) {
        // TODO if we want to make this module multi-BTCPay server, this would need to be store view scoped
        $this->configResource->saveConfig('payment/btcpay/pairing_code', $pairingCode);
        // TODO flush the cache after this
        return true;
    }

    /**
     * @return \Bitpay\KeyInterface
     */
    public function getPrivateKey() {
        return $this->encryptedConfigStorage->load(\Storefront\BTCPay\Model\BTCPay\InvoiceService::KEY_PRIVATE);
    }

    /**
     * @return \Bitpay\KeyInterface
     */
    public function getPublicKey() {
        return $this->encryptedConfigStorage->load(\Storefront\BTCPay\Model\BTCPay\InvoiceService::KEY_PUBLIC);
    }

    private function getPort($storeId) {
        // TODO port is hard coded for now
        return 443;
    }

    /**
     * Create a unique hash for an order
     * @param \Magento\Sales\Model\Order $order
     * @return string
     */
    public function getOrderHash(\Magento\Sales\Model\Order $order) {
        $preHash = $order->getId() . '-' . $order->getIncrementId() . '-' . $order->getSecret() . '-' . $order->getCreatedAt();
        $r = sha1($preHash);
        return $r;
    }
}
