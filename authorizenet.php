<?php

// don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	die();
}

/*
Plugin Name: Gravity Forms Authorize.Net Add-On
Plugin URI: https://www.gravityforms.com
Description: Integrates Gravity Forms with Authorize.Net, enabling end users to purchase goods and services through Gravity Forms.
Version: 2.6
Author: rocketgenius
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