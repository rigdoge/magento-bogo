<?php
namespace Bogo\BuyOneGetOne\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Bogo\BuyOneGetOne\Helper\Data;
use Magento\Framework\Message\ManagerInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\LocalizedException;

class SyncQuantities implements ObserverInterface
{
    /**
     * @var Data
     */
    protected $helper;

    /**
     * @var ManagerInterface
     */
    protected $messageManager;

    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * @param Data $helper
     * @param ManagerInterface $messageManager
     * @param CheckoutSession $checkoutSession
     */
    public function __construct(
        Data $helper,
        ManagerInterface $messageManager,
        CheckoutSession $checkoutSession
    ) {
        $this->helper = $helper;
        $this->messageManager = $messageManager;
        $this->checkoutSession = $checkoutSession;
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
            $quote = $this->checkoutSession->getQuote();
            if (!$quote) {
                return;
            }

            $items = $quote->getAllVisibleItems();
            $bogoItems = [];

            // 首先收集所有买一送一商品
            foreach ($items as $item) {
                $product = $item->getProduct();
                if (!$product || !$product->getData('buy_one_get_one')) {
                    continue;
                }

                $productId = $product->getId();
                if (!isset($bogoItems[$productId])) {
                    $bogoItems[$productId] = [
                        'paid' => null,
                        'free' => null
                    ];
                }

                if ($item->getData('is_bogo_free')) {
                    $bogoItems[$productId]['free'] = $item;
                } else {
                    $bogoItems[$productId]['paid'] = $item;
                }
            }

            // 然后同步数量
            foreach ($bogoItems as $items) {
                if (!$items['paid'] || !$items['free']) {
                    continue;
                }

                $paidQty = $items['paid']->getQty();
                $freeQty = $items['free']->getQty();

                if ($freeQty != $paidQty) {
                    $items['free']->setQty($paidQty);
                }
            }

            $quote->collectTotals();
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Unable to sync BOGO quantities.'));
        }
    }
} 