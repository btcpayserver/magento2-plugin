<?php
declare(strict_types=1);

namespace Storefront\BTCPay\Controller\Apikey;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Storefront\BTCPay\Model\BTCPay\BTCPayService;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class Save extends Action implements CsrfAwareActionInterface
{
    /**
     * @var BTCPayService $btcService
     */
    private $btcService;

    /**
     * @var WriterInterface $configWriter
     */
    private $configWriter;

    /**
     * @var StoreManagerInterface $storeManager
     */
    private $storeManager;

    /**
     * @var LoggerInterface $logger
     */
    private $logger;

    public function __construct(Context $context, BTCPayService $btcService, WriterInterface $configWriter, StoreManagerInterface $storeManager, LoggerInterface $logger)
    {
        parent::__construct($context);
        $this->btcService = $btcService;
        $this->configWriter = $configWriter;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
    }

    public function execute()
    {
        $magentoStoreId = $this->btcService->getCurrentMagentoStoreId();

        $apiKey = $this->getRequest()->getParam('apiKey');

        $givenSecret = $this->getRequest()->getParam('secret');

        $secret = $this->btcService->hashSecret($magentoStoreId);
        if($givenSecret===$secret){

            try {

                $baseUrl = $this->btcService->getBtcPayServerBaseUrl($magentoStoreId);

                // Safety check
                $client = new \BTCPayServer\Client\ApiKey($baseUrl, $apiKey);
                $client->getCurrent();

                // Save API key to config settings
                $this->configWriter->save('payment/btcpay/api_key', $apiKey, 'store', $magentoStoreId);

                echo ___('exact_online.connection_succeeded_close_window', 'Success! You can now close this window.');
            } catch (\Exception $e) {
                $this->logger->error($e);
                echo ___('exact_online.connection_failed_close_window', 'Something went wrong. Please try again.');
            }
        } else {
            echo ___('exact_online.connection_access_denied_close_window', 'Forbidden.');
        }


    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
