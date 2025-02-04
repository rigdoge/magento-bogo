<?php
namespace Bogo\BuyOneGetOne\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Quote\Model\Quote\Item;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;
use Magento\Quote\Model\Quote\ItemFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Bogo\BuyOneGetOne\Helper\Data;
use Bogo\BuyOneGetOne\Logger\Logger;

class AddFreeProduct implements ObserverInterface
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
     * Execute observer
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        try {
            if (!$this->helper->isEnabled()) {
                return;
            }

            $eventName = $observer->getEvent()->getName();
            $this->logger->debug('Processing BOGO event', [
                'event_name' => $eventName
            ]);

            switch ($eventName) {
                case 'checkout_cart_product_add_after':
                    $this->handleProductAdd($observer);
                    break;
                case 'checkout_cart_update_items_after':
                    $this->handleCartUpdate($observer);
                    break;
                case 'sales_quote_remove_item':
                    $this->handleItemRemove($observer);
                    break;
            }

        } catch (\Exception $e) {
            $this->logger->error('Error in BOGO observer', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Handle product add event
     *
     * @param Observer $observer
     * @return void
     */
    private function handleProductAdd(Observer $observer)
    {
        /** @var Item $quoteItem */
        $quoteItem = $observer->getEvent()->getData('quote_item');
        if (!$quoteItem || $quoteItem->getData('is_bogo_free')) {
            return;
        }

        $product = $quoteItem->getProduct();
        if (!$product->getData('buy_one_get_one') && !$product->getBuyOneGetOne()) {
            return;
        }

        $this->logger->debug('Processing BOGO for added item', [
            'item_id' => $quoteItem->getId(),
            'product_id' => $quoteItem->getProductId(),
            'qty' => $quoteItem->getQty()
        ]);

        $quote = $quoteItem->getQuote();
        $this->addFreeItem($quote, $quoteItem);
    }

    /**
     * Handle cart update event
     *
     * @param Observer $observer
     * @return void
     */
    private function handleCartUpdate(Observer $observer)
    {
        /** @var \Magento\Quote\Model\Quote $quote */
        $quote = $observer->getEvent()->getData('quote');
        if (!$quote) {
            return;
        }

        $this->logger->debug('Processing cart update');
        $this->syncAllFreeItems($quote);
    }

    /**
     * Handle item remove event
     *
     * @param Observer $observer
     * @return void
     */
    private function handleItemRemove(Observer $observer)
    {
        /** @var Item $quoteItem */
        $quoteItem = $observer->getEvent()->getData('quote_item');
        if (!$quoteItem) {
            return;
        }

        $quote = $quoteItem->getQuote();
        if ($quoteItem->getData('is_bogo_free')) {
            return;
        }

        $this->logger->debug('Processing item remove', [
            'item_id' => $quoteItem->getId(),
            'product_id' => $quoteItem->getProductId()
        ]);

        // 删除对应的免费商品
        foreach ($quote->getAllItems() as $item) {
            if ($item->getData('is_bogo_free') && 
                $item->getProductId() == $quoteItem->getProductId()) {
                $quote->removeItem($item->getId());
                $this->logger->debug('Removed free item', [
                    'item_id' => $item->getId()
                ]);
                break;
            }
        }
    }

    /**
     * Sync all free items in cart
     *
     * @param \Magento\Quote\Model\Quote $quote
     * @return void
     */
    private function syncAllFreeItems($quote)
    {
        $bogoItems = [];
        $freeItems = [];

        // 收集所有 BOGO 商品和免费商品
        foreach ($quote->getAllVisibleItems() as $item) {
            if ($item->getData('is_bogo_free')) {
                $freeItems[$item->getProductId()][] = $item;
            } else {
                $product = $item->getProduct();
                if ($product->getData('buy_one_get_one') || $product->getBuyOneGetOne()) {
                    if (!isset($bogoItems[$item->getProductId()])) {
                        $bogoItems[$item->getProductId()] = [
                            'qty' => 0,
                            'product' => $product
                        ];
                    }
                    $bogoItems[$item->getProductId()]['qty'] += $item->getQty();
                }
            }
        }

        // 更新或删除免费商品
        foreach ($bogoItems as $productId => $data) {
            $freeQty = $this->calculateFreeQty($data['qty'], $data['product']);
            
            if (isset($freeItems[$productId])) {
                $existingFreeItem = reset($freeItems[$productId]);
                if ($freeQty > 0) {
                    $existingFreeItem->setQty($freeQty);
                    // 删除多余的免费商品
                    foreach ($freeItems[$productId] as $index => $item) {
                        if ($index > 0) {
                            $quote->removeItem($item->getId());
                        }
                    }
                } else {
                    // 如果不需要免费商品，删除所有免费商品
                    foreach ($freeItems[$productId] as $item) {
                        $quote->removeItem($item->getId());
                    }
                }
            } elseif ($freeQty > 0) {
                // 创建新的免费商品
                $this->addFreeItem($quote, reset($quote->getItemsCollection()->getItemsByColumnValue('product_id', $productId)));
            }
        }

        // 删除没有对应付费商品的免费商品
        foreach ($freeItems as $productId => $items) {
            if (!isset($bogoItems[$productId])) {
                foreach ($items as $item) {
                    $quote->removeItem($item->getId());
                }
            }
        }

        $quote->collectTotals()->save();
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
        try {
            $paidQty = $paidItem->getQty();
            $freeQty = $this->calculateFreeQty($paidQty, $paidItem->getProduct());

            if ($freeQty <= 0) {
                return;
            }

            // 检查是否已存在免费商品
            $existingFreeItem = null;
            foreach ($quote->getAllVisibleItems() as $item) {
                if ($item->getData('is_bogo_free') && 
                    $item->getProductId() == $paidItem->getProductId()) {
                    $existingFreeItem = $item;
                    break;
                }
            }

            if ($existingFreeItem) {
                // 更新现有免费商品的数量
                $existingFreeItem->setQty($freeQty);
                
                $this->logger->debug('Updated existing free item', [
                    'item_id' => $existingFreeItem->getId(),
                    'new_qty' => $freeQty
                ]);
            } else {
                // 创建新的免费商品
                $freeItem = $this->itemFactory->create();
                $freeItem->setProduct($paidItem->getProduct())
                    ->setQuote($quote)
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

                $quote->addItem($freeItem);
                
                $this->logger->debug('Created new free item', [
                    'product_id' => $paidItem->getProductId(),
                    'qty' => $freeQty
                ]);

                $formattedPrice = $this->priceHelper->currency($paidItem->getProduct()->getFinalPrice(), true, false);
                $this->messageManager->addSuccessMessage(
                    __('BOGO offer applied: Free %1 (worth %2) has been added!',
                        $paidItem->getProduct()->getName(),
                        $formattedPrice
                    )
                );
            }

        } catch (\Exception $e) {
            $this->logger->error('Error adding free item', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Calculate free quantity
     *
     * @param float $paidQty
     * @param \Magento\Catalog\Model\Product $product
     * @return float
     */
    private function calculateFreeQty($paidQty, $product)
    {
        if ($paidQty <= 0) {
            return 0;
        }

        // 计算基础免费数量：每个付费商品送一个
        $baseFreeQty = $paidQty;
        
        $globalMaxFree = $this->helper->getMaxFreeItems();
        $productMaxFree = (float)$product->getData('bogo_max_free');
        
        $maxFree = $productMaxFree > 0 ? 
            ($globalMaxFree > 0 ? min($globalMaxFree, $productMaxFree) : $productMaxFree) : 
            $globalMaxFree;
        
        $finalFreeQty = $maxFree > 0 ? min($baseFreeQty, $maxFree) : $baseFreeQty;
        
        $this->logger->debug('Calculated free quantity', [
            'paid_qty' => $paidQty,
            'base_free_qty' => $baseFreeQty,
            'global_max_free' => $globalMaxFree,
            'product_max_free' => $productMaxFree,
            'final_free_qty' => $finalFreeQty
        ]);
        
        return $finalFreeQty;
    }
} 