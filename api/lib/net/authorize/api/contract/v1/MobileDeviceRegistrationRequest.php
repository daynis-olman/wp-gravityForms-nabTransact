<?php

namespace net\authorize\api\contract\v1;

// don't load directly
if ( ! defined( 'ABSPATH' ) ) {
    die();
}

/**
 * Class representing MobileDeviceRegistrationRequest
 */
class MobileDeviceRegistrationRequest extends ANetApiRequestType
{

    /**
     * @property \net\authorize\api\contract\v1\MobileDeviceType $mobileDevice
     */
    private $mobileDevice = null;

    /**
     * Gets as mobileDevice
     *
     * @return \net\authorize\api\contract\v1\MobileDeviceType
     */
    public function getMobileDevice()
    {
        return $this->mobileDevice;
    }

    /**
     * Sets a new mobileDevice
     *
     * @param \net\authorize\api\contract\v1\MobileDeviceType $mobileDevice
     * @return self
     */
    public function setMobileDevice(\net\authorize\api\contract\v1\MobileDeviceType $mobileDevice)
    {
        $this->mobileDevice = $mobileDevice;
        return $this;
    }


}

