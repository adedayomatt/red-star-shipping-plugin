<?php
/**
 * Plugin Name: Click N Ship API (Red star)
 * Plugin URI: 
 * Description: This plugin integrates the Click N Ship API by Red Star on user checkout of woocommerce
 * Version: 1.0
 * Author: Adedayo Matthews
 * Author URI: http://www.adedayomatt.com
 */
// auto load dependencies
require __DIR__.'/vendor/autoload.php';

// Defining some constants for the shipping
DEFINE('DEFAULT_SHIPPING_FEE',2000);
DEFINE('SELF_API', 'https://api.perfecttrust.com.ng');

// enque the plugin css
wp_enqueue_style( 'ts_styles', plugin_dir_url( __FILE__ ).'css/styles.css' );

//Add JS to the footer
add_action('wp_footer', 'click_n_ship_hook_js');
add_action('admin_footer', 'click_n_ship_hook_js');
function click_n_ship_hook_js() {
    //this scipt should only run on checkout page
    if (is_page ('8')) { 
      ?>
          <script type="text/javascript">

            jQuery(document).ready(function(){
                /**
                    Request is not sent to http://api.ckicknship.com.ng because of the absence of HTTPS and cross 
                    origin shit, so a sub domain was created to can make request to the clicknship with guzzlehttp 
                    instead of JS. 
                 */
                const self_api = "<?php echo SELF_API ?>";
                let city_select = jQuery('#shipping-options-container').find('select[name="shipping_city"]')
                let town_select = jQuery('#shipping-options-container').find('select[name="shipping_town"]')
                let payment_type_select = jQuery('#shipping-options-container').find('select[name="payment_type"]')
                let delivery_type_select = jQuery('#shipping-options-container').find('select[name="delivery_type"]')
                let process_alert = jQuery('#shipping-options-container').find('.process.alert');
                
                town_select.attr('disabled','true'); //disable the town by default
                jQuery('.city-loading-process').addClass('cities-loading-process ts_info').text('loading available delivery cities...');
                
                jQuery.ajax({
                    url: self_api,
                    type: "POST",
                    data: {'method':'GET', 'endpoint': 'clicknship/operations/cities'}
                })
                .done(function(response, textStatus, jqXHR){
                    response.forEach((city, index, array) => {
                        if(city.CityCode != ' '){
                            city_select.append(jQuery('<option>').attr({'value': city.CityCode, name: (city.CityName == 'MAINLAND' ? 'LAGOS MAINLAND' : city.CityName)}).text((city.CityName == 'MAINLAND' ? 'LAGOS MAINLAND' : city.CityName)))
                        }
                    });
                    jQuery('.city-loading-process').removeClass('ts_info').addClass('ts_success').text(`${response.length} cities available for delivery.`);

                })
                .fail(function(jqXHR, textStatus, errorThrown){
                    jQuery('.city-loading-process').removeClass('ts_info').addClass('ts_danger').text(`Couldn't get delivery cities`);
                });

                city_select.change(function(e){
                    // update checkout to calculate shipping fee
                    jQuery('body').trigger('update_checkout');
                    var cityCode = jQuery(this).val();
                    var cityName = jQuery(this).find(`option[value = "${cityCode}" ]`).text();
                    jQuery('.town-loading-process').text(`loading delivery areas in ${cityName}...`);

                    if(cityCode != ''){
                        jQuery.ajax({
                            url: self_api,
                            type: "POST",
                            data: {'method':'GET', 'endpoint': `clicknship/Operations/DeliveryTowns?CityCode=${cityCode}`}
                        })
                        .done(function(response, textStatus, jqXHR){
                            town_select.removeAttr('disabled');
                            town_select.empty();
                            town_select.append(jQuery('<option>').attr('value', 'blank').text(`Select Town in ${cityName}`))
                            response.forEach((town, index, array) => {
                                if(town.TownID != ''){
                                    town_select.append(jQuery('<option>').attr('value', town.TownID).text(town.TownName))
                                }
                            });
                            jQuery('.town-loading-process').removeClass('ts_info').addClass('ts_success').text(`${response.length} delivery area(s) available in ${cityName}`)
                            // update checkout to calculate shipping fee
                            town_select.change(function(e){
                                jQuery('body').trigger('update_checkout');
                            })
                        })
                        .fail(function(jqXHR, textStatus, errorThrown){
                            jQuery('.town-loading-process').removeClass('ts_info').addClass('ts_danger').text(`Failed to load areas in ${cityName}`);
                        })
                    }else{
                        town_select.attr('disabled', 'true');
                    }
                     
                });

            //Load payment types
            jQuery.ajax({
                    url: self_api,
                    type: "POST",
                    data: {'method':'GET', 'endpoint': 'clicknship/operations/PaymentTypes'}
                })
                .done(function(response, textStatus, jqXHR){
                   
                    response.forEach((payment, index, array) => {
                        payment_type_select.append(jQuery('<option>').attr({'value': payment.PaymentType}).text(payment.PaymentType))
                    });
                    jQuery('.payment-types-loading-process').removeClass('ts_info').addClass('ts_success').text(`${response.length} payment types available`);

                })
                .fail(function(jqXHR, textStatus, errorThrown){
                    jQuery('.payment-types-loading-process').removeClass('ts_info').addClass('ts_danger').text(`Couldn't get available payment types`);
                });

                
            //Load delivery types
            jQuery.ajax({
                url: self_api,
                type: "POST",
                data: {'method':'GET', 'endpoint': 'clicknship/Operations/DeliveryTypes'}
            })
            .done(function(response, textStatus, jqXHR){
                
                response.forEach((delivery, index, array) => {
                    delivery_type_select.append(jQuery('<option>').attr({'value': delivery.DeliveryTypeName}).text(delivery.DeliveryTypeName))
                });
                jQuery('.delivery-types-loading-process').removeClass('ts_info').addClass('ts_success').text(`${response.length} delivery types available`);

            })
            .fail(function(jqXHR, textStatus, errorThrown){
                jQuery('.delivery-types-loading-process').removeClass('ts_info').addClass('ts_danger').text(`Couldn't get available delivery types`);
            });
        })
          </script>
      <?php
    }
    ?>
    <script type="text/javascript">
    /**
    JS for tracking shipping
     */
        jQuery(document).ready(function(){
            const self_api = "<?php echo SELF_API ?>";
            jQuery('.shippment-tracker').each(function(index){
                let tracker = jQuery(this);
                // let waybillno = tracker.attr('data-waybillno') ;
                let waybillno = 'SA00000786' ; //demo data
                let trigger = tracker.find('button.trigger-track')
                let report = tracker.find('div.tracking-report-container');
                let statuses = [];
                trigger.click(function(e){
                    if(waybillno == '' || waybillno == 'N/A'){
                        report.find('.report-heading').html(`<h4 class="ts_danger">Invalid Waybill number</h4><div><small>There is no valid waybill number to track</small></div>`);
                    }else{
                        report.find('.report-heading').html(`<h4 class="ts_info">Receiving shippment status...</h4><div><small>Hold on a bit while we check the status of your shippment of waybill <strong>${waybillno}</strong></small></div>`);
                        report.find('.report-body').empty();
                        trigger.text(`tracking ${waybillno}...`).attr('disabled', 'true');
                        jQuery.ajax({
                                url: self_api,
                                type: "POST",
                                data: {'method':'GET', 'endpoint': `clicknship/Operations/TrackShipment?waybillno=${waybillno}`}
                            })
                            .done(function(response, textStatus, jqXHR){
                                report.find('.report-heading').html(`<h4 class="ts_success">Shipping status retrieved</h4><div><small>please find below the shipping status of the waybill number <strong>${waybillno}</strong></small></div>`);
                                trigger.text('Retrack shipping').removeAttr('disabled');

                                if(response.length > 0){
                                    response.forEach((track_report, index, array) => {
                                        report.find('.report-body').append(jQuery('<div>').addClass('ts_tracking_report').html(`
                                            <div><strong>Order No: </strong> ${track_report.OrderNo}</div>
                                            <div><strong>WayBill No: </strong> ${track_report.WaybillNumber}</div>
                                            <div><strong>Status Description: </strong> ${track_report.StatusDescription}</div>
                                            <div><strong>Status Date: </strong> ${track_report.StatusDate}</div>
                                        `))  
                                    });
                                }else{
                                    report.find('.report-body').append(jQuery('<div>').addClass('ts_tracking_report').html(`
                                        <p class="ts_info">We received a response but no order information was found for the waybill <strong>${waybillno}</strong></p>
                                    `))
                                }
                            })
                            .fail(function(jqXHR, textStatus, errorThrown){
                                report.find('.report-heading').html(`<h4 class="ts_success">Tracking failed</h4><div><small>We couldn't track the waybill <strong>${waybillno}</strong> at the moment : ${textStatus}</small></div>`);
                            })
                    }
                   
                })
            })
        })
    </script>
    <?php
  }


