<?php
/**
 * @copyright 2017 Sapient
 */
namespace Sapient\Worldpay\Model\Token;

use Sapient\Worldpay\Model\SavedToken;
use Sapient\Worldpay\Model\Token\StateInterface as TokenStateInterface;
use Magento\Vault\Model\CreditCardTokenFactory;
use Magento\Sales\Api\Data\OrderPaymentExtensionInterfaceFactory;
use Magento\Vault\Model\Ui\VaultConfigProvider;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Api\PaymentTokenManagementInterface;
use Magento\Framework\Encryption\EncryptorInterface;

/**
 * Communicate with WP server and gives back meaningful answer object
 */
class WorldpayToken
{
    /**
     * [$paymentTokenManagement description]
     * @var [type]
     */
    protected $paymentTokenManagement;
    /**
     * [$encryptor description]
     * @var [type]
     */
    protected $encryptor;
    /**
     * @var \Sapient\Worldpay\Model\Recurring\Subscription\TransactionsFactory
     */
    private $transactionsFactory;

    /**
     * Constructor
     *
     * @param SavedToken $savedtoken
     * @param Sapient\Worldpay\Logger\WorldpayLogger $wplogger
     * @param Sapient\Worldpay\Model\Recurring\Subscription\TransactionsFactory $transactionsFactory
     * @param OrderPaymentExtensionInterfaceFactory $paymentExtensionFactory
     * @param CreditCardTokenFactory $paymentTokenFactory
     * @param PaymentTokenManagementInterface $paymentTokenManagement
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        SavedToken $savedtoken,
        \Sapient\Worldpay\Logger\WorldpayLogger $wplogger,
        \Sapient\Worldpay\Model\Recurring\Subscription\TransactionsFactory $transactionsFactory,
        OrderPaymentExtensionInterfaceFactory $paymentExtensionFactory,
        CreditCardTokenFactory $paymentTokenFactory,
        PaymentTokenManagementInterface $paymentTokenManagement,
        EncryptorInterface $encryptor
    ) {
        $this->savedtoken = $savedtoken;
        $this->wplogger = $wplogger;
        $this->paymentTokenFactory = $paymentTokenFactory;
        $this->paymentExtensionFactory = $paymentExtensionFactory;
        $this->paymentTokenManagement = $paymentTokenManagement;
        $this->encryptor = $encryptor;
        $this->transactionFactory = $transactionsFactory;
    }

    /**
     * Update token of the given customer
     *
     * @param Sapient\WorldPay\Model\Token $token
     * @param \Magento\Customer\Model\Customer $customer
     * @throws Sapient\WorldPay\Model\Token\AccessDeniedException
     */
    public function updateTokenByCustomer(SavedToken $token, \Magento\Customer\Model\Customer $customer)
    {
        $this->_assertAccessForToken($token, $customer);
        $token->save();
    }

    /**
     * Check access for token
     *
     * @param Sapient\WorldPay\Model\Token $token
     * @param \Magento\Customer\Model\Customer $customer
     * @throws Sapient\WorldPay\Model\Token\AccessDeniedException
     */
    private function _assertAccessForToken(SavedToken $token, \Magento\Customer\Model\Customer $customer)
    {
        $msg = 'Access denied: token "%s" is not owned by customer "%d"';
        if (!$this->hasCustomerAccessForToken($token, $customer)) {
            throw new \AccessDeniedException(
                sprintf($msg, $token->getTokenCode(), $customer->getId())
            );
        }
    }

    /**
     * Tells if customer has any tokenised card
     *
     * @param Sapient\WorldPay\Model\Token $token
     * @param \Magento\Customer\Model\Customer $customer
     * @return bool
     */
    public function hasCustomerAccessForToken(SavedToken $token, \Magento\Customer\Model\Customer $customer)
    {
        return $token->getCustomerId() == $customer->getId();
    }

    /**
     * Delete token from a given customer
     *
     * @param Sapient\WorldPay\Model\Token $token
     * @param \Magento\Customer\Model\Customer $customer
     * @throws Sapient\WorldPay\Model\Token\AccessDeniedException
     */
    public function deleteTokenByCustomer(SavedToken $token, \Magento\Customer\Model\Customer $customer)
    {
        $this->_assertAccessForToken($token, $customer);
        $token->delete();
    }
    
