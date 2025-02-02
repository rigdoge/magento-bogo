<?php
namespace Bogo\BuyOneGetOne\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Bogo\BuyOneGetOne\Model\BogoManager;

class UpdateCartItemAfter implements ObserverInterface
{
    /**
     * @var BogoManager
     */
    private $bogoManager;

    /**
     * @param BogoManager $bogoManager
     */
    public function __construct(BogoManager $bogoManager)
    {
        $this->bogoManager = $bogoManager;
    }

    /**
     * Process BOGO after updating cart item
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $quoteItem = $observer->getEvent()->getItem();
        if ($quoteItem) {
            $quote = $quoteItem->getQuote();
            if ($quote) {
                $this->bogoManager->processBogoForItem($quote, $quoteItem);
            }
        }
    }
}
