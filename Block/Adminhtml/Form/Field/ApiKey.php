<?php
declare(strict_types=1);

namespace Storefront\BTCPay\Block\Adminhtml\Form\Field;


use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Config\Scope;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use Storefront\BTCPay\Helper\Data;

class ApiKey extends \Magento\Config\Block\System\Config\Form\Field
{
    /**
     * @var Data $helper
     */
    private $helper;

    public function __construct(Context $context, Data $helper, array $data = [], ?SecureHtmlRenderer $secureRenderer = null)
    {
        parent::__construct($context, $data, $secureRenderer);
        $this->helper = $helper;
    }

    /**
     * Render form element as HTML
     *
     * @param AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element)
    {

        $html = $this->getApiKeyInfo();

        $r = '<tr>
<td class="label">
<label><span data-config-scope="[GLOBAL]">' . $this->escapeHtml(__('API Key')) . '</span></label>
</td>
<td class="value">' . $html . '
</td>
<td class="">

</td>
</tr>';


        return $r;

    }

    private function getApiKeyInfo()
    {
        $isBaseUrlSet = $this->helper->isBtcPayBaseUrlSet();

        if (!$isBaseUrlSet) {
            return __('Save the BTCPay Base Url first.');
        }

        $html = '<div style="display: flex; justify-content: space-between; align-items:center">';

        $apiKeyInfo = $this->helper->getApiKeyInfo('default', 0);

        $html = $html . '
    <div style="font-weight: normal">' . $apiKeyInfo['api_key'] . '</div>
    <div><a class="action-default" target="_blank" href="' . $apiKeyInfo['generate_url'] . '\">' . __('Generate API Key') . '</a></div>';

        return $html;
    }

}
