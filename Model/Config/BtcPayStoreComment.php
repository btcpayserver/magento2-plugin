<?php
declare(strict_types=1);

namespace Storefront\BTCPay\Model\Config;

use Magento\Config\Model\Config\CommentInterface;
use Storefront\BTCPay\Model\Config\Source\BtcPayStore;

class BtcPayStoreComment implements CommentInterface
{
    /**
     * @var BtcPayStore $btcStoreSource
     */
    private $btcStoreSource;

    public function __construct(BtcPayStore $btcStoreSource)
    {
        $this->btcStoreSource = $btcStoreSource;

    }

    public function getCommentText($elementValue)
    {

        $btcStores = $this->btcStoreSource->toOptionArray();
        if (count($btcStores) === 1) {
            $r = '<span style="color: red">' . __('Make a BTCPay Server Store first.') . '</span>';
            return $r;
        }

        $r = __('Select the BTCPay Server Store to use');
        return $r;

    }
}
