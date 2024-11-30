<?php
/**
 * Plugin Deactivation Class
 * Collects Feedback from user about deactivation
 *
 * @package     Xylus_Plugin_Deactivation
 * @copyright   Copyright (c) 2021, Xylus Themes
 * @since       1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The admin-specific functionality of the plugin.
 *
 * @package     Import_Eventbrite_Events
 * @subpackage  Import_Eventbrite_Events/admin
 * @author     Dharmesh Patel <dspatel44@gmail.com>
 */
if ( ! class_exists( 'IFE_Plugin_Deactivation' ) ) {
    class IFE_Plugin_Deactivation {

        private $prefix = 'ife_';
        private $slug = 'import-facebook-events';
        private $plugin_name;
        private $plugin_version = '1.0.0';
        private $api_url = 'https://api.xylusthemes.com/api/v1/';

        /**
         * Initialize the class and set its properties.
         *
         * @since    1.0.0
         */
        public function __construct() {
			$this->plugin_name =  __('Import Social Events', 'import-facebook-events' );
			if ( defined( 'IFE_VERSION' ) ) {
				$this->plugin_version = IFE_VERSION;
			}

            add_action( 'admin_footer', array( $this, 'deactivation_feedback_form') );
            add_action( 'wp_ajax_'.$this->prefix.'plugin_deactivation_feedback', array( $this, 'submit_plugin_deactivation_feedback') );
		}

		public function get_deactivation_reasons() {
			return array(
				'confusing' => __('I couldn\'t understand how to make it work', 'import-facebook-events' ),
				'better_plugin' => __('I found a better plugin', 'import-facebook-events' ),
				'feature_request' => __('The plugin is great, but I need specific feature that you don\'t support', 'import-facebook-events' ),
				'buggy' => __('Plugin has bugs and it\'s not working', 'import-facebook-events' ),
				'wrong_plugin' => __('It\'s not what I was looking for', 'import-facebook-events' ),
				'not_working' => __('Plugin didn\'t work as expected', 'import-facebook-events' ),
				'temporary' => __('It\'s temporary deactivatation, for debug an issue', 'import-facebook-events' ),
				'other' => __('Other reasons', 'import-facebook-events' ),
			);
        }

        function generate_ticket(){
            $url = $this->api_url.'generateTicket';
            $user = wp_get_current_user();
            $headers = array( 'Content-Type' => 'application/json' );
            $args = array(
                'method' =>'POST',
                'body'    => json_encode(array('customer_email' => $user->user_email )),
                'blocking' => true,
                'headers' => $headers,
            );

		    $response = wp_remote_post( $url, $args );
            if ( is_wp_error( $response ) ) {
                return false;
            }

		    return wp_remote_retrieve_body($response);
        }

        function submit_plugin_deactivation_feedback(){
            if ( !wp_verify_nonce( $_REQUEST['nonce'], $this->prefix.'plugin_deactivation_feedback')) {
                exit("nonce verification failed");
            }

            $url = $this->api_url.'feedback';
            $credentials = $this->generate_ticket();
            if(!$credentials){
                die();
			}

			$credentials = json_decode($credentials);
            $user = wp_get_current_user();
            $timestamp = $credentials->timestamp;
            $client_id = $credentials->client_id;
            $client_secret = $credentials->client_secret;
            $customer_email = $user->user_email;
            $customer_name = $user->user_firstname. ' '.$user->user_lastname;
            $deactivation_reason = sanitize_text_field( $_REQUEST['reason'] );
            $deactivation_reason_message = $this->get_deactivation_reasons()[$deactivation_reason];
            $customer_query = sanitize_text_field( $_REQUEST['customerQuery'] );

            $data = array(
                "type" => "plugin_deactivation",
                "site_url" => get_site_url(),
                "customer_name" => $customer_name,
                "customer_email" => $customer_email,
                "plugin" => $this->slug,
                "plugin_name" => $this->plugin_name,
                "plugin_version" => $this->plugin_version,
                "plugin_version" => $this->plugin_version,
                "deactivation_reason" => $deactivation_reason,
                "deactivation_reason_message" => $deactivation_reason_message,
                "query" => $customer_query
            );

            $plain_string = $customer_email .  $timestamp . $client_secret;
            $sha512_hash  = hash("sha512", $plain_string);

            $body = json_encode($data);
            $headers = array( 'Content-Type' => 'application/json');
            $headers['Client-Id'] = $client_id;
            $headers['Timestamp'] = $timestamp;
            $headers['Authorization'] = $sha512_hash;
            $args = array(
                'method' =>'POST',
                'body' => $body,
                'blocking' => true,
                'headers' => $headers
            );
			$response = wp_remote_post( $url, $args );
            if ( is_wp_error( $response ) ) {
                $error_message = $response->get_error_message();
                echo "Something went wrong: $error_message";
                exit();
            }

            die(true);
        }

        public function deactivation_feedback_form() {
            $wp_screen = get_current_screen();
            $page_id = $wp_screen->id;

            // Load only for WP admin plugins page
            if($page_id !== 'plugins'){
                return;
            }
            wp_enqueue_style( 'wp-jquery-ui-dialog');
            wp_enqueue_script( 'jquery-ui-dialog');

            $deactivate_reasons = $this->get_deactivation_reasons();
        	?>

            <script>
                jQuery(document).ready(function() {
					var dataReason = jQuery('input:radio[name="<?php echo $this->prefix; ?>deactivatation_reason_radio"]').val();
                    jQuery('a#deactivate-<?php echo $this->slug; ?>').click(function (e) {
                        e.preventDefault();
                        var pluginDeactivateURL = jQuery(this).attr('href');
                        jQuery('#<?php echo $this->slug; ?>-deactivate-dialog' ).dialog({
                            'dialogClass'   : '<?php echo $this->slug . "-deactivate-dialog"; ?>',
                            'modal'         : true,
                            'closeOnEscape' : true,
                            width: 600,
                            'buttons'       : [
                                {
                                    text: "<?php _e('Submit & Deactivate', 'import-facebook-events' ); ?>",
                                    class: 'button button-primary',
                                    click: function() {
										var that = this;
										var dataQuery = jQuery('#<?php echo $this->prefix; ?>customer_query').val();
										if(dataReason == 'other' && !dataQuery){
											jQuery('#<?php echo $this->prefix; ?>customer_query').focus();
											return false;
										}
										jQuery('#<?php echo $this->prefix; ?>deactivatation_form').hide();
										jQuery('.<?php echo $this->prefix; ?>deactivatation_loading').show();
                                        jQuery.ajax({
                                            type : "post",
                                            dataType : "json",
                                            url : "<?php echo admin_url('admin-ajax.php?action='.$this->prefix.'plugin_deactivation_feedback&nonce='.wp_create_nonce($this->prefix.'plugin_deactivation_feedback')); ?>",
                                            data : {
                                                action: "<?php echo $this->prefix; ?>plugin_deactivation_feedback",
                                                reason: dataReason,
                                                customerQuery: dataQuery
                                            },
                                        }).always( function(){
											jQuery( that ).dialog( "close" );
											window.location.href=pluginDeactivateURL;
										});
                                    }
                                },
                                {
                                    text: "<?php _e('Skip & Deactivate', 'import-facebook-events' ); ?>",
                                    class: 'button',
                                    click: function() {
                                        jQuery( this ).dialog( "close" );
                                        window.location.href=pluginDeactivateURL;
                                    }
                                }
                            ]
                        });
                    });

                    jQuery('input:radio[name="<?php echo $this->prefix; ?>deactivatation_reason_radio"]').click(function () {
                        var reason = jQuery(this).val();
						dataReason = jQuery(this).val();
                        var customerQuery = jQuery('#<?php echo $this->prefix; ?>customer_query');
                        customerQuery.removeAttr('required');
                        if (reason === "confusing") {
                            customerQuery.attr("placeholder", "<?php _e('Finding it confusing? let us know so that we can improve the interface', 'import-facebook-events' ); ?>");

                        } else if (reason === "other") {
                            customerQuery.attr("placeholder", "<?php _e('Can you let us know the reason for deactivation (Required)', 'import-facebook-events' ); ?>");
                            customerQuery.prop('required', true);

                        } else if (reason === "buggy" || reason === 'not_working') {
                            customerQuery.attr("placeholder", "<?php _e('Can you please let us know about the bug/issue in detail?', 'import-facebook-events' ); ?>");

                        } else if (reason === "better_plugin") {
                            customerQuery.attr("placeholder", "<?php _e('Can you please let us know which plugin you found helpful', 'import-facebook-events' ); ?>");

                        } else if (reason === "feature_request") {
                            customerQuery.attr("placeholder", "<?php _e('Can you please let us know more about the feature you want', 'import-facebook-events' ); ?>");

                        }  else if (reason === "wrong_plugins") {
                            customerQuery.attr("placeholder", "<?php _e('Can you please let us know more about your requirement', 'import-facebook-events' ); ?>");

                        } else if (reason === "temporary") {
                            customerQuery.attr("placeholder", "<?php _e('Write your query here', 'import-facebook-events'); ?>");
                        }
                    });
                });
            </script>
			<style>
			<?php echo '.'.$this->slug; ?>-deactivate-dialog .ui-dialog-titlebar{
				display: none;
			}
            .ui-widget.<?php echo $this->slug; ?>-deactivate-dialog{
                font-family: inherit;
                font-size: 14px;
                font-weight: inherit;
                line-height: inherit;
            }
            .ui-widget.<?php echo $this->slug; ?>-deactivate-dialog textarea{
                font-family: inherit;
                font-size: 14px;
                width: 100%;
            }
            <?php echo '#'.$this->slug; ?>-deactivate-dialog {
                display : none;
            }
			</style>
            <div id="<?php echo $this->slug; ?>-deactivate-dialog">
                <div class="ui-dialog-headerbar" >
                    <div>
                        <h2 style="margin: 0 0 15px 0;"><?php esc_html_e('Quick Feedback', 'import-facebook-events'); ?></h2>
                    </div>
                </div>               
                <div style="border-top: 1px solid #dcdcde;"></div>
                <h3 style="font-size: 14px;" ><?php esc_html_e('Could you please share why you are deactivating Import Facebook Events plugin ?', 'import-facebook-events'); ?></h3>
                <form method="post" action="" id="<?php echo $this->prefix; ?>deactivatation_form">
                    <div>
                    <?php
                        foreach ( $deactivate_reasons as $key => $deactivate_reason ) {
                            ?>
                            <div class="radio" style="padding:1px;margin-left:2%">
                                <label for="<?php echo $key; ?>">
                                    <input type="radio" name="<?php echo $this->prefix; ?>deactivatation_reason_radio" id="<?php echo $key; ?>" value="<?php echo $key; ?>" required <?php if($key === 'confusing') { echo "checked"; } ?>> <?php echo $deactivate_reason; ?>
                                </label>
                            </div>
                        <?php } ?>
                        <br>
                        <textarea id="<?php echo $this->prefix; ?>customer_query" name="<?php echo $this->prefix; ?>customer_query" rows="4" placeholder="<?php _e('Write your query here', 'import-facebook-events'); ?>"></textarea>
                    </div>
                    <div style="text-align: center;">
                        <p style="font-size: 12px;margin: 2px 0 -10px 0;">
                            <?php echo esc_attr__( '* By submitting this form, you will also be sending us your email address &amp; website URL.', 'import-facebook-events' ); ?>
                        </p>
                    </div>
                </form>
				<div class="<?php echo $this->prefix; ?>deactivatation_loading" style="width: 100%;text-align: center; display:none;">
					<img src="<?php echo admin_url('images/spinner.gif'); ?>" />
				</div>
            </div>
            <?php
        }
    }
}

new IFE_Plugin_Deactivation();
