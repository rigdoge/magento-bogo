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

        } catch (\Exception $e) {
            $this->logger->error('Error in BOGO observer', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
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
            foreach ($quote->getAllItems() as $item) {
                if ($item->getData('is_bogo_free') && 
                    $item->getProductId() == $paidItem->getProductId()) {
                    $existingFreeItem = $item;
                    break;
                }
            }

            if ($existingFreeItem) {
                // 更新现有免费商品的数量
                $newFreeQty = $existingFreeItem->getQty() + $freeQty;
                
                // 检查是否超过最大限制
                $maxFree = $this->helper->getMaxFreeItems();
                if ($maxFree > 0 && $newFreeQty > $maxFree) {
                    $newFreeQty = $maxFree;
                }
                
                $existingFreeItem->setQty($newFreeQty);
                $existingFreeItem->save();
                
                $this->logger->debug('Updated existing free item', [
                    'item_id' => $existingFreeItem->getId(),
                    'orig_qty' => $existingFreeItem->getQty(),
                    'new_qty' => $newFreeQty,
                    'added_qty' => $freeQty,
                    'max_free' => $maxFree
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

            // 保存购物车
            $quote->collectTotals()->save();

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