//* Add select field to the checkout page
add_action('woocommerce_before_order_notes', 'click_n_ship_options');
function click_n_ship_options( $checkout ) {
    echo '<h4>'.__('Shippment').' (Red star)</h4><div id="shipping-options-container">';
    echo '<div class="process alert"></div>';
    // shipping name
    woocommerce_form_field( 'shipping_fullname', array(
	    'type'          => 'text',
	    'class'         => array( 'wps-input'),
        'label'         => __( 'Receipient Fullname' ),
        'required'     => true,
        'placeholder'  => 'Who will be receiving this package???'
    ),
    $checkout->get_value( 'shipping_fullname'));


    // shipping city
    woocommerce_form_field( 'shipping_address', array(
	    'type'          => 'text',
	    'class'         => array( 'wps-input'),
        'label'         => __( 'Shipping address' ),
        'required'     => true,
        'placeholder'  => 'Address for the delivery...'
    ),
    $checkout->get_value( 'shipping_address'));

    // shipping city
	woocommerce_form_field( 'shipping_city', array(
	    'type'          => 'select',
	    'class'         => array( 'wps-drop'),
        'label'         => __( 'Shipping city' ),
        'required'     => true,
	    'options'       => array(
            'blank'		=> __( 'Select city')
        )
    ),
    $checkout->get_value( 'shipping_city'));
    echo '<div class="city-loading-process"></div>';

    // shipping town
    woocommerce_form_field( 'shipping_town', array(
	    'type'          => 'select',
	    'class'         => array( 'wps-drop'),
        'label'         => __( ''),
        'required'     => true,
	    'options'       => array(
            'blank'		=> __( 'Select town')
        )
    ),
    $checkout->get_value( 'shipping_town' ));
    echo '<div class="town-loading-process"></div>';

    // delivery phone input
    woocommerce_form_field( 'delivery_phone', array(
	    'type'          => 'text',
	    'class'         => array( 'wps-input'),
        'label'         => __( 'Delivery phone' ),
        'required'     => true,
        'placeholder'  => 'Valid phone that can be contacted...'
    ),
    $checkout->get_value( 'delivery_phone'));

    // delivery phone input
    woocommerce_form_field( 'delivery_email', array(
	    'type'          => 'text',
	    'class'         => array( 'wps-input'),
        'label'         => __( 'Delivery email' ),
        'required'     => true,
        'placeholder'  => 'Valid email that can be contacted...'

    ),
    $checkout->get_value( 'delivery_email'));

    // delivery type
    // woocommerce_form_field( 'delivery_type', array(
	//     'type'          => 'select',
	//     'class'         => array( 'wps-drop'),
    //     'label'         => __( 'Delivery Type'),
    //     'required'     => true,
	//     'options'       => array(
    //         'blank'		=> __( 'Select delivery type')
    //     )
    // ),
    // $checkout->get_value( 'payment_type' ));
    // echo '<div class="delivery-types-loading-process"></div>';

    // payment types
    // woocommerce_form_field( 'payment_type', array(
	//     'type'          => 'select',
	//     'class'         => array( 'wps-drop'),
    //     'label'         => __( 'Payment Type'),
    //     'required'     => true,
	//     'options'       => array(
    //         'blank'		=> __( 'Select payment type')
    //     )
    // ),
    // $checkout->get_value( 'payment_type' ));
    // echo '<div class="payment-types-loading-process"></div>';

    echo "</div>";

}

