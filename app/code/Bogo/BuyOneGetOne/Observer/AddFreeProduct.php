<?php
namespace Bogo\BuyOneGetOne\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Bogo\BuyOneGetOne\Helper\Data;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;

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
            $item = $observer->getEvent()->getData('quote_item');
            $product = $observer->getEvent()->getData('product');

            // 检查是否已经是免费商品或是否启用了买一送一功能
            if ($item->getPrice() == 0 || !$product->getData('buy_one_get_one')) {
                return;
            }

            $quote = $this->checkoutSession->getQuote();
            $existingFreeItem = null;

            // 查找是否已存在相同产品的免费商品
            foreach ($quote->getAllItems() as $quoteItem) {
                if ($quoteItem->getData('is_bogo_free') && 
                    $quoteItem->getProduct()->getId() == $product->getId()) {
                    $existingFreeItem = $quoteItem;
                    break;
                }
            }

            if ($existingFreeItem) {
                // 如果已存在免费商品，更新数量
                $existingFreeItem->setQty($item->getQty());
            } else {
                // 创建新的免费商品
                $freeItem = clone $item;
                $freeItem->setQty($item->getQty())
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