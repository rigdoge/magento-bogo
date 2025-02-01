<?php
namespace Bogo\BuyOneGetOne\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Bogo\BuyOneGetOne\Helper\Data;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote\ItemFactory;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;

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
     * @var StockRegistryInterface
     */
    protected $stockRegistry;

    /**
     * @var PricingHelper
     */
    protected $priceHelper;

    /**
     * @param Data $helper
     * @param CheckoutSession $checkoutSession
     * @param ManagerInterface $messageManager
     * @param ItemFactory $itemFactory
     * @param StockRegistryInterface $stockRegistry
     * @param PricingHelper $priceHelper
     */
    public function __construct(
        Data $helper,
        CheckoutSession $checkoutSession,
        ManagerInterface $messageManager,
        ItemFactory $itemFactory,
        StockRegistryInterface $stockRegistry,
        PricingHelper $priceHelper
    ) {
        $this->helper = $helper;
        $this->checkoutSession = $checkoutSession;
        $this->messageManager = $messageManager;
        $this->itemFactory = $itemFactory;
        $this->stockRegistry = $stockRegistry;
        $this->priceHelper = $priceHelper;
    }

    /**
     * @param Observer $observer
     * @return void
     * @throws LocalizedException
     */
    public function execute(Observer $observer)
    {
        if (!$this->helper->isEnabled()) {
            return;
        }

        try {
            $item = $observer->getEvent()->getData('quote_item');
            $product = $observer->getEvent()->getData('product');

            // 检查是否是免费商品或没有启用买一送一
            if ($item->getPrice() == 0 || !$product->getData('buy_one_get_one')) {
                return;
            }

            // 检查库存
            $stockItem = $this->stockRegistry->getStockItem($product->getId());
            if (!$stockItem->getIsInStock() || $stockItem->getQty() < ($item->getQty() * 2)) {
                throw new LocalizedException(__('Not enough stock available for BOGO offer.'));
            }

            $quote = $this->checkoutSession->getQuote();

            // 检查是否达到最大免费商品数量限制
            if ($this->helper->hasReachedMaxFreeItems($product->getId(), $quote)) {
                throw new LocalizedException(__('Maximum number of free items reached for this product.'));
            }

            // 删除已存在的相同商品的免费项
            $this->removeExistingFreeItems($quote, $product);

            // 添加新的免费商品
            $this->addFreeItem($quote, $product, $item);

            // 显示成功消息
            $formattedPrice = $this->priceHelper->currency($product->getFinalPrice(), true, false);
            $this->messageManager->addSuccessMessage(
                __('BOGO offer applied: Free %1 (worth %2) has been added!',
                    $product->getName(),
                    $formattedPrice
                )
            );
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Unable to apply BOGO offer. Please try again.'));
        }
    }

    /**
     * Remove existing free items for the product
     *
     * @param \Magento\Quote\Model\Quote $quote
     * @param \Magento\Catalog\Model\Product $product
     * @return void
     */
    private function removeExistingFreeItems($quote, $product)
    {
        foreach ($quote->getAllItems() as $quoteItem) {
            if ($quoteItem->getProductId() == $product->getId() && 
                $quoteItem->getData('is_bogo_free') && 
                $quoteItem->getPrice() == 0) {
                $quote->removeItem($quoteItem->getId());
            }
        }
    }

    /**
     * Add free item to quote
     *
     * @param \Magento\Quote\Model\Quote $quote
     * @param \Magento\Catalog\Model\Product $product
     * @param \Magento\Quote\Model\Quote\Item $originalItem
     * @return void
     */
    private function addFreeItem($quote, $product, $originalItem)
    {
        $freeItem = $this->itemFactory->create();
        $freeItem->setProduct($product)
            ->setQty($originalItem->getQty())
            ->setCustomPrice(0)
            ->setOriginalCustomPrice(0)
            ->setData('is_bogo_free', 1)
            ->setData('original_item_id', $originalItem->getId());

        $quote->addItem($freeItem);
        $quote->collectTotals();
        $quote->save();
    }
} 