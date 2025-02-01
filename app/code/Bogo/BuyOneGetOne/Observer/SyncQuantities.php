<?php
namespace Bogo\BuyOneGetOne\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Bogo\BuyOneGetOne\Helper\Data;
use Magento\Framework\Message\ManagerInterface;

class SyncQuantities implements ObserverInterface
{
    /**
     * @var Data
     */
    protected $helper;

    /**
     * @var ManagerInterface
     */
    protected $messageManager;

    /**
     * @param Data $helper
     * @param ManagerInterface $messageManager
     */
    public function __construct(
        Data $helper,
        ManagerInterface $messageManager
    ) {
        $this->helper = $helper;
        $this->messageManager = $messageManager;
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        if (!$this->helper->isEnabled()) {
            return;
        }

        try {
            /** @var \Magento\Quote\Model\Quote $quote */
            $quote = $observer->getEvent()->getQuote();
            if (!$quote) {
                return;
            }

            $bogoItems = [];
            // 收集所有买一送一商品
            foreach ($quote->getAllItems() as $item) {
                if (!$item->getProduct()->getBuyOneGetOne()) {
                    continue;
                }

                $productId = $item->getProduct()->getId();
                if (!isset($bogoItems[$productId])) {
                    $bogoItems[$productId] = [
                        'paid' => null,
                        'free' => null
                    ];
                }

                // 根据价格区分付费和免费商品
                if ($item->getCustomPrice() === 0.0 || $item->getPrice() == 0) {
                    $bogoItems[$productId]['free'] = $item;
                } else {
                    $bogoItems[$productId]['paid'] = $item;
                }
            }

            // 同步每对商品的数量
            foreach ($bogoItems as $items) {
                if (!$items['paid'] || !$items['free']) {
                    continue;
                }

                $paidQty = $items['paid']->getQty();
                if ($items['free']->getQty() != $paidQty) {
                    $items['free']->setQty($paidQty);
                }
            }
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Unable to sync BOGO quantities.'));
        }
    }
} 