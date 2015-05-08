<?php
/**
 * Ho_Recurring
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the H&O Commercial License
 * that is bundled with this package in the file LICENSE_HO.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.h-o.nl/license
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to info@h-o.com so we can send you a copy immediately.
 *
 * @category    Ho
 * @package     Ho_Recurring
 * @copyright   Copyright © 2015 H&O (http://www.h-o.nl/)
 * @license     H&O Commercial License (http://www.h-o.nl/license)
 * @author      Maikel Koek – H&O <info@h-o.nl>
 */

/**
 * Class Ho_Recurring_Model_Profile
 *
 * @method string getStatus()
 * @method $this setStatus(string $value)
 * @method $this setErrorMessage(string $value)
 * @method int getCustomerId()
 * @method $this setCustomerId(int $value)
 * @method string getCustomerName()
 * @method $this setCustomerName(string $value)
 * @method int getOrderId()
 * @method $this setOrderId(int $value)
 * @method int getBillingAgreementId()
 * @method $this setBillingAgreementId(int $value)
 * @method int getStoreId()
 * @method $this setStoreId(int $value)
 * @method datetime getCreatedAt()
 * @method $this setCreatedAt(datetime $value)
 * @method datetime getEndsAt()
 * @method $this setEndsAt(datetime $value)
 * @method string getTerm()
 * @method $this setTerm(string $value)
 * @method datetime getNextOrderAt()
 * @method $this setNextOrderAt(datetime $value)
 * @method string getPaymentMethod()
 * @method $this setPaymentMethod(string $value)
 * @method string getShippingMethod()
 * @method $this setShippingMethod(string $value)
 */
class Ho_Recurring_Model_Profile extends Mage_Core_Model_Abstract
{
    const STATUS_ACTIVE             = 'active';
    const STATUS_INACTIVE           = 'inactive';
    const STATUS_QUOTE_ERROR        = 'quote_error';
    const STATUS_ORDER_ERROR        = 'order_error';
    const STATUS_CANCELED           = 'canceled';
    const STATUS_EXPIRED            = 'expired';
    const STATUS_AWAITING_PAYMENT   = 'awaiting_payment';
    const STATUS_AGREEMENT_EXPIRED  = 'agreement_expired';

    const TERM_3_MONTHS             = '3_month';
    const TERM_6_MONTHS             = '6_month';

    protected function _construct ()
    {
        $this->_init('ho_recurring/profile');
    }

    /**
     * @return Ho_Recurring_Model_Resource_Profile_Collection
     */
    public function getActiveProfiles()
    {
        return $this
            ->getCollection()
            ->addFieldToFilter('status', Ho_Recurring_Model_Profile::STATUS_ACTIVE);
    }

    /**
     * @return Mage_Sales_Model_Quote
     */
    public function createQuote()
    {
        try {
            $quote = Mage::getModel('ho_recurring/service_profile')->createQuote($this);

            $this->saveQuoteAtProfile($quote);

            $this->setActive();

            return $quote;
        }
        catch (Exception $e) {
            $this->setStatus(self::STATUS_QUOTE_ERROR);
            $this->setErrorMessage($e->getMessage());
            $this->save();

            Ho_Recurring_Exception::throwException($e->getMessage());
        }
    }

    /**
     * @param Mage_Sales_Model_Quote|null $quote
     * @return Mage_Sales_Model_Order
     */
    public function createOrder(Mage_Sales_Model_Quote $quote = null)
    {
        try {
            if (!$quote) {
                $quote = $this->getQuote();
            }

            if (!$quote->getId()) {
                Mage::throwException(Mage::helper('ho_recurring')->__('Can\'t create order: No quote created yet.'));
            }

            // Collect quote totals
            $quote->collectTotals();
            $quote->save();

            // Create order
            $service = Mage::getModel('sales/service_quote', $quote);
            $service->submitAll();
            $order = $service->getOrder();

            // Place payment
            $order->place();

            // Save order to profile order history
            $this->saveOrderAtProfile($order);

            $this->setActive();

            return $order;
        }
        catch (Exception $e) {
            $this->setStatus(self::STATUS_ORDER_ERROR);
            $this->setErrorMessage($e->getMessage());
            $this->save();

            Ho_Recurring_Exception::throwException($e->getMessage());
        }
    }

