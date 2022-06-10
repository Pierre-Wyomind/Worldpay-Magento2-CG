<?php
/**
 * @copyright 2017 Sapient
 */
namespace Sapient\Worldpay\Model\PaymentMethods;

/**
 * WorldPay CreditCards class extended from WorldPay Abstract Payment Method.
 */
class Moto extends \Sapient\Worldpay\Model\PaymentMethods\CreditCards
{
    /**
     * @var string
     */
    protected $_code = 'worldpay_moto';
    /**
     * @var string
     */
    protected $_isGateway = true;
    /**
     * @var string
     */
    protected $_canAuthorize = true;
    /**
     * @var string
     */
    protected $_canUseInternal = true;
    /**
     * @var string
     */
    protected $_canUseCheckout = false;
    /**
     * @var string
     */
    protected $_formBlockType = \Sapient\Worldpay\Block\Form\Card::class;

    /**
     * Method to get payment method types
     *
     * @return string
     */
    public function getPaymentMethodsType()
    {
        return 'worldpay_cc';
    }

    /**
     * Method to get payment title
     *
     * @return string
     */
    public function getTitle()
    {
        if ($order = $this->registry->registry('current_order')) {
            return $this->worlpayhelper->getPaymentTitleForOrders($order, $this->_code, $this->worldpaypayment);
        } elseif ($invoice = $this->registry->registry('current_invoice')) {
            $order = $this->worlpayhelper->getOrderByOrderId($invoice->getOrderId());
            return $this->worlpayhelper->getPaymentTitleForOrders($order, $this->_code, $this->worldpaypayment);
        } elseif ($creditMemo = $this->registry->registry('current_creditmemo')) {
            $order = $this->worlpayhelper->getOrderByOrderId($creditMemo->getOrderId());
            return $this->worlpayhelper->getPaymentTitleForOrders($order, $this->_code, $this->worldpaypayment);
        } else {
            return $this->worlpayhelper->getMotoTitle();
        }
    }
    
    /**
     * GetAuthorisationService
     *
     * @param string $storeId
     */
    public function getAuthorisationService($storeId)
    {
        $checkoutpaymentdata = $this->paymentdetailsdata;
        if (($checkoutpaymentdata['additional_data']['cc_type'] == 'cc_type')
                && empty($checkoutpaymentdata['additional_data']['tokenCode'])) {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __('Saved cards not found')
                );
        }
        if (!empty($checkoutpaymentdata['additional_data']['tokenCode'])
                && !$this->_isRedirectIntegrationModeEnabled($storeId)) {
            return $this->tokenservice;
        }
        if ($this->_isRedirectIntegrationModeEnabled($storeId)) {
            return $this->motoredirectservice;
        }
        return $this->directservice;
    }
    
    /**
     * Method to check if Worldpay is enabled
     *
     * @param \Magento\Quote\Api\Data\CartInterface $quote
     * @return bool
     */
    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {

        if ($this->worlpayhelper->isWorldPayEnable() && $this->worlpayhelper->isMotoEnabled()) {
            return true;
        }
        return false;
    }

    /**
     * Check if integration mode is redirect
     *
     * @param int $storeId
     * @return bool
     */
    private function _isRedirectIntegrationModeEnabled($storeId)
    {
        $integrationModel = $this->worlpayhelper->getCcIntegrationMode($storeId);

        return $integrationModel === 'redirect';
    }
}
