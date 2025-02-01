<?php
namespace Bogo\BuyOneGetOne\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\LocalizedException;

class Data extends AbstractHelper
{
    const XML_PATH_ENABLED = 'buyonegetone/general/enable';
    const XML_PATH_CUSTOMER_GROUPS = 'buyonegetone/general/customer_groups';
    const XML_PATH_MAX_FREE_ITEMS = 'buyonegetone/general/max_free_items';
    const XML_PATH_ACTIVE_FROM = 'buyonegetone/time_settings/active_from';
    const XML_PATH_ACTIVE_TO = 'buyonegetone/time_settings/active_to';
    const XML_PATH_SHOW_LABEL = 'buyonegetone/display/show_bogo_label';
    const XML_PATH_LABEL_TEXT = 'buyonegetone/display/bogo_label_text';

    /**
     * @var TimezoneInterface
     */
    private $timezone;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @param \Magento\Framework\App\Helper\Context $context
     * @param TimezoneInterface $timezone
     * @param CustomerSession $customerSession
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        TimezoneInterface $timezone,
        CustomerSession $customerSession
    ) {
        $this->timezone = $timezone;
        $this->customerSession = $customerSession;
        parent::__construct($context);
    }

    /**
     * Check if module is enabled and active for current customer
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled($storeId = null)
    {
        if (!$this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        )) {
            return false;
        }

        if (!$this->isActiveDate()) {
            return false;
        }

        return $this->isCustomerGroupAllowed();
    }

    /**
     * Check if current date is within active period
     *
     * @return bool
     */
    public function isActiveDate()
    {
        $now = $this->timezone->date()->getTimestamp();
        $from = $this->getActiveFromDate();
        $to = $this->getActiveToDate();

        if (!$from && !$to) {
            return true;
        }

        if ($from && $to) {
            return $now >= strtotime($from) && $now <= strtotime($to);
        }

        if ($from) {
            return $now >= strtotime($from);
        }

        if ($to) {
            return $now <= strtotime($to);
        }

        return true;
    }

    /**
     * Check if current customer group is allowed
     *
     * @return bool
     */
    public function isCustomerGroupAllowed()
    {
        try {
            $customerGroupId = $this->customerSession->getCustomerGroupId();
            $allowedGroups = $this->getAllowedCustomerGroups();
            
            return empty($allowedGroups) || in_array($customerGroupId, $allowedGroups);
        } catch (NoSuchEntityException $e) {
            return false;
        }
    }

    /**
     * Get allowed customer groups
     *
     * @param int|null $storeId
     * @return array
     */
    public function getAllowedCustomerGroups($storeId = null)
    {
        $groups = $this->scopeConfig->getValue(
            self::XML_PATH_CUSTOMER_GROUPS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        return $groups ? explode(',', $groups) : [];
    }

    /**
     * Get maximum allowed free items per product
     *
     * @param int|null $storeId
     * @return int
     */
    public function getMaxFreeItems($storeId = null)
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_MAX_FREE_ITEMS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 0;
    }

    /**
     * Get active from date
     *
     * @param int|null $storeId
     * @return string|null
     */
    public function getActiveFromDate($storeId = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_ACTIVE_FROM,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get active to date
     *
     * @param int|null $storeId
     * @return string|null
     */
    public function getActiveToDate($storeId = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_ACTIVE_TO,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Check if BOGO label should be shown
     *
     * @param int|null $storeId
     * @return bool
     */
    public function shouldShowLabel($storeId = null)
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_SHOW_LABEL,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get BOGO label text
     *
     * @param int|null $storeId
     * @return string
     */
    public function getLabelText($storeId = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_LABEL_TEXT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 'Buy 1 Get 1 Free!';
    }

    /**
     * Check if product has reached maximum free items limit
     *
     * @param string $productId
     * @param \Magento\Quote\Model\Quote $quote
     * @return bool
     */
    public function hasReachedMaxFreeItems($productId, $quote)
    {
        $maxItems = $this->getMaxFreeItems();
        if ($maxItems === 0) {
            return false;
        }

        $freeItemCount = 0;
        foreach ($quote->getAllItems() as $item) {
            if ($item->getProductId() == $productId && $item->getData('is_bogo_free')) {
                $freeItemCount += $item->getQty();
            }
        }

        return $freeItemCount >= $maxItems;
    }
}