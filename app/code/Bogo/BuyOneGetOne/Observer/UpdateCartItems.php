<?php
namespace Bogo\BuyOneGetOne\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Bogo\BuyOneGetOne\Helper\Data;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Message\ManagerInterface;

class UpdateCartItems implements ObserverInterface
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
            $cart = $observer->getEvent()->getCart();
            $data = $observer->getEvent()->getInfo();
            $quote = $cart->getQuote();
            
            // 遍历所有被更新的商品
            foreach ($data as $itemId => $itemInfo) {
                if (!isset($itemInfo['qty'])) {
                    continue;
                }

                $currentItem = $quote->getItemById($itemId);
                if (!$currentItem || !$currentItem->getProduct()->getBuyOneGetOne()) {
                    continue;
                }

                $newQty = (float)$itemInfo['qty'];
                $productId = $currentItem->getProduct()->getId();
                $isFreeItem = (bool)$currentItem->getData('is_bogo_free');

                // 查找对应的商品（如果当前是付费商品，查找免费商品，反之亦然）
                foreach ($quote->getAllItems() as $item) {
                    if ($item->getProduct()->getId() == $productId && $item->getId() != $itemId) {
                        $isItemFree = (bool)$item->getData('is_bogo_free');
                        // 确保一个是付费商品，一个是免费商品
                        if ($isItemFree != $isFreeItem) {
                            // 更新数量
                            $item->setQty($newQty);
                            $currentItem->setQty($newQty);
                            break;
                        }
                    }
                }
            }

            // 保存更改
            $quote->collectTotals()->save();
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Unable to update BOGO quantities. Please try again.'));
        }
    }
} 