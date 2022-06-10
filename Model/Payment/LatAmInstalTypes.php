<?php
/**
 * @copyright 2017 Sapient
 */
namespace Sapient\Worldpay\Model\Payment;

use Sapient\Worldpay\Api\LatAmInstalInterface;

class LatAmInstalTypes implements LatAmInstalInterface
{
    /**
     * Core store config
     *
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;
    
    /**
     * @var $configHelper
     */
    protected $configHelper;
    /**
     * Constructor
     *
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Sapient\Worldpay\Helper\Instalmentconfig $configHelper
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Sapient\Worldpay\Helper\Instalmentconfig $configHelper
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->configHelper = $configHelper;
    }
    
    /**
     * GetInstalmentType
     *
     * @param string|int $countryid
     */
    public function getInstalmentType($countryid)
    {
        $value = $this->configHelper->getConfigTypeForCountry($countryid);
        return $value;
    }
}
