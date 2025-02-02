<?php
namespace Bogo\BuyOneGetOne\Plugin;

use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use Bogo\BuyOneGetOne\Helper\Data;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;
use Magento\Quote\Model\Quote\ItemFactory;
use Bogo\BuyOneGetOne\Model\BogoManager;
use Bogo\BuyOneGetOne\Logger\Logger;

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
     * @var BogoManager
     */
    private $bogoManager;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @param Data $helper
     * @param ManagerInterface $messageManager
     * @param PricingHelper $priceHelper
     * @param ItemFactory $itemFactory
     * @param BogoManager $bogoManager
     * @param Logger $logger
     */
    public function __construct(
        Data $helper,
        ManagerInterface $messageManager,
        PricingHelper $priceHelper,
        ItemFactory $itemFactory,
        BogoManager $bogoManager,
        Logger $logger
    ) {
        $this->helper = $helper;
        $this->messageManager = $messageManager;
        $this->priceHelper = $priceHelper;
        $this->itemFactory = $itemFactory;
        $this->bogoManager = $bogoManager;
        $this->logger = $logger;
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
            $this->logger->debug('Processing afterAddProduct', [
                'quote_id' => $subject->getId(),
                'item_id' => $result->getId(),
                'product_id' => $result->getProductId(),
                'qty' => $result->getQty()
            ]);

            $product = $result->getProduct();
            
            // 检查是否是BOGO商品
            if (!$product->getData('buy_one_get_one')) {
                $this->logger->debug('Product is not BOGO eligible', [
                    'product_id' => $product->getId()
                ]);
                return $result;
            }

            // 检查是否是免费商品（避免递归）
            if ($result->getData('is_bogo_free')) {
                $this->logger->debug('Item is a free BOGO item, skipping', [
                    'item_id' => $result->getId()
                ]);
                return $result;
            }

            // 使用BogoManager处理免费商品
            $this->bogoManager->processBogoForItem($subject, $result);

        } catch (\Exception $e) {
            $this->logger->error('Error in afterAddProduct', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->messageManager->addErrorMessage(__('Unable to apply BOGO offer. Please try again.'));
        }

        return $result;
    }
}