// processing the checkout

add_action('woocommerce_checkout_process', 'validate_shipping_options');
function validate_shipping_options() {
   global $woocommerce;
   // Check if set, if its not set add an error.
   if ($_POST['shipping_fullname'] == ""){
        wc_add_notice( '<strong>Please fill the name of the recipient</strong>', 'error' );
    }

   if ($_POST['shipping_address'] == ""){
    wc_add_notice( '<strong>Please fill the address the goods are to be delivered</strong>', 'error' );
    }
   if ($_POST['shipping_city'] == "blank"){
        wc_add_notice( '<strong>Please select a delivery city</strong>', 'error' );
   }
   if ($_POST['shipping_town'] == "blank"){
    wc_add_notice( '<strong>Please select a delivery town</strong>', 'error' );
    }

    if ($_POST['delivery_phone'] == ""){
        wc_add_notice( '<strong>Please fill delivery phone number</strong>', 'error' );
    }

    if ($_POST['delivery_email'] == ""){
    wc_add_notice( '<strong>Please fill delivery email address</strong>', 'error' );
    }
            
    // if ($_POST['payment_type'] == "blank"){
    //     wc_add_notice( '<strong>Please select a payment type for your delivery</strong>', 'error' );
    // }
    // if ($_POST['delivery_type'] == "blank"){
    //     wc_add_notice( '<strong>Please select your preferred delivery type</strong>', 'error' );
    // }

}

