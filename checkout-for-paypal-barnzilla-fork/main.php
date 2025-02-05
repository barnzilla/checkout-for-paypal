<?php
/*
  Plugin Name: Checkout for PayPal (Barnzilla fork)
  Version: 1.0.15
  Plugin URI: https://noorsplugin.com/checkout-for-paypal-wordpress-plugin/  
  Author: naa986
  Author URI: https://noorsplugin.com/
  Description: Add PayPal Smart Payment Buttons to Your Website
  Text Domain: checkout-for-paypal-barnzilla-fork
  Domain Path: /languages
 */

if (!defined('ABSPATH'))
    exit;

class CHECKOUT_FOR_PAYPAL {
    
    var $plugin_version = '1.0.15';
    var $plugin_url;
    var $plugin_path;
    
    function __construct() {
        define('CHECKOUT_FOR_PAYPAL_VERSION', $this->plugin_version);
        define('CHECKOUT_FOR_PAYPAL_SITE_URL', site_url());
        define('CHECKOUT_FOR_PAYPAL_HOME_URL', home_url());
        define('CHECKOUT_FOR_PAYPAL_URL', $this->plugin_url());
        define('CHECKOUT_FOR_PAYPAL_PATH', $this->plugin_path());
        $options = checkout_for_paypal_get_option();     
        if (isset($options['enable_debug']) && $options['enable_debug']=="1") {
            define('CHECKOUT_FOR_PAYPAL_DEBUG', true);
        } else {
            define('CHECKOUT_FOR_PAYPAL_DEBUG', false);
        }
        define('CHECKOUT_FOR_PAYPAL_DEBUG_LOG_PATH', $this->debug_log_path());
        $this->plugin_includes();
        $this->loader_operations();
    }

    function plugin_includes() {
        include_once('checkout-for-paypal-order.php');
        if(is_admin()){
            include_once('addons/checkout-for-paypal-addons-menu.php');
        }
    }

    function loader_operations() {
        add_action('plugins_loaded', array($this, 'plugins_loaded_handler'));
        if (is_admin()) {
            add_filter('plugin_action_links', array($this, 'add_plugin_action_links'), 10, 2);
        }
        add_action('admin_notices', array($this, 'admin_notice'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'plugin_scripts'));
        add_action('admin_menu', array($this, 'add_options_menu'));
        add_action('init', array($this, 'plugin_init'));
        add_filter('manage_coforpaypal_order_posts_columns', 'checkout_for_paypal_order_columns');
        add_action('manage_coforpaypal_order_posts_custom_column', 'checkout_for_paypal_custom_column', 10, 2);
        add_action('wp_ajax_coforpaypal_ajax_process_order', 'checkout_for_paypal_ajax_process_order');
        add_action('wp_ajax_nopriv_coforpaypal_ajax_process_order', 'checkout_for_paypal_ajax_process_order');
        add_shortcode('checkout_for_paypal', 'checkout_for_paypal_button_handler');
    }

    function plugins_loaded_handler() {  //Runs when plugins_loaded action gets fired
        load_plugin_textdomain( 'checkout-for-paypal', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );
    }

    function admin_notice() {
        if (CHECKOUT_FOR_PAYPAL_DEBUG) {  //debug is enabled. Check to make sure log file is writable
            $real_file = CHECKOUT_FOR_PAYPAL_DEBUG_LOG_PATH;
            if (!is_writeable($real_file)) {
                echo '<div class="updated"><p>' . __('Checkout for PayPal Debug log file is not writable. Please check to make sure that it has the correct file permission (ideally 644). Otherwise the plugin will not be able to write to the log file. The log file (log.txt) can be found in the root directory of the plugin - ', 'checkout-for-paypal') . '<code>' . CHECKOUT_FOR_PAYPAL_URL . '</code></p></div>';
            }
        }
    }

    function plugin_init() {
        //register order type
        checkout_for_paypal_register_order_type();
    }
    
    function enqueue_admin_scripts($hook) {
        if('coforpaypal_order_page_checkout-for-paypal-addons' != $hook) {
            return;
        }
        wp_register_style('checkout-for-paypal-addon-menu', CHECKOUT_FOR_PAYPAL_URL.'/addons/checkout-for-paypal-addons-menu.css');
        wp_enqueue_style('checkout-for-paypal-addon-menu');
    }
    
