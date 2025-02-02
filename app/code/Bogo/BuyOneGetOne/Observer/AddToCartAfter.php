<?php
namespace Bogo\BuyOneGetOne\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Bogo\BuyOneGetOne\Model\BogoManager;
use Bogo\BuyOneGetOne\Logger\Logger;

class AddToCartAfter implements ObserverInterface
{
    /**
     * @var BogoManager
     */
    private $bogoManager;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @param BogoManager $bogoManager
     * @param Logger $logger
     */
    public function __construct(
        BogoManager $bogoManager,
        Logger $logger
    ) {
        $this->bogoManager = $bogoManager;
        $this->logger = $logger;
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
        
        $this->logger->debug('AddToCartAfter observer triggered', [
            'quote_id' => $quote ? $quote->getId() : null,
            'item_id' => $quoteItem ? $quoteItem->getId() : null,
            'product_id' => $quoteItem ? $quoteItem->getProductId() : null,
            'qty' => $quoteItem ? $quoteItem->getQty() : null
        ]);
        
        if ($quoteItem && $quote) {
            $this->bogoManager->processBogoForItem($quote, $quoteItem);
        }
    }
}
