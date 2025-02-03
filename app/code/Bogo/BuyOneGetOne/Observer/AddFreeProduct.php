<?php
namespace Bogo\BuyOneGetOne\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Bogo\BuyOneGetOne\Model\BogoManager;
use Bogo\BuyOneGetOne\Helper\Data as BogoHelper;

class AddFreeProduct implements ObserverInterface
{
    /**
     * @var BogoManager
     */
    protected $bogoManager;

    /**
     * @var BogoHelper
     */
    protected $bogoHelper;

    /**
     * @param BogoManager $bogoManager
     * @param BogoHelper $bogoHelper
     */
    public function __construct(
        BogoManager $bogoManager,
        BogoHelper $bogoHelper
    ) {
        $this->bogoManager = $bogoManager;
        $this->bogoHelper = $bogoHelper;
    }

    /**
     * Add free product when a BOGO-enabled product is added to cart
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        try {
            if (!$this->bogoHelper->isEnabled()) {
                return;
            }

            $item = $observer->getEvent()->getData('quote_item');
            if (!$item || $item->getData('is_bogo_free')) {
                return;
            }

            $quote = $item->getQuote();
            if (!$quote) {
                return;
            }

            $this->bogoManager->processBogoForItem($quote, $item);
        } catch (\Exception $e) {
            // 错误处理已经在 BogoManager 中完成
        }
    }
} 