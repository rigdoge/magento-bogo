<?php
namespace Bogo\BuyOneGetOne\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Model\Quote\ItemFactory;
use Magento\Framework\Message\ManagerInterface;
use Bogo\BuyOneGetOne\Helper\Data as BogoHelper;

class AddFreeProduct implements ObserverInterface
{
    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * @var ItemFactory
     */
    protected $itemFactory;

    /**
     * @var ManagerInterface
     */
    protected $messageManager;

    /**
     * @var BogoHelper
     */
    protected $bogoHelper;

    /**
     * @param CheckoutSession $checkoutSession
     * @param ItemFactory $itemFactory
     * @param ManagerInterface $messageManager
     * @param BogoHelper $bogoHelper
     */
    public function __construct(
        CheckoutSession $checkoutSession,
        ItemFactory $itemFactory,
        ManagerInterface $messageManager,
        BogoHelper $bogoHelper
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->itemFactory = $itemFactory;
        $this->messageManager = $messageManager;
        $this->bogoHelper = $bogoHelper;
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
                return;
            }

            $item = $observer->getEvent()->getData('quote_item');
            $product = $item->getProduct();
            
            // Check if product is BOGO enabled
            if (!$product->getData('buy_one_get_one')) {
                return;
            }

            $quote = $this->checkoutSession->getQuote();
            if (!$quote) {
                return;
            }

            // Check if we've reached the maximum allowed free items
            if ($this->bogoHelper->hasReachedMaxFreeItems($product->getId(), $quote)) {
                $this->messageManager->addNoticeMessage(__('Maximum limit for free items has been reached for this product.'));
                return;
            }

            // Get the quantity of the paid product
            $paidQty = $item->getQty();

            // Create free product item
            $freeItem = $this->itemFactory->create();
            $freeItem->setProduct($product)
                ->setQuote($quote)
                ->setQty($paidQty)
                ->setCustomPrice(0)
                ->setOriginalCustomPrice(0)
                ->setData('is_bogo_free', 1);

            $quote->addItem($freeItem);
            $quote->collectTotals();

            $this->messageManager->addSuccessMessage(__('Free product has been added to your cart.'));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Unable to add free BOGO product: ') . $e->getMessage());
        }
    }
} 