// update the woocommerce meta order data
add_action('woocommerce_checkout_update_order_meta', 'update_order_meta_data');
function update_order_meta_data( $order_id ) {
    if ($_POST['shipping_fullname']){
        update_post_meta( $order_id, '_shipping_fullname', esc_attr($_POST['shipping_fullname']));
    } 
    if ($_POST['shipping_address']){
        update_post_meta( $order_id, '_shipping_address', esc_attr($_POST['shipping_address']));
    } 
    if ($_POST['shipping_city']){
        update_post_meta( $order_id, '_shipping_city', esc_attr($_POST['shipping_city']));
    }
    if ($_POST['shipping_town']){
        update_post_meta( $order_id, '_shipping_town', esc_attr($_POST['shipping_town']));
    }
    if ($_POST['delivery_phone']){
        update_post_meta( $order_id, '_delivery_phone', esc_attr($_POST['delivery_phone']));
    }
    if ($_POST['delivery_email']){
        update_post_meta( $order_id, '_delivery_email', esc_attr($_POST['delivery_email']));
    }
    // if ($_POST['delivery_type']){
    //     update_post_meta( $order_id, '_delivery_type', esc_attr($_POST['delivery_type']));
    // }
    // if ($_POST['payment_type']){
    //     update_post_meta( $order_id, '_payment_type', esc_attr($_POST['payment_type']));
    // }
}

function origins(){
    return  [
              'lagos' =>  [
                    'name' => 'Perfect Trust Cosmetics, Lagos',
                    'city' => 'MAINLAND',
                    'city_code' => 'MLD',
                    'town_id' => '4225',
                    'address' => 'Idemili Plaza opposite Akwa – Ibom  Plaza, Between Kano plaza and Plateau plaza BBA. Trade Fair Complex, Lagos.',
                    'phone' => '09062496857',
                    'email' => 'ptclagosstore@perfecttrustcosmetics.com'
                ],
               'abuja' => [
                    'name' => 'Perfect Trust Cosmetics, Abuja',
                    'city' => 'ABUJA',
                    'city_code' => 'ABV',
                    'town_id' => '2550',
                    'address' => 'Suite 7, ASG Complex, Plot 83 Adetokunbo Ademola Crescent, Wuse 2, Abuja',
                    'phone' => '08177262022',
                    'email' => 'ptcwuse2annex@perfecttrustcosmetics.com'
                ],
               'enugu' =>  [
                    'name' => 'Perfect Trust Cosmetics, Enugu',
                    'city' => 'ENUGU',
                    'city_code' => 'ENU',
                    'town_id' => '3081',
                    'address' => 'Polo Park Mall, Abakiliki Road, Old GRA, Enugu',
                    'phone' => '09097390388',
                    'email' => 'ptcenugu@perfecttrustcosmetics.com'
                ]

        ];
}
function setOrigin($destination){
    $origins = origins();
    foreach($origins as $origin){
        if($destination == $origin['city_code']){
            return $origin;
        }
    }
    // default origin is set as lagos
    return $origins['lagos'];
}
/**
 * calculate shipping fee from redstar
 */
