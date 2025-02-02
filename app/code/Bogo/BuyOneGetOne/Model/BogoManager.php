<?php
namespace Bogo\BuyOneGetOne\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;
use Magento\Quote\Model\Quote\ItemFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Bogo\BuyOneGetOne\Helper\Data;
use Bogo\BuyOneGetOne\Logger\Logger;

class BogoManager
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
     * @var Logger
     */
    private $logger;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @param Data $helper
     * @param ManagerInterface $messageManager
     * @param PricingHelper $priceHelper
     * @param ItemFactory $itemFactory
     * @param Logger $logger
     * @param ProductRepositoryInterface $productRepository
     */
    public function __construct(
        Data $helper,
        ManagerInterface $messageManager,
        PricingHelper $priceHelper,
        ItemFactory $itemFactory,
        Logger $logger,
        ProductRepositoryInterface $productRepository
    ) {
        $this->helper = $helper;
        $this->messageManager = $messageManager;
        $this->priceHelper = $priceHelper;
        $this->itemFactory = $itemFactory;
        $this->logger = $logger;
        $this->productRepository = $productRepository;
    }

    /**
     * Process BOGO for a specific quote item
     *
     * @param Quote $quote
     * @param Item $quoteItem
     * @return void
     */
    public function processBogoForItem(Quote $quote, Item $quoteItem): void
    {
        $this->logger->debug('Processing BOGO for item', [
            'quote_id' => $quote->getId(),
            'item_id' => $quoteItem->getId(),
            'product_id' => $quoteItem->getProductId(),
            'qty' => $quoteItem->getQty(),
            'is_bogo_free' => $quoteItem->getData('is_bogo_free'),
            'is_enabled' => $this->helper->isEnabled()
        ]);

        if (!$this->helper->isEnabled() || $quoteItem->getData('is_bogo_free')) {
            $this->logger->debug('Skipping BOGO processing', [
                'reason' => !$this->helper->isEnabled() ? 'module_disabled' : 'is_free_item'
            ]);
            return;
        }

        try {
            // 重新加载产品以确保所有属性都被加载
            $product = $this->productRepository->getById($quoteItem->getProductId());
            $this->logger->debug('Checking product BOGO eligibility', [
                'product_id' => $product->getId(),
                'buy_one_get_one' => $product->getData('buy_one_get_one'),
                'buy_one_get_one_value' => $product->getBuyOneGetOne(),
                'all_attributes' => array_keys($product->getData())
            ]);

            if (!$product->getData('buy_one_get_one') && !$product->getBuyOneGetOne()) {
                $this->logger->debug('Product is not BOGO eligible', [
                    'product_id' => $product->getId()
                ]);
                return;
            }
        } catch (\Exception $e) {
            $this->logger->error('Error loading product', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return;
        }

        try {
            $this->updateBogoItemsForProduct($quote, $quoteItem);
        } catch (\Exception $e) {
            $this->logger->error('Error processing BOGO', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->messageManager->addErrorMessage(__('Unable to apply BOGO offer. Please try again.'));
        }
    }

    /**
     * Update BOGO items for a specific product
     *
     * @param Quote $quote
     * @param Item $paidItem
     * @return void
     */
    private function updateBogoItemsForProduct(Quote $quote, Item $paidItem): void
    {
        try {
            $productId = $paidItem->getProductId();
            $paidQty = $paidItem->getQty();
            
            if ($paidQty > 1000) {
                throw new LocalizedException(__('The quantity cannot exceed 1000.'));
            }
            
            $expectedFreeQty = $this->calculateExpectedFreeQty($paidQty, $paidItem->getProduct());
        
        // 获取当前的免费商品
        $freeItems = $this->getFreeItemsForProduct($quote, $productId);
        $currentFreeQty = array_sum(array_map(function($item) {
            return $item->getQty();
        }, $freeItems));

        $this->logger->debug('Updating BOGO items', [
            'product_id' => $productId,
            'paid_qty' => $paidQty,
            'expected_free_qty' => $expectedFreeQty,
            'current_free_qty' => $currentFreeQty,
            'free_items_count' => count($freeItems)
        ]);

        // 如果数量不一致，更新免费商品
        if ($expectedFreeQty !== $currentFreeQty) {
            $this->logger->debug('Updating free items quantity', [
                'from' => $currentFreeQty,
                'to' => $expectedFreeQty
            ]);
            $this->updateFreeItems($quote, $paidItem, $expectedFreeQty, $freeItems);
        }
    }
    
    /**
     * 合并购物车中相同商品的项目
     *
     * @param Quote $quote
     * @param int $productId
     */
    private function getFreeItemsForProduct(Quote $quote, $productId): array
    {
        $freeItems = [];
        foreach ($quote->getAllVisibleItems() as $item) {
            if ($item->getProductId() == $productId && $item->getData('is_bogo_free')) {
                $freeItems[] = $item;
            }
        }
        return $freeItems;
    }

    /**
     * Get total paid quantity for a product
     *
     * @param Quote $quote
     * @param int $productId
     * @return float
     */
    private function getTotalPaidQtyForProduct(Quote $quote, $productId): float
    {
        $totalQty = 0;
        $processedItems = [];
        
        // 获取购物车中的所有商品
        $items = $quote->getAllVisibleItems();
        
        $this->logger->debug('Processing cart items', [
            'quote_id' => $quote->getId(),
            'total_items' => count($items),
            'product_id' => $productId
        ]);
        
        foreach ($items as $item) {
            $this->logger->debug('Processing item', [
                'item_id' => $item->getId(),
                'product_id' => $item->getProductId(),
                'qty' => $item->getQty(),
                'is_bogo_free' => $item->getData('is_bogo_free'),
                'processed' => in_array($item->getId(), $processedItems)
            ]);
            
            if ($item->getProductId() == $productId && 
                !$item->getData('is_bogo_free') && 
                !in_array($item->getId(), $processedItems)
            ) {
                $totalQty += $item->getQty();
                $processedItems[] = $item->getId();
                
                $this->logger->debug('Added item quantity', [
                    'item_id' => $item->getId(),
                    'qty' => $item->getQty(),
                    'running_total' => $totalQty
                ]);
            } else {
                $this->logger->debug('Skipped item', [
                    'reason' => $item->getProductId() != $productId ? 'different_product' : 
                              ($item->getData('is_bogo_free') ? 'is_free_item' : 
                              (in_array($item->getId(), $processedItems) ? 'already_processed' : 'unknown'))
                ]);
            }
        }
        $this->logger->debug('Calculated total paid quantity', [
            'product_id' => $productId,
            'total_qty' => $totalQty,
            'quote_id' => $quote->getId()
        ]);
        return $totalQty;
    }

    /**
     * Get all free items for a product
     *
     * @param Quote $quote
     * @param int $productId
     * @return Item[]
     */
    private function getFreeItemsForProduct(Quote $quote, $productId): array
    {
        $freeItems = [];
        foreach ($quote->getAllItems() as $item) {
            if ($item->getProductId() == $productId && $item->getData('is_bogo_free')) {
                $freeItems[] = $item;
            }
        }
        return $freeItems;
    }

    /**
     * Calculate expected free quantity
     *
     * @param float $paidQty
     * @param \Magento\Catalog\Model\Product $product
     * @return float
     */
    private function calculateExpectedFreeQty($paidQty, $product): float
    {
        // 计算基础免费数量：每个付费商品送一个
        $baseFreeQty = $paidQty;
        
        $globalMaxFree = $this->helper->getMaxFreeItems();
        $productMaxFree = (float)$product->getData('bogo_max_free');
        
        $maxFree = $productMaxFree > 0 ? 
            ($globalMaxFree > 0 ? min($globalMaxFree, $productMaxFree) : $productMaxFree) : 
            $globalMaxFree;
        
        $finalFreeQty = $maxFree > 0 ? min($baseFreeQty, $maxFree) : $baseFreeQty;
        
        $this->logger->debug('Calculated expected free quantity', [
            'paid_qty' => $paidQty,
            'base_free_qty' => $baseFreeQty,
            'global_max_free' => $globalMaxFree,
            'product_max_free' => $productMaxFree,
            'final_free_qty' => $finalFreeQty
        ]);
        
        return $finalFreeQty;
    }

    /**
     * Update free items
     *
     * @param Quote $quote
     * @param Item $paidItem
     * @param float $expectedFreeQty
     * @param array $existingFreeItems
     * @return void
     */
    private function updateFreeItems(Quote $quote, Item $paidItem, $expectedFreeQty, array $existingFreeItems): void
    {
        $this->logger->debug('Updating free items', [
            'expected_qty' => $expectedFreeQty,
            'existing_items' => count($existingFreeItems),
            'quote_id' => $quote->getId(),
            'paid_item_id' => $paidItem->getId()
        ]);

        // 如果不需要免费商品，删除所有现有的
        if ($expectedFreeQty <= 0) {
            foreach ($existingFreeItems as $item) {
                $quote->removeItem($item->getId());
                $this->logger->debug('Removed free item', [
                    'item_id' => $item->getId()
                ]);
            }
            return;
        }

        // 如果已有免费商品，更新第一个，删除其他的
        if (!empty($existingFreeItems)) {
            $freeItem = array_shift($existingFreeItems);
            $oldQty = $freeItem->getQty();
            $freeItem->setQty($expectedFreeQty);
            
            $this->logger->debug('Updated existing free item', [
                'item_id' => $freeItem->getId(),
                'old_qty' => $oldQty,
                'new_qty' => $expectedFreeQty
            ]);
            
            foreach ($existingFreeItems as $item) {
                $quote->removeItem($item->getId());
                $this->logger->debug('Removed extra free item', [
                    'item_id' => $item->getId()
                ]);
            }
        } else {
            // 如果没有免费商品，创建新的
            $this->createFreeItem($quote, $paidItem, $expectedFreeQty);
        }
    }

    /**
     * Create new free item
     *
     * @param Quote $quote
     * @param Item $paidItem
     * @param float $freeQty
     * @return void
     */
    private function createFreeItem(Quote $quote, Item $paidItem, $freeQty): void
    {
        $freeItem = $this->itemFactory->create();
        $freeItem->setProduct($paidItem->getProduct())
            ->setQty($freeQty)
            ->setCustomPrice(0)
            ->setOriginalCustomPrice(0)
            ->setPrice(0)
            ->setBasePrice(0)
            ->setPriceInclTax(0)
            ->setBasePriceInclTax(0)
            ->setRowTotal(0)
            ->setBaseRowTotal(0)
            ->setRowTotalInclTax(0)
            ->setBaseRowTotalInclTax(0)
            ->setTaxAmount(0)
            ->setBaseTaxAmount(0)
            ->setDiscountAmount(0)
            ->setBaseDiscountAmount(0)
            ->setDiscountPercent(0)
            ->setData('is_bogo_free', true)
            ->setData('no_discount', 1);

        $this->logger->debug('Creating free item', [
            'quote_id' => $quote->getId(),
            'product_id' => $paidItem->getProduct()->getId(),
            'qty' => $freeQty,
            'price' => $freeItem->getPrice(),
            'base_price' => $freeItem->getBasePrice(),
            'custom_price' => $freeItem->getCustomPrice(),
            'row_total' => $freeItem->getRowTotal(),
            'tax_amount' => $freeItem->getTaxAmount()
        ]);

        $quote->addItem($freeItem);

        $formattedPrice = $this->priceHelper->currency($paidItem->getProduct()->getFinalPrice(), true, false);
        $this->messageManager->addSuccessMessage(
            __('BOGO offer applied: Free %1 (worth %2) has been added!',
                $paidItem->getProduct()->getName(),
                $formattedPrice
            )
        );
    }
}
