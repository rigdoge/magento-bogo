<?php
namespace Bogo\BuyOneGetOne\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Model\Quote\ItemFactory;
use Magento\Framework\Message\ManagerInterface;

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
     * @param CheckoutSession $checkoutSession
     * @param ItemFactory $itemFactory
     * @param ManagerInterface $messageManager
     */
    public function __construct(
        CheckoutSession $checkoutSession,
        ItemFactory $itemFactory,
        ManagerInterface $messageManager
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->itemFactory = $itemFactory;
        $this->messageManager = $messageManager;
    }

    /**
     * Synchronize free product quantity with its paid product.
     * For each paid product that is eligible for BOGO, ensure there is a corresponding free product with the same quantity.
     * 
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        try {
            $quote = $this->checkoutSession->getQuote();
            if (!$quote) {
                return;
            }

            foreach ($quote->getAllItems() as $item) {
                // Check if it's a paid product and BOGO is enabled for it
                if ($item->getPrice() > 0 && $item->getProduct()->getData('buy_one_get_one')) {
                    $productId = $item->getProductId();
                    $paidQty = $item->getQty();
                    $freeItem = null;

                    // Search for corresponding free item
                    foreach ($quote->getAllItems() as $qItem) {
                        if ($qItem->getProductId() == $productId &&
                            $qItem->getData('is_bogo_free') &&
                            $qItem->getPrice() == 0) {
                            $freeItem = $qItem;
                            break;
                        }
                    }

                    if ($freeItem) {
                        if ($freeItem->getQty() != $paidQty) {
                            $freeItem->setQty($paidQty);
                        }
                    } else {
                        // If free item does not exist, create one
                        $freeItem = $this->itemFactory->create();
                        $freeItem->setProduct($item->getProduct())
                            ->setQuote($quote)
                            ->setQty($paidQty)
                            ->setCustomPrice(0)
                            ->setOriginalCustomPrice(0)
                            ->setData('is_bogo_free', 1);
                        $quote->addItem($freeItem);
                    }
                }
            }

            $quote->collectTotals();
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Unable to sync BOGO free items: ') . $e->getMessage());
        }
    }
} 