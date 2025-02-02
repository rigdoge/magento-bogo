<?php
namespace Bogo\BuyOneGetOne\Plugin;

use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
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
     * After add product to quote
     *
     * @param Quote $subject
     * @param Quote\Item $result
     * @return Quote\Item
     */
    public function afterAddProduct(Quote $subject, $result)
    {
        if (!$result || !$this->helper->isEnabled()) {
            return $result;
        }

        try {
            $product = $result->getProduct();
            
            // 检查是否是BOGO商品
            if (!$product->getData('buy_one_get_one')) {
                return $result;
            }

            // 检查是否是免费商品（避免递归）
            if ($result->getData('is_bogo_free')) {
                return $result;
            }

            $maxFreeItems = $this->helper->getMaxFreeItems();
            $productMaxFree = (float)$product->getData('bogo_max_free');
            
            // 计算应该赠送的数量
            $maxFree = $productMaxFree > 0 ? 
                ($maxFreeItems > 0 ? min($maxFreeItems, $productMaxFree) : $productMaxFree) : 
                $maxFreeItems;
            
            $freeQty = $maxFree > 0 ? min($result->getQty(), $maxFree) : $result->getQty();

            // 使用BogoManager处理免费商品
            $this->bogoManager->processBogoForItem($subject, $result);

            $formattedPrice = $this->priceHelper->currency($product->getFinalPrice(), true, false);
            $this->messageManager->addSuccessMessage(
                __('BOGO offer applied: Free %1 (worth %2) has been added!',
                    $product->getName(),
                    $formattedPrice
                )
            );

        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Unable to apply BOGO offer. Please try again.'));
        }

        return $result;
    }
}
