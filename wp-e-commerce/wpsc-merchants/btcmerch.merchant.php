<?php

/*
Plugin Name: BTCMerch bitcoin and litecoin for WP e-Commerce
Plugin URI: http://wordpress.org/plugins/btcmerch-bitcoin-and-litecoin-for-wp-ecommerce/
Description:
Version: 2.01
Author: BTCMerch.com
*/

$nzshpcrt_gateways[$num]['name'] = 'Bitcoins/Litecoins';
$nzshpcrt_gateways[$num]['display_name'] = 'Bitcoins/Litecoins';
$nzshpcrt_gateways[$num]['internalname'] = 'Btcmerch';
$nzshpcrt_gateways[$num]['function'] = 'gateway_btcmerch';
$nzshpcrt_gateways[$num]['form'] = 'form_btcmerch';
$nzshpcrt_gateways[$num]['submit_function'] = "submit_btcmerch";

//Create a form of options in admin
function form_btcmerch()
{
  $rows = array();

  if (get_option('btcmerch_sandbox') == 1)
    $selected = 'checked="checked"';
  else
    $selected = '';

  $rows[] = array('Your callback URL', get_option('checkout_url'));
  $rows[] = array('API key', '<input name="btcmerch_apikey" type="text" value="' . get_option('btcmerch_apikey') . '" />');
  $rows[] = array('Merchant ID', '<input name="btcmerch_merchant_id" type="text" value="' . get_option('btcmerch_merchant_id') . '" />');
  $rows[] = array('Sandbox', '<input name="btcmerch_sandbox" type="checkbox" ' . $selected . ' />');
  $currencies = array("BTC", "USD", "EUR", "JPY", "CAD", "GBP", "CHF", "RUB", "AUD", "SEK", "DKK", "HKD", "PLN", "CNY", "SGD", "THB", "NZD", "NOK");
  $currenciesSelect = '<select name="btcmerch_currency">';
  foreach ($currencies as $currency) {
    if (get_option('btcmerch_currency') == $currency) {
      $currenciesSelect .= '<option value="' . $currency . '" selected="selected">' . $currency . '</option>';
    } else {
      $currenciesSelect .= '<option value="' . $currency . '">' . $currency . '</option>';
    }
  }
  $currenciesSelect .= '</select>';

  $rows[] = array('Currency', $currenciesSelect);
  $output = '';
  foreach ($rows as $r) {
    $output .= '<tr> <td>' . $r[0] . '</td> <td>' . $r[1];
    if (isset($r[2]))
      $output .= '<br/><small>' . $r[2] . '</small></td> ';
    $output .= '</tr>';
  }

  return $output;
}

//Saving options in admin
function submit_btcmerch()
{
  $params = array('btcmerch_apikey', 'btcmerch_merchant_id', 'btcmerch_currency');
  foreach ($params as $p)
    if ($_POST[$p] != null)
      update_option($p, $_POST[$p]);

  if ($_POST['btcmerch_sandbox'] == 'on') {
    update_option('btcmerch_sandbox', 1);
  } else {
    update_option('btcmerch_sandbox', 0);
  }
  return true;
}

