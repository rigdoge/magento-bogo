<?php
namespace Bogo\BuyOneGetOne\Plugin;

use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Bogo\BuyOneGetOne\Helper\Data;
use Bogo\BuyOneGetOne\Model\BogoManager;

class QuoteTotals
{
    /**
     * @var Data
     */
    private $helper;

    /**
     * @var BogoManager
     */
    private $bogoManager;

    /**
     * @param Data $helper
     * @param BogoManager $bogoManager
     */
    public function __construct(
        Data $helper,
        BogoManager $bogoManager
    ) {
        $this->helper = $helper;
        $this->bogoManager = $bogoManager;
    }

    /**
     * Process BOGO items before collecting totals
     *
     * @param Quote $subject
     * @return null
     */
    public function beforeCollectTotals(Quote $subject)
    {
        if (!$this->helper->isEnabled()) {
            return null;
        }

        try {
            foreach ($subject->getAllVisibleItems() as $item) {
                if (!$item->getData('is_bogo_free')) {
                    $this->bogoManager->processBogoForItem($subject, $item);
                }
            }
        } catch (LocalizedException $e) {
            // 错误处理已经在 BogoManager 中完成
        } catch (\Exception $e) {
            // 错误处理已经在 BogoManager 中完成
        }

        return null;
    }
}
