<?php
namespace Bogo\BuyOneGetOne\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Bogo\BuyOneGetOne\Helper\Data;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote\ItemFactory;
use Psr\Log\LoggerInterface;

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
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param Data $helper
     * @param CheckoutSession $checkoutSession
     * @param ManagerInterface $messageManager
     * @param ItemFactory $itemFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        Data $helper,
        CheckoutSession $checkoutSession,
        ManagerInterface $messageManager,
        ItemFactory $itemFactory,
        LoggerInterface $logger
    ) {
        $this->helper = $helper;
        $this->checkoutSession = $checkoutSession;
        $this->messageManager = $messageManager;
        $this->itemFactory = $itemFactory;
        $this->logger = $logger;
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        if (!$this->helper->isEnabled()) {
            return;
        }

        try {
            $item = $observer->getEvent()->getData('quote_item');
            $product = $observer->getEvent()->getData('product');
            $quote = $this->checkoutSession->getQuote();

            // 记录当前操作的商品信息
            $this->logger->info('Processing BOGO for product: ' . $product->getId() . ', SKU: ' . $product->getSku());

            // 如果是免费商品或没有启用买一送一，直接返回
            if ($item->getPrice() == 0 || !$product->getData('buy_one_get_one')) {
                $this->logger->info('Skipping product: price is 0 or BOGO not enabled');
                return;
            }

            // 检查是否已存在免费商品
            $hasFreeItem = false;
            foreach ($quote->getAllItems() as $quoteItem) {
                if ($quoteItem->getProductId() == $product->getId() && 
                    $quoteItem->getData('is_bogo_free') && 
                    $quoteItem->getPrice() == 0) {
                    $hasFreeItem = true;
                    $this->logger->info('Free item already exists for product: ' . $product->getId());
                    break;
                }
            }

            if (!$hasFreeItem) {
                // 创建新的免费商品
                $freeItem = $this->itemFactory->create();
                $freeItem->setProduct($product)
                    ->setQuote($quote)
                    ->setQty($item->getQty())
                    ->setCustomPrice(0)
                    ->setOriginalCustomPrice(0)
                    ->setData('is_bogo_free', 1);
                
                $quote->addItem($freeItem);
                $quote->collectTotals()->save();
                
                $this->logger->info('Added new free item for product: ' . $product->getId());
                $this->messageManager->addSuccessMessage(__('BOGO offer applied: Your free item has been added!'));
            }
        } catch (LocalizedException $e) {
            $this->logger->error('LocalizedException: ' . $e->getMessage());
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error('Exception: ' . $e->getMessage());
            $this->messageManager->addErrorMessage(__('Unable to apply BOGO offer. Please try again.'));
        }
    }
} 