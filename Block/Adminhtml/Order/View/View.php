<?php
/**
 * @copyright 2017 Sapient
 */
namespace Sapient\Worldpay\Block\Adminhtml\Order\View;

class View extends \Magento\Backend\Block\Template
{
    /**
     * @var \Magento\Framework\Registry
     */
    protected $registry;
    /**
     * @var \Sapient\Worldpay\Model\WorldpaymentFactory
     */
    protected $_worldpaymentFactory;

    /**
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Sapient\Worldpay\Model\WorldpaymentFactory $worldpaymentFactory
     * @param \Sapient\Worldpay\Helper\Multishipping $multishippingHelper
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Sapient\Worldpay\Model\WorldpaymentFactory $worldpaymentFactory,
        \Sapient\Worldpay\Helper\Multishipping $multishippingHelper,
        array $data = []
    ) {
        $this->registry = $registry;
        $this->_worldpaymentFactory= $worldpaymentFactory;
        $this->multishippingHelper = $multishippingHelper;
        parent::__construct($context, $data);
    }
     /**
      * Retrieve order object from registry
      *
      * @return object
      */
    protected function _getOrder()
    {

        $order = $this->registry->registry('current_order');
        return $order;
    }

    /**
     * Retrieve Worldpay Payment Detail
     *
     * @return object
     */
    public function getWorldPaymentsDetails()
    {
        $order=$this->_getOrder();
        $order_id=$order->getIncrementId();
        $wpp = $this->_worldpaymentFactory->create();
        $item = $wpp->loadByPaymentId($order_id);
        return $item;
    }

    /**
     * Retrieve payment method from order
     *
     * @return String
     */
    public function getPaymentMethod()
    {
        return  $this->_getOrder()->getPayment()->getMethod();
    }

    /**
     * Check if order is placed through WorldPay Payment
     *
     * @return Boolean
     */
    public function isWorldpayPayment()
    {
        $paymentMethod= $this->getPaymentMethod();
        if ($paymentMethod=='worldpay_cc' || $paymentMethod=='worldpay_apm'
                || $paymentMethod=='worldpay_moto' || $paymentMethod=='worldpay_cc_vault'
                || $paymentMethod=='worldpay_wallets' || $paymentMethod=='worldpay_paybylink') {
            return true;
        }
        return false;
    }

    /**
     * Retrieve multishipping order id's
     *
     * @return string
     */
    public function getMultishippingOrderIds()
    {
        $order = $this->_getOrder();
        $quote_id = $order->getQuoteId();
        $inc_id = $order->getIncrementId();
        $multishippingCollections = $this->multishippingHelper->getMultishippingCollections($quote_id, $inc_id);
        $multishipping_orders = '';
        foreach ($multishippingCollections as $multishippingCollection) {
            $ms_order_id = $multishippingCollection->getOrderId();
            $multishipping_orders .= ", $ms_order_id";
        }
        $multishipping_orders = substr($multishipping_orders, 1);
        return $multishipping_orders;
    }
}
