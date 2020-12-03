<?php
/**
 * BoxBilling - PlugnPay DM
 *
 * @copyright BoxBilling, Inc (http://www.boxbilling.com)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */
class Payment_Adapter_PlugnPayDM {
  private $config = array();
    
  public function __construct($config) {
    $this->config = $config;
  }

  public static function getForm() {
    return array(
      'label'  =>  'PnP - Direct Auth',
    );
  }

  public static function getConfig() {
    return array(
      'supports_one_time_payments' =>  true,
      'supports_subscriptions'     =>  true,
      'description'                =>  '<div>&bull; <a href="https://www.plugnpay.com/" target="_blank"><img width="290" height="60" src="https://www.plugnpay.com/logo.gif" alt="Online Payment Gateway" border="0" /></a><br>&bull; <a href="https://pay1.plugnpay.com" target="_blank">PlugnPay Account Login</a></div>',
        'form'  => array(
          'publisher_name' => array('text', array(
              'label' => 'Gateway Account',
            ),
          ),
          'publisher_pass' => array('password', array(
            'label' => 'Remote Client Password',
          ),
        ),
      ),
    );
  }
    
  /**
   * 
   * @param type $api_admin
   * @param type $invoice_id
   * @param type $subscription
   * @return string
   */
  public function getHtml($api_admin, $invoice_id, $subscription) {
    $invoice = $api_admin->invoice_get(array('id'=>$invoice_id));
    $buyer = $invoice['buyer'];

    $p = array(
      ':id'    => sprintf('%05s', $invoice['nr']),
      ':serie' => $invoice['serie'],
      ':title' => $invoice['lines'][0]['title']
    );
    $title = __('Payment for invoice :serie:id [:title]', $p);
    
    $mode           = ($this->config['test_mode']) ? "TEST" : "LIVE";
    $type           = 'SALE';
    $amount         = $invoice['total'];
    $publisher_name = $this->config['publisher_name'];
    $publisher_pass = $this->config['publisher_pass'];
    $tps            = md5($publisher_pass.$publisher_name.$amount.$mode);
        
    $message = '';
    if (isset($_GET['Result'])) {
      $format = '<h2 style="text-align: center; color:red;">%s</h2>';
      switch ($_GET['Result']) {
        case 'APPROVED':
          $message = sprintf($format, $_GET['MESSAGE']);
          break;
        case 'ERROR':
        case 'DECLINED':
        case 'MISSING':
        default:
          $message = sprintf($format, $_GET['MESSAGE']);
          break;
      }
    }
    // https://secure.bluepay.com/interfaces/bp10emu
    // <input type=hidden name=DECLINED_URL value="'.$this->config['cancel_url'].'">
    // <input type=hidden name=MISSING_URL value="'.$this->config['return_url'].'">
    $html = '
      <form action="https://pay1.plugnpay.com/payment/auth.cgi" method="post">
        <input type=hidden name=RESPONSEVERSION value="3">
        <input type=hidden name="publisher-name" value="'.$publisher_name.'">
        <input type=hidden name=TRANSACTION_TYPE value="'.$type.'">
        <input type=hidden name=TAMPER_PROOF_SEAL value="'.$tps.'">
        <input type=hidden name=TPS_DEF value="MERCHANT AMOUNT MODE">
        <input type=hidden name="card-amount" value="'.$amount.'">
        <input type=hidden name="success-link" value="'.$this->config['redirect_url'].'">
        <input type=hidden name="badcard-link" value="'.$this->config['cancel_url'].'">
        <input type=hidden name="problem-link" value="'.$this->config['return_url'].'">
        <input type=hidden name="comments" value="'.$title.'">
        <input type=hidden name=MODE         value="'.$mode.'">
        <input type=hidden name=AUTOCAP      value="0">
        <input type=hidden name=REBILLING    value="1">
        <input type=hidden name=REB_CYCLES   value="">
        <input type=hidden name=REB_AMOUNT   value="">
        <input type=hidden name=REB_EXPR     value="1 MONTH">
        <input type=hidden name=REB_FIRST_DATE value="1 MONTH">
        <input type=hidden name="order-id"  value="'.$invoice['id'].'">
        <input type=hidden name="acct_code"  value="'.$invoice['id'].'">
      '.$message.'
        <table>
          <tr>
            <td>'.__('Card number').'</td>
            <td><input type=text name="card-number" value=""></td>
          </tr>
          <tr>
            <td>'.__('CVV2').'</td>
            <td><input type=text name="card-cvv" value=""></td>
          </tr>
          <tr>
            <td>'.__('Expiration Date').'</td>
            <td><input type=text name="card-exp" value="" placeholder="MM/YY"></td>
          </tr>
          <tr>
            <td>'.__('Name').'</td>
            <td><input type=text name="card-name" value="'.$buyer['first_name'].' '. $buyer['last_name'].'"></td>
          </tr>
          <tr>
            <td>'.__('Address').'</td>
            <td><input type=text name="card-address1" value="'.$buyer['address'].'"></td>
          </tr>
          <tr>
            <td>'.__('City').'</td>
            <td><input type=text name="card-city" value="'.$buyer['city'].'"></td>
          </tr>
          <tr>
            <td>'.__('State').'</td>
            <td><input type=text name="card-state" value="'.$buyer['state'].'"></td>
          </tr>
          <tr>
            <td>'.__('Zipcode').'</td>
            <td><input type=text name="card-zip" value="'.$buyer['zip'].'"></td>
          </tr>
          <tr>
            <td>'.__('Phone').'</td>
            <td><input type=tel name="phone" value="'.$buyer['phone'].'"></td>
          </tr>
          <tr>
            <td>'.__('Email').'</td>
            <td><input type=email name="email" value="'.$buyer['email'].'"></td>
          </tr>
          <tfoot>
          <tr>
            <td colspan=2><input type=SUBMIT value="'.__('Pay now').'" name=SUBMIT class="bb-button bb-button-submit bb-button-big"></td>
          </tr>
          </tfoot>
        </table>
      </form>
      ';

    return $html;
  }

