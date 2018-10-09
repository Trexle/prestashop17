<?php

require_once _PS_MODULE_DIR_.'trexle/classes/trexleapi.php';

class TrexleValidationModuleFrontController extends ModuleFrontController
{

	public function __construct()
	{
		parent::__construct();

		$this->context = Context::getContext();
		$this->ssl = true;

	}

   public function initContent()
	{

       	parent::initContent();

		//Check Cart and Customer
        $customer = new Customer($this->context->cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            Tools::redirectLink(__PS_BASE_URI__.'order.php?step=1');
        }

        if (!Validate::isLoadedObject($this->context->cart)) {
            Tools::redirectLink(__PS_BASE_URI__.'order.php?step=1');
        }

	    // Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
		$authorized = false;
		foreach (Module::getPaymentModules() as $module) {
	      if ($module['name'] == 'trexle') {
				$authorized = true;
				break;
		  }
	    }

		if (!$authorized) {
            die($this->trans('This payment method is not available.', array(), 'Modules.Trexle.Shop'));
        }

		$this->assign();

    }

	public function assign()
	{

        $cart = $this->context->cart;

        $debug = Configuration::get('TREXLE_DEBUG');
		$success = false;

		/* Validate payment */

		if ($debug) {
		  Trexle::log("here 1 in validation.php in confirm block");
		}

		$currency_iso_code = $this->context->currency->iso_code;
		$total = (float)$cart->getOrderTotal(true, Cart::BOTH);

		//// HERE START API CODE
		//
		// PROCESS TRANSACTION

		$cc_n = $cc_y = $cc_m = $cc_v = $preauth_id = '';
		$timestamp = date('ymd H:i');
		$ip = $_SERVER['REMOTE_ADDR'];
		$host = gethostbyaddr($ip);

		$mode = (Configuration::get('TREXLE_ENVIRONMENT') == 1 ? 1 : 0);
		$private_key = ($mode == 1 ? Configuration::get('TREXLE_PRIVATE_KEY_LIVE') : Configuration::get('TREXLE_PRIVATE_KEY_TEST'));
		$publishable_key = ($mode == 1 ? Configuration::get('TREXLE_PUBLISHABLE_KEY_LIVE') : Configuration::get('TREXLE_PUBLISHABLE_KEY_TEST'));
		$charge = Configuration::get('TREXLE_CHARGE');
		$txn_type = 'TREXLE_TXN_CHARGE';

		if (!$charge)
		{
			$txn_type = 'TREXLE_TXN_PREAUTH';
		}

		$order_id = $cart->id;
		$reference = Configuration::get('PS_SHOP_NAME').' - Cart ID: '.$order_id;
		$address_invoice = new address($cart->id_address_invoice);
		$card_name = $address_invoice->firstname . " " . $address_invoice->lastname;
		$state = State::getNameById($address_invoice->id_state);
		$country = Country::getNameById($this->context->language->id, $address_invoice->id_country);

		$debug_message = "here in validation.php - ".$timestamp.': '.$txn_type.' request from '.$ip.' ['.$host.']'."<br />\n";

		if ($debug)
		{
				Trexle::log($debug_message);
				Trexle::log("GET Mode: ".Configuration::get('TREXLE_ENVIRONMENT'));
				Trexle::log("Mode: ".$mode);
				Trexle::log("Private_key: ".$private_key);
				Trexle::log("publishable_key: ".$publishable_key);
				Trexle::log("Order Total: ".$total);
		}

		$txn = new trexle_transaction($mode, $private_key, $publishable_key, $debug);

		$cc_n = Tools::getValue('cc_number');
		$cc_m = Tools::getValue('cc_month');
		$cc_y = Tools::getValue('cc_year');
		$cc_v = Tools::getValue('cc_cvv');

		//echo "cc_n:".$cc_n."cc_m".$cc_m."cc_y".$cc_y."cc_v".$cc_v;

		if (!$cc_n || !$cc_y || !$cc_m || !$cc_v)
		{
			$error = 'Payment error. Missing card information. Please try again.';

			if ($debug)
			{
				Trexle::log("Here in validation.php missing card information: ");
			}

			Tools::redirectLink(__PS_BASE_URI__.'order.php?step=1&cc_error='.$error);

		}
		else
		{
			 $customer = new Customer($this->context->cart->id_customer);

			if ($debug)
			{
				Trexle::log("Here in validation.php txn_type : ".$txn_type);
			}

			if ($txn_type == 'TREXLE_TXN_PREAUTH')
			{
				$result = $txn->processCreditPreauth($total, $reference, $card_name, $address_invoice->address1, $address_invoice->address2, $address_invoice->city, $address_invoice->postcode, $state, $country, $customer->email, $cc_n, $cc_m, $cc_y, $cc_v, $currency_iso_code);
				$success = strtoupper($result['success']) == 'YES' ? true : false;
				$order_status = Configuration::get('TREXLE_PREAUTH_STATUS');

			} else if ($txn_type == 'TREXLE_TXN_CHARGE')
			{
				$result = $txn->processCreditCharge($total, $reference, $card_name, $address_invoice->address1, $address_invoice->address2, $address_invoice->city, $address_invoice->postcode, $state, $country, $customer->email, $cc_n, $cc_m, $cc_y, $cc_v, $currency_iso_code);
				//$result = array(
				//	'success' => 'yes',
				//	'transactionid' => 'DummyTransID');
				$success = strtoupper($result['success']) == 'YES' ? true : false;
				$order_status = _PS_OS_PAYMENT_;
			}
		}

		if ($success)
		{
			$auth_only = '';
			if ($txn_type == 'TREXLE_TXN_PREAUTH')
				$auth_only = $this->module->l('Auth Only');

			$message = $auth_only.' '.$this->module->l('Trexle Receipt No: ').$result['transactionid'].$this->module->l(' - Last 4 digits of the card: ').substr(Tools::getValue('cc_number'), -4);

			$this->module->validateOrder($cart->id, $order_status, $total, $this->module->l('Trexle'), $message, array('transaction_id' => $result['transactionid']), (int)$this->context->currency->id, false, $customer->secure_key);

			Tools::redirect('index.php?controller=order-confirmation&id_cart='.$cart->id.'&id_module='.$this->module->id.'&id_order='.$this->module->currentOrder.'&key='.$customer->secure_key);

		}

		if (isset($result) && !empty($result['error'])) {
		   Tools::redirectLink(__PS_BASE_URI__.'order.php?step=1&cc_error='.$result['error']);
		}

		$error = 'Unknown error. Please contact web master.';

		Tools::redirectLink(__PS_BASE_URI__.'order.php?step=1&cc_error='.$error);

	}

}
