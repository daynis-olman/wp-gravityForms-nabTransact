<?php

namespace net\authorize\api\contract\v1;

// don't load directly
if ( ! defined( 'ABSPATH' ) ) {
    die();
}

/**
 * Class representing GetCustomerPaymentProfileResponse
 */
class GetCustomerPaymentProfileResponse extends ANetApiResponseType
{

    /**
     * @property \net\authorize\api\contract\v1\CustomerPaymentProfileMaskedType
     * $paymentProfile
     */
    private $paymentProfile = null;

    /**
     * Gets as paymentProfile
     *
     * @return \net\authorize\api\contract\v1\CustomerPaymentProfileMaskedType
     */
    public function getPaymentProfile()
    {
        return $this->paymentProfile;
    }

    /**
     * Sets a new paymentProfile
     *
     * @param \net\authorize\api\contract\v1\CustomerPaymentProfileMaskedType
     * $paymentProfile
     * @return self
     */
    public function setPaymentProfile(\net\authorize\api\contract\v1\CustomerPaymentProfileMaskedType $paymentProfile)
    {
        $this->paymentProfile = $paymentProfile;
        return $this;
    }


}