    /**
     * Update token of the given customer
     *
     * @param Sapient\WorldPay\Model\Token $tokenState
     * @param [type] $paymentObject
     * @param \Magento\Customer\Model\Customer $customerId
     * @throws Sapient\WorldPay\Model\Token\AccessDeniedException
     */
    public function updateOrInsertToken(TokenStateInterface $tokenState, $paymentObject, $customerId = null)
    {
        if (!$tokenState->getTokenCode()) {
            return;
        }

        $tokenModel = $this->savedtoken;
        $tokenModel->loadByTokenCode($tokenState->getTokenCode());
        $tokenModel->getResource()->beginTransaction();
        $authenticatedShopperId = $tokenState->getAuthenticatedShopperId();
        $tokenScope = 'shopper';
        if (!$authenticatedShopperId) {
            $authenticatedShopperId = $customerId;
            $tokenScope = 'merchant';
        }
        $this->wplogger->info('########## Received notification customerId ##########');
        $this->wplogger->info($authenticatedShopperId);
        
        try {
            $binNumber = $tokenState->getBin();
            $transactionIdentifier = $tokenState->getTransactionIdentifier();
            $tokenModel->setTokenCode($tokenState->getTokenCode());
            $tokenModel->setTokenReason($tokenState->getTokenReason());
            $tokenModel->setTokenExpiryDate($tokenState->getTokenExpiryDate()->format('Y-m-d'));
            $tokenModel->setCardNumber($tokenState->getObfuscatedCardNumber());
            $tokenModel->setCardholderName($tokenState->getCardholderName());
            $tokenModel->setMethod(str_replace(["_CREDIT","_DEBIT","_ELECTRON"], "", $tokenState->getPaymentMethod()));
            $tokenModel->setCardBrand($tokenState->getCardBrand());
            $tokenModel->setCardSubBrand($tokenState->getCardSubBrand());
            $tokenModel->setCardIssuerCountryCode($tokenState->getCardIssuerCountryCode());
            $tokenModel->setCardExpiryMonth($tokenState->getCardExpiryMonth());
            $tokenModel->setCardExpiryYear($tokenState->getCardExpiryYear());
            $tokenModel->setMerchantCode($tokenState->getMerchantCode());
            $tokenModel->setAuthenticatedShopperId($authenticatedShopperId);
            $tokenModel->setCustomerId($authenticatedShopperId);
            if ($transactionIdentifier) {
                $tokenModel->setTransactionIdentifier($tokenState->getTransactionIdentifier());
            }
            if ($binNumber) {
                $tokenModel->setBinNumber($tokenState->getBin());
            }
            $tokenModel->setTokenType($tokenScope);
            $tokenModel->save();
            $tokenModel->getResource()->commit();
            $this->saveTokenDataToTransactions($tokenState->getOrderCode(), $tokenState->getTokenCode());
            if ('worldpay_cc' == $paymentObject->getMethod()) {
                // vault and instant purchase configuration goes here
                $this->_updateToVault($tokenState, $paymentObject, $authenticatedShopperId);
            }
        } catch (\Exception $e) {
            $tokenEvent = $tokenState->getTokenEvent();
            if ($tokenEvent == 'CONFLICT') {
                $this->wplogger->error('Duplicate Entry, This card number is already saved.');
            } else {
                $this->wplogger->error($e->getMessage());
            }
            $tokenModel->getResource()->rollBack();
            throw $e;
        }
    }
    /**
     * [saveTokenDataToTransactions description]
     *
     * @param  [type] $worldpayOrderId [description]
     * @param  [type] $getTokenCode    [description]
     * @return [type]                  [description]
     */
    public function saveTokenDataToTransactions($worldpayOrderId, $getTokenCode)
    {
        $tokenModel = $this->savedtoken;
        $tokenModel->loadByTokenCode($getTokenCode);
        $tokenId = $tokenModel->getId();
        $orderId = explode('-', $worldpayOrderId);
        $order_increment_id = $orderId[0];
        $transactions = $this->transactionFactory->create()->load($order_increment_id, 'original_order_increment_id');
        $transactions->setData('worldpay_order_id', $worldpayOrderId);
        $transactions->setData('worldpay_token_id', $tokenId);
        $transactions->setData('original_order_increment_id', $orderId[0]);
        $transactions->save();
    }
    /**
     * [_updateToVault description]
     *
     * @param  TokenStateInterface $tokenState             [description]
     * @param  [type]              $paymentObject          [description]
     * @param  [type]              $authenticatedShopperId [description]
     * @return [type]                                      [description]
     */
    private function _updateToVault(TokenStateInterface $tokenState, $paymentObject, $authenticatedShopperId)
    {
        $paymentToken = $this->getVaultPaymentToken($tokenState);
        if (null !== $paymentToken) {
            $extensionAttributes = $this->getExtensionAttributes($paymentObject);
            $extensionAttributes->setVaultPaymentToken($paymentToken);
        }
        // saves payment token manually.
        // set all payment token attributes.
        if ($paymentToken->getEntityId() !== null) {
            $this->paymentTokenManagement->addLinkToOrderPayment(
                $paymentToken->getEntityId(),
                $paymentObject->getEntityId()
            );
            return $this;
        }
        $paymentToken->setCustomerId($authenticatedShopperId);
        $paymentToken->setIsActive(true);
        $paymentToken->setPaymentMethodCode($paymentObject->getMethod());
        $additionalInformation = $paymentObject->getAdditionalInformation();
        $additionalInformation[VaultConfigProvider::IS_ACTIVE_CODE] = true;
        $paymentObject->setAdditionalInformation($additionalInformation);
        $paymentToken->setIsVisible(true);
        $paymentToken->setPublicHash($this->generatePublicHash($paymentToken));
        $this->paymentTokenManagement->saveTokenWithPaymentLink($paymentToken, $paymentObject);
        $extensionAttributes->setVaultPaymentToken($paymentToken);
    }
    /**
     * [generatePublicHash description]
     *
     * @param  PaymentTokenInterface $paymentToken [description]
     * @return [type]                              [description]
     */
    protected function generatePublicHash(PaymentTokenInterface $paymentToken)
    {
        $hashKey = $paymentToken->getGatewayToken();
        if ($paymentToken->getCustomerId()) {
            $hashKey = $paymentToken->getCustomerId();
        }
        $hashKey .= $paymentToken->getPaymentMethodCode()
            . $paymentToken->getType()
            . $paymentToken->getTokenDetails();

        return $this->encryptor->getHash($hashKey);
    }
    /**
     * [getExtensionAttributes description]
     *
     * @param  [type] $payment [description]
     * @return [type]          [description]
     */
    private function getExtensionAttributes($payment)
    {
        $extensionAttributes = $payment->getExtensionAttributes();
        if (null === $extensionAttributes) {
            $extensionAttributes = $this->paymentExtensionFactory->create();
            $payment->setExtensionAttributes($extensionAttributes);
        }
        return $extensionAttributes;
    }
    /**
     * [getVaultPaymentToken description]
     *
     * @param  TokenStateInterface $tokenElement [description]
     * @return [type]                            [description]
     */
    protected function getVaultPaymentToken(TokenStateInterface $tokenElement)
    {
        // Check token existing in gateway response
        $token = $tokenElement->getTokenCode();
        if (empty($token)) {
            return null;
        }

        /** @var PaymentTokenInterface $paymentToken */
        $paymentToken = $this->paymentTokenFactory->create();
        $paymentToken->setGatewayToken($token);
        $paymentToken->setExpiresAt($this->getExpirationDate($tokenElement));

        $paymentToken->setTokenDetails($this->convertDetailsToJSON([
            'type' => str_replace("_CREDIT", "", $tokenElement->getPaymentMethod()),
            'maskedCC' => $this->getLastFourNumbers($tokenElement->getObfuscatedCardNumber()),
            'expirationDate'=> $this->getExpirationMonthAndYear($tokenElement)
        ]));
        return $paymentToken;
    }

    /**
     * Retrieve Last digits
     *
     * @param string $number
     * @return string
     */
    public function getLastFourNumbers($number)
    {
        return substr($number, -4);
    }

    /**
     * Retrieve expiry MM/YY
     *
     * @param TokenStateInterface $tokenElement
     * @return string
     */
    public function getExpirationMonthAndYear(TokenStateInterface $tokenElement)
    {
        return $tokenElement->getCardExpiryMonth().'/'.$tokenElement->getCardExpiryYear();
    }
    /**
     * [getExpirationDate description]
     *
     * @param  TokenStateInterface $tokenElement [description]
     * @return [type]                            [description]
     */
    private function getExpirationDate(TokenStateInterface $tokenElement)
    {
        return $tokenElement->getTokenExpiryDate()->format('Y-m-d H:i:s');
    }

    /**
     * Retrieve Token code
     *
     * @param array $details
     * @return \Zend\Json\Json
     */
    private function convertDetailsToJSON($details)
    {
        $json = \Zend_Json::encode($details);
        return $json ? $json : '{}';
    }
}
