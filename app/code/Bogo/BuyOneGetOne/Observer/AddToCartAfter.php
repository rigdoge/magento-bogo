<?php
namespace Bogo\BuyOneGetOne\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Bogo\BuyOneGetOne\Model\BogoManager;

class AddToCartAfter implements ObserverInterface
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
     * Process BOGO after adding item to cart
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $quoteItem = $observer->getEvent()->getQuoteItem();
        $quote = $observer->getEvent()->getQuote();
        
        if ($quoteItem && $quote) {
            $this->bogoManager->processBogoForItem($quote, $quoteItem);
        }
    }
}