add_action( 'woocommerce_cart_calculate_fees', 'add_shipping_fee');
function add_shipping_fee( $cart ){
        if ( ! $_POST || ( is_admin() && ! is_ajax() ) ) {
        return;
    }

    if ( isset( $_POST['post_data'] ) ) {
        parse_str( $_POST['post_data'], $post_data );
    } else {
        $post_data = $_POST; // fallback for final checkout (non-ajax)
    }

    $shipping_charge = 0;
    $origin = setOrigin($post_data['shipping_city']);
    if (isset($post_data['shipping_city']) && $post_data['shipping_city'] != 'blank') {
        try{
            $client = new \GuzzleHttp\Client;
            $delivery_fee = $client->post(SELF_API, [
                'http_errors' => false,
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ],
                'form_params' => [
                    'method' => 'POST',
                    'endpoint' => 'clicknship/Operations/DeliveryFee',
                    'header_content_type' => 'application/x-www-form-urlencoded',
    
                    'Origin' => $origin['city'],
                    'Destination' => $post_data['shipping_city'],
                    "OnforwardingTownID" => isset($post_data['shipping_town']) ? $post_data['shipping_town'] : '',
                    //'Weight' => '1.5',
                ]
            ]);

            if($delivery_fee->getStatusCode() == 200){
                $charge = json_decode($delivery_fee->getBody())[0];
                $shipping_charge = $charge->TotalAmount == 0 ? DEFAULT_SHIPPING_FEE : $charge->TotalAmount;
            }
    }
        catch(Exception $e){
            //echo ['exception' => $e->getMessage()];
        }
    }

    $charge = 'Shipping (Red star)'.
                (isset($post_data['shipping_city']) && $post_data['shipping_city'] != 'blank' ?
                     ' From our '.($origin['city'] == 'MAINLAND' ? 'LAGOS MAINLAND' : $origin['city']).' store -->> '.$post_data['shipping_city'].
                        (
                            isset($post_data['shipping_town']) && $post_data['shipping_town'] != 'blank' ?
                                '('.$post_data['shipping_town'].')'
                            : ''
                        )
                    : ' - select city');
    WC()->cart->add_fee($charge, $shipping_charge );
}

// when checkout order is saved...
//add_action('woocommerce_checkout_order_processed', 'submit_pickup_request');