    /**
     * Only one quote of each profile can be saved
     *
     * @param Mage_Sales_Model_Quote $quote
     * @throws Exception
     */
    public function saveQuoteAtProfile(Mage_Sales_Model_Quote $quote)
    {
        /** @var Ho_Recurring_Model_Profile_Quote $profileQuote */
        $profileQuote = Mage::getModel('ho_recurring/profile_quote')
            ->getCollection()
            ->addFieldToFilter('profile_id', $this->getId())
            ->getFirstItem();

        $profileQuote
            ->setProfileId($this->getId())
            ->setQuoteId($quote->getId())
            ->save();
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @throws Exception
     */
    public function saveOrderAtProfile(Mage_Sales_Model_Order $order)
    {
        $profileOrder = Mage::getModel('ho_recurring/profile_order')
            ->getCollection()
            ->addFieldToFilter('profile_id', $this->getId())
            ->addFieldToFilter('order_id', $order->getId())
            ->getFirstItem();

        if (!$profileOrder->getId()) {
            Mage::getModel('ho_recurring/profile_order')
                ->setProfileId($this->getId())
                ->setOrderId($order->getId())
                ->save();
        }

        $profileQuote = Mage::getModel('ho_recurring/profile_quote')
            ->getCollection()
            ->addFieldToFilter('profile_id', $this->getId())
            ->getFirstItem()
            ->delete();
    }

    /**
     * @param bool $active
     * @return Ho_Recurring_Model_Resource_Profile_Item_Collection
     */
    public function getItems($active = true)
    {
        $items = Mage::getModel('ho_recurring/profile_item')
            ->getCollection()
            ->addFieldToFilter('profile_id', $this->getId());

        if ($active) {
            $items->addFieldToFilter('status', Ho_Recurring_Model_Profile_Item::STATUS_ACTIVE);
        }

        return $items;
    }

    /**
     * @return Mage_Sales_Model_Resource_Order_Collection
     */
    public function getOrders()
    {
        return Mage::getModel('sales/order')
            ->getCollection()
            ->addFieldToFilter('entity_id', array('in' => $this->getOrderIds()));
    }

    /**
     * @return array
     */
    public function getOrderIds()
    {
        $profileOrders = Mage::getModel('ho_recurring/profile_order')
            ->getCollection()
            ->addFieldToFilter('profile_id', $this->getId());

        $orderIds = array();
        foreach ($profileOrders as $profileOrder) {
            $orderIds[] = $profileOrder->getOrderId();
        }

        return $orderIds;
    }

    /**
     * @return int
     */
    public function getQuoteId()
    {
        /** @var Ho_Recurring_Model_Profile_Quote $profileQuote */
        $profileQuote = Mage::getModel('ho_recurring/profile_quote')
            ->getCollection()
            ->addFieldToFilter('profile_id', $this->getId())
            ->getFirstItem();

        $quoteId = $profileQuote->getQuoteId();

        return $quoteId;
    }

    /**
     * @return Mage_Sales_Model_Quote|null
     */
    public function getQuote()
    {
        $quoteId = $this->getQuoteId();

        if (!$quoteId) return null;

        // Note: The quote won't load if we don't set the store ID
        $quote = Mage::getModel('sales/quote')
            ->setStoreId($this->getStoreId())
            ->load($quoteId);

        return $quote;
    }

    /**
     * @return Mage_Sales_Model_Order
     */
    public function getOriginalOrder()
    {
        return Mage::getModel('sales/order')->load($this->getOrderId());
    }

    /**
     * @return Adyen_Payment_Model_Billing_Agreement
     */
    public function getBillingAgreement()
    {
        return Mage::getModel('adyen/billing_agreement')->load($this->getBillingAgreementId());
    }

    /**
     * @return bool|string
     */
    public function getErrorMessage()
    {
        if ($this->getStatus() == self::STATUS_QUOTE_ERROR || $this->getStatus() == self::STATUS_ORDER_ERROR) {
            return $this->getData('error_message');
        }

        return false;
    }

    /**
     * @throws Exception
     */
    public function setActive()
    {
        $this->setStatus(self::STATUS_ACTIVE);
        $this->setErrorMessage(null);
        $this->save();
    }

    /**
     * @return array
     */
    public function getStatuses()
    {
        $helper = Mage::helper('ho_recurring');

        return array(
            self::STATUS_ACTIVE             => $helper->__('Active'),
            self::STATUS_INACTIVE           => $helper->__('Inactive'),
            self::STATUS_QUOTE_ERROR        => $helper->__('Quote Creation Error'),
            self::STATUS_ORDER_ERROR        => $helper->__('Order Creation Error'),
            self::STATUS_CANCELED           => $helper->__('Canceled'),
            self::STATUS_EXPIRED            => $helper->__('Expired'),
            self::STATUS_AWAITING_PAYMENT   => $helper->__('Awaiting Payment'),
            self::STATUS_AGREEMENT_EXPIRED  => $helper->__('Agreement Expired'),
        );
    }

    /**
     * @param string|null $status
     * @return string
     */
    public function getStatusLabel($status = null)
    {
        return $this->getStatuses()[$status ? $status : $this->getStatus()];
    }

    /**
     * @return array
     */
    public static function getStatusColors()
    {
        return array(
            self::STATUS_ACTIVE             => 'green',
            self::STATUS_INACTIVE           => 'gray',
            self::STATUS_QUOTE_ERROR        => 'red',
            self::STATUS_ORDER_ERROR        => 'red',
            self::STATUS_CANCELED           => 'yellow',
            self::STATUS_EXPIRED            => 'orange',
            self::STATUS_AWAITING_PAYMENT   => 'blue',
            self::STATUS_AGREEMENT_EXPIRED  => 'orange',
        );
    }

    /**
     * @param string|null $status
     * @return string
     */
    public function getStatusColor($status = null)
    {
        return $this->getStatusColors()[$status ? $status : $this->getStatus()];
    }

    /**
     * @param string|null $status
     * @return string
     */
    public function renderStatusBar($status = null)
    {
        if (is_null($status)) {
            $status = $this->getStatus();
        }

        $class = sprintf('status-bar status-bar-%s', $this->getStatusColor($status));

        return '<span class="' . $class . '"><span>' . $this->getStatusLabel($status) . '</span></span>';
    }

    /**
     * @return array
     */
    public function getTerms()
    {
        $helper = Mage::helper('ho_recurring');

        return array(
            self::TERM_3_MONTHS     => $helper->__('3 months'),
            self::TERM_6_MONTHS     => $helper->__('6 months'),
        );
    }

    /**
     * @return string
     */
    public function getTermLabel()
    {
        return $this->getTerms()[$this->getTerm()];
    }
}