    function plugin_scripts() {
        if (!is_admin()) {
            global $post;
            if(is_a($post, 'WP_Post')
                    && has_shortcode($post->post_content, 'checkout_for_paypal')
                        || has_shortcode(get_post_meta($post->ID, 'checkout-for-paypal-custom-field', true), 'checkout_for_paypal')){
                $options = checkout_for_paypal_get_option();
                $args = array(
                    'client-id' => $options['app_client_id'],
                    'currency' => $options['currency_code'],                 
                );
                if(isset($options['enable_venmo']) && $options['enable_venmo'] == '1'){
                    $args['enable-funding'] = 'venmo';
                }
				
				if( ! empty( $_POST['bzc_paypal_app_client_id'] ) ) :
				
					$args['client-id'] = sanitize_text_field( $_POST['bzc_paypal_app_client_id'] );
				
				endif;
				
				if( ! empty( $_POST['bzc_paypal_app_currency'] ) ) :
				
					$args['currency'] = sanitize_text_field( $_POST['bzc_paypal_app_currency'] );
				
				endif;
				
                $sdk_js_url = add_query_arg($args, 'https://www.paypal.com/sdk/js');
                wp_enqueue_script('jquery');
                wp_register_script('checkout-for-paypal', $sdk_js_url, array('jquery'), null);
                wp_enqueue_script('checkout-for-paypal');
            }        
        }
    }
    
    function plugin_url() {
        if ($this->plugin_url)
            return $this->plugin_url;
        return $this->plugin_url = plugins_url(basename(plugin_dir_path(__FILE__)), basename(__FILE__));
    }

    function plugin_path() {
        if ($this->plugin_path)
            return $this->plugin_path;
        return $this->plugin_path = untrailingslashit(plugin_dir_path(__FILE__));
    }

    function debug_log_path() {
        return CHECKOUT_FOR_PAYPAL_PATH . '/log.txt';
    }

    function add_plugin_action_links($links, $file) {
        if ($file == plugin_basename(dirname(__FILE__) . '/main.php')) {
            $links[] = '<a href="'.esc_url(admin_url('edit.php?post_type=coforpaypal_order&page=checkout-for-paypal-settings')).'">'.__('Settings', 'checkout-for-paypal').'</a>';
        }
        return $links;
    }

    function add_options_menu() {
        if (is_admin()) {
            add_submenu_page('edit.php?post_type=coforpaypal_order', __('Settings', 'checkout-for-paypal'), __('Settings', 'checkout-for-paypal'), 'manage_options', 'checkout-for-paypal-settings', array($this, 'options_page'));
            add_submenu_page('edit.php?post_type=coforpaypal_order', __('Debug', 'checkout-for-paypal'), __('Debug', 'checkout-for-paypal'), 'manage_options', 'checkout-for-paypal-debug', array($this, 'debug_page'));
            add_submenu_page('edit.php?post_type=coforpaypal_order', __('Add-ons', 'checkout-for-paypal'), __('Add-ons', 'checkout-for-paypal'), 'manage_options', 'checkout-for-paypal-addons', 'checkout_for_paypal_display_addons_menu');
        }
    }

    function options_page() {
        $plugin_tabs = array(
            'checkout-for-paypal-settings' => __('General', 'checkout-for-paypal')
        );
        echo '<div class="wrap"><h2>'.__('Checkout for PayPal', 'checkout-for-paypal').' v' . CHECKOUT_FOR_PAYPAL_VERSION . '</h2>';
        $url = 'https://noorsplugin.com/checkout-for-paypal-wordpress-plugin/';
        $link_msg = sprintf(__( 'Please visit the <a target="_blank" href="%s">Checkout for PayPal</a> documentation page for instructions.', 'checkout-for-paypal' ), esc_url($url));
        $allowed_html_tags = array(
            'a' => array(
                'href' => array(),
                'target' => array()
            )
        );
        echo '<div class="update-nag">'.wp_kses($link_msg, $allowed_html_tags).'</div>';
        echo '<div id="poststuff"><div id="post-body">';
        $current = '';
        $tab = '';
        if (isset($_GET['page'])) {
            $current = sanitize_text_field($_GET['page']);
            if (isset($_GET['tab'])) {
                $tab = sanitize_text_field($_GET['tab']);
                $current .= "&tab=" . $tab;
            }
        }
        $content = '';
        $content .= '<h2 class="nav-tab-wrapper">';
        foreach ($plugin_tabs as $location => $tabname) {
            if ($current == $location) {
                $class = ' nav-tab-active';
            } else {
                $class = '';
            }
            $content .= '<a class="nav-tab' . $class . '" href="?post_type=coforpaypal_order&page=' . $location . '">' . $tabname . '</a>';
        }
        $content .= '</h2>';
        $allowed_html_tags = array(
            'a' => array(
                'href' => array(),
                'class' => array()
            ),
            'h2' => array(
                'href' => array(),
                'class' => array()
            )
        );
        echo wp_kses($content, $allowed_html_tags);

        $this->general_settings();

        echo '</div></div>';
        echo '</div>';
    }

