<?php
declare(strict_types=1);

namespace Bogo\BuyOneGetOne\Model;

use Magento\Framework\Model\AbstractModel;

class Rule extends AbstractModel
{
    const ACTION_TYPE_BUY_X_GET_X = 'buy_x_get_x';
    const ACTION_TYPE_BUY_X_GET_Y = 'buy_x_get_y';
    
    const DISCOUNT_TYPE_FREE = 'free';
    const DISCOUNT_TYPE_PERCENT = 'percent';
    const DISCOUNT_TYPE_FIXED = 'fixed';

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(ResourceModel\Rule::class);
    }

    /**
     * Get available action types
     *
     * @return array
     */
    public function getAvailableActionTypes(): array
    {
        return [
            self::ACTION_TYPE_BUY_X_GET_X => __('Buy X Get X (Same Product)'),
            self::ACTION_TYPE_BUY_X_GET_Y => __('Buy X Get Y (Different Product)')
        ];
    }

    /**
     * Get available discount types
     *
     * @return array
     */
    public function getAvailableDiscountTypes(): array
    {
        return [
            self::DISCOUNT_TYPE_FREE => __('Free'),
            self::DISCOUNT_TYPE_PERCENT => __('Percent Discount'),
            self::DISCOUNT_TYPE_FIXED => __('Fixed Amount Discount')
        ];
    }
}
