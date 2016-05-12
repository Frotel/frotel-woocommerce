<?php
/**
Plugin Name: Frotel WooCommerce
Plugin URI: http://frotel.com/
Description: افزونه ثبت سفارشات در <strong><a href="http://frotel.com" target="_blank">فروتل</a></strong>
Version: 1.3.3
Author: ReZa ZaRe
Author URI: http://frotel.com
Text Domain: frotel
 **/

const FROTEL_WOOCOMMERCE_VERSION = '1.3.3';

if(in_array('woocommerce/woocommerce.php',apply_filters('active_plugins',get_option('active_plugins')))) {
    /**
     * غیر فعال کردن لیست شهر های ووکامرس پارسی
     */
    $pw_options = get_option('PW_Options');
    if(isset($pw_options['enable_iran_cities'])) {
        $pw_options['enable_iran_cities'] = 'no';
        update_option('PW_Options',$pw_options);
    }

    require_once 'lib/frotel_helper.php';

    function frotel_shipping_method_init()
    {

        if (!class_exists('WC_Frotel_Shipping_Method')) {
            class WC_Frotel_Shipping_Method extends WC_Shipping_Method
            {

                /**
                 * Constructor for frotel pishtaz shipping
                 */
                public function __construct()
                {
                    $this->id = 'frotel_shipping';
                    $this->title = __('فروتل');
                    $this->method_title = __('فروتل');
                    $this->method_description = __('ارسال توسط فروتل و پست');

                    $this->init();
                }

                /**
                 * init frotel pishtaz shipping settings
                 */
                public function init()
                {
                    $this->init_form_fields();
                    $this->init_settings();

                    $this->enabled		= $this->get_option('enabled');

                    add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
                }

                /**
                 * field of form settings
                 */
                public function init_form_fields()
                {
                    $this->form_fields = require('include/settings-frotel.php');
                }

                /**
                 * calculate shipping
                 *
                 * @param $package
                 *
                 * @return bool
                 * @throws Exception
                 */
                public function calculate_shipping ($package)
                {
                    $options = get_option('woocommerce_frotel_shipping_settings');
 
                    if ($options['enabled'] == 'no')
                        return false;

                    if (strlen($options['url']) == 0 || strlen($options['api']) == 0) {
                        return false;
                    }

                    /**
                     * @var WooCommerce $woocommerce
                     */
                    global $woocommerce;

                    $total_price = convertToRial(floatval(preg_replace('#[^\d.]#', '', $woocommerce->cart->get_cart_total())));

                    $total_weight = 0;
                    $emptyBasket = true;
                    $total_packing = 0;
                    foreach ($package['contents'] as $product) {
                        # محصولات مجازی را نمی توان در فروتل ثبت کرد
                        if ($product['data']->virtual != 'no')
                            continue;

                        $emptyBasket = false;
                        $packing = $product['quantity']*intval(get_post_meta($product['data']->id,'packing',true));
                        $total_packing += $packing;

                        if ($product['data']->weight>0)
                            $total_weight += $product['quantity']*$product['data']->get_weight();
                        else
                            $total_weight += $product['quantity']*$options['default_weight'];
                    }

                    if ($emptyBasket)
                        return false;

                    $city = 0;

                    if (isset($_POST['post_data']))
                        parse_str($_POST['post_data'],$post_data);
                    else
                        $post_data = $_POST;

                    if (isset($post_data['shipping_city']) && intval($post_data['shipping_city'])>0){
                        $city = intval($post_data['shipping_city']);
                    } elseif (isset($post_data['billing_city']) && intval($post_data['billing_city'])>0){
                        $city = intval($post_data['billing_city']);
                    }
                    $coupon = '';
                    if (isset($post_data['billing_frotel_coupon']) && strlen($post_data['billing_frotel_coupon']))
                        $coupon = sanitize_text_field($post_data['billing_frotel_coupon']);

                    // اگر شهر مقصد انتخاب نشده بود
                    if ($city<=0){
                        return false;
                    }

                    $buyOnline  = $options['online_enable'] == 'yes';
                    $buyCOD     = $options['cod_enable'] == 'yes';

                    $buy_types = array();
                    if ($buyOnline)
                        $buy_types[] = frotel_helper::BUY_ONLINE;

                    if ($buyCOD)
                        $buy_types[] = frotel_helper::BUY_COD;


                    $deliveryPishtaz    = $options['pishtaz_enable'] == 'yes';
                    $deliverySefareshi  = $options['sefareshi_enable'] == 'yes';
                    $deliveryFixed  = $options['fixed_enable'] == 'yes';

                    $delivery_types = array();
                    if ($deliveryPishtaz)
                        $delivery_types[] = frotel_helper::DELIVERY_PISHTAZ;

                    if ($deliverySefareshi)
                        $delivery_types[] = frotel_helper::DELIVERY_SEFARESHI;

                    // convert to gram
                    $total_weight = wc_get_weight($total_weight,'g');

                    $frotel_helper = new frotel_helper($options['url'],$options['api']);

                    $options['fixed_city'] = str_replace(' ','',$options['fixed_city']);
                    $fixed_in_city = explode(',',$options['fixed_city']);

                    /**
                     * در صورتی که مدیر شهری را وارد نکرده باشد هزینه ثابت برای تمام شهرها فعال می شود.
                     * اگر شهر کاربر جز شهرهایی که هزینه ثابت برای آنها فعال نبود
                     */
                    if (strlen($options['fixed_city']) && in_array($city,$fixed_in_city) === false) {
                        $deliveryFixed = false;
                    }


                    $free_send = false;
                    if ($options['total_order_free_send']>0 && $total_price >= $options['total_order_free_send'])
                        $free_send = true;

                    $order_packing = intval($options['order_packing']);
                    if ($free_send) {
                        $order_packing = 0;
                        $total_packing = 0;
                    }

                    $total_price += $total_packing;

                    $fixed_online = array(
                        'post'=>$free_send?0:$options['default_fixed_online'],
                        'tax'=>0,
                        'frotel_service'=>$free_send?0:2000,
                        'packing'=>$order_packing
                    );

                    $fixed_cod = array(
                        'post'=>$free_send?0:$options['default_fixed_cod'],
                        'tax'=>0,
                        'frotel_service'=>$free_send?0:2000,
                        'packing'=>$order_packing
                    );

                    try {
                        if ($free_send)
                            throw new FrotelResponseException('ارسال رایگان');

                        $key = md5($city.$total_price.$total_weight.json_encode(array($buy_types,$delivery_types)));

                        if (isset($_SESSION['frotel_get_prices'][$key])){
                            $result = $_SESSION['frotel_get_prices'][ $key ];
                        } else {
                            $result = $frotel_helper->getPrices($city, $total_price, $total_weight, $buy_types, $delivery_types);

                            if ($deliveryFixed) {
                                if ($buyOnline)
                                    $result['naghdi']['fixed'] = $fixed_online;

                                if ($buyCOD)
                                    $result['posti']['fixed'] = $fixed_cod;
                            }

                            $_SESSION['frotel_get_prices'][$key] = $result;
                        }
                        if (strlen($coupon)) {
                            if (isset($_SESSION['frotel_coupon'][$coupon])) {
                                $coupon = $_SESSION['frotel_coupon'][$coupon];
                            } else {
                                $_SESSION['frotel_coupon'][$coupon] = array('error' => 1, 'message' => '');
                                try {
                                    $coupon_result = $frotel_helper->checkCoupon($coupon);
                                    $_SESSION['frotel_coupon'][$coupon] = array('error' => 0, 'message' => $coupon_result);
                                } catch (FrotelWebserviceException $e) {
                                    wc_add_notice($e->getMessage(), 'error');
                                    $_SESSION['frotel_coupon'][$coupon] = array('error' => 1, 'message' => $e->getMessage());
                                } catch (FrotelResponseException $e) {
                                }
                                $coupon = $_SESSION['frotel_coupon'][$coupon];
                            }

                            if (isset($coupon['error']) && $coupon['error'] == 0) {
                                $efficacy_type = stripos($coupon['message']['efficacy'], '%') !== false ? 1 : 0;
                                $efficacy = floatval(str_replace(',', '', $coupon['message']['efficacy']));

                                if ($efficacy_type == 1) { // if type == percent
                                    $total_discount = $total_price * $efficacy / 100;
                                } else { // if type == value
                                    $total_discount = convertToShopUnitCurrency($efficacy);
                                }
                                $woocommerce->cart->add_fee(__('تخفیف', 'woocommerce'), $total_discount);
                                $_SESSION['frotel_coupon_used'] = array(
                                    'code' => $coupon['message']['code'],
                                    'efficacy_type' => $efficacy_type,
                                    'efficacy' => $efficacy
                                );
                            }

                        }
                    } catch (FrotelWebserviceException $e) {        // خطا در اجرای دستورات رخ داده باشد
                        wc_add_notice($e->getMessage(),'error');
                        return false;
                    } catch (FrotelResponseException $e) {          // خطا در اتصال به سرور فروتل و یا دریافت اطلاعات به صورت نامعتبر
                        // اگر در اتصال به سرور فروتل خطا رخ داده باشد باید هزینه های پیشفرض درنظر گرفته شود
                        $result = array();
                        if ($buyOnline) {
                            if ($deliveryPishtaz) {
                                $result['naghdi'][frotel_helper::DELIVERY_PISHTAZ] = array(
                                    'post'              => $free_send?0:$options['default_pishtaz_online'],
                                    'tax'               => 0,
                                    'frotel_service'    => 0,
                                    'packing'           => $order_packing
                                );
                            }
                            if ($deliverySefareshi) {
                                $result['naghdi'][frotel_helper::DELIVERY_SEFARESHI] = array(
                                    'post'              => $free_send?0:$options['default_sefareshi_online'],
                                    'tax'               => 0,
                                    'frotel_service'    => 0,
                                    'packing'           => $order_packing
                                );
                            }

                            if ($deliveryFixed)
                                $result['naghdi']['fixed'] = $fixed_online;
                        }

                        if ($buyCOD) {
                            if ($deliveryPishtaz) {
                                $result['posti'][frotel_helper::DELIVERY_PISHTAZ] = array(
                                    'post'              => $free_send?0:$options['default_pishtaz_cod'],
                                    'tax'               => 0,
                                    'frotel_service'    => 0,
                                    'packing'           => $order_packing
                                );
                            }
                            if ($deliverySefareshi) {
                                $result['posti'][frotel_helper::DELIVERY_SEFARESHI] = array(
                                    'post'              => $free_send?0:$options['default_sefareshi_cod'],
                                    'tax'               => 0,
                                    'frotel_service'    => 0,
                                    'packing'           => $order_packing
                                );
                            }

                            if ($deliveryFixed)
                                $result['posti']['fixed'] = $fixed_cod;
                        }

                    }
                    $total_packing = $free_send?0:$total_packing;

                    foreach ($result as $buyType=>$deliveryType) {
                        $buyTypeLabel = $buyType == 'naghdi' ? 'نقدی' : 'پرداخت در محل';
                        foreach ($deliveryType as $delivery=>$data) {
                            if ($delivery == frotel_helper::DELIVERY_SEFARESHI) {
                                $deliveryLabel = 'سفارشی '.$buyTypeLabel;
                                $id = $this->id.'_sefareshi_'.$buyType;
                            } elseif ($delivery == 'fixed') {
                                $deliveryLabel = 'پیک شهری '.$buyTypeLabel;
                                $id = $this->id.'_fixed_'.$buyType;
                            } else {
                                $deliveryLabel = 'پیشتاز '.$buyTypeLabel;
                                $id = $this->id.'_pishtaz_'.$buyType;
                            }


                            $rate = array(
                                'id'    => $id,
                                'label' => $deliveryLabel,
                                'cost'  => convertToShopUnitCurrency($data['post'] + $data['tax'] + $data['frotel_service'] + $data['packing'] + $total_packing)
                            );

                            $this->add_rate($rate);
                        }
                    }
                    return true;
                }

            }
        }

    }

    function frotel_gateway_class()
    {
        class WC_Gateway_Frotel extends WC_Payment_Gateway
        {
            public function __construct()
            {
                $this->id = 'frotel';
                $this->icon = '';
                $this->title = 'فروتل';
                $this->method_title = 'فروتل';
                $this->has_fields = false;

                $this->init_form_fields();
                $this->init_settings();

                $this->enabled = $this->get_option('enabled');

                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            }

            public function is_available()
            {
                // if gateway is disabled
                if ($this->enabled == 'no')
                    return false;

                /**
                 * @var WooCommerce $woocommerce
                 */
                global $woocommerce;

                $shipping_method = $woocommerce->session->get('chosen_shipping_methods',null);
                if (!isset($shipping_method[0]))
                    return false;

                $shipping_method = explode('_',$shipping_method[0]);
                $shipping_method = end($shipping_method);

                // نمایش روش پرداخت انتخاب شده
                if ($shipping_method == 'naghdi') {
                    $this->title = 'پرداخت نقدی';
                    $this->description = $this->get_option('title_naghdi');
                } else {
                    $this->title = 'پرداخت در محل';
                    $this->description = $this->get_option('title_posti');
                }

                return parent::is_available();
            }


            public function init_form_fields()
            {
                $this->form_fields = array(
                    'enabled' => array(
                        'title' => __('Enable/Disable', 'woocommerce'),
                        'type' => 'checkbox',
                        'label' => __('فعال سازی', 'woocommerce'),
                        'default' => 'no'
                    ),
                    'title_naghdi' => array(
                        'title' => __('توضیحات پرداخت نقدی', 'woocommerce'),
                        'type' => 'text',
                        'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
                        'default' => __('پرداخت از طریق درگاه های بانکی', 'woocommerce'),
                        'desc_tip'      => true,
                    ),
                    'title_posti' => array(
                        'title' => __('توضیحات پرداخت در محل', 'woocommerce'),
                        'type' => 'text',
                        'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
                        'default' => __('پرداخت پس از تحویل کالا', 'woocommerce'),
                        'desc_tip'      => true,
                    )
                );

                parent::init_form_fields();
            }

            public function process_payment($order_id)
            {
                /**
                 * @var $woocommerce woocommerce
                 */
                global $woocommerce;
                $order = new WC_Order($order_id);

                $options = get_option('woocommerce_frotel_shipping_settings');

                $products = $woocommerce->cart->get_cart();

                $total_price = convertToRial(floatval(preg_replace('#[^\d.]#', '', $woocommerce->cart->get_cart_total())));
                $basket = array();
                $free_send = false;
                if ($options['total_order_free_send']>0 && $total_price >= $options['total_order_free_send'])
                    $free_send = true;

                foreach ($products as $product) {
                    # محصولات مجازی را نمی توان در فروتل ثبت کرد
                    if ($product['data']->virtual != 'no')
                        continue;
                    $name = $product['data']->post->post_title;

                    if ($product['data']->product_type == 'variation'){
                        $a = array();
                        foreach($product['variation'] as $index=>$attr){
                            $a[] = urldecode(str_replace('attribute_','',$index).':'.$attr);
                        }
                        $name .= '('.implode(',',$a).')';
                    }

                    $item['pro_code'] = $product['product_id'];
                    $item['name'] = $name;
                    $item['price'] = convertToRial($product['data']->price);

                    $item['count'] = $product['quantity'];

                    $item['weight'] = $product['data']->weight;
                    // اگر برای محصولات وزن تعیین نشده است
                    // از وزن پیشفرض به ازای هر محصول استفاده می کنیم
                    if ($item['weight']<=0)
                        $item['weight'] = $options['default_weight'];

                    // convert to gram
                    $item['weight'] = wc_get_weight($item['weight'],'g');

                    $item['porsant'] = 0;
                    $item['bazaryab'] = 0;
                    $item['discount'] = 0;
                    $item['free_send'] = $free_send;
                    $item['tax'] = 0;
                    $item['packing'] = intval(get_post_meta($product['data']->id,'packing',true));
                    $basket[] = $item;
                }

                $chosen_methods = $woocommerce->session->get('chosen_shipping_methods');
                $chosen_shipping = $chosen_methods[0];

                $chosen_shipping = str_ireplace('frotel_shipping_','',$chosen_shipping);
                $chosen_shipping = explode('_',$chosen_shipping);

                if (empty($basket)) {
                    throw new Exception (__('سبد خرید خالی است.','woocommerce'));
                }

                $frotel_helper = new frotel_helper($options['url'],$options['api']);

                if ($chosen_shipping[1] == 'naghdi') {
                    $buyType = frotel_helper::BUY_ONLINE;
                } else {
                    $buyType = frotel_helper::BUY_COD;
                }

                $postPrice = 0;
                switch ($chosen_shipping[0]) {
                    case 'sefareshi':
                    default:
                        $deliveryType = frotel_helper::DELIVERY_SEFARESHI;
                        break;
                    case 'pishtaz':
                        $deliveryType = frotel_helper::DELIVERY_PISHTAZ;
                        break;
                    case 'fixed':
                        $options['fixed_city'] = str_replace(' ','',$options['fixed_city']);
                        $fixed_in_city = explode(',',$options['fixed_city']);
                        $deliveryFixed  = $options['fixed_enable'] == 'yes';

                        /**
                         * در صورتی که مدیر شهری را وارد نکرده باشد هزینه ثابت برای تمام شهرها فعال می شود.
                         * اگر شهر کاربر جز شهرهایی که هزینه ثابت برای آنها فعال نبود
                         */
                        if (strlen($options['fixed_city']) && in_array($order->shipping_frotel_city,$fixed_in_city) === false) {
                            $deliveryFixed = false;
                        }
                        if ($deliveryFixed) {
                            $deliveryType = frotel_helper::DELIVERY_FIXED;
                            if ($buyType == frotel_helper::BUY_ONLINE)
                                $postPrice = $free_send ? 0 : $options['default_fixed_online'];
                            else
                                $postPrice = $free_send ? 0 : $options['default_fixed_cod'];
                        } else {
                            $deliveryType = frotel_helper::DELIVERY_SEFARESHI;
                        }
                        break;
                }

                try{
                    $result = $frotel_helper->registerOrder(
                        $order->shipping_first_name,
                        $order->shipping_last_name,
                        1,
                        $order->billing_phone,
                        '',
                        $order->billing_email,
                        $order->shipping_state,
                        $order->shipping_city,
                        $order->shipping_address_1.' '.$order->shipping_address_2,
                        $order->shipping_postcode,
                        $buyType,
                        $deliveryType,
                        $order->customer_note,
                        $basket,
                        array(),
                        $postPrice,
                        $free_send,
                        $order->billing_frotel_coupon
                    );
                } catch (FrotelWebserviceException $e) {
                    /**
                     * به دلیل ناقص بودن اطلاعات خطایی رخ داده است و خریدار باید
                     * این خطا ها را برطرف کند
                     */
                    throw new Exception($e->getMessage());

                } catch (FrotelResponseException $e) { // اگر در اتصال به سرور فروتل خطایی رخ داده بود
                    /**
                     * در صورتی که اتصال به سرور فروتل به هر دلیلی ممکن نباشد
                     * سفارش ثبت می شود اما چون سفارش در فروتل ثبت نشده است
                     * به همین دلیل مدیر فروشگاه بعدا باید سفارش را به صورت دستی
                     * و با استفاده از دکمه ایی که به این منظور در نظر گرفته شده است
                     * سفارش را در فروتل ثبت کند
                     */
                    $order->update_status('on-hold', __('منتظر بررسی توسط مدیر فروشگاه', 'woocommerce'));
                    $order->add_order_note('در هنگام ثبت سفارش ارتباط با سرور فروتل برقرار نشد. ');

                    // Reduce stock levels
                    $order->reduce_order_stock();

                    // Remove cart
                    $woocommerce->cart->empty_cart();

                    return array(
                        'result' => 'success',
                        'redirect' => $this->get_return_url($order)
                    );
                }

                // Reduce stock levels
                $order->reduce_order_stock();

                // Remove cart
                $woocommerce->cart->empty_cart();

                // set factor id on order
                update_post_meta($order_id,'_frotel_factor',$result['factor']['id']);
                $order->add_order_note('سفارش در فروتل ثبت شد. شماره فاکتور <strong>'.$result['factor']['id'].'</strong>');

                unset($result['items']);
                $result['order_id'] = $order_id;
                unset($_SESSION['frotel_coupon'],$_SESSION['frotel_coupon_used']);
                /**
                 * اگر مدیر قصد نداشت تا فاکتور فروتل نمایش داده شود
                 * فاکتور را از نتیجه حذف می کنیم
                 */
                if ($options['show_factor'] != 'yes') {
                    unset($result['factor']['view']);
                }

                $woocommerce->session->set('frotel_result',$result);

                if (isset($result['factor']['banks'])) {
                    // change order status
                    $order->update_status('on-hold', __('در حال انتظار برای پرداخت هزینه سفارش','woocommerce'));
                    $chose_page_id = get_option('frotel_chose_bank_page_id');

                    if (!$chose_page_id)
                        return array(
                            'result' => 'success',
                            'redirect' => $this->get_return_url($order)
                        );

                    $permalink = get_permalink($chose_page_id);

                    if (!$permalink)
                        return array(
                            'result' => 'success',
                            'redirect' => $this->get_return_url($order)
                        );

                    return array(
                        'result' => 'success',
                        'redirect' => $permalink
                    );
                } else {
                    // change order status
                    $order->update_status('on-hold', __('منتظر بررسی توسط مدیر فروشگاه', 'woocommerce'));

                    // Return thank you redirect
                    return array(
                        'result' => 'success',
                        'redirect' => $this->get_return_url($order)
                    );
                }
            }
        }
    }


    function add_frotel_gateway_class($methods)
    {
        $methods[] = 'WC_Gateway_Frotel';
        return $methods;
    }

    /**
     * در زمان نصب افزونه صفحه انتخاب درگاه ایجاد میشود
     */
    function install_frotel_plugin()
    {
        global $wpdb;

        $page_title = 'انتخاب درگاه بانک';
        $page_name = 'chose_bank';

        delete_option('frotel_chose_bank_title');
        add_option('frotel_chose_bank_title',$page_title,'','yes');

        delete_option('frotel_chose_bank_name');
        add_option('frotel_chose_bank_name',$page_name,'','yes');

        delete_option('frotel_chose_bank_page_id');
        add_option('frotel_chose_bank_page_id','0','','yes');

        $page = get_page_by_title($page_title);

        if (!$page) {
            // Create post object
            $_p = array();
            $_p['post_title'] = $page_title;
            $_p['post_content'] = '<h1>انتخاب درگاه بانکی</h1><p>لطفا برای تکمیل سفارش خود یکی از درگاه های زیر را انتخاب کنید : </p><div class="frotel_banks">[frotel_banks]</div>';
            $_p['post_status'] = 'publish';
            $_p['post_type'] = 'page';
            $_p['comment_status'] = 'closed';
            $_p['ping_status'] = 'closed';
            $_p['post_category'] = array(1); // the default 'Uncatrgorised'

            // Insert the post into the database
            $page_id = wp_insert_post($_p);
        } else {
            $page_id = $page->ID;

            //make sure the page is not trashed...
            $page->post_status = 'publish';
            $page_id = wp_update_post($page);
        }

        delete_option('frotel_chose_bank_page_id');
        add_option('frotel_chose_bank_page_id',$page_id);
    }

    /**
     * در صورت حذف و پلاگین صفحه ها و آپشن هایی که در مرحله نصب ایجاد شدند
     * حذف می شود
     */
    function remove_frotel_plugin()
    {
        global $wpdb;

        //  the id of our page...
        $the_page_id = get_option('frotel_chose_bank_page_id');
        if($the_page_id) {
            wp_delete_post($the_page_id); // this will trash, not delete
        }

        delete_option("frotel_chose_bank_title");
        delete_option("frotel_chose_bank_name");
        delete_option("frotel_chose_bank_page_id");
    }


    add_filter( 'woocommerce_states', 'custom_frotel_woocommerce_states' ,99);
    // add state and cities based on frotel ID
    function custom_frotel_woocommerce_states( $states ) {
        $fstates = json_decode(file_get_contents('http://pc.fpanel.ir/city.json'),true);
        $tmp_states = array();
        foreach($fstates as $state){
            $tmp_states[$state['id']] =  $state['name'];
        }
        $states['IR'] = $tmp_states;
        return $states;
    }




    /**
     * اضافه کردن فیلد های شهر  و استان به فرم
     *
     * @param array $fields
     *
     * @return mixed
     */
    function field_city_province($fields)
    {
        wp_enqueue_style('chose_bank_stylesheet',plugins_url('css/bank.css?v='.FROTEL_WOOCOMMERCE_VERSION, __FILE__));
        
        $frotel_options = get_option('woocommerce_frotel_shipping_settings');
        if (isset($frotel_options['enable_coupon']) && $frotel_options['enable_coupon'] == 'yes') {
            $fields['billing']['billing_frotel_coupon'] = array(
                'type' => 'text',
                'label' => __('Coupon', 'woocommerce'),
                'class' => array('form-row-last'),
            );

            $fields['billing']['billing_frotel_radio'] = array(
                'type' => 'radio',
                'label' => '',
                'class' => array('form-row-wide', 'radio_button_frotel'),
                'options' => array(
                    '' => ''
                )
            );
        }
        $fields['billing']['billing_city'] = array(
            'type'      => 'select',
            'label'     => __('City', 'woocommerce'),
            'required'  => true,
            'class'     => array('address-field','form-row-last'),
            'options'   => array(
                '' => 'استان خود را انتخاب کنید'
            )
        );

        $fields['billing']['billing_state']['clear'] = true;

        // reorder fields
        $order = array(
            'first_name',
            'last_name',
            'company',
            'email',
            'phone',
            'country',
            'city',
            'state',
            'address_1',
            'address_2',
            'postcode',
            'frotel_coupon',
            'frotel_radio',
        );
        $tmp = array();
        foreach($order as $item){
            if (isset($fields['shipping']['shipping_'.$item]))
                $tmp['shipping']['shipping_'.$item] = $fields['shipping']['shipping_'.$item];
            if (isset($fields['billing']['billing_'.$item]))
                $tmp['billing']['billing_'.$item] = $fields['billing']['billing_'.$item];
        }

        $fields['billing'] = $tmp['billing'];
        $fields['shipping'] = $tmp['shipping'];
        unset($tmp);

        $fields['shipping']['shipping_postcode']['class'] = $fields['billing']['billing_postcode']['class'] = array('form-row-first');
        $fields['order']['order_comments']['class'] = array('form-row-wide','notes');

        return $fields;
    }

    /**
     *
     * لود کردن شهر و استان های از سرور فروتل
     *
     */
    function add_load_state_js()
    {
        wp_enqueue_script('add_frotel_js',plugins_url('js/lib.js?v='.FROTEL_WOOCOMMERCE_VERSION,__FILE__));
        echo '
        <script type="text/javascript" src="http://pc.fpanel.ir/city.js"></script>
        <script type="text/javascript">
            jQuery(function(){           
                jQuery("select#billing_state").change(function(){
                        ldMenu(jQuery(this).val(),"billing_city");               
                })
            });
            var wc_ajax_url_frotel = "'.WooCommerce::instance()->ajax_url().'";
        </script>';
    }


    /**
     * register shipping method to woocommerce
     *
     * @param array $methods
     *
     * @return array
     */
    function add_frotel_shipping_method($methods)
    {
        $methods[] = 'WC_Frotel_Shipping_Method';
        return $methods;
    }

    /**
     * نمایش درگاه های بانکی برای پرداخت سفارشات نقدی
     */
    function chose_bank()
    {
        if (!isset($_POST['frotel_bank']) && intval($_POST['frotel_bank'])<1) {
            echo json_encode(array('error'=>1,'message'=>'لطفا برای پرداخت هزینه سفارش ، یکی از درگاه های بانکی را انتخاب کنید.'));
            exit;
        }
        /**
         * @var $woocommerce woocommerce
         */
        global $woocommerce;
        $options = get_option('woocommerce_frotel_shipping_settings');
        $order = $woocommerce->session->get('frotel_result');
        if (!isset($order['factor']['id'])) {
            echo json_encode(array('error'=>1,'message'=>'سفارشی برای پرداخت یافت نشد.'));
            exit;
        }


        $frotelHelper = new frotel_helper($options['url'],$options['api']);
        $bankId = intval($_POST['frotel_bank']);
        $chose_page_id = get_option('frotel_chose_bank_page_id');
        $permalink = get_permalink($chose_page_id);

        try{
            $result = $frotelHelper->pay($order['factor']['id'],$bankId,$permalink);
        } catch (FrotelResponseException $e) {
            echo json_encode(array(
                'error'=>1,
                'message'=>$e->getMessage()
            ));
            exit;
        } catch (FrotelWebserviceException $e) {
            echo json_encode(array(
                'error'=>1,
                'message'=>$e->getMessage()
            ));
            exit;
        }

        echo json_encode(array(
            'error'=>0,
            'message'=>'<div class="start_transaction">برای شروع تراکنش و پرداخت هزینه سفارش بر روی دکمه زیر کلیک کنید: <br />'.$result.'</div>'
        ));
        exit;
    }

    /**
     * بررسی کد کوپن
     */
    function check_coupon()
    {
        if (!isset($_POST['coupon']) && intval($_POST['coupon'])<1) {
            echo json_encode(array('error'=>1,'message'=>'لطفا برای پرداخت هزینه سفارش ، یکی از درگاه های بانکی را انتخاب کنید.'));
            exit;
        }

        /**
         * @var $woocommerce woocommerce
         */
        $options = get_option('woocommerce_frotel_shipping_settings');
        $frotelHelper = new frotel_helper($options['url'],$options['api']);
        $coupon = sanitize_text_field($_POST['coupon']);
        try{
            $result = $frotelHelper->checkCoupon($coupon);
        } catch (FrotelResponseException $e) {
            echo json_encode(array(
                'error'=>1,
                'message'=>$e->getMessage()
            ));
            exit;
        } catch (FrotelWebserviceException $e) {
            echo json_encode(array(
                'error'=>1,
                'message'=>$e->getMessage()
            ));
            exit;
        }

        echo json_encode(array(
            'error'=>0,
            'message'=>'کوپن معتبر است. و به میزان '.$result['efficacy'].' بر روی قیمت کالا تاثیر گذار خواهد بود.'
        ));
        exit;
    }

    /**
     * نمایش درگاه های بانکی
     *
     * @return string
     */
    function frotel_banks()
    {
        if (is_admin()) {
            return '';
        }
        /**
         * @var $woocommerce woocommerce
         */
        global $woocommerce;
        $order = $woocommerce->session->get('frotel_result');
        ob_start();

        if (!isset($order['order_id'])) {
            ?>
            <script type="text/javascript">window.location="<?php echo home_url(); ?>";</script>
            <?php
            return ob_get_clean();
        }

        $wcOrder = new WC_Order($order['order_id']);
        // اگر سفارش نقدی ثبت نشده بود و اطلاعات بانک های آن در سشن ذخیره نشده بود
        // کاربر به صفحه اول هدایت می شود
        if (!isset($order['factor']['banks'])){
            ?>
            <script type="text/javascript">window.location="<?php echo $wcOrder->get_checkout_order_received_url(); ?>";</script>
            <?php
            return ob_get_clean();
        }

        // بررسی برای بازگشت از بانک
        if (isset($_GET['_i'],$_GET['sb'])) {
            $options = get_option('woocommerce_frotel_shipping_settings');
            $frotelHelper = new frotel_helper($options['url'],$options['api']);
            try {
                $result = $frotelHelper->checkPay($order['factor']['id'],$_GET['_i'],$_GET['sb']);
            } catch (FrotelResponseException $e) {
                echo '<div class="alert alert-danger">'.$e->getMessage().'</div>';
                return '';
            } catch (FrotelWebserviceException $e) {
                echo '<div class="alert alert-danger">'.$e->getMessage().'</div>';
                return '';
            }

            if ($result['verify'] == 1) { // اگر پرداخت با موفقیت انجام شده بود
                $wcOrder->add_order_note('پرداخت با موفقیت انجام شد. کد رهگیری '.$result['code']);
                $order['factor']['code'] = $result['code'];
                unset($order['factor']['banks']);
                $woocommerce->session->set('frotel_result',$order);
                ?>
                <script type="text/javascript">window.location="<?php echo $wcOrder->get_checkout_order_received_url(); ?>";</script>
                <?php
                return ob_get_clean();
            } else { // اگر در هنگام پرداخت خطایی رخ داده بود
                $error = $result['message'];
            }
        }

        wp_enqueue_style('chose_bank_stylesheet',plugins_url('css/bank.css', __FILE__));
        $banks = $order['factor']['banks'];

        $first = true;
        $chose_page_id = get_option('frotel_chose_bank_page_id');
        $permalink = get_permalink($chose_page_id);
        ?>
        <form action="<?php echo $permalink; ?>" method="post" id="frotel-payment">
            <?php if (isset($error)) { ?>
            <div class="row">
                <div class="col-md-12">
                    <div class="alert alert-danger">
                        <?php echo $error; ?>
                    </div>
                </div>
            </div>
            <?php } ?>
            <div class="row">
                <div class="col-md-12" id="lst_bank_frotel">
            <?php
            foreach($banks as $bank) {
                ?>
                <div class="item-menu text-center">
                    <label class="banks<?php echo $first?' selected':''; ?>" data-id="<?php echo $bank['id']; ?>">
                        <img src="<?php echo $bank['logo']; ?>" class="logo_bank center-block img-responsive img-circle" /><?php echo $bank['name']; ?>
                        <input type="radio" id="frotel_bank_<?php echo $bank['id']; ?>" name="frotel_bank" <?php echo $first?'checked="checked"':''; ?> value="<?php echo $bank['id']; ?>" style="display: none;"/>
                    </label>
                </div>
                <?php

                $first = false;
            }
            ?>
                </div>
            </div>
            <div class="clear"></div>
            <div class="row">
                <div class="col-md-12">
                    <input type="button" value="پرداخت" class="btn" id="bank_chose" />
                    <div id="bank_wait"></div>
                </div>
            </div>
        </form>
        <div class="frotel_result"></div>
        <script type="text/javascript">
            jQuery(function(){
                jQuery('#bank_chose').click(function(){
                    var t=jQuery(this);
                    if (t.hasClass('disabled'))
                        return false;
                    t.addClass('disabled');
                    t.val('منتظر بمانید ...');
                    jQuery('#bank_wait').html('<div class="alert alert-info">در حال اتصال به بانک</div>');
                    jQuery.ajax({
                        url:'<?php echo admin_url('admin-ajax.php','relative'); ?>',
                        type:'post',
                        dataType:'json',
                        data:'action=chose_bank&'+jQuery('#frotel-payment').serialize(),
                        success:function(d){
                            var fr = jQuery('.frotel_result');
                            if (d.error!=undefined){
                                if(d.error==0){
                                    jQuery('#lst_bank_frotel').slideUp();
                                    fr.slideDown();
                                    t.slideUp();
                                    fr.html('<div class="alert alert-info">'+d.message+'</div><a href="javascript:void(0);" id="another_bank" >انتخاب درگاه دیگر</a>');
                                }else{
                                    alert(d.message);
                                    fr.html('');
                                }
                            }else{
                                alert('خطا در دریافت اطلاعات از سرور');
                                fr.html('');
                            }
                        },
                        complete:function(){
                            t.removeClass('disabled');
                            jQuery('#bank_wait').html('');
                            t.val('پرداخت');
                        }
                    });
                });

                jQuery(document).on('click','#another_bank',function(e){
                    e.preventDefault();
                    jQuery('.frotel_result').slideUp();
                    jQuery('#lst_bank_frotel').slideDown();
                    jQuery('#bank_chose').slideDown();
                });
            });
            function addListener(element, eventName, handler) {
                if (element.addEventListener) { element.addEventListener(eventName, handler, false); }
                else if (element.attachEvent) { element.attachEvent('on' + eventName, handler); }
                else { element['on' + eventName] = handler; }
            }
            var el=document.getElementsByClassName('banks');

            function func1(e){
                var el=document.getElementsByClassName('banks');
                for(var i=0;i<el.length;i++){ el[i].className = "banks"; }
                this.className += " selected";
            }
            for(var i=0;i<el.length;i++){
                addListener(el[i],'click',func1);
            }
        </script>
        <?php
        return ob_get_clean();
    }


    /**
     * نمایش کد رهگیری برای پرداخت های نقدی
     * نمایش فاکتور فروتل در صورت فعال سازی توسط مدیر
     *
     * @param int $orderId
     *
     * @return string
     */
    function show_factor_thank_you_page($orderId)
    {
        /**
         * @var $woocommerce woocommerce
         */
        global $woocommerce;
        $session = $woocommerce->session->get('frotel_result');
        if (!isset($session['factor']))
            return '';

        if (isset($session['factor']['code'])) {
            echo '<div class="alert alert-success code_tracking">پرداخت شما با موفقیت انجام شد،کد رهگیری پرداخت  شما «<strong style="color: red;" id="tracking_code">'.$session['factor']['code'].'</strong>» است.</div>';
        }
        $options = get_option('woocommerce_frotel_shipping_settings');

        if ($options['show_factor'] == 'yes') {
            echo '<div class="frotel_factor">'.$session['factor']['view'].'</div>';
        }
    }


    add_action('plugins_loaded', 'frotel_gateway_class');
    add_filter('woocommerce_payment_gateways', 'add_frotel_gateway_class');

    register_activation_hook(__FILE__,'install_frotel_plugin');
    register_deactivation_hook(__FILE__,'remove_frotel_plugin');

    add_action('wp_ajax_chose_bank','chose_bank');
    add_action('wp_ajax_nopriv_chose_bank','chose_bank');

    $frotel_options = get_option('woocommerce_frotel_shipping_settings');
    if (isset($frotel_options['enable_coupon']) && $frotel_options['enable_coupon'] == 'yes') {
        add_filter('woocommerce_coupons_enabled','disable_woocommerce_coupon');
        function disable_woocommerce_coupon()
        {
            return false;
        }


        add_action('wp_ajax_check_coupon', 'check_coupon');
        add_action('wp_ajax_nopriv_check_coupon', 'check_coupon');
    }

    add_filter('woocommerce_shipping_methods','add_frotel_shipping_method');
    add_filter('woocommerce_checkout_fields','field_city_province');

    add_action('woocommerce_shipping_init','frotel_shipping_method_init');
    add_action('woocommerce_after_checkout_form','add_load_state_js');

    add_action('woocommerce_thankyou_frotel','show_factor_thank_you_page');
    add_shortcode('frotel_banks','frotel_banks');

    add_filter('woocommerce_calculated_total','calculated_total_price');
    function calculated_total_price($total_price)
    {
        /**
         * @var WooCommerce $woocommerce
         */
        global $woocommerce;
        if (isset($_SESSION['frotel_coupon_used'])) {
            $coupon = $_SESSION['frotel_coupon_used'];
            $woocommerce->cart->add_fee(__('تخفیف', 'woocommerce'), convertToShopUnitCurrency($coupon['efficacy']));
            $total_price -= convertToShopUnitCurrency($coupon['efficacy']);
        }

        return $total_price;
    }

    /**
     * تبدیل مبلغ به ریال
     *
     * @param float $price
     *
     * @return float
     */
    function convertToRial($price)
    {
        $symbol = strtoupper(get_woocommerce_currency());
        switch ($symbol) {
            case 'IRR':         # ریال
            default:
                return $price;
                break;
            case 'IRHR':        # هزار ریال
                return $price*1000;
                break;
            case 'IRT':        # تومان
                return $price*10;
                break;
            case 'IRHT':        # هزار تومان
                return $price*10000;
                break;
        }
    }

    /**
     * تبدیل مبلغ از ریال به واحد پولی فروشگاه
     *
     * @param float $price
     *
     * @return float
     */
    function convertToShopUnitCurrency($price)
    {
        $symbol = strtoupper(get_woocommerce_currency());
        switch ($symbol) {
            case 'IRR':         # ریال
            default:
                return $price;
                break;
            case 'IRHR':        # هزار ریال
                return $price/1000;
                break;
            case 'IRT':        # تومان
                return $price/10;
                break;
            case 'IRHT':        # هزار تومان
                return $price/10000;
                break;
        }

    }

}
