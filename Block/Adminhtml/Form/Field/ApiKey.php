<?php
declare(strict_types=1);

namespace Storefront\BTCPay\Block\Adminhtml\Form\Field;


use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use Storefront\BTCPay\Helper\Data;

class ApiKey extends \Magento\Config\Block\System\Config\Form\Field
{
    /**
     * @var Data $helper
     */
    private $helper;

    /**
     * @var SecureHtmlRenderer
     */
    private $secureRenderer;

    public function __construct(Context $context, Data $helper, array $data = [], ?SecureHtmlRenderer $secureRenderer = null)
    {
        parent::__construct($context, $data, $secureRenderer);
        $this->helper = $helper;
        $this->secureRenderer = $secureRenderer
            ?? ObjectManager::getInstance()->get(SecureHtmlRenderer::class);
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
        $html = '<div style="display: flex; justify-content: space-between; align-items:center">';

        $apiKey = $this->helper->getApiKey('default', 0);
        $apiKeyHtml = $apiKey
            ? $this->escapeHtml($this->helper->maskApiKey($apiKey))
            : '<span style="color: red">' . $this->escapeHtml(__('No API key yet.')) . '</span>';
        $buttonId = 'btcpay-generate-api-key';

        $html = $html . '
    <div style="font-weight: normal">' . $apiKeyHtml . '</div>
    <div><button class="action-default" type="button" id="' . $buttonId . '">' .
            $this->escapeHtml(__('Generate API Key')) . '</button></div>
</div>';

        $generateUrl = json_encode(
            $this->getUrl('btcpay/apikey/generate'),
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
        );
        $missingUrlMessage = json_encode(
            (string)__('Please enter a BTCPay Server URL first.'),
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
        );
        $html .= $this->secureRenderer->renderEventListenerAsTag(
            'onclick',
            <<<JS
                var baseUrlField = document.querySelector(
                    'input[name="groups[btcpay][fields][btcpay_base_url][value]"]'
                ) || document.querySelector('input[id$="_btcpay_base_url"]');
                if (!baseUrlField || !baseUrlField.value.trim()) {
                    alert({$missingUrlMessage});
                    return false;
                }

                var form = document.createElement('form');
                form.method = 'post';
                form.action = {$generateUrl};
                form.target = '_blank';

                var formKey = document.createElement('input');
                formKey.type = 'hidden';
                formKey.name = 'form_key';
                formKey.value = window.FORM_KEY;
                form.appendChild(formKey);

                var baseUrl = document.createElement('input');
                baseUrl.type = 'hidden';
                baseUrl.name = 'base_url';
                baseUrl.value = baseUrlField.value;
                form.appendChild(baseUrl);

                document.body.appendChild(form);
                form.submit();
                form.remove();
                return false;
            JS,
            '#' . $buttonId
        );

        return $html;
    }

}
