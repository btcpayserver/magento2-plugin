<?php
declare(strict_types=1);

namespace Storefront\BTCPay\Controller\Adminhtml\Apikey;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Storefront\BTCPay\Helper\Data;

class Generate extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Magento_Payment::payment';

    /**
     * @var Data
     */
    private $helper;

    public function __construct(Context $context, Data $helper)
    {
        parent::__construct($context);
        $this->helper = $helper;
    }

    public function execute()
    {
        $baseUrl = $this->getRequest()->getParam('base_url');
        if (!is_string($baseUrl) || trim($baseUrl) === '') {
            return $this->redirectToPaymentConfig(__('Please enter a BTCPay Server URL first.'));
        }

        try {
            $authorizeUrl = $this->helper->getGenerateApiKeyUrl(0, $baseUrl);
        } catch (\InvalidArgumentException $e) {
            return $this->redirectToPaymentConfig(__('Please enter a valid BTCPay Server URL.'));
        }

        if (!$authorizeUrl) {
            return $this->redirectToPaymentConfig(__('Could not create the BTCPay API key URL.'));
        }

        return $this->resultRedirectFactory->create()->setUrl($authorizeUrl);
    }

    private function redirectToPaymentConfig($message): Redirect
    {
        $this->messageManager->addErrorMessage($message);

        return $this->resultRedirectFactory->create()->setPath(
            'adminhtml/system_config/edit',
            ['section' => 'payment']
        );
    }
}
