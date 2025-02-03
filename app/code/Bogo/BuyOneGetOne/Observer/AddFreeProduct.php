<?php
namespace Bogo\BuyOneGetOne\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Bogo\BuyOneGetOne\Model\BogoManager;
use Bogo\BuyOneGetOne\Helper\Data as BogoHelper;
use Bogo\BuyOneGetOne\Logger\Logger;

class AddFreeProduct implements ObserverInterface
{
    /**
     * @var BogoManager
     */
    protected $bogoManager;

    /**
     * @var BogoHelper
     */
    protected $bogoHelper;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @param BogoManager $bogoManager
     * @param BogoHelper $bogoHelper
     * @param Logger $logger
     */
    public function __construct(
        BogoManager $bogoManager,
        BogoHelper $bogoHelper,
        Logger $logger
    ) {
        $this->bogoManager = $bogoManager;
        $this->bogoHelper = $bogoHelper;
        $this->logger = $logger;
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
                $this->logger->debug('BOGO module is disabled');
                return;
            }

            $item = $observer->getEvent()->getData('quote_item');
            if (!$item) {
                $this->logger->debug('No quote item found');
                return;
            }

            if ($item->getData('is_bogo_free')) {
                $this->logger->debug('Skipping BOGO free item');
                return;
            }

            $quote = $item->getQuote();
            if (!$quote) {
                $this->logger->debug('No quote found');
                return;
            }

            $this->logger->debug('Processing new item for BOGO', [
                'item_id' => $item->getId(),
                'product_id' => $item->getProductId(),
                'qty' => $item->getQty()
            ]);

            // 创建免费商品
            $this->createFreeItem($quote, $item);

            // 保存购物车
            $quote->collectTotals()->save();

        } catch (\Exception $e) {
            $this->logger->error('Error in BOGO observer', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Create free item for the paid item
     *
     * @param \Magento\Quote\Model\Quote $quote
     * @param \Magento\Quote\Model\Quote\Item $paidItem
     * @return void
     */
    private function createFreeItem($quote, $paidItem)
    {
        try {
            $product = $paidItem->getProduct();
            
            // 检查产品是否启用 BOGO
            if (!$product->getData('buy_one_get_one') && !$product->getBuyOneGetOne()) {
                $this->logger->debug('Product is not BOGO eligible', [
                    'product_id' => $product->getId()
                ]);
                return;
            }

            // 计算免费商品数量
            $paidQty = $paidItem->getQty();
            $freeQty = $this->calculateFreeQty($paidQty, $product);

            if ($freeQty <= 0) {
                return;
            }

            // 创建免费商品项
            $freeItem = $quote->getItemFactory()->create();
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
                'product_id' => $product->getId(),
                'paid_qty' => $paidQty,
                'free_qty' => $freeQty
            ]);

            $quote->addItem($freeItem);

        } catch (\Exception $e) {
            $this->logger->error('Error creating free item', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Calculate free quantity based on paid quantity
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
        
        $globalMaxFree = $this->bogoHelper->getMaxFreeItems();
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