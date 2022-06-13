<?php

//error_reporting(0);
/**
 * @copyright 2017 Sapient
 */

namespace Sapient\Worldpay\Controller\Samsungpay;

use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Exception;
use Magento\Framework\Controller\ResultFactory;

class CallBack extends \Magento\Framework\App\Action\Action
{
    /**
     * @var $orderFactory
     */
    protected $orderFactory;
    /**
     * @var $worldpayHelper
     */
    protected $worldpayHelper;
    /**
     * @var $_checkoutSession
     */
    protected $_checkoutSession;
    /**
     * @var $orderManagement
     */
    protected $orderManagement;

    /**
     * Constructor
     *
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param \Sapient\Worldpay\Logger\WorldpayLogger $wplogger
     * @param \Sapient\Worldpay\Model\Payment\Service $paymentservice
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\App\Request\Http $request
     * @param \Sapient\Worldpay\Model\Request\PaymentServiceRequest $paymentservicerequest
     * @param \Magento\Backend\Model\Auth\Session $authSession
     * @param \Sapient\Worldpay\Helper\Data $worldpayHelper
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Sales\Api\OrderManagementInterface $orderManagement
     * @param \Sapient\Worldpay\Model\WorldpaymentFactory $worldpaymentFactory
     * @param \Sapient\Worldpay\Helper\CurlHelper $curlHelper
     */

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        \Sapient\Worldpay\Logger\WorldpayLogger $wplogger,
        \Sapient\Worldpay\Model\Payment\Service $paymentservice,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\App\Request\Http $request,
        \Sapient\Worldpay\Model\Request\PaymentServiceRequest $paymentservicerequest,
        \Magento\Backend\Model\Auth\Session $authSession,
        \Sapient\Worldpay\Helper\Data $worldpayHelper,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Api\OrderManagementInterface $orderManagement,
        \Sapient\Worldpay\Model\WorldpaymentFactory $worldpaymentFactory,
        \Sapient\Worldpay\Helper\CurlHelper $curlHelper
    ) {
        parent::__construct($context);
        $this->wplogger = $wplogger;
        $this->paymentservice = $paymentservice;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->scopeConfig = $scopeConfig;
        $this->request = $request;
        $this->_paymentservicerequest = $paymentservicerequest;
        $this->_authSession = $authSession;
        $this->worldpayHelper = $worldpayHelper;
        $this->orderFactory = $orderFactory;
        $this->_checkoutSession = $checkoutSession;
        $this->orderManagement = $orderManagement;
        $this->_worldpaymentFactory= $worldpaymentFactory;
        $this->curlHelper = $curlHelper;
    }
    /**
     * Execute
     *
     * @return string
     */

    public function execute()
    {
        $order = $this->_checkoutSession->getLastRealOrder();
        $orderDetails = $order->getData();
        $refId = $this->request->getParam('ref_id');
        //$orderId = $this->request->getParam('orderId');
        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        
        $serviceId = $this->scopeConfig->
                getValue('worldpay/wallets_config/samsung_pay_wallets_config/service_id', $storeScope);
        
        $orderDescription = $this->scopeConfig->
                getValue('worldpay/wallets_config/samsung_pay_wallets_config/samsung_order_description', $storeScope);
        
        $merchantCode = $this->scopeConfig->
                getValue('worldpay/general_config/merchant_code', $storeScope);
              
        $environmentMode = $this->scopeConfig->
                getValue('worldpay/general_config/environment_mode', $storeScope);

        if ($environmentMode == 'Test Mode') {
            $serviceUrl = "https://api-ops.stg.mpay.samsung.com/ops/v1/transactions/paymentCredentials/"
                    . $refId . '?serviceId=' . $serviceId;
        } else {
            $serviceUrl = "https://api-ops.mpay.samsung.com/ops/v1/transactions/paymentCredentials/"
                    . $refId . '?serviceId=' . $serviceId;
        }

        $orderCode = $order->getIncrementId();
        $orderId = $order->getId();

        $wpp = $this->_worldpaymentFactory->create();
        $item = $wpp->loadByPaymentId($orderCode);
      
        $worldpayOrderId = $item->getWorldpayOrderId();
        $this->_checkoutSession->setauthenticatedOrderId($orderCode);

        if ($refId != '') {
            try {
                $json = $this->curlHelper->sendGetCurlRequest(
                    $serviceUrl,
                    [
                        CURLOPT_ENCODING => "",
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1
                    ],
                    [
                        "serviceId"=> $serviceId,
                        "Expect"=> ""
                    ]
                );

                $response = json_decode($json, true);

                if ($response['resultMessage'] == 'SUCCESS') {

                    //response is success, collect the order details and
                    //send request to worldpay with this reponse from samsung
                    
                    //$order = $this->orderFactory->create()->loadByIncrementId($orderId);
                    //$orderDetails = $order->getData();
                    
                    $currencyCode = $this->_authSession->getCurrencyCode();
                    $exponent = $this->worldpayHelper->getCurrencyExponent($currencyCode);

                    $samsungPayOrderParams = [];
                    
                    $samsungPayOrderParams['orderCode'] = $worldpayOrderId;

                    $samsungPayOrderParams['merchantCode'] = $merchantCode;
                    $samsungPayOrderParams['orderDescription'] = $orderDescription;
                    $samsungPayOrderParams['currencyCode'] = $currencyCode;
                    $samsungPayOrderParams['amount'] = $orderDetails['grand_total'];
                    $samsungPayOrderParams['paymentType'] = 'SAMSUNGPAY-SSL';
                    $samsungPayOrderParams['shopperEmail'] = $orderDetails['customer_email'];
                    $samsungPayOrderParams['exponent'] = $exponent;
                    $samsungPayOrderParams['data'] = $response['3DS']['data'];
                    $response = $this->_paymentservicerequest->samsungPayOrder($samsungPayOrderParams);
                    $paymentService = new \SimpleXmlElement($response);
                    $lastEvent = $paymentService->xpath('//lastEvent');

                    if ($lastEvent[0] == 'AUTHORISED') {
                        $resultRedirect = $this->resultRedirectFactory->create();
                        $resultRedirect->setPath('worldpay/wallets/success');
                        $this->_checkoutSession->unsauthenticatedOrderId();
                        return $resultRedirect;
                    } else {
                        $resultRedirect = $this->resultRedirectFactory->create();
                        $resultRedirect->setPath('worldpay/Redirectresult/cancel');
                        $this->orderManagement->cancel($orderId);
                        $this->_checkoutSession->restoreQuote();
                        return $resultRedirect;
                    }
                }
            } catch (Exception $e) {
                $this->wplogger->error($e->getMessage());
            }
        } else {
             $this->orderManagement->cancel($orderId);
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath('worldpay/Redirectresult/cancel');
            $this->_checkoutSession->restoreQuote();
            return $resultRedirect;
        }
    }
}
