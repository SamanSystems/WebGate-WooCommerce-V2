<?php
/*
Plugin Name: درگاه پرداخت وب گیت زرین پال برای ووکامرس
Plugin URI: http://woocommerce.ir
Description: توسعه داده شده توسط مسعود امینی
Version: 3.1
Author: مسعود امینی
Author URI: http://MasoudAmini.ir
Copyright: 2013 MasoudAmini.ir
 */

add_action('plugins_loaded', 'woocommerce_zarinpalwebgate_init', 0);

function woocommerce_zarinpalwebgate_init() {

    if ( !class_exists( 'WC_Payment_Gateway' ) ) return;

if($_GET['msg']!=''){
        add_action('the_content', 'showMessagezarinpalwebgate');
    }

    function showMessagezarinpalwebgate($content){
            return '<div class="box '.htmlentities($_GET['type']).'-box">'.base64_decode($_GET['msg']).'</div>'.$content;
    }
    class WC_zarinpalwebgate extends WC_Payment_Gateway {
	protected $msg = array();
        public function __construct(){
            // Go wild in here
            $this -> id = 'zarinpalwebgate';
            $this -> method_title = __('درگاه zarinpalwebgate', 'zarinpalwebgate');
            $this -> has_fields = false;
            $this -> init_form_fields();
            $this -> init_settings();
            $this -> title = $this -> settings['title'];
            $this -> description = $this -> settings['description'];
            $this -> merchant_id = $this -> settings['merchant_id'];
			$this -> vahed = $this -> settings['vahed'];
            $this -> redirect_page_id = $this -> settings['redirect_page_id'];
            $this -> msg['message'] = "";
            $this -> msg['class'] = "";
			add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array( $this, 'check_zarinpalwebgate_response' ) );
            add_action('valid-zarinpalwebgate-request', array($this, 'successful_request'));
			
			
            if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
                add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
             } else {
                add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ) );
            }
			
            add_action('woocommerce_receipt_zarinpalwebgate', array($this, 'receipt_page'));
        }

        function init_form_fields(){

            $this -> form_fields = array(
                'enabled' => array(
                    'title' => __('فعال سازی/غیر فعال سازی', 'zarinpalwebgate'),
                    'type' => 'checkbox',
                    'label' => __('فعال سازی درگاه پرداخت zarinpalwebgate', 'zarinpalwebgate'),
                    'default' => 'no'),
                'title' => array(
                    'title' => __('عنوان:', 'zarinpalwebgate'),
                    'type'=> 'text',
                    'description' => __('عنوانی که کاربر در هنگام پرداخت مشاهده می کند', 'zarinpalwebgate'),
                    'default' => __('پرداخت اینترنتی zarinpalwebgate', 'zarinpalwebgate')),
                'description' => array(
                    'title' => __('توضیحات:', 'zarinpalwebgate'),
                    'type' => 'textarea',
                    'description' => __('توضیحات قابل نمایش به کاربر در هنگام انتخاب درگاه پرداخت', 'zarinpalwebgate'),
                    'default' => __('پرداخت از طریق درگاه zarinpalwebgate با کارت های عضو شتاب', 'zarinpalwebgate')),
                'merchant_id' => array(
                    'title' => __('مرچنت کد زرین پال', 'zarinpalwebgate'),
                    'type' => 'text',
                    'description' => __('مرچنت کد را وارد کنید')),
				'vahed' => array(
                    'title' => __('واحد پولی'),
                    'type' => 'select',
                    'options' => array(
					'rial' => 'ریال',
					'toman' => 'تومان'
					),
                    'description' => "نیازمند افزونه ریال و تومان هست"),
                'redirect_page_id' => array(
                    'title' => __('صفحه بازگشت'),
                    'type' => 'select',
                    'options' => $this -> get_pages('انتخاب برگه'),
                    'description' => "ادرس بازگشت از پرداخت در هنگام پرداخت"
                )
            );


        }

        public function admin_options(){
            echo '<h3>'.__('درگاه پرداخت zarinpalwebgate', 'zarinpalwebgate').'</h3>';
            echo '<p>'.__('درگاه پرداخت اینترنتی zarinpalwebgate').'</p>';
            echo '<table class="form-table">';
            $this -> generate_settings_html();
            echo '</table>';

        }

		
        function payment_fields(){
            if($this -> description) echo wpautop(wptexturize($this -> description));
        }

        function receipt_page($order){
            echo '<p>'.__('با تشکر از سفارش شما. در حال انتقال به درگاه پرداخت...', 'zarinpalwebgate').'</p>';
            echo $this -> generate_zarinpalwebgate_form($order);
        }

        function process_payment($order_id){
            $order = &new WC_Order($order_id);
            return array('result' => 'success', 'redirect' => add_query_arg('order',
                $order->id, add_query_arg('key', $order->order_key, get_permalink(get_option('woocommerce_pay_page_id'))))
            );
        }

       function check_zarinpalwebgate_response(){
            global $woocommerce;
		$order_id = $woocommerce->session->zegersot;
		$order = &new WC_Order($order_id);
		if($order_id != ''){
		if($order -> status !='completed'){
		$merchantID=$this -> merchant_id;
		$au 	= $_GET['Authority'];
		$st		= $_GET['Status'];

			$amount		= round($order -> order_total/10);
			$client = new soap_client('https://de.zarinpal.com/pg/services/WebGate/wsdl', 'wsdl');
			$res = $client->call("PaymentVerification", array(
					array(
							'MerchantID'	 => $merchantID ,
							'Authority' 	 => $au ,
							'Amount'	 	=> $amount
						)
					));
			
				if ($res['Status'] == 100)
				{
					$_SESSION['zarinpalwg_id'] = '';
					$output[status] = 1;
					$output[message] ='پرداخت با موفقیت انجام گردید.';
					$this -> msg['message'] = "پرداخت شما با موفقیت انجام شد";
                    $this -> msg['class'] = 'success';
					$order -> payment_complete();
                    $order -> add_order_note('پرداخت انجام گردید<br/>کد رهگیری بانک: '.$res['RefID']);
                    $order -> add_order_note($this->msg['message']);
                    $woocommerce -> cart -> empty_cart();
				}
				else
				{
					
					$this -> msg['class']	= 'error';
					$this -> msg['message']= 'پرداخت توسط زرین پال تایید نشد‌.'.$res['Status'];
				}
			

		
		if ($output[status] == 0)
				$order -> add_order_note($output[message]);
        
        
        function showMessage($content){
            return '<div class="box '.$this -> msg['class'].'-box">'.$this -> msg['message'].'</div>'.$content;
        }


        public function generate_zarinpalwebgate_form($order_id){
            global $woocommerce;
            $order = new WC_Order($order_id);
            $redirect_url = ($this -> redirect_page_id=="" || $this -> redirect_page_id==0)?get_site_url() . "/":get_permalink($this -> redirect_page_id);
			$redirect_url = add_query_arg( 'wc-api', get_class( $this ), $redirect_url );
			unset( $woocommerce->session->zegersot );
			unset( $woocommerce->session->zegersot_id );
			$woocommerce->session->zegersot = $order_id;
			$url = 'http://zarinpalwebgate.ir/payment/gateway-send'; 
			$api=$this -> merchant_id;
			$amount = $order -> order_total; 
			if($this -> vahed=='toman')
			$amount = $amount*10;
			$redirect = urlencode($redirect_url); 
			$result = $this ->send($url,$api,$amount,$redirect); 
			if($result > 0 && is_numeric($result)){ 
			$woocommerce->session->zegersot_id=$result;
			$go = "http://zarinpalwebgate.ir/payment/gateway-$result"; 
			header("Location: $go"); 
			}else if($result=='-1'){
			echo "api ارسالي با نوغ api تعريف شده در zarinpalwebgate سازگار نيست";
			}else if($result=='-2'){
			echo "مقدار مبلغ نبايد کمتر از 1000 ريال باشد";
			}else if($result=='-3'){
			echo "error";
			}else if($result=='-4'){
			echo "درگاهي با اطلاعات ارسالي شما يافت نشده و يا در حالت انتظار مي باشد";
			}
		

        }
		
private function send($url,$api,$amount,$redirect){ 
    $ch = curl_init(); 
    curl_setopt($ch,CURLOPT_URL,$url); 
    curl_setopt($ch,CURLOPT_POSTFIELDS,"api=$api&amount=$amount&redirect=$redirect"); 
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); 
    curl_setopt($ch,CURLOPT_RETURNTRANSFER,true); 
    $res = curl_exec($ch); 
    curl_close($ch); 
    return $res; 
}
	private function get($url,$api,$trans_id,$id_get){ 
    $ch = curl_init(); 
    curl_setopt($ch,CURLOPT_URL,$url); 
    curl_setopt($ch,CURLOPT_POSTFIELDS,"api=$api&id_get=$id_get&trans_id=$trans_id"); 
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); 
    curl_setopt($ch,CURLOPT_RETURNTRANSFER,true); 
    $res = curl_exec($ch); 
    curl_close($ch); 
    return $res; 
} 
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


    function woocommerce_add_zarinpalwebgate_gateway($methods) {
        $methods[] = 'WC_zarinpalwebgate';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_zarinpalwebgate_gateway' );
}

?>
