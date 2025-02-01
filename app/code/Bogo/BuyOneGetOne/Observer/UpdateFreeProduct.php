<?php
namespace Bogo\BuyOneGetOne\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Bogo\BuyOneGetOne\Helper\Data;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Message\ManagerInterface;

class UpdateFreeProduct implements ObserverInterface
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

        $item = $observer->getEvent()->getData('item');
        $product = $item->getProduct();

        // 检查产品是否启用了买一送一功能
        if (!$product->getBuyOneGetOne()) {
            return;
        }

        try {
            $quote = $this->checkoutSession->getQuote();
            $items = $quote->getAllItems();
            
            // 查找相关的免费商品
            foreach ($items as $quoteItem) {
                if ($quoteItem->getPrice() == 0 && 
                    $quoteItem->getProduct()->getId() == $product->getId() &&
                    $quoteItem->getId() != $item->getId()) {
                    // 更新免费商品数量
                    $quoteItem->setQty($item->getQty());
                    $quote->collectTotals()->save();
                    break;
                }
            }
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Unable to update BOGO quantity. Please try again.'));
        }
    }
} 