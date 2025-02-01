<?php
namespace Bogo\BuyOneGetOne\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Bogo\BuyOneGetOne\Helper\Data;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote\ItemFactory;

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
     * @var ItemFactory
     */
    protected $itemFactory;

    /**
     * @param Data $helper
     * @param CheckoutSession $checkoutSession
     * @param ManagerInterface $messageManager
     * @param ItemFactory $itemFactory
     */
    public function __construct(
        Data $helper,
        CheckoutSession $checkoutSession,
        ManagerInterface $messageManager,
        ItemFactory $itemFactory
    ) {
        $this->helper = $helper;
        $this->checkoutSession = $checkoutSession;
        $this->messageManager = $messageManager;
        $this->itemFactory = $itemFactory;
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
            $item = $observer->getEvent()->getData('quote_item');
            $product = $observer->getEvent()->getData('product');
            $quote = $this->checkoutSession->getQuote();

            // 如果是免费商品或没有启用买一送一，直接返回
            if ($item->getPrice() == 0 || !$product->getData('buy_one_get_one')) {
                return;
            }

            // 检查购物车中是否已存在此商品的免费版本
            $existingFreeItem = null;
            foreach ($quote->getAllItems() as $quoteItem) {
                if ($quoteItem->getProductId() == $product->getId() && 
                    $quoteItem->getData('is_bogo_free') && 
                    $quoteItem->getPrice() == 0) {
                    $existingFreeItem = $quoteItem;
                    break;
                }
            }

            if ($existingFreeItem) {
                // 如果已存在免费商品，更新其数量
                $existingFreeItem->setQty($item->getQty());
            } else {
                // 如果不存在，创建新的免费商品
                $freeItem = $this->itemFactory->create();
                $freeItem->setProduct($product)
                    ->setQuote($quote)
                    ->setQty($item->getQty())
                    ->setCustomPrice(0)
                    ->setOriginalCustomPrice(0)
                    ->setData('is_bogo_free', 1);
                
                $quote->addItem($freeItem);
            }

            $quote->collectTotals()->save();
            
            $this->messageManager->addSuccessMessage(__('BOGO offer applied: Your free item has been added!'));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Unable to apply BOGO offer. Please try again.'));
        }
    }
} 