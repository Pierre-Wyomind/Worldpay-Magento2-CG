<?php
/**
 * @copyright 2017 Sapient
 */
 namespace Sapient\Worldpay\Controller\Paybylink;

 use Magento\Framework\View\Result\PageFactory;
 use Magento\Framework\App\Action\Context;
 use Sapient\Worldpay\Model\Payment\StateResponse;

/**
 * if got notification to get cancel order from worldpay then redirect to  cart page and display the notice
 */

class Cancel extends \Magento\Framework\App\Action\Action
{
    /**
     * @var Magento\Framework\View\Result\PageFactory
     */
    protected $pageFactory;

    /**
     * Constructor
     *
     * @param Context $context
     * @param PageFactory $pageFactory
     * @param \Sapient\Worldpay\Model\Order\Service $orderservice
     * @param \Sapient\Worldpay\Model\Checkout\Service $checkoutservice
     * @param \Sapient\Worldpay\Model\Payment\Service $paymentservice
     * @param \Sapient\Worldpay\Model\Request\AuthenticationService $authenticatinservice
     * @param \Sapient\Worldpay\Model\Payment\StateResponse $paymentStateResponse
     * @param \Sapient\Worldpay\Logger\WorldpayLogger $wplogger
     * @param \Sapient\Worldpay\Model\Payment\WpResponse $wpresponse
     * @param \Magento\Sales\Model\Order $orderItemsDetails
     */
    public function __construct(
        Context $context,
        PageFactory $pageFactory,
        \Sapient\Worldpay\Model\Order\Service $orderservice,
        \Sapient\Worldpay\Model\Checkout\Service $checkoutservice,
        \Sapient\Worldpay\Model\Payment\Service $paymentservice,
        \Sapient\Worldpay\Model\Request\AuthenticationService $authenticatinservice,
        \Sapient\Worldpay\Model\Payment\StateResponseFactory $paymentStateResponse,
        \Sapient\Worldpay\Logger\WorldpayLogger $wplogger,
        \Sapient\Worldpay\Model\Payment\WpResponse $wpresponse,
        \Magento\Sales\Model\Order $orderItemsDetails
    ) {
        $this->pageFactory = $pageFactory;
        $this->orderservice = $orderservice;
        $this->wplogger = $wplogger;
        $this->checkoutservice = $checkoutservice;
        $this->paymentservice = $paymentservice;
        $this->authenticatinservice = $authenticatinservice;
        $this->paymentStateResponse = $paymentStateResponse;
        $this->wpresponse = $wpresponse;
        $this->orderItemsDetails = $orderItemsDetails;
        return parent::__construct($context);
    }
    /**
     * Execute
     *
     * @return string
     */
    public function execute()
    {
        $this->wplogger->info('worldpay returned cancel Pay By link url');
        $params = $this->getRequest()->getParams();
        $this->wplogger->info('Params :  '.json_encode($params, true));
        if (!empty($params['orderKey'])) {
            preg_match('/\^(\d+)-/', $params['orderKey'], $matches);
            $order = $this->orderItemsDetails->loadByIncrementId($matches[1]);
            if ($order->getId()) {
                $magentoorder = $order;
                $notice = $this->_getCancellationNoticeForOrder($order);
                $this->messageManager->addNotice($notice);
                $params = $this->getRequest()->getParams();
                if ($this->authenticatinservice->requestAuthenticated($params)) {
                    if (isset($params['orderKey'])) {
                        $this->_applyPaymentUpdate($this->wpresponse->createFromCancelledResponse($params), $order);
                    }
                }
                return $this->resultRedirectFactory->create()->setPath('checkout/cart', ['_current' => true]);
            } else {
                $this->wplogger->info('Order not found.Redirecting to checkout cart page');
                return $this->resultRedirectFactory->create()->setPath('checkout/cart', ['_current' => true]);
            }

        } else {
            $this->wplogger->info('Order not found.Redirecting to checkout cart page');
            return $this->resultRedirectFactory->create()->setPath('checkout/cart', ['_current' => true]);
        }
    }
    /**
     * Get Cancellation NoticeFor Order
     *
     * @param array $order
     * @return string
     */
    private function _getCancellationNoticeForOrder($order)
    {

        $incrementId = $order->getIncrementId();
        $message = $incrementId === null
           ? __('Order Cancelled')
           : __('Order #'. $incrementId.' Cancelled');

        return $message;
    }
    /**
     * Apply Payment Update
     *
     * @param string $paymentState
     * @param array $order
     * @return string
     */
    private function _applyPaymentUpdate($paymentState, $order)
    {
        try {
            $this->_paymentUpdate = $this->paymentservice
                       ->createPaymentUpdateFromWorldPayResponse($paymentState);
            $this->_paymentUpdate->apply($order->getPayment(), $order);
        } catch (\Exception $e) {
            $this->wplogger->error($e->getMessage());
        }
    }
}
