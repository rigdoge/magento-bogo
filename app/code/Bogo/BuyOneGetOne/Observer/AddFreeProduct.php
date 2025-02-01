<?php
namespace Bogo\BuyOneGetOne\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Bogo\BuyOneGetOne\Helper\Data;
use Magento\Checkout\Model\Cart;
use Magento\Framework\Message\ManagerInterface;

class AddFreeProduct implements ObserverInterface
{
    /**
     * @var Data
     */
    protected $helper;

    /**
     * @var Cart
     */
    protected $cart;

    /**
     * @var ManagerInterface
     */
    protected $messageManager;

    /**
     * @param Data $helper
     * @param Cart $cart
     * @param ManagerInterface $messageManager
     */
    public function __construct(
        Data $helper,
        Cart $cart,
        ManagerInterface $messageManager
    ) {
        $this->helper = $helper;
        $this->cart = $cart;
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

        $item = $observer->getEvent()->getData('quote_item');
        $product = $observer->getEvent()->getData('product');

        // 检查是否已经是免费商品
        if ($item->getPrice() == 0) {
            return;
        }

        try {
            // 创建免费商品
            $freeItem = clone $item;
            $freeItem->setCustomPrice(0);
            $freeItem->setOriginalCustomPrice(0);
            $freeItem->getProduct()->setIsSuperMode(true);
            
            // 添加到购物车
            $this->cart->addItem($freeItem);
            $this->cart->save();

            $this->messageManager->addSuccessMessage(__('Free product has been added to your cart!'));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Could not add free product: %1', $e->getMessage()));
        }
    }
} 