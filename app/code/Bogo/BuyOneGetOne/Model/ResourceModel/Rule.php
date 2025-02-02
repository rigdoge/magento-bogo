<?php
declare(strict_types=1);

namespace Bogo\BuyOneGetOne\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Rule extends AbstractDb
{
    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init('bogo_promotion_rule', 'rule_id');
    }
}
