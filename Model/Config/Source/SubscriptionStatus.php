<?php
/**
 * Copyright © 2020 Worldpay, LLC. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Sapient\Worldpay\Model\Config\Source;

class SubscriptionStatus extends AbstractArraySource
{
    public const ACTIVE = 'active';
    public const SUSPENDED = 'suspended';
    public const CANCELLED = 'cancelled';
    public const EXPIRED = 'expired';
    
    /**
     * Configurations
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => self::ACTIVE, 'label' => __('Active')],
            ['value' => self::SUSPENDED, 'label' => __('Suspended')],
            ['value' => self::EXPIRED, 'label' => __('Expired')],
            ['value' => self::CANCELLED, 'label' => __('Cancelled')]
        ];
    }
}
