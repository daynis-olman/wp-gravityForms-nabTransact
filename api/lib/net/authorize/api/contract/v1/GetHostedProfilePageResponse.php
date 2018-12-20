<?php

namespace net\authorize\api\contract\v1;

// don't load directly
if ( ! defined( 'ABSPATH' ) ) {
    die();
}

/**
 * Class representing GetHostedProfilePageResponse
 */
class GetHostedProfilePageResponse extends ANetApiResponseType
{

    /**
     * @property string $token
     */
    private $token = null;

    /**
     * Gets as token
     *
     * @return string
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * Sets a new token
     *
     * @param string $token
     * @return self
     */
    public function setToken($token)
    {
        $this->token = $token;
        return $this;
    }


}