    function general_settings() {
        if (isset($_POST['checkout_for_paypal_update_settings'])) {
            $nonce = sanitize_text_field($_REQUEST['_wpnonce']);
            if (!wp_verify_nonce($nonce, 'checkout_for_paypal_general_settings')) {
                wp_die(__('Error! Nonce Security Check Failed! please save the general settings again.', 'checkout-for-paypal'));
            }
            $app_client_id = '';
            if(isset($_POST['app_client_id']) && !empty($_POST['app_client_id'])){
                $app_client_id = sanitize_text_field($_POST['app_client_id']);
            }
            $currency_code = '';
            if(isset($_POST['currency_code']) && !empty($_POST['currency_code'])){
                $currency_code = sanitize_text_field($_POST['currency_code']);
            }
            $return_url = '';
            if(isset($_POST['return_url']) && !empty($_POST['return_url'])){
                $return_url = esc_url_raw($_POST['return_url']);
            }
            $enable_venmo = (isset($_POST['enable_venmo']) && $_POST['enable_venmo'] == '1') ? '1' : '';
            $paypal_options = array();
            $paypal_options['app_client_id'] = $app_client_id;
            $paypal_options['currency_code'] = $currency_code;
            $paypal_options['return_url'] = $return_url;
            $paypal_options['enable_venmo'] = $enable_venmo;
            checkout_for_paypal_update_option($paypal_options);
            echo '<div id="message" class="updated fade"><p><strong>';
            echo __('Settings Saved', 'checkout-for-paypal').'!';
            echo '</strong></p></div>';
        }
        $paypal_options = checkout_for_paypal_get_option();
        
        ?>

        <form method="post" action="">
            <?php wp_nonce_field('checkout_for_paypal_general_settings'); ?>

            <table class="form-table">

                <tbody>
                    
                    <tr valign="top">
                        <th scope="row"><label for="app_client_id"><?php _e('Client ID', 'checkout-for-paypal');?></label></th>
                        <td><input name="app_client_id" type="text" id="app_client_id" value="<?php echo esc_attr($paypal_options['app_client_id']); ?>" class="regular-text">
                            <p class="description"><?php _e('The client ID for your PayPal REST API app', 'checkout-for-paypal');?></p></td>
                    </tr>

                    <tr valign="top">
                        <th scope="row"><label for="currency_code"><?php _e('Currency Code', 'checkout-for-paypal');?></label></th>
                        <td><input name="currency_code" type="text" id="currency_code" value="<?php echo esc_attr($paypal_options['currency_code']); ?>" class="regular-text">
                            <p class="description"><?php _e('The default currency of the payment', 'checkout-for-paypal');?> (<?php _e('example', 'checkout-for-paypal');?>: USD, CAD, GBP, EUR)</p></td>
                    </tr>
                    
                    <tr valign="top">
                        <th scope="row"><label for="return_url"><?php _e('Return URL', 'checkout-for-paypal');?></label></th>
                        <td><input name="return_url" type="text" id="return_url" value="<?php echo esc_url($paypal_options['return_url']); ?>" class="regular-text">
                            <p class="description"><?php _e('The page URL to which the customer will be redirected after a successful payment.', 'checkout-for-paypal');?></p></td>
                    </tr>
                    
                    <tr valign="top">
                        <th scope="row"><?php _e('Enable Venmo', 'wp-stripe-checkout');?></th>
                        <td> <fieldset><legend class="screen-reader-text"><span>Enable Venmo</span></legend><label for="enable_venmo">
                                    <input name="enable_venmo" type="checkbox" id="enable_venmo" <?php if (isset($paypal_options['enable_venmo']) && $paypal_options['enable_venmo'] == '1') echo ' checked="checked"'; ?> value="1">
                                    <?php _e('Check this option to add the Venmo button to your checkout integration.', 'checkout-for-paypal');?></label>
                            </fieldset></td>
                    </tr>

                </tbody>

            </table>

            <p class="submit"><input type="submit" name="checkout_for_paypal_update_settings" id="checkout_for_paypal_update_settings" class="button button-primary" value="<?php _e('Save Changes', 'checkout-for-paypal');?>"></p></form>

        <?php
    }

