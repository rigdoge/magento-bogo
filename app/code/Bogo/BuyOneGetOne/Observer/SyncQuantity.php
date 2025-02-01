<?php
namespace Bogo\BuyOneGetOne\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Bogo\BuyOneGetOne\Helper\Data;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Message\ManagerInterface;

class SyncQuantity implements ObserverInterface
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
            $item = $observer->getEvent()->getData('item');
            if (!$item || !$item->getProduct()->getBuyOneGetOne()) {
                return;
            }

            $quote = $this->checkoutSession->getQuote();
            $productId = $item->getProduct()->getId();
            $newQty = $item->getQty();
            $isFree = (bool)$item->getData('is_bogo_free');

            // 查找相关联的商品
            foreach ($quote->getAllItems() as $quoteItem) {
                if ($quoteItem->getProduct()->getId() == $productId && 
                    $quoteItem->getId() != $item->getId()) {
                    $isQuoteItemFree = (bool)$quoteItem->getData('is_bogo_free');
                    
                    // 确保一个是付费商品，一个是免费商品
                    if ($isQuoteItemFree != $isFree) {
                        // 更新数量
                        $quoteItem->setQty($newQty)->save();
                        break;
                    }
                }
            }

            $quote->collectTotals()->save();
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Unable to sync BOGO quantities.'));
        }
    }
} 