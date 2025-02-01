<?php
namespace Bogo\BuyOneGetOne\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Bogo\BuyOneGetOne\Helper\Data;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Message\ManagerInterface;
use Magento\Quote\Api\Data\CartItemInterfaceFactory;
use Magento\Framework\Exception\LocalizedException;

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
     * @var CartItemInterfaceFactory
     */
    protected $cartItemFactory;

    /**
     * @param Data $helper
     * @param CheckoutSession $checkoutSession
     * @param ManagerInterface $messageManager
     * @param CartItemInterfaceFactory $cartItemFactory
     */
    public function __construct(
        Data $helper,
        CheckoutSession $checkoutSession,
        ManagerInterface $messageManager,
        CartItemInterfaceFactory $cartItemFactory
    ) {
        $this->helper = $helper;
        $this->checkoutSession = $checkoutSession;
        $this->messageManager = $messageManager;
        $this->cartItemFactory = $cartItemFactory;
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
            $quoteItem = $observer->getEvent()->getData('quote_item');
            if (!$quoteItem) {
                return;
            }

            $product = $quoteItem->getProduct();
            if (!$product || !$product->getData('buy_one_get_one')) {
                return;
            }

            // 如果是免费商品，跳过
            if ($quoteItem->getData('is_bogo_free')) {
                return;
            }

            $quote = $this->checkoutSession->getQuote();
            if (!$quote) {
                return;
            }

            // 创建免费商品
            $freeItem = $this->cartItemFactory->create();
            $freeItem->setProduct($product)
                ->setQty($quoteItem->getQty())
                ->setCustomPrice(0)
                ->setOriginalCustomPrice(0)
                ->setData('is_bogo_free', 1);
            
            $quote->addItem($freeItem);
            $quote->collectTotals();

            $this->messageManager->addSuccessMessage(__('BOGO offer applied: Your free item has been added!'));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Unable to apply BOGO offer. Please try again.'));
        }
    }
} 