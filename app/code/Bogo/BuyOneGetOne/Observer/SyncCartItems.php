<?php
namespace Bogo\BuyOneGetOne\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Bogo\BuyOneGetOne\Helper\Data;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Message\ManagerInterface;

class SyncCartItems implements ObserverInterface
{
    /**
     * @var Data
     */
    protected $helper;

    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * @var ManagerInterface
     */
    protected $messageManager;

    /**
     * @param Data $helper
     * @param CheckoutSession $checkoutSession
     * @param ManagerInterface $messageManager
     */
    public function __construct(
        Data $helper,
        CheckoutSession $checkoutSession,
        ManagerInterface $messageManager
    ) {
        $this->helper = $helper;
        $this->checkoutSession = $checkoutSession;
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
            $quote = $this->checkoutSession->getQuote();
            $items = $quote->getAllItems();
            $itemsToUpdate = [];

            // 收集需要更新的项目
            foreach ($items as $item) {
                $product = $item->getProduct();
                if (!$product->getBuyOneGetOne()) {
                    continue;
                }

                $itemsToUpdate[$product->getId()][] = $item;
            }

            // 同步数量和价格
            foreach ($itemsToUpdate as $productId => $productItems) {
                if (count($productItems) != 2) {
                    continue;
                }

                $paidItem = null;
                $freeItem = null;

                // 确定付费和免费商品
                foreach ($productItems as $item) {
                    if ($item->getPrice() > 0) {
                        $paidItem = $item;
                    } else {
                        $freeItem = $item;
                    }
                }

                if ($paidItem && $freeItem) {
                    // 同步数量
                    $freeItem->setQty($paidItem->getQty());
                    
                    // 确保价格正确
                    $freeItem->setCustomPrice(0);
                    $freeItem->setOriginalCustomPrice(0);
                    $freeItem->getProduct()->setIsSuperMode(true);
                }
            }

            // 保存更改
            $quote->collectTotals()->save();
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Unable to sync BOGO items. Please try again.'));
        }
    }
} 