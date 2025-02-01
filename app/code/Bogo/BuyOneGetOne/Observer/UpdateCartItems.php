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

        $cart = $observer->getEvent()->getCart();
        $data = $observer->getEvent()->getInfo();

        foreach ($data as $itemId => $itemInfo) {
            $item = $cart->getQuote()->getItemById($itemId);
            if (!$item) {
                continue;
            }

            $product = $item->getProduct();
            if (!$product->getBuyOneGetOne()) {
                continue;
            }

            $qty = isset($itemInfo['qty']) ? (float)$itemInfo['qty'] : 0;
            if ($qty <= 0) {
                continue;
            }

            // 如果是付费商品，同步更新免费商品
            if ($item->getPrice() > 0) {
                $this->updateFreeItem($cart->getQuote(), $item, $qty);
            }
            // 如果是免费商品，同步更新付费商品
            else {
                $this->updatePaidItem($cart->getQuote(), $item, $qty);
            }
        }
    }

    /**
     * 更新免费商品数量
     */
    private function updateFreeItem($quote, $paidItem, $qty)
    {
        foreach ($quote->getAllItems() as $item) {
            if ($item->getPrice() == 0 && 
                $item->getProduct()->getId() == $paidItem->getProduct()->getId() &&
                $item->getId() != $paidItem->getId()) {
                $item->setQty($qty);
                break;
            }
        }
    }

    /**
     * 更新付费商品数量
     */
    private function updatePaidItem($quote, $freeItem, $qty)
    {
        foreach ($quote->getAllItems() as $item) {
            if ($item->getPrice() > 0 && 
                $item->getProduct()->getId() == $freeItem->getProduct()->getId() &&
                $item->getId() != $freeItem->getId()) {
                $item->setQty($qty);
                break;
            }
        }
    }
} 