<?php
declare(strict_types=1);

namespace Bogo\BuyOneGetOne\Model\ResourceModel\Rule;

use Bogo\BuyOneGetOne\Model\Rule;
use Bogo\BuyOneGetOne\Model\ResourceModel\Rule as RuleResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(Rule::class, RuleResource::class);
    }
}