    function debug_page() {
        ?>
        <div class="wrap">
            <h2><?php _e('Checkout for PayPal Debug Log', 'checkout-for-paypal');?></h2>
            <div id="poststuff">
                <div id="post-body">
                    <?php
                    if (isset($_POST['checkout_for_paypal_update_log_settings'])) {
                        $nonce = sanitize_text_field($_REQUEST['_wpnonce']);
                        if (!wp_verify_nonce($nonce, 'checkout_for_paypal_debug_log_settings')) {
                            wp_die(__('Error! Nonce Security Check Failed! please save the debug settings again.', 'checkout-for-paypal'));
                        }
                        $options = array();
                        $options['enable_debug'] = (isset($_POST["enable_debug"]) && $_POST["enable_debug"] == '1') ? '1' : '';
                        checkout_for_paypal_update_option($options);
                        echo '<div id="message" class="updated fade"><p>'.__('Settings Saved', 'checkout-for-paypal').'!</p></div>';
                    }
                    if (isset($_POST['checkout_for_paypal_reset_log'])) {
                        $nonce = $_REQUEST['_wpnonce'];
                        if (!wp_verify_nonce($nonce, 'checkout_for_paypal_reset_log_settings')) {
                            wp_die(__('Error! Nonce Security Check Failed! please reset the debug log file again.', 'checkout-for-paypal'));
                        }
                        if (checkout_for_paypal_reset_log()) {
                            echo '<div id="message" class="updated fade"><p>'.__('Debug log file has been reset', 'checkout-for-paypal').'!</p></div>';
                        } else {
                            echo '<div id="message" class="error"><p>'.__('Debug log file could not be reset', 'checkout-for-paypal').'!</p></div>';
                        }
                    }
                    $real_file = CHECKOUT_FOR_PAYPAL_DEBUG_LOG_PATH;
                    $content = file_get_contents($real_file);
                    $options = checkout_for_paypal_get_option();
                    ?>
                    <div id="template"><textarea cols="70" rows="25" name="checkout_for_paypal_log" id="checkout_for_paypal_log"><?php echo esc_textarea($content); ?></textarea></div>                     
                    <form method="post" action="">
                        <?php wp_nonce_field('checkout_for_paypal_debug_log_settings'); ?>
                        <table class="form-table">
                            <tbody>
                                <tr valign="top">
                                    <th scope="row"><?php _e('Enable Debug', 'checkout-for-paypal');?></th>
                                    <td> <fieldset><legend class="screen-reader-text"><span>Enable Debug</span></legend><label for="enable_debug">
                                                <input name="enable_debug" type="checkbox" id="enable_debug" <?php if ($options['enable_debug'] == '1') echo ' checked="checked"'; ?> value="1">
                                                <?php _e('Check this option if you want to enable debug', 'checkout-for-paypal');?></label>
                                        </fieldset></td>
                                </tr>

                            </tbody>

                        </table>
                        <p class="submit"><input type="submit" name="checkout_for_paypal_update_log_settings" id="checkout_for_paypal_update_log_settings" class="button button-primary" value="<?php _e('Save Changes', 'checkout-for-paypal');?>"></p>
                    </form>
                    <form method="post" action="">
                        <?php wp_nonce_field('checkout_for_paypal_reset_log_settings'); ?>                            
                        <p class="submit"><input type="submit" name="checkout_for_paypal_reset_log" id="checkout_for_paypal_reset_log" class="button" value="<?php _e('Reset Log', 'checkout-for-paypal');?>"></p>
                    </form>
                </div>         
            </div>
        </div>
        <?php
    }

}

$GLOBALS['checkout_for_paypal'] = new CHECKOUT_FOR_PAYPAL();