// submit a pickup request when order status is processing..
add_action('woocommerce_order_status_processing', 'submit_pickup_request', 20);
function submit_pickup_request($order_id){

        // first check if no pickup request has been sent for the order before
        if(get_post_meta( $order_id, '_way_bill_number', true ) == '' || get_post_meta( $order_id, '_way_bill_number', true ) == 'N/A'){
            //create an order instance
            $order = wc_get_order($order_id);
            $shipments = [];
            $items = '';
            foreach ($order->get_items() as $item ) {
                $product = wc_get_product($item->get_product_id());
                array_push($shipments, [
                    'ProductId' => $product->get_id(),
                    'ItemName' => $product->get_name(),
                    'ItemUnitCost' => $product->get_price(),
                    'ItemQuantity' => $item->get_quantity()
                ]);
                $items .= $product->get_name().', ';
            } 
            try{
                $origin = setOrigin(get_post_meta($order_id, '_shipping_city', true ));
                $client = new \GuzzleHttp\Client;
                $response = $client->post(SELF_API,[
                    'http_errors' => false,
                    'headers' => [
                        'Content-Type' => 'application/x-www-form-urlencoded'
                    ],
                    'form_params' => [
                        'method' => 'POST',
                        'endpoint' => 'clicknship/Operations/PickupRequest',
                        'header_content_type' => 'application/x-www-form-urlencoded',
    
                        'OrderNo' => $order_id,
                        'Description' => $items.' and Love to be delivered to '.get_post_meta( $order->id, '_shipping_fullname', true ).' at '.get_post_meta( $order->id, '_shipping_address', true ).' from Perfect Trust Cosmetics',
                        // 'Weight' => '2.5',
    
                        // senders info
                        'SenderName' => $origin['name'],
                        'SenderCity' => $origin['city'],
                        'SenderTownID' => $origin['town_id'],
                        'SenderAddress' => $origin['address'],
                        'SenderPhone' => $origin['phone'],
                        'SenderEmail' => $origin['email'],
    
                        // recipient info
                        'RecipientName' => get_post_meta( $order->id, '_shipping_fullname', true ),
                        'RecipientCity' =>  get_post_meta( $order->id, '_shipping_city', true ),
                        'RecipientTownID' => get_post_meta( $order->id, '_shipping_town', true ),
                        'RecipientAddress' => get_post_meta( $order->id, '_shipping_address', true ),
                        'RecipientPhone' => get_post_meta( $order->id, '_delivery_phone', true ),
                        'RecipientEmail' => get_post_meta( $order->id, '_delivery_email', true ),
                        
                        // 'PaymentType' => get_post_meta( $order->id, '_payment_type', true ),
                        // 'DeliveryType' => get_post_meta( $order->id, '_delivery_type', true ),
                        'PaymentType' => 'prepaid',
                        'DeliveryType' => 'Normal Delivery',
                       
                        'ShipmentItems' => $shipments 
                        ]
                ]);
    
                if($response->getStatusCode() == 200 && $response->getBody() != null){
                    $transaction = json_decode($response->getBody());
                    $sending_from = $origin['name'].'. '.$origin['city'] == 'MAINLAND' ? 'LAGOS MAINLAND' : $origin['city'];
                    update_post_meta( $order_id, '_pickup_request_status', esc_attr($transaction->TransStatus));
                    update_post_meta( $order_id, '_pickup_request_status_details', esc_attr($transaction->TransStatusDetails));
                    update_post_meta( $order_id, '_way_bill_number', esc_attr($transaction->WaybillNumber));
                    update_post_meta( $order_id, '_delivery_fee', esc_attr($transaction->DeliveryFee));
                    update_post_meta( $order_id, '_vat_amount', esc_attr($transaction->VatAmount));
                    update_post_meta( $order_id, '_total_amount', esc_attr($transaction->TotalAmount));
                }
               // echo $response->getBody();
            }
            catch(Exception $e){
                echo ['exception' => $e->getMessage()];
            } 
        }

        
}

/**
 * Whenever the order status changes to any other thing asides 'processing', remove the pickup request
 */
// add_action('woocommerce_order_status_on-hold', 'cancel_pickup_request', 20);
// add_action('woocommerce_order_status_failed', 'cancel_pickup_request', 20);
// add_action('woocommerce_order_status_pending', 'cancel_pickup_request', 20);
// add_action('woocommerce_order_status_refunded', 'cancel_pickup_request', 20);
// add_action('woocommerce_order_status_cancelled', 'cancel_pickup_request', 20);
// function cancel_pickup_request($order_id){
//     update_post_meta( $order_id, '_pickup_request_status', esc_attr('cancelled'));
//     update_post_meta( $order_id, '_pickup_request_status_details', esc_attr('The pickup request is cancelled'));
// }

