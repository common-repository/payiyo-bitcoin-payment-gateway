<?php
/*
 * Plugin Name: Payiyo Bitcoin Payment Gateway
 * Plugin URI: https://panel.payiyo.com/
 * Description: It is the payment method that will allow you to receive payments from your customers with Bitcoin.
 * Author: Payiyo
 * Author URI: https://www.payiyo.com/
 * Version: 1.1.0
 *
 */
 
add_filter( 'woocommerce_payment_gateways', 'payiyo_add_gateway_class' );
function payiyo_add_gateway_class($gateways) {
	$gateways[] = 'WC_Payiyo_Gateway';
	return $gateways;
}
add_action( 'plugins_loaded', 'payiyo_init_gateway_class' );

function payiyo_init_gateway_class() {
	if (!class_exists('WC_Payment_Gateway') || class_exists('WC_Payiyo_Gateway')) {
        return;
    }
	class WC_Payiyo_Gateway extends WC_Payment_Gateway {
  		public function __construct() {
         	$this->id = 'payiyo';
        	$this->icon = ''; 
        	$this->has_fields = true;
			$this->callback_url = get_site_url(null, '?wc-api='.$this->id);
        	$this->method_title = __('Payiyo Payment');
        	$this->method_description = sprintf(__('It is the payment method that will allow you to receive payments from your customers with Bitcoin.<br/>Set the notification url from Payiyo panel as follows: <b>%s</b> '), $this->callback_url);
        	$this->supports = array('products');
        	$this->init_form_fields();
        	$this->init_settings();
        	$this->title = $this->get_option('title');
        	$this->description = $this->get_option('description');
        	$this->enabled = $this->get_option('enabled');
        	$this->merchant_id = $this->get_option('merchant_id');
        	$this->public_key = $this->get_option('public_key');
        	$this->secret_key = $this->get_option('secret_key');
        	add_action('woocommerce_update_options_payment_gateways_'.$this->id, array($this, 'process_admin_options' ));
        	add_action('woocommerce_receipt_'.$this->id, array($this, 'receipt_page'));
			add_action('woocommerce_api_'.$this->id, array($this,'callback_handler'));
			add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
			$this->payiyoIpAddress = array("95.217.203.169","2a01:4f9:4a:46aa::2");
 		}
 		public function getClientIp() {
 		    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
			$ip = $_SERVER['HTTP_CLIENT_IP'];
			} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
				$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
			} else {
				$ip = $_SERVER['REMOTE_ADDR'];
			}
			return $ip;
 		}
 		public function payiyoRequest($amount, $currency, $order_id) {
			$endPoint = "https://api.payiyo.com/odeme.php";
			switch($currency) {
				case "TRY":
				$currency = "TL";
				break;
				case "EUR":
				$currency = "EURO";
				break;
			}
			$fields = array(
				"merchant_id" => $this->merchant_id,
				"public_key" => $this->public_key,
				"secret_key" => $this->secret_key,
				"order_id" => $order_id,
				"amount" => $amount,
				"currency" => strtolower($currency),
				"user_ip" => $this->getClientIp()
			);
			$response = wp_remote_post($endPoint, array(
				"method" => "POST",
				"timeout" => 30,
				"redirection" => 10,
				"httpversion" => "1.0",
				"headers" => array(
					"X-SECURITY" => "PayiyoSystemV1",
					"X-Public-Key" => $fields["public_key"],
					"Content-Type" => "application/x-www-form-urlencoded"
				),
				"body" => http_build_query($fields)
			));
			$result = json_decode(wp_remote_retrieve_body($response), true);
			if(!is_wp_error($response) && isset($result["btc_address"])) {
				return $result;
			}
			else {
				return array("error_message" => wp_remote_retrieve_body($response));
			}
		}
 
 		public function init_form_fields(){
         	$this->form_fields = array(
        		'enabled' => array(
        			'title'       => __('Enable/Disable'),
        			'label'       => __('Activate the payment method'),
        			'type'        => 'checkbox',
        			'description' => '',
        			'default'     => 'no'
        		),
        		'title' => array(
        			'title'       => __('Title'),
        			'type'        => 'text',
        			'description' => __('The name of the payment method to be shown to the user.'),
        			'default'     => __('Pay with Bitcoin'),
        			'desc_tip'    => true,
        		),
        		'description' => array(
        			'title'       => __('Description'),
        			'type'        => 'textarea',
        			'description' => __('Description of the payment method to be shown to the user.'),
        			'default'     => __('You can pay safely with Bitcoin.'),
        		),
        		'merchant_id' => array(
        			'title'       => __('Merchant ID'),
					'description' => __('You can access this information from the <a href="https://panel.payiyo.com/">Payiyo Panel</a>'),
        			'type'        => 'text'
        		),
        		'public_key' => array(
        			'title'       => __('Public Key'),
					'description' => __('You can access this information from the <a href="https://panel.payiyo.com/">Payiyo Panel</a>'),
        			'type'        => 'text'
        		),
        		'secret_key' => array(
        			'title'       => __('Secret Key'),
					'description' => __('You can access this information from the <a href="https://panel.payiyo.com/">Payiyo Panel</a>'),
        			'type'        => 'password'
        		)
        	);

	 	}
 
		public function payment_fields() {
		   echo $this->description;
		}
		public function receipt_page($order_id) {
			$order = new WC_Order($order_id);
			$order_data = $order->get_data();
			$currency = $order_data["currency"];
			$amount = $order_data["total"];
			$checkout_id = sprintf("%sPAYIYO%s", time(), $order_id);
			$result = $this->payiyoRequest($amount, $currency, $checkout_id);
			if(isset($result["error_message"])) {
				print sprintf(__("Payiyo Error: %s"), $result["error_message"]);
			}
else {
?>
		<div class="payiyo-payment">
		<div class="payiyo-header">
		  <div class="payiyo-header-text">
		  <img src="<?php echo plugin_dir_url( __FILE__ ).'img/btc.png'; ?>">
		  <h3><?php _e("BTC Payment"); ?></h3>
		  </div>
		  <div class="payiyo-logo">
			<img src="<?php echo plugin_dir_url( __FILE__ ).'img/payiyo.png'; ?>">
		  </div>
		</div>
		<div id="payiyoForm" class="payiyo-form" data-order-id="<?php echo $order_id; ?>" data-check="<?php echo $this->callback_url; ?>" data-success="<?php echo $order->get_checkout_order_received_url(); ?>">
		  <div class="payiyo-pay">
			<h5><?php _e("Amount of payment"); ?></h5>
			<h4><?php echo sprintf('%f', $result["amount"]) ?> BTC</h4>
			<h5 class="payiyo-mt-1" style="display:flex;align-items:center"><span style="margin-right:.5rem"><?php _e("Wallet Address"); ?></span>
		<a href="#" id="copyWallet" style="display:inline-flex"><svg xmlns="http://www.w3.org/2000/svg" width="1.35rem" height="1.35rem" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-copy"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg></a></h5>
			<h4 id="wallet"><?php echo $result["btc_address"]; ?></h4>
			<div class="payiyo-mt-1 payiyo-status">
			<div id="paymentConfirmed" style="display:none">
			<svg enable-background="new 0 0 512 512" width="2rem" height="2rem" class="payiyo-check" viewBox="0 0 512 512" xml:space="preserve" xmlns="http://www.w3.org/2000/svg">
			<path d="m437.02 74.98c-48.352-48.351-112.64-74.98-181.02-74.98-68.381 0-132.67 26.629-181.02 74.98-48.352 48.352-74.98 112.64-74.98 181.02s26.628 132.67 74.98 181.02 112.64 74.981 181.02 74.981c68.38 0 132.67-26.629 181.02-74.981s74.981-112.64 74.981-181.02-26.629-132.67-74.981-181.02zm-181.02 407.02c-124.62 0-226-101.38-226-226s101.38-226 226-226 226 101.38 226 226-101.38 226-226 226z"/>
			<path d="m378.3 173.86c-5.857-5.856-15.355-5.856-21.212 1e-3l-132.46 132.46-69.727-69.727c-5.857-5.857-15.355-5.857-21.213 0s-5.858 15.355 0 21.213l80.333 80.333c2.929 2.929 6.768 4.393 10.606 4.393s7.678-1.465 10.606-4.393l143.07-143.07c5.858-5.857 5.858-15.355 0-21.213z"/>
			</svg>
			<span><?php _e("Payment Confirmed!"); ?></span>
			</div>
			<div id="paymentWaiting">
			  <svg class="payiyo-spinner" width="2rem" height="2rem" viewBox="0 0 66 66" xmlns="http://www.w3.org/2000/svg"><circle class="circle" fill="none" stroke-width="6" stroke-linecap="round" cx="33" cy="33" r="30"></circle></svg>
			  <span><?php _e("Waiting for Payment..."); ?></span>
			 </div>
			</div>
		  </div>
		  <div class="payiyo-qr">
			<img src="data:image/jpeg;base64,<?php echo $result["base64"]; ?>">
			<p style="text-align:center"><?php _e("You can <b> Scan QR Code </b> to pay."); ?></p>
		  </div>
		</div>
		 <p class="payiyo-info-text"><?php _e("When the payment receives at least 3 confirmations in the bitcoin account, the sales transaction will be automatically approved."); ?></p>
		</div>
		<script>
		var checker = setInterval(function() {
			jQuery.post(jQuery("#payiyoForm").data("check"), {"check":1, "id":jQuery("#payiyoForm").data("order-id")}, function(data) {
			if(typeof data !== 'object') {
				data = JSON.parse(data);
			}
			if(data.confirmed) {
				jQuery("#paymentWaiting").fadeOut(300);
				setTimeout(function() {
					jQuery("#paymentConfirmed").fadeIn(300);
				}, 300);
				setTimeout(function() {
					window.location.href = jQuery("#payiyoForm").data("success");
				}, 1500);
				clearInterval(checker);
			}
			});
		}, 30000);
		jQuery("#copyWallet").click(function(e) {
			e.preventDefault();
			var temp = jQuery("<input>");
			jQuery("body").append(temp);
			temp.val(jQuery("#wallet").text()).select();
			document.execCommand("copy");
			temp.remove();
			if(typeof swal !== "undefined") {
				swal("<?php _e("Copied to Clipboard!"); ?>", "<?php _e("The wallet address has been copied to the clipboard."); ?>", "success");
			}
			else {
				alert("<?php _e("The wallet address has been copied to the clipboard."); ?>");
			}
		});
		</script>
		<?php }
		}
		public function process_payment($order_id) {
 			$order = wc_get_order($order_id);
            return array(
                'result' => 'success',
                'redirect' => $order->get_checkout_payment_url(true),
            );
	 	}
 	 	public function payment_scripts() {
			if(!is_cart() && !is_checkout() && !isset($_GET['pay_for_order'])) {
				return;
			}
			if('no' === $this->enabled) {
				return;
			}
			wp_enqueue_style('payiyo',plugins_url( '/css/payiyo.css', __FILE__ ));
	 	}
		public function callback_handler() {
			if(isset($_POST["check"]) && isset($_POST["id"]) && is_numeric($_POST["id"])) {
				$status = (new WC_Order(intval($_POST["id"])))->get_data()["status"];
				echo json_encode(array("confirmed" => $status != "pending" && $status != "cancelled"));
			}
			if(in_array($this->getClientIp(), $this->payiyoIpAddress) && isset($_POST["merchant_id"]) && $_POST["merchant_id"] == $this->merchant_id && isset($_POST["public_key"]) && $_POST["public_key"] == $this->public_key && isset($_POST['secret_key']) && $_POST['secret_key'] == $this->secret_key && isset($_POST['order_id']) && isset($_POST['status']) && $_POST['status'] == 'OK') {
				$order_id = explode('PAYIYO', sanitize_text_field($_POST['order_id']));
				if(count($order_id) != 2) {
					exit;
				}
				$order = new WC_Order(intval($order_id[1]));
				$order_data = $order->get_data();
				$order_status = $order_data['status'];
				if($order_status == 'pending' || $order_status == 'failed') {
					if (defined('WOOCOMMERCE_VERSION') && version_compare(WOOCOMMERCE_VERSION, '3.0.0', '<')) {
                        $order->reduce_order_stock();
                    }
					else {
                        wc_reduce_stock_levels(intval($order_id[1]));
                    }
					$note = sprintf(__('Payiyo: Payment Successful! Payiyo Order ID: %s. Payment Date: %s'), sanitize_text_field($_POST['order_id']), date("d/m/Y H:i:s"));
					$order->add_order_note($note);
                    $order->update_status('processing');
					echo 'OK';
				}
			}
			exit;
	 	}
 	}
}