function checkout_for_paypal_button_handler($atts) {
		
	# Check for shortcodes/dynamic values in attributes
	# Filter any shortcodes by their hooks
	foreach( $atts as $meta_key => $meta_value ) {
	
		$atts[$meta_key] = do_shortcode(
			str_replace(
				array( '{{', '}}' ),
				array( '[', ']' ),
				$atts[$meta_key]
			)
		);
	
	}
	
    $atts = array_map('sanitize_text_field', $atts);
    $description = '';
	$atts['amount'] = 1;
    /*
    if(!isset($atts['item_description']) || empty($atts['item_description'])){
        return __('You need to provide a valid description', 'checkout-for-paypal');
    }
    */
    if(isset($atts['item_description']) && !empty($atts['item_description'])){
        $description = $atts['item_description'];
    }
    
    $options = checkout_for_paypal_get_option();
    $currency = $options['currency_code'];
    /* There seems to be a bug where currency override doesn't work on a per button basis
    if(isset($atts['currency']) && !empty($atts['currency'])){
        $currency = $atts['currency'];
    }
    */
    $return_url = (isset($options['return_url']) && !empty($options['return_url'])) ? $options['return_url'] : '';
    if(isset($atts['return_url']) && !empty($atts['return_url'])){
        $return_url = $atts['return_url'];
    }
    $return_output = '';
    if(!empty($return_url)){
        $return_output = 'window.location.replace("'.$return_url.'");';
    }
    $no_shipping = '';
    if(isset($atts['no_shipping']) && $atts['no_shipping']=='1'){
        $no_shipping .= <<<EOT
        application_context: {
            shipping_preference: "NO_SHIPPING",
        },        
EOT;
    }
    $width = '300';
    if(isset($atts['width']) && !empty($atts['width'])){
        $width = $atts['width'];
    }
    $layout = 'vertical';
    if(isset($atts['layout']) && $atts['layout'] == 'horizontal'){
        $layout = 'horizontal';
    }
    $color = 'gold';
    if(isset($atts['color']) && $atts['color'] == 'blue'){
        $color = 'blue';
    }
    else if(isset($atts['color']) && $atts['color'] == 'silver'){
        $color = 'silver';
    }
    else if(isset($atts['color']) && $atts['color'] == 'white'){
        $color = 'white';
    }
    else if(isset($atts['color']) && $atts['color'] == 'black'){
        $color = 'black';
    }
    $shape = 'rect';
    if(isset($atts['shape']) && $atts['shape'] == 'pill'){
        $shape = 'pill';
    }
    $id = uniqid();
    $atts['id'] = $id;
    $button_code = '';
    $button_code = apply_filters('checkout_for_paypal_button', $button_code, $atts);
    if(!empty($button_code)){
        return $button_code;
    }
    if(!isset($atts['amount']) || !is_numeric($atts['amount'])){
        return __('You need to provide a valid price amount', 'checkout-for-paypal');
    }
    $esc_js = 'esc_js';
    $button_id = 'coforpaypal-button-'.$id;
    $button_container_id = 'coforpaypal-button-container-'.$id;
    $button_code = '<div id="'.esc_attr($button_container_id).'" style="'.esc_attr('max-width: '.$width.'px;').'">';
    $button_code .= '<div id="'.esc_attr($button_id).'" style="'.esc_attr('max-width: '.$width.'px;').'"></div>';
    $button_code .= '</div>';
    $ajax_url = admin_url('admin-ajax.php');
	
	$purchase_units = $atts['paypal-items'];	
	
	$atts_return_url_path = get_posts(
		array(
			'post_type'              => 'page',
			'title'                  => $atts['return-url-path'],
			'post_status'            => 'publish',
			'numberposts'            => 1,
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false,           
			'orderby'                => 'post_date ID',
			'order'                  => 'ASC',
		)
	);
	
	$atts_return_url_path = get_permalink( $atts_return_url_path[0]->ID );
	
	$return_output = 'window.location.replace("' . esc_url( $atts_return_url_path . '?bzc_id=' . sanitize_text_field( $_POST['bzc_id'] ) ) . '");';
		                	
	if( $purchase_units ) :
	
		$button_code .= <<<EOT
			<script>
			jQuery(document).ready(function() {
					
				function initPayPalButton{$id}() {
					paypal.Buttons({
						style: {
							layout: '{$layout}',
							color: '{$color}',
							shape: '{$shape}'
						},
						
						onInit: function (data, actions) {},  
						
						onClick: function () {},    
							
						createOrder: function(data, actions) {
							return actions.order.create({
								$purchase_units
								$no_shipping    
							});
						},
									
						onApprove: function(data, actions) {
							return actions.order.capture().then(function(details) {
								//console.log('Transaction completed by ' + details.payer.name.given_name);
								//console.log(details);
								var data = {
									'action': "coforpaypal_ajax_process_order",
									'coforpaypal_ajax_process_order': "1",
									'details': details 
								};  
								jQuery.ajax({
									url : "{$ajax_url}",
									type : "POST",
									data : data,
									success: function(response) {
										//console.log(response);
										$return_output
									}
								});
							});
						},
											
						onError: function (err) {
							console.log(err);
						}
							
					}).render('#$button_id');
				}
				initPayPalButton{$id}();
			});                     
			</script>        
		EOT;
	
	else :
	
		$button_code = do_shortcode(
			
			'
			
				</form><form name="bzc-form" id="bzc-form" method="post" action="' . esc_url( $atts_return_url_path . '?bzc_id=' . sanitize_text_field( $_POST['bzc_id'] ) ) . '&paypal_skipped=true">
							
					[bzc-row][bzc-form-field column-size="1/5" type="submit" class="fusion-button button-flat fusion-button-default-size button-default fusion-button-default button-1 fusion-button-default-span fusion-button-default-type" name="submit" value="Submit" required="true"][/bzc-row]
					
				</form>
			
			'
		
		);
	
	endif;
    
    return $button_code;
}

