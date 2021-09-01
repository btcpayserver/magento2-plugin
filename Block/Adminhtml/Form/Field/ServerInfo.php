<?php
declare(strict_types=1);

namespace Storefront\BTCPay\Block\Adminhtml\Form\Field;

use Magento\Backend\Block\Template\Context;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use Storefront\BTCPay\Helper\Data;

class ServerInfo extends \Magento\Config\Block\System\Config\Form\Field
{

    /**
     * @var \Storefront\BTCPay\Model\BTCPay\BTCPayService
     */
    private $btcPayService;
    /**
     * @var Data
     */
    private $helper;

    public function __construct(\Storefront\BTCPay\Model\BTCPay\BTCPayService $btcPayService, Data $helper, Context $context, array $data = [], ?SecureHtmlRenderer $secureRenderer = null)
    {
        parent::__construct($context, $data, $secureRenderer);
        $this->btcPayService = $btcPayService;
        $this->helper = $helper;
    }

    /**
     * Render form element as HTML
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return string
     */
    public function render(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        // TODO support different API keys per store
        $storeId = 0;

        $html = $this->getErrorHtml($storeId);

        $r = '<tr><td class="label"><label><span>' . $this->escapeHtml(__('Connection Status')) . '</span></label></td><td class="value">' . $html . '</span></p></td><td class=""></td></tr>';
        return $r;
    }


    public function getErrorHtml(int $magentoStoreId): string
    {
        $lines = $this->helper->getInstallationErrors($magentoStoreId, false);

        if (count($lines) > 0) {
            $r = '<ul style="padding-left: 25px">';
            foreach ($lines as $line) {
                $r .= '<li style="margin-bottom: 10px; color: red">' . $line . '</li>';
            }
            $r .= '</ul>';
        } else {
            $r = '<span style="font-weight: bold; color: green;">OK</span>';
        }
        return $r;
    }

}
