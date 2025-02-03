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
            'qty' => $quoteItem->getQty()
        ]);

        if (!$this->helper->isEnabled()) {
            $this->logger->debug('BOGO module is disabled');
            return;
        }

        if ($quoteItem->getData('is_bogo_free')) {
            $this->logger->debug('Skipping BOGO free item');
            return;
        }

        try {
            // 重新加载产品以确保所有属性都被加载
            $product = $this->productRepository->getById($quoteItem->getProductId());
            
            if (!$product->getData('buy_one_get_one') && !$product->getBuyOneGetOne()) {
                $this->logger->debug('Product is not BOGO eligible', [
                    'product_id' => $product->getId()
                ]);
                return;
            }

            // 同步所有 BOGO 商品
            $this->syncBogoItems($quote);
            
        } catch (\Exception $e) {
            $this->logger->error('Error processing BOGO', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->messageManager->addErrorMessage(__('Unable to apply BOGO offer. Please try again.'));
        }
    }

    /**
     * Synchronize all BOGO items in the cart
     *
     * @param Quote $quote
     * @return void
     */
    private function syncBogoItems(Quote $quote): void
    {
        try {
            $bogoProducts = [];
            
            // 第一步：收集所有 BOGO 商品信息
            foreach ($quote->getAllVisibleItems() as $item) {
                if (!$item->getData('is_bogo_free')) {
                    $product = $item->getProduct();
                    if ($product->getData('buy_one_get_one') || $product->getBuyOneGetOne()) {
                        $productId = $item->getProductId();
                        if (!isset($bogoProducts[$productId])) {
                            $bogoProducts[$productId] = [
                                'paid_qty' => 0,
                                'product' => $product,
                                'free_items' => []
                            ];
                        }
                        $bogoProducts[$productId]['paid_qty'] += $item->getQty();
                    }
                }
            }
            
            // 第二步：收集免费商品信息
            foreach ($quote->getAllItems() as $item) {
                if ($item->getData('is_bogo_free')) {
                    $productId = $item->getProductId();
                    if (isset($bogoProducts[$productId])) {
                        $bogoProducts[$productId]['free_items'][] = $item;
                    }
                }
            }
            
            // 第三步：更新或创建免费商品
            foreach ($bogoProducts as $productId => $data) {
                $expectedFreeQty = $this->calculateExpectedFreeQty($data['paid_qty'], $data['product']);
                
                if (!empty($data['free_items'])) {
                    // 更新第一个免费商品，删除其他的
                    $freeItem = reset($data['free_items']);
                    $freeItem->setQty($expectedFreeQty);
                    
                    $count = 0;
                    foreach ($data['free_items'] as $item) {
                        if ($count++ > 0) {
                            $quote->removeItem($item->getId());
                        }
                    }
                    
                    $this->logger->debug('Updated free item', [
                        'product_id' => $productId,
                        'paid_qty' => $data['paid_qty'],
                        'free_qty' => $expectedFreeQty
                    ]);
                } else if ($expectedFreeQty > 0) {
                    // 创建新的免费商品
                    $this->createFreeItem($quote, $data['product'], $expectedFreeQty);
                }
            }
            
        } catch (\Exception $e) {
            $this->logger->error('Error in syncBogoItems', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
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

    /**
     * Create new free item
     *
     * @param Quote $quote
     * @param \Magento\Catalog\Model\Product $product
     * @param float $freeQty
     * @return void
     */
    private function createFreeItem(Quote $quote, $product, $freeQty): void
    {
        $freeItem = $this->itemFactory->create();
        $freeItem->setProduct($product)
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
            'product_id' => $product->getId(),
            'qty' => $freeQty
        ]);

        $quote->addItem($freeItem);

        $formattedPrice = $this->priceHelper->currency($product->getFinalPrice(), true, false);
        $this->messageManager->addSuccessMessage(
            __('BOGO offer applied: Free %1 (worth %2) has been added!',
                $product->getName(),
                $formattedPrice
            )
        );
    }
}
