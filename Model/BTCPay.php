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
namespace Storefront\BTCPay\Model;

use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Payment\Model\Method\AbstractMethod;

/**
 * Pay In Store payment method model
 */
class BTCPay extends AbstractMethod {


    const PAYMENT_METHOD_CODE = 'btcpay';

    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = self::PAYMENT_METHOD_CODE;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_isGateway = true;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_isInitializeNeeded = true;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_isOffline = true;
    /**
     * @var \Magento\Framework\UrlInterface
     */
    private $url;
    /**
     * @var \Storefront\BTCPay\Helper\Data
     */
    private $btcPayHelper;

    public function __construct(\Storefront\BTCPay\Helper\Data $btcPayHelper, \Magento\Framework\Model\Context $context, \Magento\Framework\Registry $registry, \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory, \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory, \Magento\Payment\Helper\Data $paymentData, \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig, \Magento\Framework\UrlInterface $url, \Magento\Payment\Model\Method\Logger $logger, \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null, \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null, array $data = [], DirectoryHelper $directory = null) {
        parent::__construct($context, $registry, $extensionFactory, $customAttributeFactory, $paymentData, $scopeConfig, $logger, $resource, $resourceCollection, $data, $directory);
        $this->url = $url;
        $this->btcPayHelper = $btcPayHelper;
    }

    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null) {
        $r = parent::isAvailable($quote);

        $errors = $this->btcPayHelper->getInstallationErrors((int) $quote->getStoreId(), true);
        if(count($errors) > 0){
            $r = false;
        }

        return $r;
    }

    public function getOrderPlaceRedirectUrl(){
        $r = $this->url->getUrl('btcpay/redirect/forwardtopayment', [
            '_secure' => true,
            '_nosid' => true
        ]);
        return $r;
    }

    public function getConfigData($field, $storeId = null) {
        if ($field === 'order_place_redirect_url') {
            return $this->getOrderPlaceRedirectUrl();
        } else {
            return parent::getConfigData($field, $storeId);
        }
    }

    /**
     * Method that will be executed instead of authorize or capture
     * if flag isInitializeNeeded set to true
     *
     * @param string $paymentAction
     * @param object $stateObject
     *
     * @return \Storefront\PayIngenico\Model\Payment\PaymentAbstract
     */
    public function initialize($paymentAction, $stateObject) {
        $stateObject->setState(\Magento\Sales\Model\Order::STATE_NEW)->setStatus(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);

        $message = __('Customer is forwarded to BTCPay Server to pay. Awaiting feedback.');

        /** @var \Magento\Sales\Model\Order $order */
        $order = $this->getInfoInstance()->getOrder();

        $order->addStatusHistoryComment($message);

        return $this;
    }


}
