<?php
namespace Bogo\BuyOneGetOne\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Bogo\BuyOneGetOne\Helper\Data;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Message\ManagerInterface;
use Magento\Quote\Api\Data\CartItemInterfaceFactory;

class AddFreeProduct implements ObserverInterface
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
     * @var CartItemInterfaceFactory
     */
    protected $cartItemFactory;

    /**
     * @param Data $helper
     * @param CheckoutSession $checkoutSession
     * @param ManagerInterface $messageManager
     * @param CartItemInterfaceFactory $cartItemFactory
     */
    public function __construct(
        Data $helper,
        CheckoutSession $checkoutSession,
        ManagerInterface $messageManager,
        CartItemInterfaceFactory $cartItemFactory
    ) {
        $this->helper = $helper;
        $this->checkoutSession = $checkoutSession;
        $this->messageManager = $messageManager;
        $this->cartItemFactory = $cartItemFactory;
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

        // 检查产品是否启用了买一送一功能
        if (!$product->getBuyOneGetOne()) {
            return;
        }

        try {
            $quote = $this->checkoutSession->getQuote();
            
            // 创建免费商品
            $freeItem = $this->cartItemFactory->create();
            $freeItem->setProduct($product);
            $freeItem->setQty($item->getQty());
            $freeItem->setCustomPrice(0);
            $freeItem->setOriginalCustomPrice(0);
            $freeItem->getProduct()->setIsSuperMode(true);
            
            // 添加到购物车
            $quote->addItem($freeItem);
            $quote->collectTotals()->save();

            $this->messageManager->addSuccessMessage(__('Buy 1 get 1 free has been added to your cart!'));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Could not add free product: %1', $e->getMessage()));
        }
    }
} 