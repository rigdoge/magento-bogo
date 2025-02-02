<?php
declare(strict_types=1);

namespace Bogo\BuyOneGetOne\Model\Rule\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Bogo\BuyOneGetOne\Model\Rule;

class ActionType implements OptionSourceInterface
{
    /**
     * @var Rule
     */
    private $rule;

    /**
     * @param Rule $rule
     */
    public function __construct(Rule $rule)
    {
        $this->rule = $rule;
    }

    /**
     * @inheritDoc
     */
    public function toOptionArray()
    {
        $options = [];
        foreach ($this->rule->getAvailableActionTypes() as $value => $label) {
            $options[] = [
                'value' => $value,
                'label' => $label
            ];
        }
        return $options;
    }
}