function gateway_btcmerch($seperator, $sessionid)
{
  require('wp-content/plugins/wp-e-commerce/wpsc-merchants/btcmerch/btc_lib.php');
  require('wp-content/plugins/wp-e-commerce/wpsc-merchants/btcmerch/btc_options.php');

  global $wpsc_gateways, $wpdb, $wpsc_cart, $btcOptions, $gateway_checkout_form_fields;


  $purchase_log = $wpdb->get_row(
    "SELECT * FROM `" . WPSC_TABLE_PURCHASE_LOGS .
    "` WHERE `sessionid`= " . $sessionid . " LIMIT 1"
    , ARRAY_A);

  //Getting information about the customer
  $usersql = "SELECT `" . WPSC_TABLE_SUBMITED_FORM_DATA . "`.value,
    `" . WPSC_TABLE_CHECKOUT_FORMS . "`.`name`,
    `" . WPSC_TABLE_CHECKOUT_FORMS . "`.`unique_name` FROM
    `" . WPSC_TABLE_CHECKOUT_FORMS . "` LEFT JOIN
    `" . WPSC_TABLE_SUBMITED_FORM_DATA . "` ON
    `" . WPSC_TABLE_CHECKOUT_FORMS . "`.id =
    `" . WPSC_TABLE_SUBMITED_FORM_DATA . "`.`form_id` WHERE
    `" . WPSC_TABLE_SUBMITED_FORM_DATA . "`.`log_id`=" . $purchase_log['id'];
  $userinfo = $wpdb->get_results($usersql, ARRAY_A);


  foreach ((array)$userinfo as $value)
    if (strlen($value['value']))
      $ui[$value['unique_name']] = $value['value'];
  $userinfo = $ui;


  // name
  if (isset($userinfo['billingfirstname'])) {
    $options['first_name'] = $userinfo['billingfirstname'];
    if (isset($userinfo['billinglastname']))
      $options['last_name'] = $userinfo['billinglastname'];
  }

  //address
  if (isset($userinfo['billingaddress'])) {
    $newline = strpos($userinfo['billingaddress'], "\n");
    if ($newline !== FALSE) {
      $options['address1'] = substr($userinfo['billingaddress'], 0, $newline);
      $options['address2'] = substr($userinfo['billingaddress'], $newline + 1);
    }else{
      $options['address1'] = $userinfo['billingaddress'];
    }

  }
  if (isset($userinfo['billingstate']))
    $options['state'] = wpsc_get_state_by_id($userinfo['billingstate'], 'code');

  if (isset($userinfo['billingcountry'])) {
    $country = wpsc_country_has_state($userinfo['billingcountry']);
    $options['country'] = $country['country'];
  }
  if (isset($userinfo['billingcity']))
    $options['city'] = $userinfo['billingcity'];

  if (isset($userinfo['billingpostcode']))
    $options['zip'] = $userinfo['billingpostcode'];

  if (isset($userinfo['billingemail']))
    $options['email'] = $userinfo['billingemail'];


  $products = array();

  $itemTotal = 0;
  $taxTotal = $wpsc_cart->total_tax;
  $shippingTotal = number_format($wpsc_cart->base_shipping, 2);
  $shippingTotal += number_format($wpsc_cart->total_shipping, 2);

  //products descrption
  foreach ($wpsc_cart->cart_items as $item) {
    $products[] = $item->product_name . ' x ' . $item->quantity;

    $shippingTotal += number_format($item->shipping, 2);
    $itemTotal += number_format($item->unit_price, 2) * $item->quantity;
  }
  $options['item_name'] = implode(', ', $products);

  if ($wpsc_cart->has_discounts) {
    $discountValue = number_format($wpsc_cart->cart_discount_value, 2);

    $coupon = new wpsc_coupons($wpsc_cart->cart_discount_data);

    // free shipping
    if ($coupon->is_percentage == 2) {
      $shippingTotal = 0;
      $discountValue = 0;
    } elseif ($discountValue >= $itemTotal) {
      $discountValue = $itemTotal - 0.01;
      $shippingTotal -= 0.01;
    }


    $itemTotal -= $discountValue;
  }

  $totalAmount = number_format($itemTotal, 2) + number_format($shippingTotal, 2) + number_format($taxTotal, 2);

  //saving information in an array to create a iframe.
  $options['currency_code'] = get_option('btcmerch_currency');
  $options['apiKey'] = get_option('btcmerch_apikey');
  $options['item_number'] = $sessionid;
  $options['fullNotifications'] = true;
  $options['merchant_id'] = get_option('btcmerch_merchant_id');
  $options['merchant_api_key'] = get_option('btcmerch_apikey');
  $options['quantity'] = $itemTotal;
  $options['amount'] = $totalAmount;
  $options['sandbox_mode'] = get_option('btcmerch_sandbox');


  //creating iframe
  $invoice = btcCreateInvoice($options);

  echo $invoice;
  die();
}

function btcmerch_callback()
{
  global $wpdb;

  //create a hash code
  $str = '';
  $keys = array_keys($_POST);
  sort($keys);

  for ($i = 0; $i < count($keys); $i++) {
    if ($keys[$i] !== 'hash') {
      $str .= $_POST[$keys[$i]];
    }
  }

  $str .= get_option('btcmerch_apikey');


  if ($_POST['hash'] && $_POST['item_number']) {
    if ($_POST['hash'] == md5($str)) {
      $sessionid = $_POST['item_number'];

      //change in the payment status
      $sql = "UPDATE `" . WPSC_TABLE_PURCHASE_LOGS .
        "` SET `processed`= '2' WHERE `sessionid`=" . $sessionid;
      if (is_numeric($sessionid)) {
        $wpdb->query($sql);
        echo "OK";
      } else {
        header("HTTP/1.0 404 Not Found");
        die();
      }
    } else {
      header("HTTP/1.0 404 Not Found");
      die();
    }
  }
}

add_action('init', 'btcmerch_callback');

?>
