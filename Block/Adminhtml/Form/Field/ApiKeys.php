<?php
declare(strict_types=1);

namespace Storefront\BTCPay\Block\Adminhtml\Form\Field;


use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use Storefront\BTCPay\Helper\Data;

class ApiKeys extends \Magento\Config\Block\System\Config\Form\Field
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

        $html = $this->getApiKeyInfoPerStore();

        $r = '<tr>
<td class="label">
<label><span>' . $this->escapeHtml(__('API Keys')) . '</span></label>
</td>
<td class="value">' . $html . '
</td>
<td class="">

</td>
</tr>';


        return $r;

    }

    private function getApiKeyInfoPerStore()
    {
        $isBaseUrlSet = $this->helper->isBtcPayBaseUrlSet();

        if (!$isBaseUrlSet) {
            return __('Save the BTCPay Base Url first.');
        }


        $html = '<table>
  <tr>
    <th style="text-align: left; width: 60px">' . __('Store') . '</th>
    <th style="text-align: left">' . __('API Key') . '</th>
    <th></th>
  </tr>';


        $magentoStoreViewsWithApiKeyInfo = $this->helper->getStoreViewsWithApiKeyInfo();

        foreach ($magentoStoreViewsWithApiKeyInfo as $store => $info) {

            $html = $html . '<tr>
    <td>' . $store . '</td>
    <td>' . $info['api_key'] . '</td>
    <td><a target="_blank" href="' . $info['generate_url'] . '\">' . __('Generate API Key') . '</a></td>
  </tr>';
        }
        return $html . '</table>';
    }

}
