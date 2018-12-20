<?php

// don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	die();
}

GFForms::include_payment_addon_framework();

class GFAuthorizeNet extends GFPaymentAddOn {

	protected $_version = GF_AUTHORIZENET_VERSION;
	protected $_min_gravityforms_version = '1.9.12';
	protected $_slug = 'gravityformsauthorizenet';
	protected $_path = 'gravityformsauthorizenet/authorizenet.php';
	protected $_full_path = __FILE__;
	protected $_title = 'Authorize.Net Add-On';
	protected $_short_title = 'Authorize.Net';
	protected $_requires_credit_card = true;
	protected $_supports_callbacks = true;

	// Members plugin integration
	protected $_capabilities = array(
		'gravityforms_authorizenet',
		'gravityforms_authorizenet_uninstall',
		'gravityforms_authorizenet_plugin_page'
	);

	// Permissions
	protected $_capabilities_settings_page = 'gravityforms_authorizenet';
	protected $_capabilities_form_settings = 'gravityforms_authorizenet';
	protected $_capabilities_uninstall = 'gravityforms_authorizenet_uninstall';
	protected $_capabilities_plugin_page = 'gravityforms_authorizenet_plugin_page';

	// Automatic upgrade enabled
	protected $_enable_rg_autoupgrade = true;

	/**
	 * @var array $_args_for_deprecated_hooks Will hold a few arrays which are needed by some deprecated hooks, keeping them out of the $authorization array so that potentially sensitive data won't be exposed in logging statements.
	 */
	private $_args_for_deprecated_hooks = array();

	private static $_instance = null;

	public static function get_instance() {
		if ( self::$_instance == null ) {
			self::$_instance = new GFAuthorizeNet();
		}

		return self::$_instance;
	}

	public function init_admin() {
		parent::init_admin();
		add_filter( 'gform_addon_navigation', array( $this, 'maybe_create_menu' ) );
	}

	public function init_ajax() {
		parent::init_ajax();

		add_action( 'wp_ajax_gf_dismiss_authorizenet_menu', array( $this, 'ajax_dismiss_menu' ) );

	}

	public function maybe_create_menu( $menus ) {
		$current_user              = wp_get_current_user();
		$dismiss_authorizenet_menu = get_metadata( 'user', $current_user->ID, 'dismiss_authorizenet_menu', true );
		if ( $dismiss_authorizenet_menu != '1' ) {
			$menus[] = array(
				'name'       => $this->_slug,
				'label'      => $this->get_short_title(),
				'callback'   => array( $this, 'temporary_plugin_page' ),
				'permission' => $this->_capabilities_form_settings
			);
		}

		return $menus;
	}

	public function ajax_dismiss_menu() {

		$current_user = wp_get_current_user();
		update_metadata( 'user', $current_user->ID, 'dismiss_authorizenet_menu', '1' );
	}

	public function temporary_plugin_page() {
		$current_user = wp_get_current_user();
		?>
		<script type="text/javascript">
			function dismissMenu() {
				jQuery('#gf_spinner').show();
				jQuery.post(ajaxurl, {
						action: 'gf_dismiss_authorizenet_menu'
					},
					function (response) {
						document.location.href = '?page=gf_edit_forms';
						jQuery('#gf_spinner').hide();
					}
				);

			}
		</script>

		<div class="wrap about-wrap">
			<h1><?php esc_html_e( 'Authorize.Net Add-On v2.0', 'gravityformsauthorizenet' ) ?></h1>

			<div
				class="about-text"><?php esc_html_e( 'Thank you for updating! The new version of the Gravity Forms Authorize.Net Add-On makes changes to how you manage your Authorize.Net integration.', 'gravityformsauthorizenet' ) ?></div>
			<div class="changelog">
				<hr/>
				<div class="feature-section col two-col">
					<div class="col-1">
						<h3><?php esc_html_e( 'Manage Authorize.Net Contextually', 'gravityformsauthorizenet' ) ?></h3>

						<p><?php esc_html_e( 'Authorize.Net Feeds are now accessed via the Authorize.Net sub-menu within the Form Settings for the Form with which you would like to integrate Authorize.Net.', 'gravityformsauthorizenet' ) ?></p>
					</div>
					<div class="col-2 last-feature">
						<img src="http://gravityforms.s3.amazonaws.com/webimages/AddonNotice/NewAuthorizeNet2.png">
					</div>
				</div>

				<hr/>

				<form method="post" id="dismiss_menu_form" style="margin-top: 20px;">
					<input type="checkbox" name="dismiss_authorizenet_menu" value="1" onclick="dismissMenu();">
					<label><?php esc_html_e( 'I understand this change, dismiss this message!', 'gravityformsauthorizenet' ) ?></label>
					<img id="gf_spinner" src="<?php echo GFCommon::get_base_url() . '/images/spinner.gif' ?>"
					     alt="<?php esc_html_e( 'Please wait...', 'gravityformsauthorizenet' ) ?>" style="display:none;"/>
				</form>

			</div>
		</div>
		<?php
	}

