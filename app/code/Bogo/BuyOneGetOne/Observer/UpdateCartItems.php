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
            $itemsToUpdate = [];

            // 首先收集所有需要更新的项目
            foreach ($data as $itemId => $itemInfo) {
                $item = $quote->getItemById($itemId);
                if (!$item || !isset($itemInfo['qty'])) {
                    continue;
                }

                $product = $item->getProduct();
                if (!$product->getBuyOneGetOne()) {
                    continue;
                }

                $qty = (float)$itemInfo['qty'];
                
                // 将相同产品的付费和免费商品分组
                $productId = $product->getId();
                if (!isset($itemsToUpdate[$productId])) {
                    $itemsToUpdate[$productId] = [
                        'paid' => null,
                        'free' => null,
                        'qty' => 0
                    ];
                }

                if ($item->getPrice() > 0) {
                    $itemsToUpdate[$productId]['paid'] = $item;
                    $itemsToUpdate[$productId]['qty'] = $qty;
                } else {
                    $itemsToUpdate[$productId]['free'] = $item;
                }
            }

            // 查找并更新配对的商品
            foreach ($quote->getAllItems() as $item) {
                $productId = $item->getProduct()->getId();
                if (!isset($itemsToUpdate[$productId])) {
                    continue;
                }

                $updateInfo = $itemsToUpdate[$productId];
                
                // 如果是付费商品被更新，找到对应的免费商品
                if ($item->getPrice() == 0 && $updateInfo['paid']) {
                    $item->setQty($updateInfo['qty']);
                }
                // 如果是免费商品被更新，找到对应的付费商品
                elseif ($item->getPrice() > 0 && $updateInfo['free']) {
                    $item->setQty($updateInfo['qty']);
                }
            }

            // 保存更改
            $quote->collectTotals();
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Unable to update BOGO quantities. Please try again.'));
        }
    }
} 