  public function processTransaction($api_admin, $id, $data, $gateway_id) {
    if (APPLICATION_ENV != 'testing' && !$this->_isIpnValid($data)) {
      throw new Payment_Exception('IPN is not valid');
    }

    $ipn = array_merge($data['get'], $data['post']);
      
    $tx = $api_admin->invoice_transaction_get(array('id'=>$id));

    if (!$tx['invoice_id']) {
      $api_admin->invoice_transaction_update(array('id'=>$id, 'invoice_id'=>$ipn['acct_code']));
    }
 
    if (!$tx['type']) {
      $api_admin->invoice_transaction_update(array('id'=>$id, 'type'=>$ipn['TRANS_TYPE']));
    }

    if (!$tx['txn_id']) {
      $api_admin->invoice_transaction_update(array('id'=>$id, 'txn_id'=>$ipn['TRANS_ID']));
    }

    if (!$tx['txn_status']) {
      $api_admin->invoice_transaction_update(array('id'=>$id, 'txn_status'=>$ipn['Result']));
    }

    if (!$tx['amount']) {
      $api_admin->invoice_transaction_update(array('id'=>$id, 'amount'=>$ipn['AMOUNT']));
    }

    if (!$tx['currency']) {
      $api_admin->invoice_transaction_update(array('id'=>$id, 'currency'=>'USD'));
    }

    $invoice = $api_admin->invoice_get(array('id'=>$ipn['acct_code']));
    $client_id = $invoice['client']['id'];

    //echo "<pre>";
    //print_r($ipn);
    //echo "</pre>";

    switch ($ipn['TRANS_TYPE']) {
      // TRANSACTION_TYPE
      // -- Required
      // AUTH, SALE, CAPTURE, REFUND, REBCANCEL
      // AUTH = Reserve funds on a customer's card. No funds are transferred.
      // SALE = Make a sale. Funds are transferred.TRANS_TYPE
      // CAPTURE = Capture a previous AUTH. Funds are transferred.
      // REFUND = Reverse a previous SALE. Funds are transferred.
      // REBCANCEL = Cancel a rebilling sequence.

      case 'AUTH':
      case 'SALE':
        if ($ipn['STATUS']) {
          $bd = array(
            'id'          =>  $client_id,
            'amount'      =>  $ipn['AMOUNT'],
            'description' =>  'PlugnPayDM transaction '.$ipn['TRANS_ID'],
            'type'        =>  'PlugnPayDM',
            'rel_id'      =>  $ipn['TRANS_ID'],
          );
          $api_admin->client_balance_add_funds($bd);
          $api_admin->invoice_batch_pay_with_credits(array('client_id'=>$client_id));
        }
        break;
      case 'REBCANCEL':
        $s = $api_admin->invoice_subscription_get(array('sid'=>$ipn['CUSTOM_ID']));
        $api_admin->invoice_subscription_update(array('id'=>$s['id'], 'status'=>'canceled'));
        break;
      case 'CAPTURE':
      case 'REFUND':
        $refd = array(
          'id'    => $invoice['CUSTOM_ID'],
          'note'  => 'PlugnPayDM refund '.$ipn['TRANS_ID'],
        );
        $api_admin->invoice_refund($refd);
        break;
      default:
        error_log('Unknown PlugnPay transaction '.$id);
        break;
    }

    $d = array(
      'id'         => $id,
      'error'      => '',
      'error_code' => '',
      'status'     => 'processed',
      'updated_at' => date('Y-m-d H:i:s'),
    );
    $api_admin->invoice_transaction_update($d);
  }

  private function _isIpnValid($data) {
    $md5sign = isset($data['md5sign']) ? $data['md5sign'] : '';
    if (empty($md5sign)){
      return true;
    }

    return $md5sign == $this->genHashSign($data);
  }

  /**
   * @param array $data
   * @return string
   */
  private function genHashSign(array $data) {
    $master_id = isset($data['master_id']) ? $data['master_id'] : '';
    $payment_account = isset($data['payment_account']) ? $data['payment_account'] : '';

    $hashstr = $this->config['publisher_pass'] . $this->config['publisher_name'] . $data['trans_type'] .
    $data['card-amount'] . $master_id . $data['card-name'] . $payment_account;

    return bin2hex( md5($hashstr, true) );
  }
}