function checkout_for_paypal_ajax_process_order(){
    checkout_for_paypal_debug_log('Received a response from frontend', true);
    if(!isset($_POST['coforpaypal_ajax_process_order'])){
        wp_die();
    }
    checkout_for_paypal_debug_log('Received a notification from PayPal', true);
    $post_data = $_POST;
    array_walk_recursive($post_data, function(&$v) { $v = sanitize_text_field($v); });
    checkout_for_paypal_debug_log_array($post_data, true);
    if(!isset($post_data['details'])){
        checkout_for_paypal_debug_log("No transaction details. This payment cannot be processed.", false);
        wp_die();
    }
    $details = $post_data['details'];
    if(!isset($details['payer'])){
        checkout_for_paypal_debug_log("No payer data. This payment cannot be processed.", false);
        wp_die();
    }
    $payer = $details['payer'];
    if(!isset($details['purchase_units'][0])){
        checkout_for_paypal_debug_log("No purchase unit data. This payment cannot be processed.", false);
        wp_die();
    }
    $purchase_units = $details['purchase_units'][0];
    if(!isset($purchase_units['payments']['captures'][0])){
        checkout_for_paypal_debug_log("No payment capture data. This payment cannot be processed.", false);
        wp_die();
    }
    $capture = $purchase_units['payments']['captures'][0];
    $payment_status = '';
    if (isset($capture['status'])) {
        $payment_status = sanitize_text_field($capture['status']);
        checkout_for_paypal_debug_log("Payment Status - " . $payment_status, true);
    }
    if (isset($capture['status']['status_details']['reason'])) {
        $status_reason = sanitize_text_field($capture['status']['status_details']['reason']);
        checkout_for_paypal_debug_log("Reason - " . $status_reason, true);
    }
    $txn_id = '';
    if (isset($capture['id'])) {
        $txn_id = sanitize_text_field($capture['id']);
    } else {
        checkout_for_paypal_debug_log("No transaction ID. This payment cannot be processed.", false);
        wp_die();
    }
    $args = array(
        'post_type' => 'coforpaypal_order',
        'meta_query' => array(
            array(
                'key' => '_txn_id',
                'value' => $txn_id,
                'compare' => '=',
            ),
        ),
    );
    $query = new WP_Query($args);
    if ($query->have_posts()) {  //a record already exists
        checkout_for_paypal_debug_log("An order with this transaction ID already exists. This payment will not be processed.", false);
        wp_die();
    } 
    $payer_name = '';
    $first_name = '';
    if (isset($payer['name']['given_name'])) {
        $first_name = sanitize_text_field($payer['name']['given_name']);
        $payer_name .= $first_name;
    }
    $last_name = '';
    if (isset($payer['name']['surname'])) {
        $last_name = sanitize_text_field($payer['name']['surname']);
        $payer_name .= ' '.$last_name;
    }
    $email = '';
    if (isset($payer['email_address'])) {
        $email = sanitize_email($payer['email_address']);
    }
    $item_description = '';
    if (isset($purchase_units['description'])) {
        $item_description = sanitize_text_field($purchase_units['description']);
    }
    $mc_gross = '';
    if (isset($purchase_units['amount']['value'])) {
        $mc_gross = sanitize_text_field($purchase_units['amount']['value']);
    }
    $currency = '';
    if (isset($purchase_units['amount']['currency_code'])) {
        $currency = sanitize_text_field($purchase_units['amount']['currency_code']);
    }
    $ship_to_name = '';
    if (isset($purchase_units['shipping']['name'])) {
        $ship_to_name = isset($purchase_units['shipping']['name']['full_name']) ? sanitize_text_field($purchase_units['shipping']['name']['full_name']) : '';
    }
    /*
    if(empty($ship_to_name)){
        $ship_to_name = $first_name.' '.$last_name;
    }
    */
    $ship_to = '';
    if (isset($purchase_units['shipping']['address'])) {
        $address_street = isset($purchase_units['shipping']['address']['address_line_1']) ? sanitize_text_field($purchase_units['shipping']['address']['address_line_1']) : '';
        $ship_to .= !empty($address_street) ? $address_street.'<br />' : '';
        $address_city = isset($purchase_units['shipping']['address']['admin_area_2']) ? sanitize_text_field($purchase_units['shipping']['address']['admin_area_2']) : '';
        $ship_to .= !empty($address_city) ? $address_city.', ' : '';
        $address_state = isset($purchase_units['shipping']['address']['admin_area_1']) ? sanitize_text_field($purchase_units['shipping']['address']['admin_area_1']) : '';
        $ship_to .= !empty($address_state) ? $address_state.' ' : '';
        $address_zip = isset($purchase_units['shipping']['address']['postal_code']) ? sanitize_text_field($purchase_units['shipping']['address']['postal_code']) : '';
        $ship_to .= !empty($address_zip) ? $address_zip.'<br />' : '';
        $address_country = isset($purchase_units['shipping']['address']['country_code']) ? sanitize_text_field($purchase_units['shipping']['address']['country_code']) : '';
        $ship_to .= !empty($address_country) ? $address_country : '';
    }
    $checkout_for_paypal_order = array(
        'post_title' => 'order',
        'post_type' => 'coforpaypal_order',
        'post_content' => '',
        'post_status' => 'publish',
    );
    checkout_for_paypal_debug_log("Inserting order information", true);
    $post_id = wp_insert_post($checkout_for_paypal_order, true);
    if (is_wp_error($post_id)) {
        checkout_for_paypal_debug_log("Error inserting order information: ".$post_id->get_error_message(), false);
        wp_die();
    }
    if (!$post_id) {
        checkout_for_paypal_debug_log("Order information could not be inserted", false);
        wp_die();
    }
    $post_updated = false;
    if ($post_id > 0) {
        $post_content = '';
        if(!empty($item_description)){
            $post_content .= '<strong>Item Description:</strong> '.$item_description.'<br />';
        }
        if(!empty($mc_gross)){
            $post_content .= '<strong>Amount:</strong> '.$mc_gross.'<br />';
        }
        if(!empty($currency)){
            $post_content .= '<strong>Currency:</strong> '.$currency.'<br />';
        }
        if(!empty($payer_name)){
            $post_content .= '<strong>Payer Name:</strong> '.$payer_name.'<br />';
        }
        if(!empty($email)){
            $post_content .= '<strong>Email:</strong> '.$email.'<br />';
        }
        if(!empty($ship_to)){
            $ship_to = '<h2>'.__('Ship To', 'wp-paypal').'</h2><br />'.$ship_to_name.'<br />'.$ship_to.'<br />';
        }
        $post_content .= $ship_to;
        $post_content .= '<h2>'.__('Payment Data', 'wp-paypal').'</h2><br />';
        $post_content .= print_r($details, true);
        $updated_post = array(
            'ID' => $post_id,
            'post_title' => $post_id,
            'post_type' => 'coforpaypal_order',
            'post_content' => $post_content
        );
        $updated_post_id = wp_update_post($updated_post, true);
        if (is_wp_error($updated_post_id)) {
            checkout_for_paypal_debug_log("Error updating order information: ".$updated_post_id->get_error_message(), false);
            wp_die();
        }
        if (!$updated_post_id) {
            checkout_for_paypal_debug_log("Order information could not be updated", false);
            wp_die();
        }
        if ($updated_post_id > 0) {
            $post_updated = true;
        }
    }
    //save order information
    if ($post_updated) {
        update_post_meta($post_id, '_txn_id', $txn_id);
        update_post_meta($post_id, '_first_name', $first_name);
        update_post_meta($post_id, '_last_name', $last_name);
        update_post_meta($post_id, '_email', $email);
        update_post_meta($post_id, '_mc_gross', $mc_gross);
        update_post_meta($post_id, '_payment_status', $payment_status);
        checkout_for_paypal_debug_log("Order information updated", true);
        $details['post_order_id'] = $post_id;
        do_action('checkout_for_paypal_order_processed', $details);
		
		# Update form data table that user completed before proceeding to PayPal
		global $wpdb;
		
		$explode = explode( '-', sanitize_text_field( $purchase_units['custom_id'] ) );
		$bzc_id = $explode[0];
		$wp_user_id = $explode[1];
		
		$sql_placeholders = null;
		$sql_data = array();
		
		$variables_to_add = array(
			'_txn_id' => $txn_id,
			'_first_name' => $first_name,
			'_last_name' => $last_name,
			'_email' => $email,
			'_address_street' => $address_street,
			'_address_city' => $address_city,
			'_address_state' => $address_state,
			'_address_zip' => $address_zip,
			'_address_country' => $address_country,
			'_mc_gross' => $mc_gross,
			'_payment_status' => $payment_status
		);
		
		$current_date_time = new DateTime( 'now', new DateTimeZone( 'America/Toronto' ) );
	
		foreach( $variables_to_add as $meta_key => $meta_value ) {
			
			$sql_placeholders .= '( %s, %s, %d, %s, %s ),';
			array_push( $sql_data, $bzc_id, $current_date_time->format( 'Y-m-d H:i:s' ), $wp_user_id, $meta_key, wp_unslash( $meta_value ) );
			 
		}
		
		$query = $wpdb->query( $wpdb->prepare( 
			'
				REPLACE INTO `' . $wpdb->base_prefix . 'bzc_form`
				( bzc_id, date_time, wp_user_id, meta_key, meta_value )
				VALUES ' . rtrim( $sql_placeholders, ',' ) . '
			', 
				$sql_data
		) );
		
    } else {
        checkout_for_paypal_debug_log("Order information could not be updated", false);
        wp_die();
    }
    checkout_for_paypal_debug_log("Payment processing completed", true, true);   
    wp_die();   
}

