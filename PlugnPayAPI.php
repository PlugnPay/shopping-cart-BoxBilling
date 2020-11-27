<?php
/**
 * BoxBilling - PlugnPay API
 *
 * @copyright BoxBilling, Inc (http://www.boxbilling.com)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

class Payment_Adapter_PlugnPay implements \Box\InjectionAwareInterface {
    private $config = array();

    protected $di;

    public function setDi($di) {
      $this->di = $di;
    }

    public function getDi() {
      return $this->di;
    }
    
    public function __construct($config) {
      $this->config = $config;
        
      if (!function_exists('curl_exec')) {
        throw new Payment_Exception('PHP Curl extension must be enabled in order to use PlugnPay gateway');
      }
        
      if (!isset($this->config['email'])) {
        throw new Payment_Exception('Payment gateway "PlugnPay" is not configured properly. Please update configuration parameter "PlugnPay Email address" at "Configuration -> Payments".');
      }

      if (!isset($this->config['publisher_name'])) {
        throw new Payment_Exception('Payment gateway "PlugnPay" is not configured properly. Please update configuration parameter "PlugnPay Gateway Account" at "Configuration -> Payments".');
      }

      if (!isset($this->config['publisher_password'])) {
        throw new Payment_Exception('Payment gateway "PlugnPay" is not configured properly. Please update configuration parameter "PlugnPay Remote Client Password" at "Configuration -> Payments".');
      }
    }

    public static function getConfig() {
      return array(
        'supports_one_time_payments' => true,
        'supports_subscriptions'     => true,
        'description'                => 'Enter your PlugnPay data to start accepting payments by PlugnPay.',
        'form'  => array(
          'publisher_email' => array('text', array(
              'label' => 'PlugnPay email address for payments',
              'validators' => array('EmailAddress'),
            ),
          ),
          'publisher_name' => array('text', array(
              'label' => 'PlugnPay Gateway Account (Keep this secret)',
            ),
          ),
          'publisher_password' => array('text', array(
              'label' => 'PlugnPay Remote Client Password (Keep this secret)',
            ),
          ),
        ),
      );
    }

    public function getHtml($api_admin, $invoice_id, $subscription) {
    $invoice = $api_admin->invoice_get(array('id'=>$invoice_id));
    $data = array();
    if ($subscription) {
      $data = $this->getSubscriptionFields($invoice);
    } else {
      $data = $this->getOneTimePaymentFields($invoice);
    }
    $url = $this->config['notify_url'];
    return $this->_generateForm($url, $data);
  }

  public function processTransaction($api_admin, $id, $data, $gateway_id) {
    if (isset($_POST['sale'])) {
      $array = $data['post'];
        
      $client = new PlugnPayRestClient($this->config['publisher_name'], $this->config['publisher_password']);

      try {
        $status = $client->cardSale($array);
      } catch (Exception $e) {
        // handle exceptions here
      }   

      if ($client->isSuccess()) {
        // if ($this->config['test_mode']) {
        //   print_r(json_encode($status)); die();
        // }

        $ips = $status;
        $ipn = $data['post'];

        $tx = $api_admin->invoice_transaction_get(array('id'=>$id));
               
        if (!$tx['invoice_id']) {
          $api_admin->invoice_transaction_update(array('id'=>$id, 'invoice_id'=>$data['get']['bb_invoice_id']));
        }

        if (!$tx['txn_id'] && isset($ips['id_sale'])) {
          $api_admin->invoice_transaction_update(array('id'=>$id, 'txn_id'=>$ips['id_sale']));
        }

        if (!$tx['txn_status'] && isset($ips['success'])) {
          $api_admin->invoice_transaction_update(array('id'=>$id, 'txn_status'=>'success'));
        }

        if (!$tx['amount'] && isset($ipn['sale']['amount'])) {
          $api_admin->invoice_transaction_update(array('id'=>$id, 'amount'=>$ipn['sale']['amount']));
        }
                
        if (!$tx['currency'] && isset($ipn['sale']['currency'])) {
          $api_admin->invoice_transaction_update(array('id'=>$id, 'currency'=>$ipn['sale']['currency']));
        }

        $invoice = $api_admin->invoice_get(array('id'=>$data['get']['bb_invoice_id']));
        $client_id = $invoice['client']['id'];

            if ($ips['success'] == true) {
              $bd = array(
                'id'            =>  $client_id,
                'amount'        =>  $ipn['sale']['amount'],
                'description'   =>  'Paylane transaction '.$ips['id_sale'],
                'type'          =>  'Paylane',
                'rel_id'        =>  $ips['id_sale'],
              );
              if ($this->isIpnDuplicate($ips)){
                throw new Payment_Exception('IPN is duplicate');
              }
              $api_admin->client_balance_add_funds($bd);
              if($tx['invoice_id']) {
                $api_admin->invoice_pay_with_credits(array('id'=>$tx['invoice_id']));
              }
              $api_admin->invoice_batch_pay_with_credits(array('client_id'=>$client_id));
            }
                
            $d = array(
              'id'        => $id, 
              'error'     => '',
              'error_code'=> '',
              'status'    => 'processed',
              'updated_at'=> date('Y-m-d H:i:s'),
            );
            $api_admin->invoice_transaction_update($d);

        } else {
          die("Error ID: {$status['error']['id_error']}, \n".
              "Error number: {$status['error']['error_number']}, \n".
              "Error description: {$status['error']['error_description']}");
      }
    }
        
    header("Location: ". $this->config['return_url']);
    exit;
  }

  private function moneyFormat($amount, $currency) {
    // HUF currency do not accept decimal values
    if ($currency == 'HUF') {
      return number_format($amount, 0);
    }
    return number_format($amount, 2, '.', '');
  }
    
  /**
  * @param string $url
  */
  private function _generateForm($url, $data, $method = 'post') {

    $form = '
      <style type="text/css">
        fieldset.scheduler-border {
          border: 1px groove #ffffff !important;
          padding: 0 1.4em 1.4em 1.4em !important;
          margin: 0 0 1.5em 0 !important;
          -webkit-box-shadow:  0px 0px 0px 0px #000;
          box-shadow:  0px 0px 0px 0px #000;
        }
        legend.scheduler-border {
          font-size: 1em !important;
          font-weight: bold !important;
          text-align: left !important;
          width:auto;
          padding:0 10px;
          border-bottom:none;
        }
      </style>
      <form id=plugnpay-form  action="'.$url.'" method='.$method.'>
        <div class=row>
          <div class="col-4 col-s-6">
            <fieldset class=scheduler-border>
              <legend class=scheduler-border>Sale:</legend>
              <label>Amount:</label>
                <input type=text required readonly name="sale[amount]" value="'.$data['amount'].'">
              <label>Currency:</label>
                <input type=text required readonly name="sale[currency]" value="'.$data['currency'].'">
              <label>Description:</label>
                <input type=text required readonly name="sale[description]" value="'.$data['transaction_description'].'">
            </fieldset>
          </div>
          <div class="col-4 col-s-6">
            <fieldset class=scheduler-border>
              <legend class=scheduler-border>Customer:</legend>
              <label>Nama:</label>
                <input type=text required name="customer[name]" value="'.$data['name'].'">
              <label>Email:</label>
                <input type=text required name="customer[email]" value="'.$data['email'].'">
              <label>IP:</label>
                <input type=text required name="customer[ip]" value="'.$_SERVER['REMOTE_ADDR'].'">
              <label>Address:</label>
                <input type=text required name="customer[address][street_house]" value="'.$data['address'].'">
              <label>City:</label>
                <input type=text required name="customer[address][city]" value="'.$data['city'].'">
              <label>State:</label>
                <input type=text required name="customer[address][state]" value="'.$data['state'].'">
              <label>Zip:</label>
                <input type=text required name="customer[address][zip]" value="'.$data['zip'].'">
              <label>Country Code:</label>
                <input type=text required name="customer[address][country_code]" value="'.$data['country'].'">
            </fieldset>
          </div>
          <div class="col-4 col-s-12">
            <fieldset class=scheduler-border>
              <legend class=scheduler-border>Card:</legend>
              <label>Card number:</label>
                <input type=text required name="card[card_number]"  value='.$data['card_number'].'" /><br>
              <label>Name on card:</label>
                <input type=text required name="card[name_on_card]"  value="'.$data['name_on_card'].'" /><br>
              <label>Expiration date:</label>
                <input type=text required name="card[expiration_month]" value="'.$data['expiration_month'].'" />
                <input type=text required name="card[expiration_year]" value="'.$data['expiration_year'].'" /><br>
              <label>CVV/CVC number:</label>
                <input type=text name="card[card_code]" value="'.$data['card_code'].'" /><br>
            </fieldset>
          </div>
        </div>
        <input type=submit id="plugnpay-submit" value="Submit" />
      </form>
    ';

    return $form;   
  }

  /**
  * @param string $txn_id
  */
  public function isIpnDuplicate(array $ipn) {
    $sql = 'SELECT id
              FROM transaction
             WHERE txn_id = :transaction_id
               AND txn_status = :transaction_status
               AND type = :transaction_type
               AND amount = :transaction_amount
             LIMIT 2';

    $bindings = array(
      ':transaction_id' => $ipn['txn_id'],
      ':transaction_status' => $ipn['payment_status'],
      ':transaction_type' => $ipn['txn_type'],
      ':transaction_amount' => $ipn['mc_gross'],
    );

    $rows = $this->di['db']->getAll($sql, $bindings);
    if (count($rows) > 1){
        return true;
    }

    return false;
  }

  public function getInvoiceTitle(array $invoice) {
    $p = array(
      ':id'=>sprintf('%05s', $invoice['nr']),
      ':serie'=>$invoice['serie'],
      ':title'=>$invoice['lines'][0]['title']
    );
    return __('Payment for invoice :serie:id [:title]', $p);
  }

  public function getSubscriptionFields(array $invoice) {
    $data = array();
    $subs = $invoice['subscription'];

    $data['item_name']          = $this->getInvoiceTitle($invoice);
    $data['item_number']        = $invoice['nr'];
    $data['no_shipping']        = '1';
    $data['no_note']            = '1'; // Do not prompt payers to include a note with their payments. Allowable values for Subscribe buttons:
    $data['currency_code']      = $invoice['currency'];
    $data['return']             = $this->config['return_url'];
    $data['cancel_return']      = $this->config['cancel_url'];
    $data['notify_url']         = $this->config['notify_url'];
    $data['business']           = $this->config['email'];

    $data['cmd']                = '_xclick-subscriptions';
    $data['rm']                 = '2';

    $data['invoice_id']         = $invoice['id'];

    // Recurrence info
    $data['a3']                 = $this->moneyFormat($invoice['total'], $invoice['currency']); // Regular subscription price.
    $data['p3']                 = $subs['cycle']; // Subscription duration. Specify an integer value in the allowable range for the units of duration that you specify with t3.

    /**
     * t3: Regular subscription units of duration. Allowable values:
     *  D – for days; allowable range for p3 is 1 to 90
     *  W – for weeks; allowable range for p3 is 1 to 52
     *  M – for months; allowable range for p3 is 1 to 24
     *  Y – for years; allowable range for p3 is 1 to 5
     */
    $data['t3']                 = $subs['unit'];

    $data['src']                = 1; // Recurring payments. Subscription payments recur unless subscribers cancel their subscriptions before the end of the current billing cycle or you limit the number of times that payments recur with the value that you specify for srt.
    $data['sra']                = 1; // Reattempt on failure. If a recurring payment fails, PayPal attempts to collect the payment two more times before canceling the subscription.
    $data['charset']            = 'UTF-8'; // Sets the character encoding for the billing information/log-in page, for the information you send to PayPal in your HTML button code, and for the information that PayPal returns to you as a result of checkout processes initiated by the payment button. The default is based on the character encoding settings in your account profile.

    // Client data
    $buyer              = $invoice['buyer'];
    $data['address1']   = $buyer['address'];
    $data['city']       = $buyer['city'];
    $data['email']      = $buyer['email'];
    $data['first_name'] = $buyer['first_name'];
    $data['last_name']  = $buyer['last_name'];
    $data['zip']        = $buyer['zip'];
    $data['state']      = $buyer['state'];
    $data['bn']         = 'BoxBilling_SP';
    return $data;
  }

  public function getOneTimePaymentFields(array $invoice) {
    $data = array();

    $data['amount']                  = $this->moneyFormat($invoice['total'], $invoice['currency']); // Regular subscription price.
    $data['currency']                = $invoice['currency'];
    $data['transaction_description'] = $this->getInvoiceTitle($invoice);

    $buyer                           = $invoice['buyer'];

    $data['name']                    = $buyer['first_name'];
    $data['email']                   = $buyer['email'];
    $data['ip']                      = $_SERVER['REMOTE_ADDR'];
    $data['address']                 = $buyer['address'];
    $data['city']                    = $buyer['city'];
    $data['state']                   = $buyer['state'];
    $data['zip']                     = $buyer['zip'];
    $data['country_code']            = $buyer['country_code'],

    $data['card_number']             = $buyer['card_number'];
    $data['name_on_card']            = $buyer['first_name'] . ' ' . $buyer['last_name'];
    $data['expiration_month']        = $buyer['expiration_month'];
    $data['expiration_year']         = $buyer['expiration_year'];
    $data['card_code']               = $buyer['card_code'];
    return $data;
  }
}
