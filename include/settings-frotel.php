<?php
/**
 * User: ReZa ZaRe <rz.zare@gmail.com>
 * Date: 11/10/15
 * Time: 11:52 PM
 */

return array(
	'enabled' => array(
		'title' 		=> __( 'Enable/Disable', 'woocommerce' ),
		'type' 			=> 'checkbox',
		'label' 		=> __( 'Enable this shipping method', 'woocommerce' ),
		'default' 		=> 'no',
	),
	'api' => array(
		'title'         => __( 'API Key', 'woocommerce' ),
		'type'          => 'text',
		'custom_attributes' => array(
			'maxlenght'     =>32,
			'size'=>32
		)
	),
	'url' => array(
		'title'         => __( 'WebService URL', 'woocommerce' ),
		'type'          => 'text',
		'custom_attributes' => array(
			'dir'=>'ltr',
			'align'=>'left',
		)
	),
	'pishtaz_enable' => array(
		'title'         => 'روش های ارسال',
		'label'         => __('پست پیشتاز','woocommerce'),
		'type'          => 'checkbox',
		'default'       => 'yes',
	),
	'sefareshi_enable' => array(
		'label'         => __('پست سفارشی','woocommerce'),
		'type'          => 'checkbox',
		'default'       => 'yes',
	),
	'fixed_enable' => array(
		'label'         => __('هزینه ثابت','woocommerce'),
		'type'          => 'checkbox',
		'default'       => 'no',
	),
	'online_enable' => array(
		'title'         => 'روش های پرداخت',
		'label'         => __('پرداخت نقدی','woocommerce'),
		'type'          => 'checkbox',
		'default'       => 'yes',
	),
	'cod_enable' => array(
		'label'         => __('پرداخت در محل','woocommerce'),
		'type'          => 'checkbox',
		'default'       => 'yes',
	),
	'default_pishtaz_online' => array(
		'title'         => __('پیشتاز نقدی (ریال)','woocommerce'),
		'type'          => 'text',
		'description'   => __('در صورتی که به هر دلیلی وب سرویس قطع این مبلغ به صورت هزینه ارسال در نظر گرفته می شود','woocommerce'),
		'default'       => 110000,
		'desc_tip'      => true
	),
	'default_pishtaz_cod' => array(
		'title'         => __('پیشتاز پرداخت در محل (ریال)','woocommerce'),
		'type'          => 'text',
		'description'   => __('در صورتی که به هر دلیلی وب سرویس قطع این مبلغ به صورت هزینه ارسال در نظر گرفته می شود','woocommerce'),
		'default'       => 100000,
		'desc_tip'      => true
	),
	'default_sefareshi_online' => array(
		'title'         => __('سفارشی نقدی (ریال)','woocommerce'),
		'type'          => 'text',
		'description'   => __('در صورتی که به هر دلیلی وب سرویس قطع این مبلغ به صورت هزینه ارسال در نظر گرفته می شود','woocommerce'),
		'default'       => 90000,
		'desc_tip'      => true
	),
	'default_sefareshi_cod' => array(
		'title'         => __('سفارشی پرداخت در محل (ریال)','woocommerce'),
		'type'          => 'text',
		'description'   => __('در صورتی که به هر دلیلی وب سرویس قطع این مبلغ به صورت هزینه ارسال در نظر گرفته می شود','woocommerce'),
		'default'       => 100000,
		'desc_tip'      => true
	),
	'default_fixed_cod' => array(
		'title'         => __('هزینه ثابت - پرداخت در محل(ریال)','woocommerce'),
		'type'          => 'text',
		'description'   => __('هزینه ثابت برای پرداخت در محل، این مبلغ به عنوان هزینه ارسال دریافت می شود و نوع ارسال، ارسال شخصی  است و نمی توان با این روش از طریق پست سفارشات ارسال نمی شود.','woocommerce'),
		'default'       => 60000,
		'desc_tip'      => true
	),
	'default_fixed_online' => array(
		'title'         => __('هزینه ثابت - نقدی(ریال)','woocommerce'),
		'type'          => 'text',
        'description'   => __('هزینه ثابت برای پرداخت نقدی، این مبلغ به عنوان هزینه ارسال دریافت می شود و نوع ارسال، ارسال شخصی  است و نمی توان با این روش از طریق پست سفارشات ارسال نمی شود.','woocommerce'),
		'default'       => 60000,
		'desc_tip'      => true
	),
	'total_order_free_send' => array(
		'title'         => __('جمع کل سفارش برای ارسال رایگان (ریال)','woocommerce'),
		'type'          => 'text',
        'description'   => __('در صورتی که جمع کالاهای سفارش به این مبلغ برسد، سفارش به صورت ارسال رایگان ارسال می شود.','woocommerce'),
		'default'       => 0,
		'desc_tip'      => true
	),
	'fixed_city' => array(
		'title'         => __('هزینه ثابت برای شهرهای','woocommerce'),
		'type'          => 'text',
        'description'   => __('شناسه شهرهایی که قصد دارید تا هزینه ثابت برای آن ها فعال شود را وارد کنید.','woocommerce'),
		'default'       => '',
		'desc_tip'      => true
	),
	'order_packing' => array(
		'title'         => __('هزینه بسته بندی کلی','woocommerce'),
		'type'          => 'text',
        'description'   => __('شما می تونید علاوه بر هزینه ارسال ، هزینه بسته بندی را نیز از خریدار دریافت کنید. نکته مهم : حتما باید این مقدار را در پنل پستی فروتل در بخش اصلاح مشخصات بخش هزینه اضافه بسته بندی وارد کرده باشید.','woocommerce'),
		'default'       => 0,
		'desc_tip'      => true
	),
	'default_method' => array(
		'title'         => __('روش پیشفرض','woocommerce'),
		'type'          => 'select',
		'default'       => 'pishtaz_cod',
		'options'       => array(
			'sefareshi_online' => __('سفارشی - نقدی','woocommerce'),
			'sefareshi_cod' => __('سفارشی - پرداخت در محل','woocommerce'),
			'pishtaz_online' => __('پیشتاز - نقدی','woocommerce'),
			'pishtaz_cod' => __('پیشتاز - پرداخت در محل','woocommerce'),
		)
	),
	'default_weight' => array(
		'title'         => __('وزن پیشفرض','woocommerce').' ('.strtolower( get_option('woocommerce_weight_unit') ).')',
		'type'          => 'text',
		'description'   => __('در صورتی که برای محصول خود وزن تعریف نکرده باشید، این مقدار به عنوان وزن در نظر گرفته می شود.','woocommerce'),
		'default'       => 30,
		'desc_tip'      => true
	),
	'show_factor' => array(
			'title' 		=> __( 'نمایش فاکتور', 'woocommerce' ),
			'type' 			=> 'checkbox',
			'label' 		=> __( 'بعد از ثبت سفارش فاکتور فروتل نمایش داده شود؟', 'woocommerce' ),
			'default' 		=> 'yes',
	),
	'enable_coupon' => array(
			'title' 		=> __( 'کوپن', 'woocommerce' ),
			'type' 			=> 'checkbox',
			'label' 		=> __( 'آیا کوپن سیستم فروتل فعال باشد؟', 'woocommerce' ),
			'description'	=> __('با فعال کردن این گزینه، سیستم کوپن داخلی ووکامرس غیرفعال می شود.','woocommerce'),
			'default' 		=> 'no',
	),
);