function checkout_for_paypal_get_option(){
    $options = get_option('checkout_for_paypal_options');
    if(!is_array($options)){
        $options = checkout_for_paypal_get_empty_options_array();
    }
    return $options;
}

function checkout_for_paypal_update_option($new_options){
    $empty_options = checkout_for_paypal_get_empty_options_array();
    $options = checkout_for_paypal_get_option();
    if(is_array($options)){
        $current_options = array_merge($empty_options, $options);
        $updated_options = array_merge($current_options, $new_options);
        update_option('checkout_for_paypal_options', $updated_options);
    }
    else{
        $updated_options = array_merge($empty_options, $new_options);
        update_option('checkout_for_paypal_options', $updated_options);
    }
}

function checkout_for_paypal_get_empty_options_array(){
    $options = array();
    $options['app_client_id'] = '';
    $options['currency_code'] = '';
    $options['return_url'] = '';
    $options['enable_venmo'] = '';
    $options['enable_debug'] = '';
    return $options;
}

function checkout_for_paypal_debug_log($msg, $success, $end = false) {
    if (!CHECKOUT_FOR_PAYPAL_DEBUG) {
        return;
    }
    $date_time = date('F j, Y g:i a');//the_date('F j, Y g:i a', '', '', FALSE);
    $text = '[' . $date_time . '] - ' . (($success) ? 'SUCCESS :' : 'FAILURE :') . $msg . "\n";
    if ($end) {
        $text .= "\n------------------------------------------------------------------\n\n";
    }
    // Write to log.txt file
    $fp = fopen(CHECKOUT_FOR_PAYPAL_DEBUG_LOG_PATH, 'a');
    fwrite($fp, $text);
    fclose($fp);  // close file
}

