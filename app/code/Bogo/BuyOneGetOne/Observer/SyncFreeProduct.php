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
     * Only update quantity of existing free products, don't add new ones.
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

                    // Only update existing free items, don't create new ones
                    foreach ($quote->getAllItems() as $qItem) {
                        if ($qItem->getProductId() == $productId &&
                            $qItem->getData('is_bogo_free') &&
                            $qItem->getPrice() == 0) {
                            // Update quantity if different
                            if ($qItem->getQty() != $paidQty) {
                                $qItem->setQty($paidQty);
                            }
                            break;
                        }
                    }
                }
            }

            $quote->collectTotals();
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Unable to sync BOGO free items: ') . $e->getMessage());
        }
    }
} 