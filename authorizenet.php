<?php

// don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	die();
}

/*
Plugin Name: Gravity Forms NAB Transact Add-On
Plugin URI: https://www.gravityforms.com
Description: Integrates Gravity Forms with NAB Transact Direct Post API, enabling end users to purchase goods and services through Gravity Forms. Forked from Authorize.net DP API
Version: 0.1
Author: Daynis Olman
Author URI: https://www.rocketgenius.com
License: GPL-2.0+
Text Domain: gravityformsauthorizenet
Domain Path: /languages

------------------------------------------------------------------------
Copyright 2009-2016 rocketgenius

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
*/

define( 'GF_AUTHORIZENET_VERSION', '2.6' );


/// START HANDLE THE GRAVITY FORM HOOK FOR NAB API ////
require 'nab/vendor/autoload.php';
use Omnipay\Omnipay;
use Omnipay\Common\CreditCard;


add_action( 'gform_pre_submission', 'pre_submission_handler' );

function de_gforms_confirmation_dynamic_redirect( $confirmation, $form, $entry, $ajax )
{   
    
    $confirmation = array( 'redirect' => site_url().'/payment-error/' );

    return $confirmation;
}

function pre_submission_handler( $form )
{
    // Stage 2 & 3 have different form ID's. This will synched prior to going live 
    if($_POST['gform_submit']==5 || $_POST['gform_submit']==6)
    {
        $nabsettings = get_option( 'gravityformsaddon_wp-gravityForms-nabTransact_settings');
        $loginId=$nabsettings['loginId'];
        $transactionKey=$nabsettings['transactionKey'];
        $gateway = Omnipay::create('NABTransact_SecureXML');
        $gateway->setMerchantId($loginId);
        $gateway->setTransactionPassword($transactionKey);
        $isTestMode=false;
        
        if($nabsettings['mode']=='test')
        {
         $isTestMode=true;   
        }
        
        $gateway->setTestMode($isTestMode);
        
        $Price=$_POST['input_50'];
        $cardHolderName=$_POST['input_47_5'];
        $cardNo=$_POST['input_47_1'];
        $cardexpiryMonth=$_POST['input_47_2'][0];
        $cardexpiryYear=$_POST['input_47_2'][1];
        $cardcvv=$_POST['input_47_3'];
        $transactionId=time();
        
        $card = new CreditCard([
                'firstName' => $cardHolderName,
                'lastName' => '',
                'number'      => $cardNo,
                'expiryMonth' => $cardexpiryMonth,
                'expiryYear'  => $cardexpiryYear,
                'cvv'         => $cardcvv,
            ]
        );
    
        $transaction = $gateway->purchase([
                'amount'        => $Price,
                'currency'      => 'AUD',
                'transactionId' => $transactionId,
                'card'          => $card,
            ]
        );
    
        $response = $transaction->send();
        
         if ($response->isSuccessful()) 
         {
            $_POST['input_51']=sprintf('Transaction %s was successful!', $response->getTransactionReference()); 
         } 
         else
         {
            $_POST['input_51']=sprintf('Transaction %s failed: %s', $response->getTransactionReference(), $response->getMessage());
            add_filter( 'gform_confirmation', 'de_gforms_confirmation_dynamic_redirect', 10, 4 );
         }
        
    }
}
/// END HANDLE THE GRAVITY FORM HOOK FOR NAB API ////



add_action( 'gform_loaded', array( 'GF_AuthorizeNet_Bootstrap', 'load' ), 5 );

class GF_AuthorizeNet_Bootstrap {

	public static function load() {

		if ( ! method_exists( 'GFForms', 'include_payment_addon_framework' ) ) {
			return;
		}

		require_once( 'class-gf-authorizenet.php' );

		GFAddOn::register( 'GFAuthorizeNet' );
	}

}

function gf_authorizenet() {
	return GFAuthorizeNet::get_instance();
}