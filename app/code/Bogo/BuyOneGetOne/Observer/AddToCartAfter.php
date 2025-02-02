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
        $quote = $observer->getQuote();
        $item = $observer->getQuoteItem();
        if ($quote && $item) {
            $this->bogoManager->processBogoForItem($quote, $item);
        }
    }
}
