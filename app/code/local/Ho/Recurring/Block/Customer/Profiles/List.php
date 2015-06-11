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

class Ho_Recurring_Block_Customer_Profiles_List extends Mage_Core_Block_Template
{
    /**
     * @return Ho_Recurring_Model_Resource_Profile_Collection
     */
    public function getProfiles()
    {
        $customerId = Mage::getSingleton('customer/session')->getCustomer()->getId();

        $profiles = Mage::getModel('ho_recurring/profile')->getCollection()
            ->addFieldToFilter('main_table.customer_id', $customerId)
            ->addBillingAgreementToSelect();

        return $profiles;
    }

    /**
     * @param Ho_Recurring_Model_Profile $profile
     * @return string
     */
    public function getViewUrl($profile)
    {
        return $this->getUrl('ho_recurring/customer/view', array('profile_id' => $profile->getId()));
    }

    /**
     * @param Ho_Recurring_Model_Profile $profile
     * @return string
     */
    public function getAgreementUrl($profile)
    {
        $agreementId = $profile->getBillingAgreementId();

        return $this->getUrl('sales/billing_agreement/view', array('agreement' => $agreementId));
    }
}