function checkout_for_paypal_debug_log_array($array_msg, $success, $end = false) {
    if (!CHECKOUT_FOR_PAYPAL_DEBUG) {
        return;
    }
    $date_time = date('F j, Y g:i a');//the_date('F j, Y g:i a', '', '', FALSE);
    $text = '[' . $date_time . '] - ' . (($success) ? 'SUCCESS :' : 'FAILURE :') . "\n";
    ob_start();
    print_r($array_msg);
    $var = ob_get_contents();
    ob_end_clean();
    $text .= $var;
    if ($end) {
        $text .= "\n------------------------------------------------------------------\n\n";
    }
    // Write to log.txt file
    $fp = fopen(CHECKOUT_FOR_PAYPAL_DEBUG_LOG_PATH, 'a');
    fwrite($fp, $text);
    fclose($fp);  // close filee
}

function checkout_for_paypal_reset_log() {
    $log_reset = true;
    $date_time = date('F j, Y g:i a');//the_date('F j, Y g:i a', '', '', FALSE);
    $text = '[' . $date_time . '] - SUCCESS : Log reset';
    $text .= "\n------------------------------------------------------------------\n\n";
    $fp = fopen(CHECKOUT_FOR_PAYPAL_DEBUG_LOG_PATH, 'w');
    if ($fp != FALSE) {
        @fwrite($fp, $text);
        @fclose($fp);
    } else {
        $log_reset = false;
    }
    return $log_reset;
}
