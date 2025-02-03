<?php
namespace Bogo\BuyOneGetOne\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Quote\Model\Quote\ItemFactory;
use Magento\Framework\Message\ManagerInterface;
use Bogo\BuyOneGetOne\Helper\Data as BogoHelper;

class AddFreeProduct implements ObserverInterface
{
    /**
     * @var ItemFactory
     */
    protected $itemFactory;

    /**
     * @var ManagerInterface
     */
    protected $messageManager;

    /**
     * @var BogoHelper
     */
    protected $bogoHelper;

    /**
     * @param ItemFactory $itemFactory
     * @param ManagerInterface $messageManager
     * @param BogoHelper $bogoHelper
     */
    public function __construct(
        ItemFactory $itemFactory,
        ManagerInterface $messageManager,
        BogoHelper $bogoHelper
    ) {
        $this->itemFactory = $itemFactory;
        $this->messageManager = $messageManager;
        $this->bogoHelper = $bogoHelper;
    }

    /**
     * Add free product when a BOGO-enabled product is added to cart
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        try {
            if (!$this->bogoHelper->isEnabled()) {
                return;
            }

            $item = $observer->getEvent()->getData('quote_item');
            if ($item->getData('is_bogo_free')) {
                return; // 如果当前项目是免费商品，直接返回，避免重复处理
            }

            $product = $item->getProduct();
            
            // Check if product is BOGO enabled
            if (!$product->getData('buy_one_get_one')) {
                return;
            }

            $quote = $item->getQuote();
            if (!$quote) {
                return;
            }

            // Get the quantity of the paid product
            $paidQty = $item->getQty();

            // 移除所有已存在的相同产品的免费项
            foreach ($quote->getAllItems() as $quoteItem) {
                if ($quoteItem->getProduct()->getId() == $product->getId() 
                    && $quoteItem->getData('is_bogo_free')
                    && $quoteItem->getId() != $item->getId()) {
                    $quote->removeItem($quoteItem->getId());
                }
            }

            // 创建新的免费商品
            $freeItem = $this->itemFactory->create();
            $freeItem->setProduct($product)
                ->setQuote($quote)
                ->setQty($paidQty)
                ->setCustomPrice(0)
                ->setOriginalCustomPrice(0)
                ->setData('is_bogo_free', 1);

            $quote->addItem($freeItem);
            $quote->collectTotals();

            $this->messageManager->addSuccessMessage(
                __('BOGO offer applied: Free %1 (worth %2) has been added!', 
                    $product->getName(),
                    $product->getFormatedPrice()
                )
            );
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Unable to add free BOGO product: ') . $e->getMessage());
        }
    }
} 