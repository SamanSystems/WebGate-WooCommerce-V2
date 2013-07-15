<?php
/*
Plugin Name: درگاه زرین پال-وب گیت  ووکامرس
Plugin URI: http://zarinpal.com	
Description: افزودن درگاه پرداخت زرین پال به فروشگاه ساز ووکامرس
Version: 2.0
Author: Masoud Amini
Author URI: http://haftir.ir

 */
require_once("lib/nusoap.php");
add_action('plugins_loaded', 'woocommerce_zarinpalwg_init', 0);

function woocommerce_zarinpalwg_init() {

    if ( !class_exists( 'WC_Payment_Gateway' ) ) return;



    class WC_zarinpalwg_Pay extends WC_Payment_Gateway {
	protected $msg = array();
        public function __construct(){
            // Go wild in here
            $this -> id = 'zarinpalwg';
            $this -> method_title = __('درگاه زرین پال', 'zarinpalwg');
            $this -> has_fields = false;
            $this -> init_form_fields();
            $this -> init_settings();
            $this -> title = $this -> settings['title'];
            $this -> merchant = $this -> settings['merchant'];
            $this -> redirect_page_id = $this -> settings['redirect_page_id'];
            
            $this -> msg['message'] = "";
            $this -> msg['class'] = "";
            add_action('init', array(&$this, 'check_zarinpalwg_response'));
            add_action('valid-zarinpalwg-request', array(&$this, 'successful_request'));
			
            if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
                add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
             } else {
                add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
            }
			
			
            add_action('woocommerce_receipt_zarinpalwg', array(&$this, 'receipt_page'));
            add_action('woocommerce_thankyou_zarinpalwg',array(&$this, 'thankyou_page'));
        }

       function init_form_fields(){

            $this -> form_fields = array(
                'enabled' => array(
                    'title' => __('فعال سازی/غیر فعال سازی', 'zarinpalwg'),
                    'type' => 'checkbox',
                    'label' => __('فعال سازی درگاه پرداخت zarinpalwg', 'zarinpalwg'),
                    'default' => 'no'),
                'title' => array(
                    'title' => __('عنوان:', 'zarinpalwg'),
                    'type'=> 'text',
                    'description' => __('عنوانی که کاربر در هنگام پرداخت مشاهده می کند', 'zarinpalwg'),
                    'default' => __('پرداخت اینترنتی zarinpalwg', 'zarinpalwg')),
                'description' => array(
                    'title' => __('توضیحات:', 'zarinpalwg'),
                    'type' => 'textarea',
                    'description' => __('توضیحات قابل نمایش به کاربر در هنگام انتخاب درگاه پرداخت', 'zarinpalwg'),
                    'default' => __('پرداخت از طریق درگاه zarinpalwg با کارت های عضو شتاب', 'zarinpalwg')),
                'merchant' => array(
                    'title' => __('پین کد', 'zarinpalwg'),
                    'type' => 'text',
                    'description' => __('پین کد درگاه را وارد کنید')),
                'redirect_page_id' => array(
                    'title' => __('صفحه بازگشت'),
                    'type' => 'select',
                    'options' => $this -> get_pages('انتخاب برگه'),
                    'description' => "ادرس بازگشت از پرداخت در هنگام پرداخت"
                )
            );


        }
        /**
         * Admin Panel Options
         * - Options for bits like 'title' and availability on a country-by-country basis
         **/
        public function admin_options(){
            echo '<h3>'.__('درگاه زرین پال', 'zarinpalwg').'</h3>';
            echo '<p>'.__('درگاه زرین پال').'</p>';
            echo '<table class="form-table">';
            $this -> generate_settings_html();
            echo '</table>';

        }
        /**
         *  There are no payment fields for zarinpalwg, but we want to show the description if set.
         **/
        function payment_fields(){
            if($this -> description) echo wpautop(wptexturize($this -> description));
        }
        /**
         * Receipt Page
         **/
		function receipt_page($order){
            echo '<p>'.__('با تشکر از سفارش شما. در حال انتقال به درگاه پرداخت...', 'zarinpalwg').'</p>';
            echo $this -> generate_zarinpalwg_form($order);
        }
        /**
         * Process the payment and return the result
         **/
        function process_payment($order_id){
            $order = &new WC_Order($order_id);
            return array('result' => 'success', 'redirect' => add_query_arg('order',
                $order->id, add_query_arg('key', $order->order_key, get_permalink(get_option('woocommerce_pay_page_id'))))
            );
        }
        /**
         * Check for valid zarinpalwg server callback
         **/
       function check_zarinpalwg_response(){
        global $woocommerce;
		$order_id=$_SESSION['zarinpalwg_id'];
		$order = new WC_Order($order_id);
		$au 	= $_GET['Authority'];
		$st		= $_GET['Status'];
		if($order_id != '' AND $au !='' AND $st == "OK" ){
		if($order -> status !=='completed'){
		

			$merchantID = $this -> merchant;

			$amount		= round($order -> order_total/10);
			$client = new nusoap_client('https://www.zarinpal.com/pg/services/WebGate/wsdl', 'wsdl');
			$res = $client->call("PaymentVerification", array(
					array(
							'MerchantID'	 => $merchantID ,
							'Authority' 	 => $au ,
							'Amount'	 	=> $amount
						)
					));
			
				if ($res->Status == 100)
				{
					$_SESSION['zarinpalwg_id'] = '';
					$output[status] = 1;
					$output[message] ='پرداخت با موفقیت انجام گردید.';
					$order -> payment_complete();
                    $order -> add_order_note('پرداخت انجام گردید<br/>کد رهگیری بانک: '.$res->RefID);
                    $order -> add_order_note($this->msg['message']);
                    $woocommerce -> cart -> empty_cart();
				}
				else
				{
					
					$output[status]	= 0;
					$output[message]= 'پرداخت توسط زرین پال تایید نشد‌.';
				}
			

		
		if ($output[status] == 0)
				$order -> add_order_note($output[message]);
        
			
           
}
}
		
}



        
        function showMessage($content){
            return '<div class="box '.$this -> msg['class'].'-box">'.$this -> msg['message'].'</div>'.$content;
        }
        /**
         * Generate zarinpalwg button link
         **/

      public function generate_zarinpalwg_form($order_id){
            global $woocommerce;
            $order = &new WC_Order($order_id);
            $redirect_url = ($this -> redirect_page_id=="" || $this -> redirect_page_id==0)?get_site_url() . "/":get_permalink($this -> redirect_page_id);
        $_SESSION['zarinpalwg_id'] = $order_id;
		$merchantID 	= trim($this -> merchant);
		$amount 		= round($order -> order_total/10);
		$invoice_id=date('Y').date('H').date('i').date('s').$order_id;
		$callBackUrl 	= $redirect_url;
		
		$client = new nusoap_client('https://www.zarinpal.com/pg/services/WebGate/wsdl', 'wsdl');
		$res = $client->call('PaymentVerification', array(
		array(
					'MerchantID' 	=> $merchantID ,
					'Amount' 		=> $amount ,
					'Description' 	=> $order_id ,
					'Email' 		=> '' ,
					'Mobile' 		=> '' ,
					'CallbackURL' 	=> $callBackUrl

					)
	 ));
		if ($res->Status == 100)
		{
			header('location: https://www.zarinpal.com/pg/StartPay/' . $res->Authority);
			exit;
		}
		else
		{
			$this -> msg['class'] = 'error';
			echo $this -> msg['message'] = '<font color="red">در اتصال به درگاه زرین پال مشکلی به وجود آمد٬ لطفا از درگاه سایر بانک‌ها استفاده نمایید.</font>'.$res;

		}
		
		
				

			
			
	if($this -> msg['class']=='error')
	$order -> add_order_note($this->msg['message']);	

        }

	
        // get all pages
        function get_pages($title = false, $indent = true) {
            $wp_pages = get_pages('sort_column=menu_order');
            $page_list = array();
            if ($title) $page_list[] = $title;
            foreach ($wp_pages as $page) {
                $prefix = '';
                // show indented child pages?
                if ($indent) {
                    $has_parent = $page->post_parent;
                    while($has_parent) {
                        $prefix .=  ' - ';
                        $next_page = get_page($has_parent);
                        $has_parent = $next_page->post_parent;
                    }
                }
                // add to page list array array
                $page_list[$page->ID] = $prefix . $page->post_title;
            }
            return $page_list;
        }

    }

    /**
     * Add the Gateway to WooCommerce
     **/
    function woocommerce_add_zarinpalwg_gateway($methods) {
        $methods[] = 'WC_zarinpalwg_Pay';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_zarinpalwg_gateway' );
}

?>
