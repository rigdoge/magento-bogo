<?php
namespace Bogo\BuyOneGetOne\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Bogo\BuyOneGetOne\Helper\Data;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote\ItemFactory;

class AddFreeProduct implements ObserverInterface
{
    /**
     * @var Data
     */
    protected $helper;

    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * @var ManagerInterface
     */
    protected $messageManager;

    /**
     * @var ItemFactory
     */
    protected $itemFactory;

    /**
     * @param Data $helper
     * @param CheckoutSession $checkoutSession
     * @param ManagerInterface $messageManager
     * @param ItemFactory $itemFactory
     */
    public function __construct(
        Data $helper,
        CheckoutSession $checkoutSession,
        ManagerInterface $messageManager,
        ItemFactory $itemFactory
    ) {
        $this->helper = $helper;
        $this->checkoutSession = $checkoutSession;
        $this->messageManager = $messageManager;
        $this->itemFactory = $itemFactory;
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
            $cart = $observer->getEvent()->getCart();
            $quote = $cart->getQuote();
            
            // 收集所有需要处理的商品
            $bogoProducts = [];
            foreach ($quote->getAllItems() as $item) {
                if ($item->getPrice() > 0 && $item->getProduct()->getData('buy_one_get_one')) {
                    $productId = $item->getProductId();
                    if (!isset($bogoProducts[$productId])) {
                        $bogoProducts[$productId] = [
                            'paid' => $item,
                            'free' => null
                        ];
                    }
                } elseif ($item->getData('is_bogo_free')) {
                    $productId = $item->getProductId();
                    if (isset($bogoProducts[$productId])) {
                        $bogoProducts[$productId]['free'] = $item;
                    }
                }
            }

            // 处理每个商品
            foreach ($bogoProducts as $productId => $items) {
                $paidItem = $items['paid'];
                $freeItem = $items['free'];

                if ($freeItem) {
                    // 更新已存在的免费商品数量
                    if ($freeItem->getQty() != $paidItem->getQty()) {
                        $freeItem->setQty($paidItem->getQty());
                    }
                } else {
                    // 创建新的免费商品
                    $freeItem = $this->itemFactory->create();
                    $freeItem->setProduct($paidItem->getProduct())
                        ->setQuote($quote)
                        ->setQty($paidItem->getQty())
                        ->setCustomPrice(0)
                        ->setOriginalCustomPrice(0)
                        ->setData('is_bogo_free', 1);
                    
                    $quote->addItem($freeItem);
                }
            }

            $quote->collectTotals()->save();
            
            $this->messageManager->addSuccessMessage(__('BOGO offer applied: Your free item has been added!'));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Unable to apply BOGO offer. Please try again.'));
        }
    }
} 