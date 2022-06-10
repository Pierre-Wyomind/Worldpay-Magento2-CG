<?php
/**
 * @copyright 2017 Sapient
 */
namespace Sapient\Worldpay\Model\PaymentMethods;

/**
 * WorldPay CreditCards class extended from WorldPay Abstract Payment Method.
 */
class CreditCards extends \Sapient\Worldpay\Model\PaymentMethods\AbstractMethod
{
    /**
     * Payment code
     * @var string
     */
    protected $_code = 'worldpay_cc';
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
    protected $_canUseInternal = false;
    /**
     * @var string
     */
    protected $_canUseCheckout = true;

    /**
     * Authorize payment abstract method
     *
     * @param \Magento\Framework\DataObject|InfoInterface $payment
     * @param float $amount
     * @return $this
     */
    public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $this->_wplogger->info('WorldPay Payment CreditCards Authorise Method Executed:');
        parent::authorize($payment, $amount);
        return $this;
    }
    
    /**
     * GetAuthorisationService
     *
     * @param string|int $storeId
     */
    public function getAuthorisationService($storeId)
    {
        $integrationModel = $this->worlpayhelper->getCcIntegrationMode($storeId);
        
        $checkoutpaymentdata = $this->paymentdetailsdata;
        if (!empty($checkoutpaymentdata['additional_data']['isSavedCardPayment'])
                && !empty($checkoutpaymentdata['additional_data']['tokenCode'])
                && $integrationModel == 'direct') {
            return $this->tokenservice;
        }
        if ($this->_isRedirectIntegrationModeEnabled($storeId)) {
            if ($this->_isEmbeddedIntegrationModeEnabled($storeId)) {
                return $this->hostedpaymentpageservice;
            }
            return $this->redirectservice;
        }
        return $this->directservice;
    }
    
    /**
     * Method to check if integration mode is redirect
     *
     * @param int $storeId
     * @return bool
     */
    private function _isRedirectIntegrationModeEnabled($storeId)
    {
        $integrationModel = $this->worlpayhelper->getCcIntegrationMode($storeId);

        return $integrationModel === 'redirect';
    }

    /**
     * Method to check if Worldpay is enabled
     *
     * @param \Magento\Quote\Api\Data\CartInterface $quote
     * @return bool
     */
    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {

        if ($this->worlpayhelper->isWorldPayEnable() && $this->worlpayhelper->isCreditCardEnabled()) {
            return true;
        }
        return false;
    }

    /**
     * Method to check EmbeddedIntegrationMode
     *
     * @param int $storeId
     * @return bool
     */
    private function _isEmbeddedIntegrationModeEnabled($storeId)
    {
        return $this->worlpayhelper->isIframeIntegration($storeId);
    }

    /**
     * Method to return payment title
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
            return $this->worlpayhelper->getCcTitle();
        }
    }
}
