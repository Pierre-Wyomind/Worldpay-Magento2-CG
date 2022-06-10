<?php
/**
 * @copyright 2017 Sapient
 */
namespace Sapient\Worldpay\Model\XmlBuilder;

use Sapient\Worldpay\Model\XmlBuilder\Config\ThreeDSecureConfig;

/**
 * Build xml for RedirectOrder request
 */
class SamsungPayOrder
{
    public const ROOT_ELEMENT = <<<EOD
<?xml version="1.0" encoding="UTF-8"?><!DOCTYPE paymentService PUBLIC '-//WorldPay/DTD WorldPay PaymentService v1//EN'
        'http://dtd.worldpay.com/paymentService_v1.dtd'> <paymentService/>
EOD;

    /**
     * [$merchantCode description]
     * @var [type]
     */
    private $merchantCode;
    /**
     * [$orderCode description]
     * @var [type]
     */
    private $orderCode;
    /**
     * [$orderDescription description]
     * @var [type]
     */
    private $orderDescription;
    /**
     * [$currencyCode description]
     * @var [type]
     */
    private $currencyCode;
    /**
     * [$amount description]
     * @var [type]
     */
    private $amount;
    /**
     * [$paymentType description]
     * @var [type]
     */
    private $paymentType;
    /**
     * [$exponent description]
     * @var [type]
     */
    private $exponent;
    
    /**
     * [build description]
     *
     * @param  [type] $merchantCode     [description]
     * @param  [type] $orderCode        [description]
     * @param  [type] $orderDescription [description]
     * @param  [type] $currencyCode     [description]
     * @param  [type] $amount           [description]
     * @param  [type] $paymentType      [description]
     * @param  [type] $shopperEmail     [description]
     * @param  [type] $data             [description]
     * @param  [type] $exponent         [description]
     * @return [type]                   [description]
     */
    public function build(
        $merchantCode,
        $orderCode,
        $orderDescription,
        $currencyCode,
        $amount,
        $paymentType,
        $shopperEmail,
        $data,
        $exponent
    ) {
        $this->merchantCode = $merchantCode;
        $this->orderCode = $orderCode;
        $this->orderDescription = $orderDescription;
        $this->currencyCode = $currencyCode;
        $this->amount = $amount;
        $this->paymentType = $paymentType;
        $this->shopperEmail = $shopperEmail;
       
        $this->data = $data;

        $this->exponent = $exponent;
        $xml = new \SimpleXMLElement(self::ROOT_ELEMENT);
        $xml['merchantCode'] = $this->merchantCode;
        $xml['version'] = '1.4';

        $submit = $this->_addSubmitElement($xml);
        $this->_addOrderElement($submit);
        return $xml;
    }

    /**
     * Add submit tag to xml
     *
     * @param SimpleXMLElement $xml
     * @return SimpleXMLElement
     */
    private function _addSubmitElement($xml)
    {
        return $xml->addChild('submit');
    }

    /**
     * Add order tag to xml
     *
     * @param SimpleXMLElement $submit
     * @return SimpleXMLElement $order
     */
    private function _addOrderElement($submit)
    {
        $order = $submit->addChild('order');
        $order['orderCode'] = $this->orderCode;
        $order['shopperLanguageCode'] = "en";

        $this->_addDescriptionElement($order);
        $this->_addAmountElement($order);
        $this->_addPaymentDetailsElement($order);
        $this->_addShopperElement($order);
        return $order;
    }

    /**
     * Add description tag to xml
     *
     * @param SimpleXMLElement $order
     */
    private function _addDescriptionElement($order)
    {
        $description = $order->addChild('description');
        $this->_addCDATA($description, $this->orderDescription);
    }

    /**
     * Add amount tag to xml
     *
     * @param SimpleXMLElement $order
     */
    private function _addAmountElement($order)
    {
        $amountElement = $order->addChild('amount');
        $amountElement['currencyCode'] = $this->currencyCode;
        $amountElement['exponent'] = $this->exponent;
        $amountElement['value'] = $this->_amountAsInt($this->amount);
    }

    /**
     * Add PaymentDetails and its child tag to xml
     *
     * @param SimpleXMLElement $order
     */
    private function _addPaymentDetailsElement($order)
    {
        $paymentDetails = $order->addChild('paymentDetails');
        $paymentType = $paymentDetails->addChild($this->paymentType);
        $paymentThreeDS = $paymentType->addChild('ThreeDS');
        $paymentThreeDS->addChild('data', $this->data);
        $paymentThreeDS->addChild('version', 100);
    }

    /**
     * Add shopper and its child tag to xml
     *
     * @param SimpleXMLElement $order
     */
    private function _addShopperElement($order)
    {
        $shopper = $order->addChild('shopper');
        $shopper->addChild('shopperEmailAddress', $this->shopperEmail);
    }

    /**
     * Add cdata element
     *
     * @param SimpleXMLElement $element
     * @param string $content
     */
    private function _addCDATA($element, $content)
    {
        $node = dom_import_simplexml($element);
        $no   = $node->ownerDocument;
        $node->appendChild($no->createCDATASection($content));
    }

    /**
     * Retrieve amount value
     *
     * @param float $amount
     * @return int
     */
    private function _amountAsInt($amount)
    {
        return round($amount, $this->exponent, PHP_ROUND_HALF_EVEN) * pow(10, $this->exponent);
    }
}
