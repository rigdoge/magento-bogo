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

            $items = $quote->getAllItems();
            foreach ($items as $item) {
                if (!$item->getData('is_bogo_free')) {
                    continue;
                }

                $product = $item->getProduct();
                if (!$product || !$product->getData('buy_one_get_one')) {
                    continue;
                }

                // 查找对应的付费商品
                foreach ($items as $paidItem) {
                    if ($paidItem->getData('is_bogo_free')) {
                        continue;
                    }

                    if ($paidItem->getProduct()->getId() == $product->getId()) {
                        // 同步数量
                        $item->setQty($paidItem->getQty());
                        break;
                    }
                }
            }
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Unable to sync BOGO quantities.'));
        }
    }
} 