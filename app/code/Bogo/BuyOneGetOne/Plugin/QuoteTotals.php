<?php
namespace Bogo\BuyOneGetOne\Plugin;

use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Bogo\BuyOneGetOne\Helper\Data;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;
use Magento\Quote\Model\Quote\ItemFactory;

class QuoteTotals
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
     * Process BOGO items before collecting totals
     *
     * @param Quote $subject
     * @return null
     */
    public function beforeCollectTotals(Quote $subject)
    {
        if (!$this->helper->isEnabled()) {
            return null;
        }

        try {
            $this->processBOGOItems($subject);
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Unable to apply BOGO offer. Please try again.'));
        }

        return null;
    }

    /**
     * Process all BOGO items in quote
     *
     * @param Quote $quote
     * @return void
     */
    private function processBOGOItems(Quote $quote)
    {
        $bogoItems = [];
        $maxFreeItems = $this->helper->getMaxFreeItems();
        $freeItems = [];
        
        // 第一步：收集所有商品信息
        foreach ($quote->getAllItems() as $item) {
            $productId = $item->getProductId();
            if ($item->getData('is_bogo_free')) {
                // 收集免费商品信息
                if (!isset($freeItems[$productId])) {
                    $freeItems[$productId] = [];
                }
                $freeItems[$productId][] = $item;
            } elseif ($item->getProduct()->getData('buy_one_get_one')) {
                // 收集付费商品信息
                if (!isset($bogoItems[$productId])) {
                    $bogoItems[$productId] = [
                        'paid_qty' => 0,
                        'items' => [],
                    ];
                }
                $bogoItems[$productId]['paid_qty'] += $item->getQty();
                $bogoItems[$productId]['items'][] = $item;
            }
        }
        
        // 第二步：处理每个BOGO商品
        foreach ($bogoItems as $productId => $data) {
            // 计算应该有的免费商品数量
            $expectedFreeQty = $maxFreeItems > 0 ? min($data['paid_qty'], $maxFreeItems) : $data['paid_qty'];
            
            // 计算当前已有的免费商品数量
            $currentFreeQty = 0;
            if (isset($freeItems[$productId])) {
                foreach ($freeItems[$productId] as $freeItem) {
                    $currentFreeQty += $freeItem->getQty();
                }
            }
            
            // 如果需要添加新的免费商品
            if ($expectedFreeQty > $currentFreeQty) {
                $newFreeQty = $expectedFreeQty - $currentFreeQty;
                $this->createFreeItem($quote, end($data['items']), $newFreeQty);
            }
            // 如果需要减少免费商品
            elseif ($expectedFreeQty < $currentFreeQty && isset($freeItems[$productId])) {
                $remainingQty = $expectedFreeQty;
                foreach ($freeItems[$productId] as $freeItem) {
                    if ($remainingQty > 0) {
                        // 更新数量
                        $freeItem->setQty(min($freeItem->getQty(), $remainingQty));
                        $remainingQty -= $freeItem->getQty();
                    } else {
                        // 移除多余的免费商品
                        $quote->removeItem($freeItem->getId());
                    }
                }
            }
        }
        
        // 第三步：清理不需要的免费商品
        foreach ($quote->getAllItems() as $item) {
            if ($item->getData('is_bogo_free')) {
                $productId = $item->getProductId();
                if (!isset($bogoItems[$productId])) {
                    // 如果没有对应的付费商品，移除免费商品
                    $quote->removeItem($item->getId());
                }
            }
        }
    }

    /**
     * Find existing free item for product
     *
     * @param Quote $quote
     * @param int $productId
     * @return Quote\Item|null
     */
    private function findExistingFreeItem(Quote $quote, $productId)
    {
        foreach ($quote->getAllItems() as $item) {
            if ($item->getProductId() == $productId && $item->getData('is_bogo_free')) {
                return $item;
            }
        }
        return null;
    }

    /**
     * Update or create free item
     *
     * @param Quote $quote
     * @param Quote\Item $paidItem
     * @param float $freeQty
     * @return void
     */
    private function updateFreeItem(Quote $quote, $paidItem, $freeQty)
    {
        $freeItem = $this->findExistingFreeItem($quote, $paidItem->getProductId());
        
        if ($freeItem) {
            if ($freeQty > 0) {
                $freeItem->setQty($freeQty);
            } else {
                $quote->removeItem($freeItem->getId());
            }
        } elseif ($freeQty > 0) {
            $this->createFreeItem($quote, $paidItem, $freeQty);
        }
    }

    /**
     * Create new free item
     *
     * @param Quote $quote
     * @param Quote\Item $paidItem
     * @param float $freeQty
     * @return void
     */
    private function createFreeItem(Quote $quote, $paidItem, $freeQty)
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
            ->setData('is_bogo_free', 1)
            ->setData('no_discount', 1);

        $quote->addItem($freeItem);

        $formattedPrice = $this->priceHelper->currency($paidItem->getProduct()->getFinalPrice(), true, false);
        $this->messageManager->addSuccessMessage(
            __('BOGO offer applied: Free %1 (worth %2) has been added!',
                $paidItem->getProduct()->getName(),
                $formattedPrice
            )
        );
    }

    /**
     * Remove unnecessary free items
     *
     * @param Quote $quote
     * @param array $validProductIds
     * @return void
     */
    private function cleanupFreeItems(Quote $quote, array $validProductIds)
    {
        foreach ($quote->getAllItems() as $item) {
            if ($item->getData('is_bogo_free') && 
                !in_array($item->getProductId(), $validProductIds)
            ) {
                $quote->removeItem($item->getId());
            }
        }
    }
}
