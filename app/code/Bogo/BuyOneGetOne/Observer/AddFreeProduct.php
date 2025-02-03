<?php
namespace Bogo\BuyOneGetOne\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Bogo\BuyOneGetOne\Model\BogoManager;
use Bogo\BuyOneGetOne\Helper\Data as BogoHelper;
use Bogo\BuyOneGetOne\Logger\Logger;

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
     * @var Logger
     */
    protected $logger;

    /**
     * @param BogoManager $bogoManager
     * @param BogoHelper $bogoHelper
     * @param Logger $logger
     */
    public function __construct(
        BogoManager $bogoManager,
        BogoHelper $bogoHelper,
        Logger $logger
    ) {
        $this->bogoManager = $bogoManager;
        $this->bogoHelper = $bogoHelper;
        $this->logger = $logger;
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

            $eventName = $observer->getEvent()->getName();
            $this->logger->debug('BOGO event triggered', [
                'event_name' => $eventName
            ]);

            // 获取相关对象
            $item = null;
            $quote = null;

            switch ($eventName) {
                case 'checkout_cart_product_add_after':
                    $item = $observer->getEvent()->getData('quote_item');
                    if ($item) {
                        $quote = $item->getQuote();
                    }
                    break;

                case 'sales_quote_item_qty_set_after':
                    $item = $observer->getEvent()->getData('item');
                    if ($item) {
                        $quote = $item->getQuote();
                    }
                    break;

                case 'sales_quote_remove_item':
                    $item = $observer->getEvent()->getData('quote_item');
                    if ($item) {
                        $quote = $item->getQuote();
                    }
                    break;

                case 'checkout_cart_save_after':
                case 'sales_quote_save_after':
                    $quote = $observer->getEvent()->getData('quote');
                    if ($quote && $quote->getAllVisibleItems()) {
                        $item = $quote->getAllVisibleItems()[0];
                    }
                    break;
            }

            if (!$item || !$quote) {
                $this->logger->debug('No valid item or quote found');
                return;
            }

            if ($item->getData('is_bogo_free')) {
                $this->logger->debug('Skipping BOGO free item');
                return;
            }

            $this->logger->debug('Processing BOGO event', [
                'event_name' => $eventName,
                'item_id' => $item->getId(),
                'product_id' => $item->getProductId(),
                'quote_id' => $quote->getId()
            ]);

            // 处理 BOGO 逻辑
            $this->bogoManager->processBogoForItem($quote, $item);

            // 确保更改被保存
            if (!in_array($eventName, ['checkout_cart_save_after', 'sales_quote_save_after'])) {
                $quote->collectTotals()->save();
            }

        } catch (\Exception $e) {
            $this->logger->error('Error in BOGO observer', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
} 