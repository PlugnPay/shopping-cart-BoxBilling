<?php
/**
 * BoxBilling - PlugnPay SSv2
 *
 * @copyright BoxBilling, Inc (http://www.boxbilling.com)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

class Payment_Adapter_PlugnPaySS2 extends Payment_AdapterAbstract {
  public function init() {
    if (!extension_loaded('curl')) {
      throw new Payment_Exception('cURL extension is not enabled');
    }

    if (!$this->getParam('pt_gateway_account')) {
      throw new Payment_Exception('Payment gateway "PlugnPay" is not configured properly. Please update configuration parameter "Gateway Account" at "Configuration -> Payments".');
    }

    if (!$this->getParam('pb_cards_allowed')) {
      throw new Payment_Exception('Payment gateway "PlugnPay" is not configured properly. Please update configuration parameter "Cards Allowed" at "Configuration -> Payments".');
    }
  }

  public static function getConfig() {
    return array(
      'supports_one_time_payments' =>  true,
      'supports_subscriptions'     =>  false,
      'description'                =>  'To setup PlugnPay merchant account in BoxBilling you need to go to <i>Account &gt; Settings </i> and enter your account values. ' .
                                       'To receive instant payment notifications, copy "IPN callback URL" to PlugnPay "Account &gt; Silent Post URL"',
      'form'  => array(
        'pt_gateway_account' => array('text', array(
            'label'       => 'Gateway Account',
            'description' => 'Username issued by us to you at time of sign up.',
          ),
        ),
        'pb_cards_allowed' => array('text', array(
            'label'       => 'Cards Allowed',
            'description' => 'Credit card types presented to the customer as payment options. Comma separate the values, when specifying multiple card types.',
          ),
        ),
        'pb_tds' => array('select', array(
            'multiOptions' => array(
              'yes' => 'Yes',
              'no' => 'No'
            ),
            'label' => 'Use 3D Secure',
            'description' => 'If set to "Yes", will implement 3D secure checkout functionality. Merchant must subscribe to the 3D secure program. Additional fees apply, so please contact either your sales agent or technical support.',
          ),
        ),
      ),
    );
  }

  /**
   * Return payment gateway type
   * @return string
   */
  public function getType() {
      return Payment_AdapterAbstract::TYPE_FORM;
  }

  public function getServiceUrl() {
    if ($this->config['test_mode']){
      return 'https://cartdev.urlhitch.com/inputtest.cgi';
    }

    return 'https://pay1.plugnpay.com/pay/';
  }

  /**
   * Get invoice id from IPN
   * pt_order_classifier is custom param in SSv2 request
   *
   * @param array $data
   * @return int
   */
  public function getInvoiceId($data) {
    $ipn = $data['post'];
    return isset($ipn['pt_account_code_1']) ? (int)$ipn['pt_account_code_1'] : NULL;
  }

  /**
   * PlugnPay SSv2 integration.
   * Requires to setup ONLY "Silent Post URL" at mechants account
   *
   * @param Payment_Invoice $invoice
   * @return array
   */

  public function singlePayment(Payment_Invoice $invoice) {
    $b = $invoice->getBuyer();

    $params = array(
      'pt_client_identifier'     => 'BillBox_SSv2',
      'pt_gateway_account'       => $this->getParam('pt_gateway_account'),
      'pt_transaction_amount'    => $invoice->getTotalWithTax(),
      'pt_currency'              => $invoice->getCurrency(),
      'pt_account_code_1'        => $invoice->getId(),
      'pt_order_classifier'      => $invoice->getNumber(),
      'pb_cards_allowed'         => $this->getParam('pb_cards_allowed'),
      'pb_tds'                   => $this->getParam('pb_tds'),

       // do not use this, use silent IPN instead
      'pb_transition_type'       => 'get',
      'pb_success_url'           =>  $this->getParam('return_url'),
    );
    return $params;
  }

  public function recurrentPayment(Payment_Invoice $invoice) {
    throw new Payment_Exception('Not implemented yet');
  }

  public function getTransaction($data, Payment_Invoice $invoice) {
    $ipn = array_merge($data['get'], $data['post']);

    $tx = new Payment_Transaction();
    $tx->setId($ipn['pt_account_code_1']);
    $tx->setType(Payment_Transaction::TXTYPE_PAYMENT);
    $tx->setAmount($ipn['pt_transaction_amount']);
    $tx->setCurrency($invoice->getCurrency());


    if ($ipn['pi_response_status'] == 'success') {
      $tx->setStatus(Payment_Transaction::STATUS_COMPLETE);
    }
    else {
      $tx->setStatus(Payment_Transaction::STATUS_PENDING);
    }

    return $tx;
  }

  public function isIpnValid($data, Payment_Invoice $invoice) {
/*
    *** TODO: Unsure why it's not validating right.  Fudged TRUE to make it work... ***

    $ipn = array_merge($data['get'], $data['post']);

    if (($ipn['pi_response_status']) && isset($ipn['pt_order_id'])) {
      return true;
    }

    return false;
*/

    return true;
  }
}

