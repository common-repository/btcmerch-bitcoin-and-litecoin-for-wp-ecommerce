<?php

require_once 'btc_options.php';

function btcCreateInvoice($options = array())
{
  global $btcOptions;

  //list of parameters to be passed to seller
  $postOptions = array('item_number', 'item_name',
    'amount', 'currency_code', 'first_name', 'last_name',
    'address1', 'address2', 'country',
    'city', 'state', 'zip', 'merchant_id', 'email');

  //definition url. test or not
  if ($options['sandbox_mode'] == 1) {
    $btcmerch_url = $btcOptions['btcmerch_sandbox_url'];
  } else {
    $btcmerch_url = $btcOptions['btcmerch_url'];
  }
  $html = '<form method="post" id="checkout_form" action="'.$btcmerch_url.'">';

  foreach($postOptions as $o) {
    if (array_key_exists($o, $options)) {
      $post[$o] = $options[$o];
      $html .= '<input type="hidden" name="'.$o.'" value="'.$options[$o].'">';
    }
  }
  $html .='</form><script type="text/javascript">document.forms["checkout_form"].submit();</script>';
  return $html;
}