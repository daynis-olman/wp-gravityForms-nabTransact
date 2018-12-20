<?php

namespace net\authorize\api\contract\v1;

// don't load directly
if ( ! defined( 'ABSPATH' ) ) {
    die();
}

/**
 * Class representing ProfileTransCaptureOnlyType
 *
 *
 * XSD Type: profileTransCaptureOnlyType
 */
class ProfileTransCaptureOnlyType extends ProfileTransOrderType
{

    /**
     * @property string $approvalCode
     */
    private $approvalCode = null;

    /**
     * Gets as approvalCode
     *
     * @return string
     */
    public function getApprovalCode()
    {
        return $this->approvalCode;
    }

    /**
     * Sets a new approvalCode
     *
     * @param string $approvalCode
     * @return self
     */
    public function setApprovalCode($approvalCode)
    {
        $this->approvalCode = $approvalCode;
        return $this;
    }


}

