<?php
declare(strict_types=1);
/**
 * Integrates BTCPay Server with Magento 2 for online payments
 * @copyright Copyright © 2019-2021 Storefront bv. All rights reserved.
 * @author    Wouter Samaey - wouter.samaey@storefront.be
 *
 * This file is part of Storefront/BTCPay.
 *
 * Storefront/BTCPay is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace Storefront\BTCPay\Model\BTCPay;

use BTCPayServer\Client\InvoiceCheckoutOptions;
use BTCPayServer\Result\Invoice as BTCPayServerInvoice;
use BTCPayServer\Util\PreciseNumber;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Magento\Config\Model\ResourceModel\Config\Data\CollectionFactory;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Config\ValueFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Transaction;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\Helper\Data;
use Magento\Framework\Url;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderRepository;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\StoresConfig;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Storefront\BTCPay\Model\BTCPay\Exception\ForbiddenException;
use Storefront\BTCPay\Model\Invoice;
use Storefront\BTCPay\Model\OrderStatuses;

class BTCPayService
{
    const CONFIG_API_KEY = 'payment/btcpay/api_key';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var AdapterInterface
     */
    private $db;

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

    /**
     * @var Url $urlBuilder
     */
    private $urlBuilder;

    /**
     * @var StoreManagerInterface $storeManager
     */
    private $storeManager;

    /**
     * @var WriterInterface $configWriter
     */
    private $configWriter;

    /**
     * @var ValueFactory
     */
    private $configValueFactory;

    /**
     * @var CollectionFactory $configCollectionFactory
     */
    private $configCollectionFactory;

    /**
     * @var RequestInterface $request
     */
    private $request;

    /**
     * @var StoresConfig $storesConfig
     */
    private $storesConfig;

    /**
     * @var ReinitableConfigInterface $reinitableConfig
     */
    private $reinitableConfig;

    /**
     * @var Data $priceHelper
     */
    private $priceHelper;


    public function __construct(ResourceConnection $resource, ScopeConfigInterface $scopeConfig, OrderRepository $orderRepository, Transaction $transaction, LoggerInterface $logger, Url $urlBuider, StoreManagerInterface $storeManager, WriterInterface $configWriter, ValueFactory $configValueFactory, CollectionFactory $configCollectionFactory, RequestInterface $request, StoresConfig $storesConfig, ReinitableConfigInterface $reinitableConfig, Data $priceHelper)
    {
        $this->scopeConfig = $scopeConfig;
        $this->db = $resource->getConnection();
        $this->orderRepository = $orderRepository;
        $this->transaction = $transaction;
        $this->logger = $logger;
        $this->urlBuilder = $urlBuider;
        $this->storeManager = $storeManager;
        $this->configWriter = $configWriter;
        $this->configValueFactory = $configValueFactory;
        $this->configCollectionFactory = $configCollectionFactory;
        $this->request = $request;
        $this->storesConfig = $storesConfig;
        $this->reinitableConfig = $reinitableConfig;
        $this->priceHelper = $priceHelper;
    }

    /**
     * @param int $storeId
     * @return Client
     */
    private function getClient(int $storeId): Client
    {
        $host = $this->getHost($storeId);
        $port = $this->getPort($storeId);
        $scheme = $this->getScheme($storeId);

        $client = new Client(['base_uri' => $scheme . '://' . $host . ':' . $port]);

        return $client;
    }

    public function getBtcPayServerBaseUrl(): ?string
    {
        $r = $this->getStoreConfig('payment/btcpay/btcpay_base_url', 0);
        $r = rtrim((string)$r, '/') . '/';
        return $r;
    }

    /**
     * @param Order $order
     * @return Invoice
     * @throws NoSuchEntityException
     * @throws \JsonException
     */
    public function createInvoice(Order $order): array
    {
        $magentoStoreId = (int)$order->getStoreId();
        $btcPayStoreId = $this->getBtcPayStore($magentoStoreId);
        if (!$btcPayStoreId) {
            throw new \Storefront\BTCPay\Model\BTCPay\Exception\NoBtcPayStoreConfigured();
        }
        $client = new \BTCPayServer\Client\Invoice($this->getBtcPayServerBaseUrl(), $this->getApiKey('default', 0));

        // TODO limit payment methods. By default all methods are shown.
        $paymentMethods = null;
        // Example: array with 'BTC', 'BTC-LightningNetwork'

        $expirationMinutes = null;

        $invoiceIdPlaceholder = 'INVOICE_ID';
        $orderHash = $this->getOrderHash($order);
        $returnUrl = $order->getStore()->getUrl('btcpay/redirect/returnafterpayment', [
            'orderId' => $order->getId(),
            'invoiceId' => $invoiceIdPlaceholder,
            'hash' => $orderHash,
            '_secure' => true
        ]);

        $returnUrl = str_replace($invoiceIdPlaceholder, '{InvoiceId}', $returnUrl);

        $orderLocale = $this->getStoreConfig('general/locale/code', $magentoStoreId);
        $defaultLanguage = str_replace('_', '-', $orderLocale);

        $ba = $order->getBillingAddress();

        if ($order->getIsVirtual() || $order->getIsDownloadable()) {
            $sa = $ba;
        } else {
            $sa = $order->getShippingAddress();
        }

        $postData = [];
        $postData['amount'] = PreciseNumber::parseFloat((float)$order->getGrandTotal());

        $postData['currency'] = $order->getOrderCurrencyCode();
        $postData['metadata']['buyerName'] = trim($order->getBillingAddress()->getCompany() . ', ' . $order->getCustomerName(), ', ');
        /*        $postData['metadata']['buyerEmail'] = $order->getCustomerEmail();*/
        $postData['metadata']['buyerCountry'] = $sa->getCountryId();
        $postData['metadata']['buyerZip'] = $sa->getPostcode();
        $postData['metadata']['buyerCity'] = $sa->getCity();
        $postData['metadata']['buyerState'] = $sa->getRegionCode();
        $postData['metadata']['buyerAddress1'] = $sa->getStreetLine(1);
        $postData['metadata']['buyerAddress2'] = $sa->getStreetLine(2);
        $postData['metadata']['buyerPhone'] = $ba->getTelephone();
        $postData['metadata']['physical'] = !$order->getIsVirtual();
        $postData['metadata']['taxIncluded'] = $order->getTaxAmount() > 0;

        //$postData['metadata']['posData'] = null;
        // $postData['metadata']['itemCode'] = '';
        // $postData['metadata']['itemDesc'] = '';
        // $postData['checkout']['speedPolicy'] = $speedPolicy;
        // $btcpayInvoice['checkout']['paymentMethods'] = $paymentMethods;
        // $btcpayInvoice['checkout']['monitoringMinutes'] = 90;
        // $btcpayInvoice['checkout']['paymentTolerance'] = 0;
        $postData['checkout']['redirectURL'] = $returnUrl;
        $postData['checkout']['redirectAutomatically'] = true;
        $postData['checkout']['defaultLanguage'] = $defaultLanguage;

        // Some extra fields not part of the BTCPay Server spec, but we are including them anyway
        $postData['metadata']['magentoOrderId'] = $order->getId();
        $postData['metadata']['buyerAddress3'] = $sa->getStreetLine(3);
        $postData['metadata']['buyerCompany'] = $ba->getCompany();
        $postData['metadata']['buyerFirstname'] = $order->getCustomerFirstname();
        $postData['metadata']['buyerMiddlename'] = $order->getCustomerMiddlename();
        $postData['metadata']['buyerLastname'] = $order->getCustomerLastname();
        $postData['metadata']['magentoStoreId'] = $magentoStoreId;

        $checkoutOptions = InvoiceCheckoutOptions::create(null, null, null, null, null, $returnUrl, true, $defaultLanguage);

        $invoice = $client->createInvoice($btcPayStoreId, $postData['currency'], $postData['amount'], $order->getIncrementId(), $order->getCustomerEmail(), $postData['metadata'], $checkoutOptions);

        return $invoice->getData();

        // Example:
        // {
        //  "id": "7ePcu635UAV9CiGBKDDQvr",
        //  "checkoutLink": "https:\/\/mybtcpay.com\/i\/7ePcu635UAV9CiGBKDDQvr",
        //  "status": "New",
        //  "additionalStatus": "None",
        //  "monitoringExpiration": 1622043882,
        //  "expirationTime": 1622040282,
        //  "createdTime": 1622039382,
        //  "amount": "166.0000",
        //  "currency": "EUR",
        //  "metadata": {
        //    "orderId": "1",
        //    "buyerName": "ACME Corp, Test Tester",
        //    "buyerEmail": "test.tester@acme.com",
        //    "buyerCountry": "US",
        //    "buyerZip": "1000",
        //    "buyerCity": "Test City",
        //    "buyerState": "TE",
        //    "buyerAddress1": "Test Street",
        //    "buyerAddress2": "100",
        //    "buyerPhone": "+123456789101",
        //    "physical": true,
        //    "taxIncluded": false,
        //    "orderIncrementId": "000000001"
        //  },
        //  "checkout": {
        //    "speedPolicy": "MediumSpeed",
        //    "paymentMethods": [
        //      "BTC"
        //    ],
        //    "expirationMinutes": 15,
        //    "monitoringMinutes": 60,
        //    "paymentTolerance": 0,
        //    "redirectURL": "http:\/\/domain.com\/btcpay\/redirect\/returnafterpayment\/orderId\/1\/invoiceId\/%7BInvoiceId%7D\/hash\/ab49d6c0a6696e17cffc9ae6386be83997741a4f\/",
        //    "redirectAutomatically": true,
        //    "defaultLanguage": "en"
        //  }
        //}
    }

    public function getBtcPayInvoiceIdFromMagentoId(int $magentoInvoiceId): ?string
    {
        $tableName = $this->db->getTableName('btcpay_invoices');
        $select = $this->db->select()->from($tableName, ['invoice_id'])->where('id = ?', $magentoInvoiceId)->limit(1);

        $btcPayInvoiceId = $this->db->fetchOne($select);
        if ($btcPayInvoiceId) {
            return $btcPayInvoiceId;
        } else {
            return null;
        }
    }

    /**
     * @param string $btcPayStoreId
     * @param string $invoiceId
     * @param bool|null $logPayment
     * @return Order|null
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws \JsonException
     */
    public function updateInvoice(string $btcPayStoreId, string $invoiceId, $logPayment = false): ?Order
    {
        $tableName = $this->db->getTableName('btcpay_invoices');
        $select = $this->db->select()->from($tableName)->where('invoice_id = ?', $invoiceId)->where('btcpay_store_id = ?', $btcPayStoreId)->limit(1);

        $row = $this->db->fetchRow($select);
        if ($row) {
            $orderId = (int)$row['order_id'];
            /* @var $order Order */
            $order = $this->orderRepository->get($orderId);

            $magentoStoreId = (int)$order->getStoreId();

            $invoice = $this->getInvoice($invoiceId, $btcPayStoreId, $magentoStoreId);

            if ($order->getIncrementId() !== $invoice->getData()['metadata']['orderId']) {
                throw new RuntimeException('The supplied order "' . $orderId . '"" does not match BTCPay Invoice "' . $invoiceId . '"". Cannot process BTCPay Server Webhook.');
            }

            $invoiceStatus = $invoice->getStatus();

            if ($logPayment) {
                $paymentInfo = $this->getPaymentInfo($magentoStoreId, $btcPayStoreId, $invoiceId, $invoice);

                $comment = 'Incoming payment: <br> Total invoice amount in ' . $paymentInfo['invoice_amount']
                    . '<br> Received: ' . $paymentInfo['amount_paid_store_currency'] . ' - ' . $paymentInfo['amount_received'] . $paymentInfo['currency']
                    . '<br> Rate at time of payment: 1 ' . $paymentInfo['currency'] . ' = ' . $paymentInfo['rate'] . ' ' . $paymentInfo['store_currency']
                    . '<br> Payment received: ' . $paymentInfo['paid_at'];

                $status = false;
                if ($invoice->isPartiallyPaid()) {
                    // paid partially
                    $status = OrderStatuses::STATUS_CODE_UNDERPAID;
                }
                $order->addCommentToStatusHistory($comment, $status, true);
                $order->save();
            }

            $where = $this->db->quoteInto('order_id = ?', $orderId) . ' and ' . $this->db->quoteInto('invoice_id = ?', $invoiceId);
            $rowsChanged = $this->db->update($tableName, ['status' => $invoiceStatus], $where);

            if ($rowsChanged === 1) {
                switch ($invoiceStatus) {
                    case BTCPayServerInvoice::STATUS_PROCESSING:

                        if ($invoice->isOverpaid()) {
                            // overpaid
                            $overPaidStatus = OrderStatuses::STATUS_CODE_OVERPAID;
                            $order->addCommentToStatusHistory('Payment underway: overpaid. Not confirmed yet.', $overPaidStatus, true);
                        } else {
                            // paid correctly
                            $paidCorrectlyStatus = OrderStatuses::STATUS_CODE_PAID_CORRECTLY;
                            $order->addCommentToStatusHistory('Payment underway: paid correctly. Not confirmed yet.', $paidCorrectlyStatus, true);
                        }
                        break;
                    case BTCPayServerInvoice::STATUS_SETTLED:
                        // 2) Payments are settled (marked or not)
                        $settledStatus = \Magento\Sales\Model\Order::STATE_COMPLETE;

                        if ($invoice->isOverpaid()) {
                            $order->addCommentToStatusHistory('Payment confirmed: overpaid.');
                        } elseif ($order->canInvoice()) {

                            // You can't be sure of the amount, when marked manually the additionalStatus is set to 'Marked' and has priority over 'Overpaid'
                            $comment = 'Payment confirmed.';

                            $marked = $invoice->isMarked();
                            if ($marked) {
                                $comment .= ' Marked manually.';
                            }
                            $order->addCommentToStatusHistory($comment, $settledStatus, true);
                            $invoice = $order->prepareInvoice();
                            $invoice->setRequestedCaptureCase(Order\Invoice::CAPTURE_OFFLINE);
                            $invoice->register();

                            $invoiceSave = $this->transaction->addObject($invoice)->addObject($invoice->getOrder());
                            $invoiceSave->save();
                        }

                        break;
                    case BTCPayServerInvoice::STATUS_INVALID:
                        // 3) Payment invalid (marked or not)

                        //When invalid you can't be sure of the amount either..

                        $invalidStatus = OrderStatuses::STATUS_CODE_INVALID;
                        $comment = 'Failed to confirm the order. The order will automatically update when the status changes.';
                        $marked = $invoice->isMarked();
                        if ($marked) {
                            $comment .= ' Marked manually.';
                        }
                        $order->addCommentToStatusHistory($comment, $invalidStatus, true);
                        break;
                    case BTCPayServerInvoice::STATUS_EXPIRED:

                        if ($invoice->isPartiallyPaid()) {
                            //Customer underpaid and the payment has been expired.
                            $order->addCommentToStatusHistory(('Payment is expired.'));
                        } else {

                            // Auto-cancel on Expiry
                            $autoCancel = $this->autoCancelOnExpiry();
                            if ($autoCancel) {
                                $btcpayInvoices = $this->getInvoicesByOrderIds($magentoStoreId, [$order->getIncrementId()]);
                                $isEverythingExpired = true;
                                foreach ($btcpayInvoices->all() as $invoice) {
                                    if (!$invoice->isExpired()) {
                                        $isEverythingExpired = false;
                                        break;
                                    }
                                }

                                if ($isEverythingExpired) {
                                    //Cancel Order
                                    $order->cancel();
                                    $order->addCommentToStatusHistory(('Payment is expired. Order is canceled'));
                                }
                            } else {
                                $order->addCommentToStatusHistory('Payment is expired.');
                            }
                        }

                        // TODO: Restore cart the cart even though the customer is not around. Can (s)he recover his/her cart without session?
                        break;
                    default:
                        $order->addCommentToStatusHistory('Invoice status: ' . $invoiceStatus);
                        $this->logger->error('Unknown invoice state "' . $invoiceStatus . '" for invoice "' . $invoiceId . '"');
                        break;
                }
                $order->save();

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

                return $order;
            } else {
                // Nothing was changed
                return null;
            }
        } else {
            // No invoice record found
            return null;
        }
    }

    public function updateIncompleteInvoices(): int
    {
        $tableName = $this->db->getTableName('btcpay_invoices');
        $select = $this->db->select()->from($tableName);
        $select->where('status != ?', BTCPayServerInvoice::STATUS_SETTLED);

        $r = 0;

        $rows = $this->db->fetchAll($select);

        foreach ($rows as $row) {
            $invoiceId = $row['invoice_id'];
            $btcPayStoreId = $row['btcpay_store_id'];
            $this->updateInvoice($btcPayStoreId, $invoiceId);
            $r++;
        }
        return $r;
    }

    public function getStoreConfig(string $path, int $storeId): ?string
    {
        $r = $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
        return $r;
    }

    private function getHost(int $storeId): ?string
    {
        $r = $this->getStoreConfig('payment/btcpay/host', $storeId);
        return $r;
    }

    private function getScheme(int $storeId): ?string
    {
        $r = $this->getStoreConfig('payment/btcpay/http_scheme', $storeId);
        return $r;
    }

    public function getInvoiceDetailUrl(int $magentoStoreId, string $invoiceId): string
    {
        $baseUrl = $this->getBtcPayServerBaseUrl();
        $r = $baseUrl . 'invoices/' . $invoiceId;
        return $r;
    }

    private function getPort(int $storeId): int
    {
        $r = $this->getStoreConfig('payment/btcpay/http_port', $storeId);
        if (!$r) {
            $scheme = $this->getScheme($storeId);
            if ($scheme === 'https') {
                $r = 443;
            } elseif ($scheme === 'http') {
                $r = 80;
            }
        }
        return (int)$r;
    }

    /**
     * Create a unique hash for an order
     * @param Order $order
     * @return string
     */
    public function getOrderHash(Order $order): string
    {
        $preHash = $order->getId() . '-' . $order->getSecret() . '-' . $order->getCreatedAt();
        $r = sha1($preHash);
        return $r;
    }

    public function getApiKeyPermissions($scope, $scopeId): ?array
    {
        try {
            $apiKey = $this->getApiKey($scope, $scopeId);
            if ($apiKey) {
                $client = new \BTCPayServer\Client\ApiKey($this->getBtcPayServerBaseUrl(), $apiKey);
                $data = $client->getCurrent();
                $data = $data->getData();
                $currentPermissions = $data['permissions'];
                sort($currentPermissions);
                return $currentPermissions;
            }
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function doRequest(int $magentoStoreId, string $url, string $method, array $postData = null): ResponseInterface
    {
        $apiKey = $this->getApiKey('default', 0);
        $client = $this->getClient($magentoStoreId);
        $options = [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => 'token ' . $apiKey
            ],
        ];

        if ($postData) {
            $options['json'] = $postData;
        }

        try {
            $r = $client->request($method, $url, $options);
        } catch (ClientException $e) {
            $r = $e->getResponse();
            if ($r->getStatusCode() === 403) {
                throw new ForbiddenException($e);
            }
        }
        return $r;
    }

    public function getApiKey($scope, $scopeId): ?string
    {
        $config = $this->getConfigWithoutCache('payment/btcpay/api_key', $scope, $scopeId);
        return $config;
    }

    private function getConfigWithoutCache($path, $scope, $scopeId): ?string
    {
        $dataCollection = $this->configValueFactory->create()->getCollection();
        $dataCollection->addFieldToFilter('path', ['like' => $path . '%']);
        $dataCollection->addFieldToFilter('scope', ['like' => $scope . '%']);
        $dataCollection->addFieldToFilter('scope_id', ['like' => $scopeId . '%']);

        $config = null;
        foreach ($dataCollection as $row) {
            $config[$row->getPath()] = $row->getValue();
        }

        return $config[$path] ?? null;
    }

    public function getInvoice(string $invoiceId, string $btcpayStoreId, int $magentoStoreId): \BTCPayServer\Result\Invoice
    {
        $client = new \BTCPayServer\Client\Invoice($this->getBtcPayServerBaseUrl(), $this->getApiKey('default', 0));

        $invoice = $client->getInvoice($btcpayStoreId, $invoiceId);

        return $invoice;
    }

    public function getAllBtcPayStores($baseUrl, $apiKey): ?array
    {
        $client = new \BTCPayServer\Client\Store($baseUrl, $apiKey);

        $stores = $client->getStores();

        return $stores;
    }

    public function getBtcPayStore(int $magentoStoreId): ?string
    {
        $btcPayStoreId = $this->getStoreConfig('payment/btcpay/btcpay_store_id', $magentoStoreId);

        return $btcPayStoreId;
    }

    public function removeDeletedBtcPayStores()
    {
        $storedBtcPayStores = array_filter($this->storesConfig->getStoresConfigByPath('payment/btcpay/btcpay_store_id'));

        $baseUrl = $this->getBtcPayServerBaseUrl();
        $apiKey = $this->getApiKey('default', 0);

        $allActiveBtcPayStores = $this->getAllBtcPayStoresAssociative($baseUrl, $apiKey);

        foreach ($storedBtcPayStores as $storedBtcPayStore) {
            $storeStillExists = array_key_exists($storedBtcPayStore, $allActiveBtcPayStores);
            if (!$storeStillExists) {
                $tableName = 'core_config_data';
                $whereConditions = [
                    $this->db->quoteInto('value = ?', $storedBtcPayStore),
                ];
                $deleteRows = $this->db->delete($tableName, $whereConditions);
                $this->reinitableConfig->reinit();
            }
        }
        return true;
    }

    public function getWebhooksForStore(int $magentoStoreId, $btcPayStoreId, string $apiKey): ?array
    {
        $client = new \BTCPayServer\Client\Webhook($this->getBtcPayServerBaseUrl(), $apiKey);

        $webhooks = $client->getStoreWebhooks($btcPayStoreId);

        $url = $this->getWebhookUrl($magentoStoreId);

        foreach ($webhooks->all() as $webhook) {
            $data = $webhook->getData();
            if ($data['url'] === $url) {
                return $data;
            }
        }
        return null;
    }

    public function createWebhook(int $magentoStoreId, $apiKey): ?array
    {
        $client = new \BTCPayServer\Client\Webhook($this->getBtcPayServerBaseUrl(), $apiKey);
        $btcPayStoreId = $this->getBtcPayStore($magentoStoreId);
        if ($btcPayStoreId) {
            $url = $this->getWebhookUrl($magentoStoreId);
            try {
                $data = $client->createWebhook($btcPayStoreId, $url, null, $this->getWebhookSecret($magentoStoreId));
                return $data->getData();
            } catch (\Exception $e) {
                return null;
            }
        }
        return null;
    }

    public function getWebhookUrl(int $magentoStoreId): string
    {
        $url = $this->getStoreConfig('web/secure/base_url', $magentoStoreId);
        $url .= 'rest/V2/btcpay/webhook';
        return $url;
    }

    public function getReceiveApikeyUrl(int $magentoStoreId): string
    {
        $url = $this->getStoreConfig('web/secure/base_url', $magentoStoreId);
        $url = rtrim($url, '/');
        $url .= '/btcpay/apikey/save';
        $hashedSecret = $this->hashSecret($magentoStoreId);
        return $url . '?secret=' . urlencode($hashedSecret) . '&store=' . $magentoStoreId;
    }

    public function getCurrentMagentoStoreId(): ?int
    {
        $storeId = $this->request->getParam('store');
        if (!$storeId) {
            return null;
        }
        return (int)$storeId;
    }

    public function getWebhookSecret(int $magentoStoreId): ?string
    {
        $secret = $this->getConfigWithoutCache('payment/btcpay/webhook_secret', 'default', 0);
        if (!$secret) {
            $secret = $this->createWebhookSecret();

            //Save globally
            $this->configWriter->save('payment/btcpay/webhook_secret', $secret);
        }
        return $secret;
    }

    public function createWebhookSecret(): string
    {
        $str = (string)rand();
        return hash("sha256", $str);
    }

    public function hashSecret(int $magentoStoreId)
    {
        $secret = $this->getWebhookSecret($magentoStoreId);
        $salt = (string)$magentoStoreId;
        return sha1($secret . $salt);
    }

    public function deleteWebhook(int $magentoStoreId, string $btcStoreId, string $webhookId, string $apiKey)
    {
        $client = new \BTCPayServer\Client\Webhook($this->getBtcPayServerBaseUrl(), $apiKey);

        try {
            $deleted = $client->deleteWebhook($btcStoreId, $webhookId);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getAllBtcPayStoresAssociative($baseUrl, $apiKey): array
    {
        $storesArray = [];
        $stores = $this->getAllBtcPayStores($baseUrl, $apiKey);

        foreach ($stores as $store) {
            $storeData = $store->getData();
            $storeId = $storeData['id'];
            $storesArray[$storeId] = $storeData;
        }
        return $storesArray;
    }

    public function getInvoicesByOrderIds(int $magentoStoreId, array $orderIds): \BTCPayServer\Result\InvoiceList
    {
        $client = new \BTCPayServer\Client\Invoice($this->getBtcPayServerBaseUrl(), $this->getApiKey('default', 0));
        $btcStoreId = $this->getBtcPayStore($magentoStoreId);
        $invoices = $client->getInvoicesByOrderIds($btcStoreId, $orderIds);
        return $invoices;
    }

    public function saveInvoiceInDb($invoice): bool
    {
        $tableName = $this->db->getTableName('btcpay_invoices');

        $magentoStoreId = $invoice['metadata']['magentoStoreId'];
        $magentoOrderId = $invoice['metadata']['magentoOrderId'];
        $invoiceId = $invoice['id'];
        $status = $invoice['status'];
        $btcPayStoreId = $invoice['storeId'];

        $affectedRows = $this->db->insert($tableName, ['order_id' => $magentoOrderId, 'invoice_id' => $invoiceId, 'status' => $status, 'btcpay_store_id' => $btcPayStoreId, 'magento_store_id' => $magentoStoreId]);

        if ($affectedRows > 0) {
            return true;
        }
        return false;
    }

    public function getPaymentInfo($magentoStoreId, $btcPayStoreId, $btcPayInvoiceId, $invoice)
    {
        $r = [];

        $btcPayInvoiceClient = new \BTCPayServer\Client\Invoice($this->getBtcPayServerBaseUrl(), $this->getApiKey('default', 0));
        $paymentMethods = $btcPayInvoiceClient->getPaymentMethods($btcPayStoreId, $btcPayInvoiceId);

        bcscale(16);

        $btcAddress = null;
        $btcRates = [];
        $usedPaymentCurrencies = [];

        foreach ($paymentMethods as $paymentMethod) {

            $pmData = $paymentMethod->getData();

            //TODO: getTotalPaid is the total, fees included. Maybe it is possible later to get the exact amount the shopkeeper will receive
            $totalPaid = $paymentMethod->getTotalPaid();

            //TODO: get due (amount left to be paid to shopkeeper, not actual amount that customer has to pay with fees included)

            //$due = $paymentMethod->getDue();
            //$amountOfTransactions = count($paymentMethod->getPayments());

            $currency = explode('-', $paymentMethod->getPaymentMethod())[0];


            if (!in_array($currency, $usedPaymentCurrencies, true)) {
                // There can only be paid in 1 currency for now
                $usedPaymentCurrencies[] = $currency;
                if (count($usedPaymentCurrencies) > 1) {
                    throw new \Exception('Only 1 currency supported. Used currencies:' . implode(',', $usedPaymentCurrencies));
                }
            }
            $btcRates[$invoice->getData()['currency']] = $pmData['rate'];
        }

        if (bccomp($totalPaid, '0') === 1) {
            $r['amount_received'] = $totalPaid;
            //$r['amount_due'] = $due;
            $r['currency'] = $currency;
            $r['rate'] = $pmData['rate'];

            $storeCurrency = $invoice->getData()['currency'];
            $r['store_currency'] = $storeCurrency;

            $invoiceAmount = $invoice->getData()['amount'];
            $invoiceAmountConverted = $this->getFormattedPrice((int)$magentoStoreId, (float)$invoiceAmount);
            $r['invoice_amount'] = $invoiceAmountConverted;

            $amountPaid = bcmul($btcRates[$storeCurrency], $totalPaid);
            $amountPaidConverted = $this->getFormattedPrice((int)$magentoStoreId, (float)$amountPaid);
            $r['amount_paid_store_currency'] = $amountPaidConverted;

            //TODO: get due in store currency
            $percentPaid = bcdiv($amountPaid, $invoiceAmount);
            $r['percent_paid'] = $percentPaid;

            $r['paid_at'] = date('Y-m-d H:i:s', $invoice->getData()['createdTime']);
        }

        return $r;
    }

    public function getFormattedPrice(int $storeId, float $price)
    {
        return $this->priceHelper->currencyByStore($price, $storeId, true, false);
    }

    public function getBtcPayStorePaymentMethods(string $btcStoreId): array
    {
        $btcPayInvoiceClient = new \BTCPayServer\Client\StorePaymentMethod($this->getBtcPayServerBaseUrl(), $this->getApiKey('default', 0));

        return $btcPayInvoiceClient->getPaymentMethods($btcStoreId);
    }

    public function autoCancelOnExpiry(): bool
    {
        return (bool)$this->scopeConfig->getValue('payment/btcpay/auto_cancel');
    }

    public function markBtcPayInvoice(string $orderId, string $markInvoiceAs): array
    {
        // ONLY MARK MOST RECENT BTCPay Invoice
        $tableName = $this->db->getTableName('btcpay_invoices');
        $select = $this->db->select()->from($tableName);
        $select->where('order_id = ?', $orderId);
        $select->order('created_at DESC');
        $select->limit(1);

        $btcPayInvoice = $this->db->fetchAll($select)[0];

        $btcPayStoreId = $btcPayInvoice['btcpay_store_id'];
        $btcPayinvoiceId = $btcPayInvoice['invoice_id'];

        $client = new \BTCPayServer\Client\Invoice($this->getBtcPayServerBaseUrl(), $this->getApiKey('default', 0));

        $invoice = $client->markInvoiceStatus($btcPayStoreId, $btcPayinvoiceId, $markInvoiceAs);
        return $invoice->getData();
    }
}