	//----- SETTINGS PAGES ----------//
	public function plugin_settings_fields() {

		$description = '<p style="text-align: left;">' . sprintf( esc_html__( 'Authorize.Net is a payment gateway for merchants. Use Gravity Forms to collect payment information and automatically integrate to your Authorize.Net account. If you don\'t have an Authorize.Net account, you can %ssign up for one here.%s', 'gravityformsauthorizenet' ), '<a href="http://www.authorizenet.com" target="_blank">', '</a>' ) . '</p>';

		return array(
			array(
				'title'       => esc_html__( 'Authorize.Net Account Information', 'gravityformsauthorizenet' ),
				'description' => $description,
				'fields'      => array(
					array(
						'name'          => 'mode',
						'label'         => esc_html__( 'Mode', 'gravityformsauthorizenet' ),
						'type'          => 'radio',
						'default_value' => 'test',
						'choices'       => array(
							array(
								'label' => esc_html__( 'Production', 'gravityformsauthorizenet' ),
								'value' => 'production',
							),
							array(
								'label' => esc_html__( 'Test', 'gravityformsauthorizenet' ),
								'value' => 'test',
							),
						),
						'horizontal'    => true,
					),
					array(
						'name'              => 'loginId',
						'label'             => esc_html__( 'API Login ID', 'gravityformsauthorizenet' ),
						'type'              => 'api_login',
						'class'             => 'medium',
						'feedback_callback' => array( $this, 'is_valid_plugin_key' ),
					),
					array(
						'name'              => 'transactionKey',
						'label'             => esc_html__( 'Transaction Key', 'gravityformsauthorizenet' ),
						'type'              => 'api_key',
						'class'             => 'medium',
						'feedback_callback' => array( $this, 'is_valid_plugin_key' ),
					),
				),
			),
			array(
				'title'  => esc_html__( 'Automated Recurring Billing Setup', 'gravityformsauthorizenet' ),
				'fields' => array(
					array(
						'name'    => 'arb',
						'label'   => 'ARB',
						'type'    => 'checkbox',
						'onchange' => "if(jQuery(this).prop('checked')){
										jQuery('#gaddon-setting-row-automaticRetry').show();

									} else {
										jQuery('#gaddon-setting-row-automaticRetry').hide();

									}",
						'choices' => array(
							array(
								'label' => esc_html__( 'ARB is set up in my Authorize.Net account.', 'gravityformsauthorizenet' ),
								'name'  => 'arb'
							)
						),
					),
					array(
						'name'    => 'automaticRetry',
						'label'   => 'Automatic Retry',
						'type'    => 'checkbox',
						'hidden'  => ! $this->get_setting( 'arb' ),
						'tooltip'       => '<h6>' . esc_html__( 'Automatic Retry', 'gravityformsauthorizenet' ) . '</h6>' . esc_html__( 'Automatic Retry enhances Recurring Billing so you do not need to manually collect failed payments. With Automatic Retry, your customer\'s subscriptions will not terminate due to payment failures and will remain in a suspended status until you update the subscription\'s payment details. Once updated, Authorize.Net will automatically retry the failed payment in the subscription.'  , 'gravityformsauthorizenet' ),
						'choices' => array(
							array(
								'label' => esc_html__( 'Automatic Retry is turned on in my Authorize.Net account. To enable this feature in your Authorize.Net account, go to the Recurring Billing page under Tools and click on "Enable Automatic Retry" under Settings.', 'gravityformsauthorizenet' ),
								'name'  => 'automaticRetry'
							)
						),
					),
				),
				array(
					'type'     => 'save',
					'messages' => array( 'success' => esc_html__( 'Settings updated successfully', 'gravityformsauthorizenet' ) )

				),
			),
		);
	}

	public function settings_api_login( $field, $echo = true ) {

		$api_login_field = $this->settings_text( $field, false );

		$caption = sprintf( esc_html__( 'You can find your unique %sAPI Login ID%s by clicking on the \'Account\' link at the Authorize.Net Merchant Interface. Then click \'API Login ID and Transaction Key\'. Your API Login ID will be displayed.', 'gravityformsauthorizenet' ), '<strong>', '</strong>' );

		if ( $echo ) {
			echo $api_login_field . '</br><small>' . $caption . '</small>';
		}

		return $api_login_field . '</br><small>' . $caption . '</small>';

	}

	public function settings_api_key( $field, $echo = true ) {

		$api_key_field = $this->settings_text( $field, false );

		$caption = sprintf( esc_html__( 'You can find your unique %sTransaction Key%s by clicking on the \'Account\' link at the Authorize.Net Merchant Interface. Then click \'API Login ID and Transaction Key\'. For security reasons, you cannot view your Transaction Key, but you will be able to generate a new one.', 'gravityformsauthorizenet' ), '<strong>', '</strong>' );

		if ( $echo ) {
			echo $api_key_field . '</br><small>' . $caption . '</small>';
		}

		return $api_key_field . '</br><small>' . $caption . '</small>';

	}

	public function is_valid_plugin_key() {
		return $this->is_valid_key();
	}

	public function is_valid_custom_key() {
		//get override settings
		$apiSettingsEnabled = $this->get_setting( 'apiSettingsEnabled' );
		if ( $apiSettingsEnabled ) {
			$custom_settings['overrideMode']  = $this->get_setting( 'overrideMode' );
			$custom_settings['overrideLogin'] = $this->get_setting( 'overrideLogin' );
			$custom_settings['overrideKey']   = $this->get_setting( 'overrideKey' );

			return $this->is_valid_key( $custom_settings );
		}

		return false;
	}

	public function is_valid_key( $settings = array() ) {
		$auth = $this->get_aim( $settings );

		$response             = $auth->AuthorizeOnly();
		$failure              = $response->error;
		$response_reason_code = $response->response_reason_code;
		//13 - The merchant login ID or password is invalid or the account is inactive.
		//103 -
		if ( $failure && ( $response_reason_code == 13 || $response_reason_code == 103 ) ) {
			$this->log_debug( __METHOD__ . '(): ' . $response->error_message );

			return false;
		} else {
			return true;
		}
	}

	private function get_aim( $local_api_settings = array() ) {
		$this->include_api();

		if ( ! empty( $local_api_settings ) ) {
			$api_settings = array(
				'login_id'        => rgar( $local_api_settings, 'overrideLogin' ),
				'transaction_key' => rgar( $local_api_settings, 'overrideKey' ),
				'mode'            => rgar( $local_api_settings, 'overrideMode' )
			);
		} else {
			$api_settings = $this->get_api_settings( $local_api_settings );
		}

		$is_sandbox = $api_settings['mode'] == 'test';

		if ( $is_sandbox ) {
			$this->log_debug( __METHOD__ . '(): In test mode. Using the Authorize.net Sandbox.' );
		}

		$aim = new AuthorizeNetAIM( $api_settings['login_id'], $api_settings['transaction_key'] );
		$aim->setSandbox( $is_sandbox );

		return $aim;
	}

	private function include_api() {
		if ( ! class_exists( 'AuthorizeNetRequest' ) ) {
			require_once $this->get_base_path() . '/api/AuthorizeNet.php';
		}
	}

	// function needed for cancel subscription and check_status where $this->current_feed is not available
	private function get_local_api_settings( $feed ) {

		if($feed['meta']['apiSettingsEnabled'])
		{
			$local_api_settings = array(
				'login_id'        => rgar( $feed['meta'], 'overrideLogin' ),
				'transaction_key' => rgar( $feed['meta'], 'overrideKey' ),
				'mode'            => rgar( $feed['meta'], 'overrideMode' ));

		}else{
			$local_api_settings = array();
		}

		return $local_api_settings;

	}

	private function get_api_settings( $local_api_settings ) {

		//for authorize.net, each feed can have its own login id and transaction key specified which overrides the master plugin one
		//use the custom settings if found, otherwise use the master plugin settings

		$apiSettingsEnabled = $this->current_feed['meta']['apiSettingsEnabled'];

		if ( $apiSettingsEnabled ) {

			$login_id        = $this->current_feed['meta']['overrideLogin'];
			$transaction_key = $this->current_feed['meta']['overrideKey'];
			$mode            = $this->current_feed['meta']['overrideMode'];

			return array( 'login_id' => $login_id, 'transaction_key' => $transaction_key, 'mode' => $mode );

		} else {
			$settings = $this->get_plugin_settings();

			return array(
				'login_id'        => rgar( $settings, 'loginId' ),
				'transaction_key' => rgar( $settings, 'transactionKey' ),
				'mode'            => rgar( $settings, 'mode' )
			);
		}

	}

	private function get_arb( $local_api_settings = array() ) {

		if ( ! empty( $local_api_settings ) ) {
			$this->log_debug( __METHOD__ . '(): Local API settings enabled.');
			$api_settings = array(
				'login_id'        => rgar( $local_api_settings, 'login_id' ),
				'transaction_key' => rgar( $local_api_settings, 'transaction_key' ),
				'mode'            => rgar( $local_api_settings, 'mode' )
			);
		} else {
			$api_settings = $this->get_api_settings( $local_api_settings );
		}

		$this->include_api();

		$is_sandbox = $api_settings['mode'] == 'test';

		$arb = new AuthorizeNetARB( $api_settings['login_id'], $api_settings['transaction_key'] );
		$arb->setSandbox( $is_sandbox );

		return $arb;
	}


	//-------- Form Settings ---------

	/**
	 * Prevent feeds being listed or created if the api keys aren't valid.
	 *
	 * @return bool
	 */
	public function can_create_feed() {
		return $this->is_valid_plugin_key();
	}

	public function feed_settings_fields() {
		$default_settings = parent::feed_settings_fields();

		//remove default options before adding custom
		$default_settings = parent::remove_field( 'options', $default_settings );

		$fields = array(
			array(
				'name'    => 'options',
				'label'   => esc_html__( 'Options', 'gravityformsauthorizenet' ),
				'type'    => 'options',
				'tooltip' => '<h6>' . esc_html__( 'Options', 'gravityformsauthorizenet' ) . '</h6>' . esc_html__( 'Turn on or off the available Authorize.Net checkout options.', 'gravityformsauthorizenet' ),
			),
		);

		//Add post fields if form has a post
		$form = $this->get_current_form();
		if ( GFCommon::has_post_field( $form['fields'] ) ) {
			if ( $this->get_setting( 'transactionType' ) == 'subscription' ) {
				$post_settings = array(
					'name'    => 'post_checkboxes',
					'label'   => esc_html__( 'Posts', 'gravityformsauthorizenet' ),
					'type'    => 'checkbox',
					'tooltip' => '<h6>' . esc_html__( 'Posts', 'gravityformsauthorizenet' ) . '</h6>' . esc_html__( 'Enable this option if you would like to change the post status when a subscription is canceled.', 'gravityformsauthorizenet' ),
					'choices' => array(
						array(
							'label'    => esc_html__( 'Change post status when subscription is canceled.', 'gravityformsauthorizenet' ),
							'name'     => 'change_post_status',
							'onChange' => 'var action = this.checked ? "draft" : ""; jQuery("#update_post_action").val(action);',
						),
					),
				);

				$fields[] = $post_settings;
			}
		}

		$default_settings = $this->add_field_after( 'billingInformation', $fields, $default_settings );

		$fields = array(
			array(
				'name'     => 'apiSettingsEnabled',
				'label'    => esc_html__( 'API Settings', 'gravityformsauthorizenet' ),
				'type'     => 'checkbox',
				'tooltip'  => '<h6>' . esc_html__( 'API Settings', 'gravityformsauthorizenet' ) . '</h6>' . esc_html__( 'Override the settings provided on the Authorize.Net Settings page and use these instead for this feed.', 'gravityformsauthorizenet' ),
				'onchange' => "if(jQuery(this).prop('checked')){
										jQuery('#gaddon-setting-row-overrideMode').show();
										jQuery('#gaddon-setting-row-overrideLogin').show();
										jQuery('#gaddon-setting-row-overrideKey').show();
									} else {
										jQuery('#gaddon-setting-row-overrideMode').hide();
										jQuery('#gaddon-setting-row-overrideLogin').hide();
										jQuery('#gaddon-setting-row-overrideKey').hide();
										jQuery('#overrideLogin').val('');
										jQuery('#overrideKey').val('');
										jQuery('i').removeClass('icon-check fa-check gf_valid');
									}",
				'choices'  => array(
					array(
						'label' => esc_html__( 'Override Default Settings', 'gravityformsauthorizenet' ),
						'name'  => 'apiSettingsEnabled',
					),
				)
			),
			array(
				'name'          => 'overrideMode',
				'label'         => esc_html__( 'Mode', 'gravityformsauthorizenet' ),
				'type'          => 'radio',
				'default_value' => 'test',
				'hidden'        => ! $this->get_setting( 'apiSettingsEnabled' ),
				'tooltip'       => '<h6>' . esc_html__( 'Mode', 'gravityformsauthorizenet' ) . '</h6>' . esc_html__( 'Select either Production or Test mode to override the chosen mode on the Authorize.Net Settings page.', 'gravityformsauthorizenet' ),
				'choices'       => array(
					array(
						'label' => esc_html__( 'Production', 'gravityformsauthorizenet' ),
						'value' => 'production',
					),
					array(
						'label' => esc_html__( 'Test', 'gravityformsauthorizenet' ),
						'value' => 'test',
					),
				),
				'horizontal'    => true,
			),
			array(
				'name'              => 'overrideLogin',
				'label'             => esc_html__( 'API Login ID', 'gravityformsauthorizenet' ),
				'type'              => 'text',
				'class'             => 'medium',
				'hidden'            => ! $this->get_setting( 'apiSettingsEnabled' ),
				'tooltip'           => '<h6>' . esc_html__( 'API Login ID', 'gravityformsauthorizenet' ) . '</h6>' . esc_html__( 'Enter a new value to override the API Login ID on the Authorize.Net Settings page.', 'gravityformsauthorizenet' ),
				'feedback_callback' => array( $this, 'is_valid_custom_key' ),
			),
			array(
				'name'              => 'overrideKey',
				'label'             => esc_html__( 'Transaction Key', 'gravityformsauthorizenet' ),
				'type'              => 'text',
				'class'             => 'medium',
				'hidden'            => ! $this->get_setting( 'apiSettingsEnabled' ),
				'tooltip'           => '<h6>' . esc_html__( 'Transaction Key', 'gravityformsauthorizenet' ) . '</h6>' . esc_html__( 'Enter a new value to override the Transaction Key on the Authorize.Net Settings page.', 'gravityformsauthorizenet' ),
				'feedback_callback' => array( $this, 'is_valid_custom_key' ),
			),
		);

		$default_settings = $this->add_field_after( 'conditionalLogic', $fields, $default_settings );

		return $default_settings;
	}

	public function settings_options( $field, $echo = true ) {
		$checkboxes = array(
			'name'    => 'options_checkboxes',
			'type'    => 'checkboxes',
			'choices' => array(
				array(
					'label' => esc_html__( 'Send Authorize.Net email receipt.', 'gravityformsauthorizenet' ),
					'name'  => 'enableReceipt'
				),
			)
		);

		$html = $this->settings_checkbox( $checkboxes, false );

		if ( $echo ) {
			echo $html;
		}

		return $html;
	}

	public function checkbox_input_change_post_status( $choice, $attributes, $value, $tooltip ) {
		$markup = $this->checkbox_input( $choice, $attributes, $value, $tooltip );

		$dropdown_field = array(
			'name'     => 'update_post_action',
			'choices'  => array(
				array( 'label' => '' ),
				array( 'label' => esc_html__( 'Mark Post as Draft', 'gravityformsauthorizenet' ), 'value' => 'draft' ),
				array( 'label' => esc_html__( 'Delete Post', 'gravityformsauthorizenet' ), 'value' => 'delete' ),

			),
			'onChange' => "var checked = jQuery(this).val() ? 'checked' : false; jQuery('#change_post_status').attr('checked', checked);",
		);
		$markup .= '&nbsp;&nbsp;' . $this->settings_select( $dropdown_field, false );

		return $markup;
	}

	public function supported_billing_intervals() {
		//authorize.net does not use years or weeks, override framework function
		$billing_cycles = array(
			'day'   => array( 'label' => esc_html__( 'day(s)', 'gravityformsauthorizenet' ), 'min' => 7, 'max' => 365 ),
			'month' => array( 'label' => esc_html__( 'month(s)', 'gravityformsauthorizenet' ), 'min' => 1, 'max' => 12 )
		);

		return $billing_cycles;
	}

	/**
	 * Append the phone field to the default billing_info_fields added by the framework.
	 *
	 * @return array
	 */
	public function billing_info_fields() {

		$fields = parent::billing_info_fields();

		$fields[] = array(
				'name'     => 'phone',
				'label'    => esc_html__( 'Phone', 'gravityformsauthorizenet' ),
				'required' => false
		);

		return $fields;
	}

	/**
	 * Add supported notification events.
	 *
	 * @param array $form The form currently being processed.
	 *
	 * @return array
	 */
	public function supported_notification_events( $form ) {
		if ( ! $this->has_feed( $form['id'] ) ) {
			return false;
		}

		return array(
				'complete_payment'          => esc_html__( 'Payment Completed', 'gravityformsauthorizenet' ),
				'create_subscription'       => esc_html__( 'Subscription Created', 'gravityformsauthorizenet' ),
				'cancel_subscription'       => esc_html__( 'Subscription Canceled', 'gravityformsauthorizenet' ),
				'expire_subscription'       => esc_html__( 'Subscription Expired', 'gravityformsauthorizenet' ),
				'add_subscription_payment'  => esc_html__( 'Subscription Payment Added', 'gravityformsauthorizenet' ),
				'fail_subscription_payment' => esc_html__( 'Subscription Payment Failed', 'gravityformsauthorizenet' ),
		);
	}

	// used to upgrade old feeds into new version
	public function get_old_feeds() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'rg_authorizenet';

		if ( ! $this->table_exists( $table_name ) ) {
			return false;
		}

		$form_table_name = RGFormsModel::get_form_table_name();
		$sql             = "SELECT s.id, s.is_active, s.form_id, s.meta, f.title as form_title
				FROM $table_name s
				INNER JOIN $form_table_name f ON s.form_id = f.id";

		$results = $wpdb->get_results( $sql, ARRAY_A );

		$count = sizeof( $results );
		$this->log_debug( __METHOD__ . "(): {$count} feed(s) found to copy." );
		for ( $i = 0; $i < $count; $i ++ ) {
			$results[ $i ]['meta'] = maybe_unserialize( $results[ $i ]['meta'] );
		}

		return $results;
	}

	public function convert_interval( $interval, $to_type ) {
		//convert single character into long text for new feed settings or convert long text into single character for sending to paypal
		//$to_type: text (change character to long text), OR char (change long text to character)
		if ( empty( $interval ) ) {
			return '';
		}

		$new_interval = '';
		if ( $to_type == 'text' ) {
			//convert single char to text
			switch ( strtoupper( $interval ) ) {
				case 'D' :
					$new_interval = 'day';
					break;
				case 'W' :
					$new_interval = 'week';
					break;
				case 'M' :
					$new_interval = 'month';
					break;
				case 'Y' :
					$new_interval = 'year';
					break;
				default :
					$new_interval = $interval;
					break;
			}
		} else {
			//convert text to single char
			switch ( strtolower( $interval ) ) {
				case 'day' :
					$new_interval = 'D';
					break;
				case 'week' :
					$new_interval = 'W';
					break;
				case 'month' :
					$new_interval = 'M';
					break;
				case 'year' :
					$new_interval = 'Y';
					break;
				default :
					$new_interval = $interval;
					break;
			}
		}

		return $new_interval;
	}

	public function upgrade( $previous_version ) {

		if ( empty( $previous_version ) ) {
			$previous_version = get_option( 'gf_authorizenet_version' );
		}
		$previous_is_pre_addon_framework = ! empty( $previous_version ) && version_compare( $previous_version, '2.0.dev1', '<' );

		if ( $previous_is_pre_addon_framework ) {
			$this->log_debug( __METHOD__ . '(): Copying over data to new table structure.' );

			$old_feeds = $this->get_old_feeds();
			if ( $old_feeds ) {
				$this->log_debug( __METHOD__ . '(): Migrating feeds.' );

				$counter = 1;
				foreach ( $old_feeds as $old_feed ) {
					$feed_name       = 'Feed ' . $counter;
					$form_id         = $old_feed['form_id'];
					$is_active       = $old_feed['is_active'];
					$customer_fields = rgar( $old_feed['meta'], 'customer_fields' );

					$new_meta = array(
						'feedName'                     => $feed_name,
						'transactionType'              => rgar( $old_feed['meta'], 'type' ),
						'enableReceipt'                => rgar( $old_feed['meta'], 'enable_receipt' ),
						'change_post_status'           => rgar( $old_feed['meta'], 'update_post_action' ) ? '1' : '0',
						'update_post_action'           => rgar( $old_feed['meta'], 'update_post_action' ),
						'recurringAmount'              => rgar( $old_feed['meta'], 'recurring_amount_field' ) == 'all' ? 'form_total' : rgar( $old_feed['meta'], 'recurring_amount_field' ),
						'recurringTimes'               => rgar( $old_feed['meta'], 'recurring_times' ),
						'paymentAmount'                => 'form_total',
						//default to this for new field in framework version
						'billingCycle_length'          => rgar( $old_feed['meta'], 'billing_cycle_number' ),
						'billingCycle_unit'            => $this->convert_interval( rgar( $old_feed['meta'], 'billing_cycle_type' ), 'text' ),
						'setupFee_enabled'             => rgar( $old_feed['meta'], 'setup_fee_enabled' ),
						'setupFee_product'             => rgar( $old_feed['meta'], 'setup_fee_amount_field' ),
						//trial_period_number is always set to 1 in the old version, no need to use trial_period_number in the database
						'trial_enabled'                => rgar( $old_feed['meta'], 'trial_period_enabled' ),
						'trial_product'                => 'enter_amount',
						//default to this for new field in framework version
						'trial_amount'                 => rgar( $old_feed['meta'], 'trial_amount' ),
						'billingInformation_firstName' => rgar( $customer_fields, 'first_name' ),
						'billingInformation_lastName'  => rgar( $customer_fields, 'last_name' ),
						'billingInformation_email'     => rgar( $customer_fields, 'email' ),
						'billingInformation_address'   => rgar( $customer_fields, 'address1' ),
						'billingInformation_address2'  => rgar( $customer_fields, 'address2' ),
						'billingInformation_city'      => rgar( $customer_fields, 'city' ),
						'billingInformation_state'     => rgar( $customer_fields, 'state' ),
						'billingInformation_zip'       => rgar( $customer_fields, 'zip' ),
						'billingInformation_country'   => rgar( $customer_fields, 'country' ),
						'apiSettingsEnabled'           => rgar( $old_feed['meta'], 'api_settings_enabled' ),
						'overrideMode'                 => rgar( $old_feed['meta'], 'api_mode' ),
						'overrideLogin'                => rgar( $old_feed['meta'], 'api_login' ),
						'overrideKey'                  => rgar( $old_feed['meta'], 'api_key' ),
					);

					$optin_enabled = rgar( $old_feed['meta'], 'authorizenet_conditional_enabled' );
					if ( $optin_enabled ) {
						$new_meta['feed_condition_conditional_logic']        = 1;
						$new_meta['feed_condition_conditional_logic_object'] = array(
							'conditionalLogic' => array(
								'actionType' => 'show',
								'logicType'  => 'all',
								'rules'      => array(
									array(
										'fieldId'  => $old_feed['meta']['authorizenet_conditional_field_id'],
										'operator' => $old_feed['meta']['authorizenet_conditional_operator'],
										'value'    => $old_feed['meta']['authorizenet_conditional_value'],
									),
								)
							)
						);
					} else {
						$new_meta['feed_condition_conditional_logic'] = 0;
					}

					$this->insert_feed( $form_id, $is_active, $new_meta );
					$counter ++;

				}
			}

			$old_settings = get_option( 'gf_authorizenet_settings' );

			if ( ! empty( $old_settings ) ) {
				$this->log_debug( __METHOD__ . '(): Copying plugin settings.' );
				$new_settings = array(
					'mode'           => rgar( $old_settings, 'mode' ),
					'loginId'        => rgar( $old_settings, 'login_id' ),
					'transactionKey' => rgar( $old_settings, 'transaction_key' ),
					'arb'            => rgar( $old_settings, 'arb_configured' ) == 'on' ? '1' : '0',
				);

				parent::update_plugin_settings( $new_settings );
			}

			//copy existing authorize.net transactions to new table
			$this->copy_transactions();

		}

	}

	public function copy_transactions() {
		//copy transactions from the authorize.net transaction table to the addon payment transaction table
		global $wpdb;
		$old_table_name = $this->get_old_transaction_table_name();

		if ( ! $this->table_exists( $old_table_name ) ) {
			return false;
		}

		$this->log_debug( __METHOD__ . '(): Copying old Authorize.Net transactions into new table structure.' );

		$new_table_name = $this->get_new_transaction_table_name();

		$sql = "INSERT INTO {$new_table_name} (lead_id, transaction_type, transaction_id, is_recurring, amount, date_created)
					SELECT entry_id, transaction_type, transaction_id, is_renewal, amount, date_created FROM {$old_table_name}";

		$wpdb->query( $sql );

		$this->log_debug( __METHOD__ . "(): Transactions: {$wpdb->rows_affected} rows were added." );
	}

	public function get_old_transaction_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'rg_authorizenet_transaction';
	}

	public function get_new_transaction_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'gf_addon_payment_transaction';
	}


	//------ AUTHORIZE AND CAPTURE SINGLE PAYMENT ------//
	public function authorize( $feed, $submission_data, $form, $entry ) {

		$original_transaction = $this->get_payment_transaction( $feed, $submission_data, $form, $entry );

		$config    = $this->get_config( $feed, $submission_data );
		$form_data = $this->get_form_data( $submission_data, $form, $config );

		$transaction = apply_filters( 'gform_authorizenet_transaction_pre_authorize', $original_transaction, $form_data, $config, $form );
		$transaction = apply_filters( 'gform_authorizenet_transaction_pre_capture', $transaction, $form_data, $config, $form, $entry );

		//Check if transaction is false after gform_authorizenet_transaction_pre_capture filter. If false, payment is not captured; run authorizeOnly transaction
		if ( ! $transaction ) {

			$this->log_debug( __METHOD__ . '(): Running authorization only. The gform_authorizenet_transaction_pre_capture filter was used to set the transaction to false.' );

			$auth_amount = apply_filters( 'gform_authorizenet_amount_pre_authorize', $form_data['amount'] + floatval( $form_data['fee_amount'] ), $original_transaction, $form_data, $config, $form, $entry );

			$response = $original_transaction->authorizeOnly( $auth_amount );

			if ( $response->approved || $response->held ) {
				$this->log_debug( __METHOD__ . "(): Authorization successful. Amount: {$response->amount}. Transaction Id: {$response->transaction_id}." );

				$auth = array(
					'is_authorized'  => true,
					'transaction_id' => $response->transaction_id,
				);

				$this->_args_for_deprecated_hooks = array(
					'aim_response' => $response,
					'config'       => $config,
				);
			} else {
				$this->log_error( __METHOD__ . '(): Authorization failed. Response => ' . print_r( $response->error_message, true ) );

				// needed for filter backwards compatibility (gform_authorizenet_validation_message)
				$error_message     = $this->get_error_message( $_POST, $response, 'aim' );
				$validation_result = $this->get_valiation_result( $form, $error_message );
				$error_message     = apply_filters( 'gform_authorizenet_validation_message', $error_message, $validation_result, $_POST, $response, 'aim' );

				$auth = array(
					'is_authorized'  => false,
					'transaction_id' => $response->transaction_id,
					'error_message'  => $error_message
				);
			}

			return $auth;
		}

		//deprecated
		$transaction = apply_filters( 'gform_authorizenet_before_single_payment', $transaction, $form_data, $config, $form );

		$this->log_debug( __METHOD__ . '(): Capturing funds.' );
		$response = $transaction->authorizeAndCapture();

		if ( $response->approved || $response->held ) {
			$this->log_debug( __METHOD__ . "(): Funds captured successfully. Amount: {$response->amount}. Transaction Id: {$response->transaction_id}." );

			$auth = array(
				'is_authorized'    => true,
				'transaction_id'   => $response->transaction_id,
				'captured_payment' => array(
					'is_success'     => true,
					'error_message'  => '',
					'transaction_id' => $response->transaction_id,
					'amount'         => $response->amount
				),
			);

			$this->_args_for_deprecated_hooks = array(
				'aim_response' => $response,
				'config'       => $config,
			);
		} else {
			if(!empty($response->error_message))
				$this->log_error( __METHOD__ . '(): Funds could not be captured (error_message). Response => ' . print_r( $response->error_message, true ) );
			else
				$this->log_error( __METHOD__ . '(): Funds could not be captured (response_reason_text). Response => ' . $response->response_reason_text);

			// needed for filter backwards compatibility (gform_authorizenet_validation_message)
			$error_message     = $this->get_error_message( $_POST, $response, 'aim' );
			$validation_result = $this->get_valiation_result( $form, $error_message );
			$error_message     = apply_filters( 'gform_authorizenet_validation_message', $error_message, $validation_result, $_POST, $response, 'aim' );

			$auth = array(
				'is_authorized'    => false,
				'transaction_id'   => $response->transaction_id,
				'error_message'    => $error_message,
				'captured_payment' => array(
					'is_success' => false,
				)
			);
		}

		return $auth;

	}

	public function get_payment_transaction( $feed, $submission_data, $form, $entry ) {

		$transaction = $this->get_aim();

		$feed_name = rgar( $feed['meta'], 'feedName' );
		$this->log_debug( __METHOD__ . "(): Initializing new AuthorizeNetAIM object based on feed #{$feed['id']} - {$feed_name}." );

		$transaction->amount    = $submission_data['payment_amount'];
		$transaction->card_num  = $submission_data['card_number'];
		$exp_date               = str_pad( $submission_data['card_expiration_date'][0], 2, '0', STR_PAD_LEFT ) . '-' . $submission_data['card_expiration_date'][1];
		$transaction->exp_date  = $exp_date;
		$transaction->card_code = $submission_data['card_security_code'];

		$names                   = $this->get_first_last_name( $submission_data['card_name'] );
		$transaction->first_name = $names['first_name'];
		$transaction->last_name  = $names['last_name'];

		$transaction->address          = trim( $submission_data['address'] . ' ' . $submission_data['address2'] );
		$transaction->city             = $submission_data['city'];
		$transaction->state            = $submission_data['state'];
		$transaction->zip              = $submission_data['zip'];
		$transaction->country          = $submission_data['country'];
		$transaction->email            = $submission_data['email'];
		$transaction->description      = $submission_data['form_title'];
		$transaction->email_customer   = $feed['meta']['enableReceipt'] == 1 ? 'true' : 'false';
		$transaction->duplicate_window = 5;
		$transaction->customer_ip      = GFFormsModel::get_ip();
		$transaction->invoice_num      = empty( $invoice_number ) ? uniqid() : $invoice_number; //???
		$transaction->phone            = $submission_data['phone'];

		foreach ( $submission_data['line_items'] as $line_item ) {
			$taxable = rgempty( 'taxable', $line_item ) ? 'Y' : $line_item['taxable'];
			$transaction->addLineItem( $line_item['id'], $this->remove_spaces( $this->truncate( $line_item['name'], 31 ) ), $this->truncate( $line_item['description'], 255 ), $line_item['quantity'], GFCommon::to_number( $line_item['unit_price'] ), $taxable );

		}

		$this->log_debug( __METHOD__ . '(): $submission_data line_items => ' . print_r( $submission_data['line_items'], 1 ) );

		return $transaction;

	}

	public function process_capture( $authorization, $feed, $submission_data, $form, $entry ) {

		/**
		 * HOOK for backwards compatibility.
		 *
		 * @deprecated
		 */
		do_action( 'gform_authorizenet_post_capture', rgar( $authorization, 'is_authorized' ), rgars( $authorization, 'captured_payment/amount' ), $entry, $form, $this->_args_for_deprecated_hooks['config'], $this->_args_for_deprecated_hooks['aim_response'] );

		return parent::process_capture( $authorization, $feed, $submission_data, $form, $entry );
	}

	public function subscribe( $feed, $submission_data, $form, $entry ) {

		//Capture setup fee payment if needed
		$fee_amount = $submission_data['setup_fee'];

		$config    = $this->get_config( $feed, $submission_data );
		$form_data = $this->get_form_data( $submission_data, $form, $config );

		$setup_fee_result       = true;
		$setup_payment_captured = false;
		if ( ! empty( $fee_amount ) && $fee_amount > 0 ) {

			//Getting transaction
			$transaction         = $this->get_payment_transaction( $feed, $submission_data, $form, $entry );
			$transaction->amount = $fee_amount;

			$transaction = apply_filters( 'gform_authorizenet_transaction_pre_capture_setup_fee', $transaction, $form_data, $config, $form, $entry );

			//Capturing setup fee payment
			$response = $transaction->authorizeAndCapture();
			$this->log_debug( __METHOD__ . '(): Capturing setup fee payment. Response => ' . print_r( $response, true ) );

			$setup_fee_result = $response->approved;

			if ( $setup_fee_result ) {
				$captured_payment       = array(
						'is_success'     => true,
						'transaction_id' => $response->transaction_id,
						'amount'         => $response->amount
				);
				$setup_payment_captured = true;
			}
		}

		if ( $setup_fee_result ) {
			//Create subscription.
			$subscription = $this->get_subscription( $feed, $submission_data, $entry['id'] );

			if ( has_filter( 'gform_authorizenet_subscription_pre_create' ) ) {
				$this->log_debug( __METHOD__ . '(): Executing functions hooked to gform_authorizenet_subscription_pre_create.' );
				$subscription = apply_filters( 'gform_authorizenet_subscription_pre_create', $subscription, $form_data, $config, $form, $entry );
			}

			//deprecated
			if ( has_filter( 'gform_authorizenet_before_start_subscription' ) ) {
				$this->log_debug( __METHOD__ . '(): Executing functions hooked to gform_authorizenet_before_start_subscription.' );
				$subscription = apply_filters( 'gform_authorizenet_before_start_subscription', $subscription, $form_data, $config, $form );
			}

			if ( ! $subscription instanceof AuthorizeNet_Subscription ) {
				return array( 'is_success' => false, 'error_message' => __( 'Unable to create subscription. Subscription object not available.', 'gravityformsauthorizenet' ) );
			}

			//Send subscription request.
			$request      = $this->get_arb();
			$arb_response = $request->createSubscription( $subscription );

			$this->log_debug( __METHOD__ . '(): Sending create subscription request. Response => ' . print_r( $arb_response, true ) );

			if ( $arb_response->isOk() ) {
				//Getting subscription ID
				$subscription_id = $arb_response->getSubscriptionId();
				$this->log_debug( __METHOD__ . "(): Subscription created successfully. Subscription Id: {$subscription_id}" );

				$subscription_result = array(
					'is_success'                => true,
					'subscription_id'           => $subscription_id,
					'amount'                    => $subscription->amount,
					'subscription_trial_amount' => $subscription->trialAmount,
					'subscription_start_date' => $subscription->startDate,
				);

				if ( $setup_payment_captured ) {
					$subscription_result['captured_payment'] = $captured_payment;
				}

				$this->_args_for_deprecated_hooks = array(
					'arb_subscription' => $subscription,
					'arb_response'     => $arb_response,
					'config'           => $config,
				);

			} else {

				//Need to void setup fee transaction capture (if any) since subscription was not successfully created
				$void_text = '';
				if ( $setup_payment_captured ) {

					//Void setup fee transaction
					$void_transaction = $this->get_payment_transaction( $feed, $submission_data, $form, $entry );
					$void_response    = $transaction->void( $response->transaction_id );
					if ( $void_response->approved ) {
						$void_text = ' Initial setup fee transaction has been voided.';
					}
				}

				// needed for filter backwards compatibility (gform_authorizenet_validation_message)
				$error_message     = $this->get_error_message( $_POST, $arb_response, 'arb' );
				$validation_result = $this->get_valiation_result( $form, $error_message );
				$error_message     = apply_filters( 'gform_authorizenet_validation_message', $error_message, $validation_result, $_POST, $arb_response, 'arb' );

				$this->log_debug( __METHOD__ . '(): There was an error creating Subscription.' . $void_text );
				$subscription_result = array( 'is_success' => false, 'error_message' => $error_message );
			}
		} else {

			$this->log_debug( __METHOD__ . '(): There was an error capturing the Setup Fee. Subscription was not created.' );
			$subscription_result = array( 'is_success' => false, 'error_message' => $response->response_reason_text );

		}

		return $subscription_result;

	}

	// get ARB subscription object
	private function get_subscription( $feed, $submission_data, $invoice_number ) {

		$this->include_api();
		$subscription = new AuthorizeNet_Subscription;

		$feed_name = rgar( $feed['meta'], 'feedName' );
		$this->log_debug( __METHOD__ . "(): Initializing new AuthorizeNet_Subscription object based on feed #{$feed['id']} - {$feed_name}." );

		//getting trial information
		$trial_info = $this->get_trial_info( $feed, $submission_data );

		$total_occurrences = empty( $feed['meta']['recurringTimes'] ) || $feed['meta']['recurringTimes'] == 'Infinite' ? '9999' : $feed['meta']['recurringTimes'];
		if ( $total_occurrences <> '9999' ) {
			$total_occurrences += $trial_info['trial_occurrences'];
		}

		//setting trial properties
		if ( $trial_info['trial_enabled'] ) {
			$subscription->trialOccurrences = $trial_info['trial_occurrences'];
			$subscription->trialAmount      = $trial_info['trial_amount'];
		}

		$names              = $this->get_first_last_name( $submission_data['card_name'] );
		$subscription->name = $names['first_name'] . ' ' . $names['last_name'];

		$subscription->intervalLength = $feed['meta']['billingCycle_length'];
		$subscription->intervalUnit   = $feed['meta']['billingCycle_unit'] == 'day' ? 'days' : 'months';

		//setting the time zone to Mountain Time since the validation checks against Authorize.net's local server date, which is Mountain Time.
		$timezone = date_default_timezone_get();
		date_default_timezone_set( 'US/Mountain' );
		$subscription->startDate = gmdate( 'Y-m-d' );
		//restoring timezone so logging statements are correct.
		date_default_timezone_set( $timezone );

		$subscription->totalOccurrences         = $total_occurrences;
		$subscription->amount                   = $submission_data['payment_amount'];
		$subscription->creditCardCardNumber     = $submission_data['card_number'];
		$exp_date                               = $submission_data['card_expiration_date'][1] . '-' . str_pad( $submission_data['card_expiration_date'][0], 2, '0', STR_PAD_LEFT );
		$subscription->creditCardExpirationDate = $exp_date;
		$subscription->creditCardCardCode       = $submission_data['card_security_code'];
		//authorize.net requires billToFirstName and billToLastName, if one isn't populated, populate it with the other
		$billToFirstName               = empty( $names['first_name'] ) ? $names['last_name'] : $names['first_name'];
		$billToLastName                = empty( $names['last_name'] ) ? $names['first_name'] : $names['last_name'];
		$subscription->billToFirstName = $billToFirstName;
		$subscription->billToLastName  = $billToLastName;

		$subscription->customerEmail       = $submission_data['email'];
		$subscription->billToAddress       = trim( $submission_data['address'] . ' ' . $submission_data['address2'] );
		$subscription->billToCity          = $submission_data['city'];
		$subscription->billToState         = $submission_data['state'];
		$subscription->billToZip           = $submission_data['zip'];
		$subscription->billToCountry       = $submission_data['country'];
		$subscription->orderInvoiceNumber  = $invoice_number;
		$subscription->orderDescription    = esc_html( $submission_data['form_title'] );
		$subscription->customerPhoneNumber = $submission_data['phone'];

		return $subscription;
	}

	private function get_trial_info( $feed, $submission_data ) {

		$trial_amount      = false;
		$trial_occurrences = 0;
		if ( $feed['meta']['trial_enabled'] == 1 ) {
			$trial_occurrences = 1; // always 1
			$trial_amount      = $submission_data['trial'];
			if ( empty( $trial_amount ) ) {
				$trial_amount = 0;
			}
		}
		$trial_enabled = $trial_amount !== false;

		if ( $trial_enabled && ! empty( $trial_amount ) ) {
			$trial_amount = GFCommon::to_number( $trial_amount );
		}

		return array(
			'trial_enabled'     => $trial_enabled,
			'trial_amount'      => $trial_amount,
			'trial_occurrences' => $trial_occurrences
		);
	}

	public function process_subscription( $authorization, $feed, $submission_data, $form, $entry ) {

		//gform_update_meta( $entry['id'], 'subscription_payment_date', gmdate( 'Y-m-d H:i:s' ) );

		gform_update_meta( $entry['id'], 'subscription_payment_date', $authorization['subscription']['subscription_start_date']);
		gform_update_meta( $entry['id'], 'subscription_payment_count', '1' );
		gform_update_meta( $entry['id'], 'subscription_regular_amount', $authorization['subscription']['amount'] );
		gform_update_meta( $entry['id'], 'subscription_trial_amount', $authorization['subscription']['subscription_trial_amount'] );

		/**
		 * HOOKS for backwards compatibility.
		 *
		 * @deprecated
		 */
		do_action( 'gform_authorizenet_post_create_subscription', $authorization['subscription']['is_success'], $this->_args_for_deprecated_hooks['arb_subscription'], $this->_args_for_deprecated_hooks['arb_response'], $entry, $form, rgars($authorization, 'subscription/config') );
		do_action( 'gform_authorizenet_after_subscription_created', $authorization['subscription']['subscription_id'], $authorization['subscription']['amount'], rgars( $authorization, 'subscription/captured_payment/amount' ) );

		return parent::process_subscription( $authorization, $feed, $submission_data, $form, $entry );
	}

	// Cancel Subscription
	public function cancel( $entry, $feed ) {

		// loading authorizenet api and getting credentials
		$this->include_api();

		// cancel the subscription
		$local_api_settings =  $this->get_local_api_settings( $feed );
		$cancellation    = $this->get_arb($local_api_settings);
		$cancel_response = $cancellation->cancelSubscription( $entry['transaction_id'] );

		$this->log_debug( __METHOD__ . '(): Response to subscription cancellation request => ' . print_r( $cancel_response, 1 ) );

		if ( $cancel_response->isOk() ) {
			
			/**
			 * Fires after a subscription is canceled in Gravity Forms
			 *
			 * @param array $entry The Entry Object to filter through
			 * @param array $feed The Feed object to filter through
			 * @param int $entry['transaction_id'] Get the transaction ID to filter from the entry object
			 */
			do_action( 'gform_subscription_canceled', $entry, $feed, $entry['transaction_id'], 'authorize.net' );

			return true;
		}

		return false;

	}

	/**
	 * Check if the current entry was processed by this add-on.
	 *
	 * @param int $entry_id The ID of the current Entry.
	 *
	 * @return bool
	 */
	public function is_payment_gateway( $entry_id ) {

		if ( $this->is_payment_gateway ) {
			return true;
		}

		$gateway = gform_get_meta( $entry_id, 'payment_gateway' );

		return in_array( $gateway, array( 'authorize.net', $this->_slug ) );
	}

	// Check subscription status; Active subscriptions will be checked to see if their status needs to be updated
	public function check_status() {
		// this is where we will check subscription status and update as needed
		$this->log_debug( __METHOD__ . '(): Checking subscription status.' );

		// getting all authorize.net subscription feeds
		$recurring_feeds = $this->get_feeds_by_slug( $this->_slug );

		foreach ( $recurring_feeds as $feed ) {

			// process renewal's if authorize.net feed is subscription feed
			if ( $feed['meta']['transactionType'] == 'subscription' ) {

				$form_id = $feed['form_id'];

				// getting billing cycle information
				$billing_cycle_number = $feed['meta']['billingCycle_length'];
				$billing_cycle_type   = $feed['meta']['billingCycle_unit'];

				if ( $billing_cycle_type == 'day' ) {
					$billing_cycle = $billing_cycle_number . ' day';
				} else {
					$billing_cycle = $billing_cycle_number . ' month';
				}

				$querytime = strtotime( gmdate( 'Y-m-d' ) . '-' . $billing_cycle );
				$querydate = gmdate( 'Y-m-d', $querytime ) . ' 00:00:00';

				// finding leads with a late payment date
				global $wpdb;

				// Get entry table names and entry ID column.
				$entry_table      = self::get_entry_table_name();
				$entry_meta_table = self::get_entry_meta_table_name();
				$entry_id_column  = version_compare( self::get_gravityforms_db_version(), '2.3-dev-1', '<' ) ? 'lead_id' : 'entry_id';

				$results = $wpdb->get_results( "SELECT l.id, l.transaction_id, m.meta_value as payment_date
                                                FROM {$entry_table} l
                                                INNER JOIN {$entry_meta_table} m ON l.id = m.{$entry_id_column}
                                                WHERE l.form_id={$form_id}
                                                AND payment_status = 'Active'
                                                AND meta_key = 'subscription_payment_date'
                                                AND meta_value < '{$querydate}'" );

				foreach ( $results as $result ) {

					$this->log_debug( __METHOD__ . '(): Lead with late payment => ' . print_r( $result, 1 ) );

					//Getting entry
					$entry_id = $result->id;
					$entry    = GFAPI::get_entry( $entry_id );

					//Getting subscription status from authorize.net
					$subscription_id = $result->transaction_id;
					$local_api_settings = $this->get_local_api_settings($feed);
					$status_request  = $this->get_arb($local_api_settings);
					//$status_request  = $this->get_arb();
					$status_response = $status_request->getSubscriptionStatus( $subscription_id );
					$this->log_debug( __METHOD__ . '(): Subscription status response => ' . print_r($status_response,1));
					$status          = $status_response->getSubscriptionStatus();

					$this->log_debug( __METHOD__ . '(): Subscription (' . $subscription_id  .') status => ' . $status);
					switch ( strtolower( $status ) ) {
						case 'active' :
							// getting feed trial information
							$trial_period_enabled     = $feed['meta']['trial_enabled'];
							$trial_period_occurrences = 1;

							// finding payment date
							$new_payment_time = strtotime( $result->payment_date . '+' . $billing_cycle );
							$new_payment_date = gmdate( 'Y-m-d H:i:s', $new_payment_time );

							// finding payment amount
							$payment_count      = gform_get_meta( $entry_id, 'subscription_payment_count' );
							$new_payment_amount = gform_get_meta( $entry_id, 'subscription_regular_amount' );
							if ( $trial_period_enabled == 1 && $trial_period_occurrences >= $payment_count ) {
								$new_payment_amount = gform_get_meta( $entry_id, 'subscription_trial_amount' );
							}

							// update subscription payment and lead information
							gform_update_meta( $entry_id, 'subscription_payment_count', $payment_count + 1 );
							gform_update_meta( $entry_id, 'subscription_payment_date', $new_payment_date );

							$action = array(
								'amount'          => $new_payment_amount,
								'subscription_id' => $subscription_id,
								'type'            => 'add_subscription_payment'
							);
							$this->add_subscription_payment( $entry, $action );

							//deprecated
							do_action( 'gform_authorizenet_post_recurring_payment', $subscription_id, $entry, $new_payment_amount, $payment_count );
							do_action( 'gform_authorizenet_after_recurring_payment', $entry, $subscription_id, $subscription_id, $new_payment_amount );

							break;

						case 'expired' :

							$action = array(
								'subscription_id' => $subscription_id,
								'type'            => 'expire_subscription'
							);
							$this->expire_subscription( $entry, $action );

							//deprecated
							do_action( 'gform_authorizenet_post_expire_subscription', $subscription_id, $entry );
							do_action( 'gform_authorizenet_subscription_ended', $entry, $subscription_id, $transaction_id, $new_payment_amount );

							break;

						case 'suspended':

							$note   = sprintf( __( 'Subscription is currently suspended due to a transaction decline, rejection, or error. Suspended subscriptions must be reactivated before the next scheduled transaction or the subscription will be terminated by the payment gateway. Subscription Id: %s', 'gravityforms' ), $subscription_id );
							$action = array(
								'note'            => $note,
								'subscription_id' => $subscription_id,
								'type'            => 'fail_subscription_payment'
							);
							$this->fail_subscription_payment( $entry, $action );

							//deprecated
							do_action( 'gform_authorizenet_post_suspend_subscription', $subscription_id, $entry );
							do_action( 'gform_authorizenet_subscription_suspended', $entry, $subscription_id, $transaction_id, $new_payment_amount );

							break;

						case 'terminated':
						case 'canceled':

							$this->cancel_subscription( $entry, $feed );

						/**
						 * @deprecated
						 */
							do_action( 'gform_authorizenet_post_cancel_subscription', $subscription_id, $entry );
							do_action( 'gform_authorizenet_subscription_canceled', $entry, $subscription_id, $transaction_id, $new_payment_amount );

							break;

						default:
							$action = array(
								'subscription_id' => $subscription_id,
								'type'            => 'fail_subscription_payment'
							);
							$this->fail_subscription_payment( $entry, $action );

							//deprecated
							do_action( 'gform_authorizenet_post_suspend_subscription', $subscription_id, $entry );
							do_action( 'gform_authorizenet_subscription_suspended', $entry, $subscription_id, $transaction_id, $new_payment_amount );
							break;
					}


				}

			}

		}
	}

	public function get_error_message( $post, $response, $responsetype ) {

		if ( $responsetype == 'aim' ) {
			$code = $response->response_reason_code;
			switch ( $code ) {
				case '2' :
				case '3' :
				case '4' :
				case '41' :
					$message = esc_html__( 'This credit card has been declined by your bank. Please use another form of payment.', 'gravityformsauthorizenet' );
					break;

				case '8' :
					$message = esc_html__( 'The credit card has expired.', 'gravityformsauthorizenet' );
					break;

				case '17' :
				case '28' :
					$message = esc_html__( 'The merchant does not accept this type of credit card.', 'gravityformsauthorizenet' );
					break;

				case '27' :
					$message = esc_html__( 'The address provided does not match the billing address of the cardholder. Please verify the information and try again.', 'gravityformsauthorizenet' );
					break;

				case '49' :
					$message = esc_html__( 'The transaction amount is greater than the maximum amount allowed.', 'gravityformsauthorizenet' );
					break;

				case '7' :
				case '44' :
				case '45' :
				case '65' :
				case '78' :
				case '6' :
				case '37' :
				case '200' :
				case '201' :
				case '202' :
					$message = esc_html__( 'There was an error processing your credit card. Please verify the information and try again.', 'gravityformsauthorizenet' );
					break;

				default :
					$message = esc_html__( 'There was an error processing your credit card. Please verify the information and try again.', 'gravityformsauthorizenet' );

			}
		} else {
			$code = $response->getMessageCode();
			switch ( $code ) {
				case 'E00012' :
					$message = esc_html__( 'A duplicate subscription already exists.', 'gravityformsauthorizenet' );
					break;
				case 'E00018' :
					$message = esc_html__( 'The credit card expires before the subscription start date. Please use another form of payment.', 'gravityformsauthorizenet' );
					break;
				default :
					$message = esc_html__( 'There was an error processing your credit card. Please verify the information and try again.', 'gravityformsauthorizenet' );
			}
		}

		$message = '<!-- Error: ' . $code . ' -->' . $message;

		return $message;

	}

	// HELPERS

	private function remove_spaces( $text ) {

		$text = str_replace( "\t", ' ', $text );
		$text = str_replace( "\n", ' ', $text );
		$text = str_replace( "\r", ' ', $text );

		return $text;

	}

	private function truncate( $text, $max_chars ) {
		if ( strlen( $text ) <= $max_chars ) {
			return $text;
		}

		return substr( $text, 0, $max_chars );
	}

	private function get_first_last_name( $text ) {
		$names      = explode( ' ', $text );
		$first_name = rgar( $names, 0 );
		$last_name  = '';
		if ( count( $names ) > 1 ) {
			$last_name = rgar( $names, count( $names ) - 1 );
		}

		$names_array = array( 'first_name' => $first_name, 'last_name' => $last_name );

		return $names_array;
	}

	// Convert submission_data into form_data for hooks backwards compatibility
	private function get_form_data( $submission_data, $form, $config ) {

		$form_data = array();

		// getting billing information
		$form_data['form_title'] = $submission_data['form_title'];
		$form_data['email']      = $submission_data['email'];
		$form_data['address1']   = $submission_data['address'];
		$form_data['address2']   = $submission_data['address2'];
		$form_data['city']       = $submission_data['city'];
		$form_data['state']      = $submission_data['state'];
		$form_data['zip']        = $submission_data['zip'];
		$form_data['country']    = $submission_data['country'];
		$form_data['phone']      = $submission_data['phone'];

		$form_data['card_number']     = $submission_data['card_number'];
		$form_data['expiration_date'] = $submission_data['card_expiration_date'];
		$form_data['security_code']   = $submission_data['card_security_code'];
		$names                        = $this->get_first_last_name( $submission_data['card_name'] );
		$form_data['first_name']      = $names['first_name'];
		$form_data['last_name']       = $names['last_name'];

		// form_data line items
		$i = 0;
		foreach ( $submission_data['line_items'] as $line_item ) {
			$form_data['line_items'][ $i ]['item_id']          = $line_item['id'];
			$form_data['line_items'][ $i ]['item_name']        = $line_item['name'];
			$form_data['line_items'][ $i ]['item_description'] = $line_item['description'];
			$form_data['line_items'][ $i ]['item_quantity']    = $line_item['quantity'];
			$form_data['line_items'][ $i ]['item_unit_price']  = $line_item['unit_price'];
			$form_data['line_items'][ $i ]['item_taxable']     = 'Y';
			$i ++;
		}

		$form_data['amount']     = $submission_data['payment_amount'];
		$form_data['fee_amount'] = $submission_data['setup_fee'];

		// need an easy way to filter the order info as it is not modifiable once it is added to the transaction object
		$form_data = gf_apply_filters( 'gform_authorizenet_form_data', $form['id'], $form_data, $form, $config );

		return $form_data;
	}

	// Create validation_result object  for hooks backwards compatibility
	private function get_valiation_result( $form, $message ) {

		$validation_result['is_valid'] = false;
		$validation_result['form']     = $form;

		foreach ( $validation_result['form']['fields'] as &$field ) {
			if ( $field->type == 'creditcard' ) {
				$field->failed_validation                   = true;
				$field->validation_message                  = $message;
				$validation_result['field_validation_page'] = $field->pageNumber;
				break;
			}

		}

		return $validation_result;
	}

	// Convert feed into config for hooks backwards compatibility
	private function get_config( $feed, $submission_data ) {

		$config = array();

		$config['id']        = $feed['id'];
		$config['form_id']   = $feed['form_id'];
		$config['is_active'] = $feed['is_active'];

		$config['meta']['type']               = rgar( $feed['meta'], 'transactionType' );
		$config['meta']['enable_receipt']     = rgar( $feed['meta'], 'enableReceipt' );
		$config['meta']['update_post_action'] = rgar( $feed['meta'], 'update_post_action' );

		$config['meta']['authorizenet_conditional_enabled'] = rgar( $feed['meta'], 'feed_condition_conditional_logic' );
		if ( $feed['meta']['feed_condition_conditional_logic'] ) {
			$config['meta']['authorizenet_conditional_field_id'] = $feed['meta']['feed_condition_conditional_logic_object']['conditionalLogic']['rules'][0]['fieldId'];
			$config['meta']['authorizenet_conditional_operator'] = $feed['meta']['feed_condition_conditional_logic_object']['conditionalLogic']['rules'][0]['operator'];
			$config['meta']['authorizenet_conditional_value']    = $feed['meta']['feed_condition_conditional_logic_object']['conditionalLogic']['rules'][0]['value'];
		}

		if ( $feed['meta']['transactionType'] == 'subscription' ) {
			$config['meta']['recurring_amount_field'] = $feed['meta']['recurringAmount'];
			$config['meta']['billing_cycle_number']   = $feed['meta']['billingCycle_length'];
			$config['meta']['billing_cycle_type']     = $feed['meta']['billingCycle_unit'] == 'day' ? 'D' : 'M';
			$config['meta']['recurring_times']        = $feed['meta']['recurringTimes'];
			$config['meta']['setup_fee_enabled']      = $feed['meta']['setupFee_enabled'];
			$config['meta']['setup_fee_amount_field'] = $feed['meta']['setupFee_product'];
			$config['meta']['trial_period_enabled']   = $feed['meta']['trial_enabled'];
			if ( $feed['meta']['trial_enabled'] ) {
				$config['meta']['trial_period_number'] = 1;
				$config['meta']['trial_amount']        = $submission_data['trial'];
			}

		}

		$config['meta']['api_settings_enabled'] = rgar( $feed['meta'], 'apiSettingsEnabled' );
		$config['meta']['api_mode']             = rgar( $feed['meta'], 'overrideMode' );
		$config['meta']['api_login']            = rgar( $feed['meta'], 'overrideLogin' );
		$config['meta']['api_key']              = rgar( $feed['meta'], 'overrideKey' );

		$config['meta']['customer_fields']['email']    = rgar( $feed['meta'], 'billingInformation_email' );
		$config['meta']['customer_fields']['address1'] = rgar( $feed['meta'], 'billingInformation_address' );
		$config['meta']['customer_fields']['address2'] = rgar( $feed['meta'], 'billingInformation_address2' );
		$config['meta']['customer_fields']['city']     = rgar( $feed['meta'], 'billingInformation_city' );
		$config['meta']['customer_fields']['state']    = rgar( $feed['meta'], 'billingInformation_state' );
		$config['meta']['customer_fields']['zip']      = rgar( $feed['meta'], 'billingInformation_zip' );
		$config['meta']['customer_fields']['country']  = rgar( $feed['meta'], 'billingInformation_country' );

		return $config;

	}

	/**
	 * Get version of Gravity Forms database.
	 *
	 * @since  2.4
	 * @access public
	 *
	 * @uses   GFFormsModel::get_database_version()
	 *
	 * @return string
	 */
	public static function get_gravityforms_db_version() {

		return method_exists( 'GFFormsModel', 'get_database_version' ) ? GFFormsModel::get_database_version() : GFForms::$version;

	}

	/**
	 * Get name for entry table.
	 *
	 * @since  2.4
	 * @access public
	 *
	 * @uses   GFFormsModel::get_entry_table_name()
	 * @uses   GFFormsModel::get_lead_table_name()
	 * @uses   GFPayPalPaymentsPro::get_gravityforms_db_version()
	 *
	 * @return string
	 */
	public static function get_entry_table_name() {

		return version_compare( self::get_gravityforms_db_version(), '2.3-dev-1', '<' ) ? GFFormsModel::get_lead_table_name() : GFFormsModel::get_entry_table_name();

	}

	/**
	 * Get name for entry meta table.
	 *
	 * @since  2.4
	 * @access public
	 *
	 * @uses   GFFormsModel::get_entry_meta_table_name()
	 * @uses   GFFormsModel::get_lead_meta_table_name()
	 * @uses   GFPayPalPaymentsPro::get_gravityforms_db_version()
	 *
	 * @return string
	 */
	public static function get_entry_meta_table_name() {

		return version_compare( self::get_gravityforms_db_version(), '2.3-dev-1', '<' ) ? GFFormsModel::get_lead_meta_table_name() : GFFormsModel::get_entry_meta_table_name();

	}


}
