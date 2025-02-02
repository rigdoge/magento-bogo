<?php
namespace Bogo\BuyOneGetOne\Plugin;

use Magento\Quote\Model\Quote\Item;
use Magento\Checkout\Model\Cart;
use Bogo\BuyOneGetOne\Helper\Data;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;
use Magento\Quote\Model\Quote\ItemFactory;

class CartItemAdd
{
    /**
     * @var Data
     */
    private $helper;

    /**
     * @var ManagerInterface
     */
    private $messageManager;

    /**
     * @var PricingHelper
     */
    private $priceHelper;

    /**
     * @var ItemFactory
     */
    private $itemFactory;

    /**
     * @param Data $helper
     * @param ManagerInterface $messageManager
     * @param PricingHelper $priceHelper
     * @param ItemFactory $itemFactory
     */
    public function __construct(
        Data $helper,
        ManagerInterface $messageManager,
        PricingHelper $priceHelper,
        ItemFactory $itemFactory
    ) {
        $this->helper = $helper;
        $this->messageManager = $messageManager;
        $this->priceHelper = $priceHelper;
        $this->itemFactory = $itemFactory;
    }

    /**
     * After add product to cart
     *
     * @param Cart $subject
     * @param Cart $result
     * @param mixed $productInfo
     * @param mixed $requestInfo
     * @return Cart
     */
    public function afterAddProduct(
        Cart $subject,
        Cart $result,
        $productInfo,
        $requestInfo = null
    ) {
        if (!$this->helper->isEnabled()) {
            return $result;
        }

        try {
            $quote = $subject->getQuote();
            $lastItem = $this->getLastAddedItem($quote);
            
            if ($lastItem && 
                !$lastItem->getData('is_bogo_free') && 
                $lastItem->getProduct()->getData('buy_one_get_one')
            ) {
                $this->addFreeItem($quote, $lastItem);
            }
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Unable to apply BOGO offer. Please try again.'));
        }

        return $result;
    }

    /**
     * Get last added item from quote
     *
     * @param \Magento\Quote\Model\Quote $quote
     * @return Item|null
     */
    private function getLastAddedItem($quote)
    {
        $items = $quote->getAllItems();
        return end($items);
    }

    /**
     * Add free item to quote
     *
     * @param \Magento\Quote\Model\Quote $quote
     * @param Item $paidItem
     * @return void
     */
    private function addFreeItem($quote, $paidItem)
    {
        $maxFreeItems = $this->helper->getMaxFreeItems();
        if ($maxFreeItems > 0) {
            $totalFreeItems = $this->getTotalFreeItems($quote, $paidItem->getProductId());
            if ($totalFreeItems >= $maxFreeItems) {
                return;
            }
        }

        $freeItem = $this->itemFactory->create();
        $freeItem->setProduct($paidItem->getProduct())
            ->setQty($paidItem->getQty())
            ->setCustomPrice(0)
            ->setOriginalCustomPrice(0)
            ->setPrice(0)
            ->setBasePrice(0)
            ->setPriceInclTax(0)
            ->setBasePriceInclTax(0)
            ->setData('is_bogo_free', 1)
            ->setData('no_discount', 1);

        $quote->addItem($freeItem);
        $quote->collectTotals();

        $formattedPrice = $this->priceHelper->currency($paidItem->getProduct()->getFinalPrice(), true, false);
        $this->messageManager->addSuccessMessage(
            __('BOGO offer applied: Free %1 (worth %2) has been added!',
                $paidItem->getProduct()->getName(),
                $formattedPrice
            )
        );
    }

    /**
     * Get total number of free items for a product
     *
     * @param \Magento\Quote\Model\Quote $quote
     * @param int $productId
     * @return float
     */
    private function getTotalFreeItems($quote, $productId)
    {
        $totalFreeItems = 0;
        foreach ($quote->getAllItems() as $item) {
            if ($item->getProductId() == $productId && 
                $item->getData('is_bogo_free')
            ) {
                $totalFreeItems += $item->getQty();
            }
        }
        return $totalFreeItems;
    }
}
