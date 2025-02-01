<?php
namespace Bogo\BuyOneGetOne\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Bogo\BuyOneGetOne\Helper\Data;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Message\ManagerInterface;

class UpdateCartItems implements ObserverInterface
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
     * @param Data $helper
     * @param CheckoutSession $checkoutSession
     * @param ManagerInterface $messageManager
     */
    public function __construct(
        Data $helper,
        CheckoutSession $checkoutSession,
        ManagerInterface $messageManager
    ) {
        $this->helper = $helper;
        $this->checkoutSession = $checkoutSession;
        $this->messageManager = $messageManager;
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
            $data = $observer->getEvent()->getInfo();
            $quote = $cart->getQuote();
            
            // 收集所有买一送一商品的信息
            $bogoItems = [];
            foreach ($quote->getAllItems() as $item) {
                $product = $item->getProduct();
                if (!$product->getBuyOneGetOne()) {
                    continue;
                }

                $productId = $product->getId();
                if (!isset($bogoItems[$productId])) {
                    $bogoItems[$productId] = [
                        'paid' => null,
                        'free' => null,
                        'new_qty' => 0
                    ];
                }

                // 记录商品信息
                if ($item->getPrice() > 0) {
                    $bogoItems[$productId]['paid'] = $item;
                    // 如果这个商品在更新数据中，使用新数量
                    if (isset($data[$item->getId()]['qty'])) {
                        $bogoItems[$productId]['new_qty'] = (float)$data[$item->getId()]['qty'];
                    } else {
                        $bogoItems[$productId]['new_qty'] = $item->getQty();
                    }
                } else {
                    $bogoItems[$productId]['free'] = $item;
                    // 如果免费商品在更新数据中，使用新数量
                    if (isset($data[$item->getId()]['qty'])) {
                        $bogoItems[$productId]['new_qty'] = (float)$data[$item->getId()]['qty'];
                    }
                }
            }

            // 同步数量
            foreach ($bogoItems as $items) {
                if ($items['paid'] && $items['free']) {
                    $items['paid']->setQty($items['new_qty']);
                    $items['free']->setQty($items['new_qty']);
                }
            }

            // 保存更改
            $quote->collectTotals()->save();
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Unable to update BOGO quantities. Please try again.'));
        }
    }
} 