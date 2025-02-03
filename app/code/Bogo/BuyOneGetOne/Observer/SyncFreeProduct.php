<?php
namespace Bogo\BuyOneGetOne\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Model\Quote\ItemFactory;
use Magento\Framework\Message\ManagerInterface;
use Bogo\BuyOneGetOne\Helper\Data as BogoHelper;

class SyncFreeProduct implements ObserverInterface
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
     * Handle BOGO products in cart
     * 
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        try {
            if (!$this->bogoHelper->isEnabled()) {
                return;
            }

            $quote = $this->checkoutSession->getQuote();
            if (!$quote) {
                return;
            }

            $processedProducts = [];

            foreach ($quote->getAllItems() as $item) {
                // Skip if already processed this product
                if (in_array($item->getProductId(), $processedProducts)) {
                    continue;
                }

                // Check if it's a paid product and BOGO is enabled for it
                if ($item->getPrice() > 0 && $item->getProduct()->getData('buy_one_get_one')) {
                    $productId = $item->getProductId();
                    $paidQty = $item->getQty();
                    $freeItem = null;

                    // Check if we've reached the maximum allowed free items
                    if ($this->bogoHelper->hasReachedMaxFreeItems($productId, $quote)) {
                        continue;
                    }

                    // Look for existing free item
                    foreach ($quote->getAllItems() as $qItem) {
                        if ($qItem->getProductId() == $productId &&
                            $qItem->getData('is_bogo_free') &&
                            $qItem->getPrice() == 0) {
                            $freeItem = $qItem;
                            break;
                        }
                    }

                    if ($freeItem) {
                        // Update quantity if different
                        if ($freeItem->getQty() != $paidQty) {
                            $freeItem->setQty($paidQty);
                        }
                    } else {
                        // Create new free item
                        $freeItem = $this->itemFactory->create();
                        $freeItem->setProduct($item->getProduct())
                            ->setQuote($quote)
                            ->setQty($paidQty)
                            ->setCustomPrice(0)
                            ->setOriginalCustomPrice(0)
                            ->setData('is_bogo_free', 1);
                        $quote->addItem($freeItem);
                        $this->messageManager->addSuccessMessage(__('Free product has been added to your cart.'));
                    }

                    $processedProducts[] = $productId;
                }
            }

            $quote->collectTotals();
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Unable to process BOGO items: ') . $e->getMessage());
        }
    }
} 