add_action( 'woocommerce_thankyou', 'show_shipping_details', 20);
add_action( 'woocommerce_view_order', 'show_shipping_details', 20);
function show_shipping_details($order_id){
    $way_bill_num = get_post_meta( $order_id, '_way_bill_number', true ) == '' || get_post_meta( $order_id, '_way_bill_number', true ) == 'N/A' ? 
                    '<span class="ts_danger">Not available yet</span>' : 
                    get_post_meta( $order_id, '_way_bill_number', true );

   echo "<h4>Shipping</h4>";
   echo "<div><strong>Pickup request status: </strong>".get_post_meta( $order_id, '_pickup_request_status', true )."</div>";
   echo "<div><strong>Waybill Number: </strong>".$way_bill_num."</div>";
   echo "<div><strong>Recipient: </strong>".get_post_meta( $order_id, '_shipping_fullname', true )."</div>";
   echo "<div><strong>Address: </strong>".get_post_meta( $order_id, '_shipping_address', true )."</div>";
   echo "<div><strong>City: </strong>".get_post_meta( $order_id, '_shipping_city', true )."(".get_post_meta( $order_id, '_shipping_town', true ).")</div>";
   echo "<div><strong>Phone: </strong>".get_post_meta( $order_id, '_delivery_phone', true )."</div>";
   echo "<div><strong>Email: </strong>".get_post_meta( $order_id, '_delivery_email', true )."</div>";
//    echo "<div><strong>Delivery type: </strong>".get_post_meta( $order_id, '_delivery_type', true )."</div>";
//    echo "<div><strong>Payment type: </strong>".get_post_meta( $order_id, '_payment_type', true )."</div>";
    //if there is waybill number abvailable 
   if(get_post_meta( $order_id, '_way_bill_number', true ) != '' && get_post_meta( $order_id, '_way_bill_number', true ) != 'N/A'){
        ?>
        <div class="shippment-tracker" data-waybillno="<?php echo $way_bill_num ?>" style="margin-top: 10px">
            <div class="text-right"><button type="button" class="trigger-track">Track shipping</button></div>
            <div class="tracking-report-container">
                <div class="report-heading"></div>
                <div class="report-body"></div>
            </div>
        </div>
        <?php
    }
}
 
// include the shipping details in emails
add_action( 'woocommerce_email_order_meta', 'include_shipping_details', 20);
 //Display field value on the order edition page
add_action( 'woocommerce_admin_order_data_after_billing_address', 'include_shipping_details', 20, 1 );

function include_shipping_details($order){
    $way_bill_num = get_post_meta( $order->id, '_way_bill_number', true ) == '' || get_post_meta( $order->id, '_way_bill_number', true ) == 'N/A' ? 
                        '<span class="ts_danger">Not available yet.</span>' : 
                        get_post_meta( $order->id, '_way_bill_number', true );
    
    echo "<h4>Shipping (Red star)</h4>";
    echo "<div><strong>Pickup request status: </strong>".get_post_meta( $order->id, '_pickup_request_status', true )."</div>";
    echo "<div><strong>Waybill Number: </strong>".$way_bill_num."</div>";
    echo "<div><strong>Recipient: </strong>".get_post_meta( $order->id, '_shipping_fullname', true )."</div>";
    echo "<div><strong>Address: </strong>".get_post_meta( $order->id, '_shipping_address', true )."</div>";
    echo "<div><strong>City: </strong>".get_post_meta( $order->id, '_shipping_city', true )."(".get_post_meta( $order->id, '_shipping_town', true ).")</div>";
    echo "<div><strong>Phone: </strong>".get_post_meta( $order->id, '_delivery_phone', true )."</div>";
    echo "<div><strong>Email: </strong>".get_post_meta( $order->id, '_delivery_email', true )."</div>";
    // echo "<div><strong>Delivery type: </strong>".get_post_meta( $order->id, '_delivery_type', true )."</div>";
    // echo "<div><strong>Payment type: </strong>".get_post_meta( $order->id, '_payment_type', true )."</div>";
    //if there is waybill number abvailable 
    if(get_post_meta( $order->id, '_way_bill_number', true ) != '' && get_post_meta( $order->id, '_way_bill_number', true ) != 'N/A'){
        ?>
        <div class="shippment-tracker" data-waybillno="<?php echo get_post_meta( $order->id, '_way_bill_number', true ) ?>" style="margin-top: 10px">
            <div class="text-right"><button type="button" class="trigger-track">Track shipping</button></div>
            <div class="tracking-report-container">
                <div class="report-heading"></div>
                <div class="report-body"></div>
            </div>
        </div>
        <?php
    }
 
}
