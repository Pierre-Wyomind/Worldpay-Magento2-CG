<?php
/**
 * @copyright 2017 Sapient
 */
namespace Sapient\Worldpay\Model\XmlBuilder\Config;

/**
 * Get ThreeDSecure Configuration
 */
class ThreeDSecure
{
    /**
     * [$isDynamic3D description]
     * @var [type]
     */
    private $isDynamic3D = false;
    /**
     * [$is3DSecure description]
     * @var [type]
     */
    private $is3DSecure = false;

    /**
     * [__construct description]
     *
     * @param boolean $isDynamic3D [description]
     * @param boolean $is3DSecure  [description]
     */
    public function __construct($isDynamic3D = false, $is3DSecure = false)
    {
        $this->isDynamic3D = (bool)$isDynamic3D;
        $this->is3DSecure = (bool)$is3DSecure;
    }

    /**
     * Check if dynamic 3ds is enabled
     *
     * @return bool
     */
    public function isDynamic3DEnabled()
    {
        return $this->isDynamic3D;
    }

    /**
     * Check if 3ds is enabled
     *
     * @return bool
     */
    public function is3DSecureCheckEnabled()
    {
        return $this->is3DSecure;
    }
}
