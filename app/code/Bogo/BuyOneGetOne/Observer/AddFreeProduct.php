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

            // 检查是否已经是免费商品或是否启用了买一送一功能
            if ($item->getPrice() == 0 || !$product->getData('buy_one_get_one')) {
                return;
            }

            // 检查是否已存在相同产品的免费商品
            $existingFreeItem = null;
            foreach ($quote->getAllItems() as $quoteItem) {
                if ($quoteItem->getProductId() == $product->getId() 
                    && $quoteItem->getData('is_bogo_free')
                    && $quoteItem->getPrice() == 0) {
                    $existingFreeItem = $quoteItem;
                    break;
                }
            }

            // 获取当前付费商品的数量
            $paidQty = $item->getQty();

            if ($existingFreeItem) {
                // 直接设置免费商品数量等于付费商品数量
                $existingFreeItem->setQty($paidQty);
            } else {
                // 创建新的免费商品，数量等于付费商品数量
                $freeItem = $this->itemFactory->create();
                $freeItem->setProduct($product)
                    ->setQuote($quote)
                    ->setQty($paidQty)
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