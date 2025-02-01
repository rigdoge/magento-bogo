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
            
            // 遍历所有购物车项目
            foreach ($quote->getAllItems() as $item) {
                $product = $item->getProduct();
                if (!$product->getBuyOneGetOne()) {
                    continue;
                }

                // 找到当前项目的新数量
                $newQty = isset($data[$item->getId()]['qty']) ? (float)$data[$item->getId()]['qty'] : $item->getQty();

                // 查找相关联的商品（付费或免费）
                foreach ($quote->getAllItems() as $relatedItem) {
                    if ($relatedItem->getProduct()->getId() == $product->getId() && 
                        $relatedItem->getId() != $item->getId()) {
                        // 直接设置相同的数量
                        $relatedItem->setQty($newQty);
                        break;
                    }
                }
            }

            // 保存更改
            $quote->collectTotals();
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Unable to update BOGO quantities. Please try again.'));
        }
    }
} 