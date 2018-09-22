<?php
/**
* 2007-2015 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2015 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Trexle extends PaymentModule
{
    protected $config_form = false;
    const DEBUG_FILE = 'log.txt';

    public function __construct()
    {
        $this->name = 'trexle';
        $this->tab = 'payments_gateways';
        $this->version = '1.7.0';
		$this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        $this->author = 'Trexle';
        $this->need_instance = 0;
		$this->is_eu_compatible = 0;
		$this->controllers = array('validation', 'return');

		$this->currencies = true;
        $this->currencies_mode = 'checkbox';

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->l('Trexle');
        $this->description = $this->l('Trexle Prestashop 1.7 module');

		if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = $this->l('No currency has been set for this module.');
        }

    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        if (extension_loaded('curl') == false)
        {
            $this->_errors[] = $this->l('You have to enable the cURL extension on your server to install this module');
            return false;
        }

        Configuration::updateValue('TREXLE_DEBUG', '0');
		Configuration::updateValue('TREXLE_ENVIRONMENT', '0');
		Configuration::updateValue('TREXLE_CHARGE', '1');
		Configuration::updateValue('TREXLE_REFUND_STATUS', '7');
		Configuration::updateValue('TREXLE_PREAUTH_STATUS', '2');
		Configuration::updateValue('TREXLE_CLEAR_LOG', '0');

        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('backOfficeHeader') &&
            $this->registerHook('paymentOptions') &&
			$this->registerHook('displayPaymentTop') &&
			$this->registerHook('displayAdminOrder');
    }

    public function uninstall()
    {
        Configuration::deleteByName('TREXLE_DEBUG');
		Configuration::deleteByName('TREXLE_ENVIRONMENT');
		Configuration::deleteByName('TREXLE_PRIVATE_KEY_LIVE');
		Configuration::deleteByName('TREXLE_PRIVATE_KEY_TEST');
		Configuration::deleteByName('TREXLE_REFUND_STATUS');
		Configuration::deleteByName('TREXLE_PREAUTH_STATUS');
		Configuration::deleteByName('TREXLE_PUBLISHABLE_KEY_LIVE');
		Configuration::deleteByName('TREXLE_PUBLISHABLE_KEY_TEST');
		Configuration::deleteByName('TREXLE_CHARGE');
		Configuration::deleteByName('TREXLE_CLEAR_LOG');

        return parent::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        if (((bool)Tools::isSubmit('submitTrexleModule')) == true) {
            $this->postProcess();
        }

        $this->context->smarty->assign('module_dir', $this->_path);

        $output = $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');

        return $output.$this->renderForm();
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitTrexleModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
		$switch = (version_compare(_PS_VERSION_, '1.6.0', '>=') === true) ? 'switch' : 'radio';

        return array(
            'form' => array(
                'legend' => array(
                'title' => $this->l('Settings'),
                'icon' => 'icon-cogs',
                ),
				'tabs' => array(
					'config' => $this->l('Configuration'),
					'debug' => $this->l('Debug'),
					),
                'input' => array(
						array(
						'type' => 'select',
					    'tab' => 'config',
						'label' => $this->l('Environment'),
						'name' => 'TREXLE_ENVIRONMENT',
						'tab' => 'config',
						'options' => array(
							'query' => array(
								array(
									'id' => 0,
									'name' => $this->l('Test Mode')
								),
								array(
									'id' => 1,
									'name' => $this->l('Live Mode')
								)
							),
							'id' => 'id',
							'name' => 'name',
						)
					),
					array(
						'col' => 3,
                        'type' => 'text',
						'tab' => 'config',
                        'prefix' => '<i class="icon icon-key"></i>',
                        'desc' => $this->l('Enter your Trexle Secret API Key '),
                        'name' => 'TREXLE_PRIVATE_KEY_TEST',
                        'label' => $this->l('Secret Key Test'),
                    ),
                    array(
                        'col' => 3,
						'tab' => 'config',
                        'type' => 'text',
                          'prefix' => '<i class="icon icon-key"></i>',
                        'desc' => $this->l('Enter your Trexle Public key '),
                        'name' => 'TREXLE_PUBLISHABLE_KEY_TEST',
                        'label' => $this->l('Public Key Test'),
                    ),
						array(
						'col' => 3,
                        'type' => 'text',
						'tab' => 'config',
                        'prefix' => '<i class="icon icon-key"></i>',
                        'desc' => $this->l('Enter your Trexle live Private key '),
                        'name' => 'TREXLE_PRIVATE_KEY_LIVE',
                        'label' => $this->l('Private Key Live'),
                    ),
                    array(
                        'col' => 3,
						'tab' => 'config',
                        'type' => 'text',
                          'prefix' => '<i class="icon icon-key"></i>',
                        'desc' => $this->l('Enter your Trexle live Public key '),
                        'name' => 'TREXLE_PUBLISHABLE_KEY_LIVE',
                        'label' => $this->l('Public Key Live'),
                    ),
					  array(
                        'type' => $switch,
						'tab' => 'config',
						'class' => 't',
                        'label' => $this->l('Charge'),
                        'name' => 'TREXLE_CHARGE',
                        'is_bool' => true,
                        'desc' => $this->l('Select Yes to charge card immediately or No to preauthorise payments'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Charge')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->l('Preauthorise')
                            )
                        ),
                    ),
						array(
						'type' => 'select',
						'tab' => 'config',
							 'desc' => $this->l('Only applicable if Charge is set to No.'),
						'label' => $this->l('Pre-Authorisation Order status'),
						'name' => 'TREXLE_PREAUTH_STATUS',
						'options' => array(
							'query' => OrderState::getOrderStates($this->context->language->id),
							'id' => 'id_order_state',
							'name' => 'name'
						)
					),
					array(
						'type' => 'select',
						  'tab' => 'config',
						'label' => $this->l('Refund Order status'),
						'name' => 'TREXLE_REFUND_STATUS',

						'options' => array(
							'query' => OrderState::getOrderStates($this->context->language->id),
							'id' => 'id_order_state',
							'name' => 'name'
						)
					),

					array(
                        'type' => $switch,
						'tab' => 'debug',
						'class' => 't',
                        'label' => $this->l('Debug mode'),
                        'name' => 'TREXLE_DEBUG',
                        'is_bool' => true,
                        'desc' => $this->l('Log traces such as API requests'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),

					array(
					'name' => 'TREXLE_LOG_FILE',
					'tab' => 'debug',
					'type' => 'textarea',
					'label' => $this->l('Log File Contents'),
					'desc' => $this->l('Log file contents'),
					'lang' => false,
					'cols' =>100,
					'rows' => 10,
					),

					array(
						'type' => $switch,
						'tab' => 'debug',
						'label' => $this->l('Clear Log File'),
						'name' => 'TREXLE_CLEAR_LOG',
						'is_bool' => true,
						'class' => 't',
						'values' => array(
							array(
								'id' => 'active_on',
								'value' => 1,
								'label' => $this->l('Yes')
							),
							array(
								'id' => 'active_off',
								'value' => 0,
								'label' => $this->l('No')
							)
						),
						'desc' => $this->l('Select "yes" and click Save to clear the log file.')
					),

                ),

                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
		$log_file = Tools::file_get_contents(dirname(__FILE__).'/log.txt');

        return array(
            'TREXLE_DEBUG' => Configuration::get('TREXLE_DEBUG'),
			'TREXLE_ENVIRONMENT' => Configuration::get('TREXLE_ENVIRONMENT'),
            'TREXLE_PRIVATE_KEY_LIVE' => Configuration::get('TREXLE_PRIVATE_KEY_LIVE'),
			'TREXLE_PRIVATE_KEY_TEST' => Configuration::get('TREXLE_PRIVATE_KEY_TEST'),
			'TREXLE_CHARGE' => Configuration::get('TREXLE_CHARGE'),
            'TREXLE_PUBLISHABLE_KEY_LIVE' => Configuration::get('TREXLE_PUBLISHABLE_KEY_LIVE'),
			'TREXLE_PUBLISHABLE_KEY_TEST' => Configuration::get('TREXLE_PUBLISHABLE_KEY_TEST'),
			'TREXLE_REFUND_STATUS' => Configuration::get('TREXLE_REFUND_STATUS'),
			'TREXLE_PREAUTH_STATUS' => Configuration::get('TREXLE_PREAUTH_STATUS'),
			'TREXLE_LOG_FILE' => $log_file,
			'TREXLE_CLEAR_LOG' => '0',
        );
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }

		if (Tools::getValue('TREXLE_CLEAR_LOG') == '1')
		{
			$file = self::DEBUG_FILE;
			$file = dirname(__FILE__).'/'.$file;
			file_put_contents($file, "");
		}

    }

    /**
    * Add the CSS & JavaScript files you want to be loaded in the BO.
    */
    public function hookBackOfficeHeader()
    {
        if (Tools::getValue('module_name') == $this->name) {
            $this->context->controller->addJS($this->_path.'views/js/back.js');
            $this->context->controller->addCSS($this->_path.'views/css/back.css');
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        $this->context->controller->addJS($this->_path.'/views/js/front.js');
        $this->context->controller->addCSS($this->_path.'/views/css/front.css');
    }

    /**
     * This method is used to render the payment button,
     * Take care if the button should be displayed or not.
     */
    public function hookPaymentOptions($params)
    {
		if (!$this->active)
			return ;

        if (!$this->checkCurrency($params['cart'])) {
            return;
        }

		//check pinpayment keys are valid
	    $livemode = (Configuration::get('TREXLE_ENVIRONMENT') == 1 ? 1 : 0);
		$private_key = ($livemode == 1 ? Configuration::get('TREXLE_PRIVATE_KEY_LIVE') : Configuration::get('TREXLE_PRIVATE_KEY_TEST'));
		if ($livemode && $private_key == '') {
			return;
		}

		$payment_options = [
           $this->getEmbeddedPaymentOption(),
        ];

        return $payment_options;

    }

	public function getEmbeddedPaymentOption()
    {


		$years = array();
		for ($i = date("Y");$i < date("Y") + 10; $i++ )
		{
			$years[$i]  = substr($i, -2);
		}

		$months = array();
		for ($i = 1; $i < 13; $i++)
		{
			$pi = $i < 10 ? '0' . $i : $i;
			$months[$pi] = $pi;
		}

        $this->context->smarty->assign( "total", $this->getCurrentCartTotalDisplay() );
		$this->context->smarty->assign( "years", $years );
		$this->context->smarty->assign( "months", $months );

		//echo "file: ".$this->context->link->getModuleLink($this->name, 'validation', array(), true);

	    $this->context->smarty->assign( "form_action",  $this->context->link->getModuleLink($this->name, 'validation', array(), true)  );

        $embeddedOption = new PaymentOption();
        $embeddedOption->setCallToActionText($this->l('Pay with'));
        $embeddedOption->setForm($this->context->smarty->fetch('module:trexle/views/templates/front/payform.tpl'));

		$this->context->smarty->assign( "this_path_ssl", Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->name.'/' );
		$this->smarty->assign('module_dir', $this->_path);

        $embeddedOption->setAdditionalInformation($this->context->smarty->fetch('module:trexle/views/templates/front/payment_infos.tpl'));
        $embeddedOption->setLogo(Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/views/img/trexle_checkout.png'));

        return $embeddedOption;
    }


    /**
     * Display function for Total Payment
     *
     * @return string
     * since 1.0.0
     */
    private function getCurrentCartTotalDisplay() {
        $cart = $this->context->cart;
        $order_total = round($cart->getOrderTotal(), 2);

        return $this->context->currency->iso_code . " " . $this->context->currency->sign . $order_total;
    }


	/**
	 * save log file
	 *
	 * @param $string
	 * @param null $file
	 */
	public static function log($string, $file = null)
	{
		if (empty($file))
			$file = self::DEBUG_FILE;

		$file = dirname(__FILE__).'/'.$file;
		file_put_contents($file, $string.' - '.date('Y-m-d H:i:s')."\n", FILE_APPEND | LOCK_EX);
	}

	public function hookAdminOrder($params)
	{
		$this->_html = '';

		if (Tools::getValue('trexle'))
		{
			switch (Tools::getValue('trexle'))
			{
				case 'refundOk':
					$message = $this->l('Refund has been made.');
					break;
				case 'refundError':
					$message = $this->l('Error occured when making refund request');
					break;
				case 'captureOk':
					$message = $this->l('Capture Successful.');
					break;
				case 'captureError':
					$message = $this->l('Error occured when making capture request');
					break;
			}

			if (isset($message) && $message)
			{
				$this->_html .= '
				<br />
				<div class="module_confirmation conf confirm" style="width: 400px;">
					<img src="'._PS_IMG_;
				if ((Tools::getValue('trexle') == 'refundError') || (Tools::getValue('trexle') == 'captureError'))
				   $this->_html .= 'admin/error.png'; else $this->_html .= 'admin/ok.gif';

				$this->_html .= '" alt="" title="" /> '.$message.'
				</div>';
			}
		}

		if ($this->canRefund((int)$params['id_order']))
		{
			$this->_html .= '<div class="panel">
			<fieldset style="width:400px;">
				<legend><img src="'._MODULE_DIR_.$this->name.'/logo.png" alt="" /> '.$this->l('Trexle Refund').'</legend>
				<form method="post" action="'.htmlentities($_SERVER['REQUEST_URI']).'">
					<input type="hidden" name="id_order" value="'.(int)$params['id_order'].'" />';
					$order = new Order((int)$params['id_order']);
					$total_paid = $order->total_paid;
					$this->_html .= '<p class="center">Total Refund Amount: $'.$total_paid.'</p>
						<p class="center"><input type="submit" class="button" name="submittrexleRefund" value="'.$this->l('Refund total transaction : $'.$total_paid).'" onclick="if (!confirm(\''.$this->l('Are you sure you want to refund transaction?').'\'))return false;" /></p>';
			$this->_html .= '</form>';
			$this->postRefundProcess();
			$this->_html .= '</fieldset></div>';
		}

		if ($this->canCapture((int)$params['id_order']))
		{
			$this->_html .= '<div class="panel">
			<fieldset style="width:400px;">
				<legend><img src="'._MODULE_DIR_.$this->name.'/logo.png" alt="" /> '.$this->l('Trexle Capture').'</legend>
				<form method="post" action="'.htmlentities($_SERVER['REQUEST_URI']).'">
					<input type="hidden" name="id_order" value="'.(int)$params['id_order'].'" />';
					$order = new Order((int)$params['id_order']);
					$total_paid = $order->total_paid;
					$this->_html .= '<p class="center">Capture Amount: $'.$total_paid.'</p>
						<p class="center"><input type="submit" class="button" name="submittrexleCapture" value="'.$this->l('Capture total transaction : $'.$total_paid).'" onclick="if (!confirm(\''.$this->l('Are you sure your want to capture transaction?').'\'))return false;" /></p>';
			$this->_html .= '</form>';
			$this->postCaptureProcess();
			$this->_html .= '</fieldset></div>';
		}

		return $this->_html;
	}

	private function postRefundProcess()
	{
		if (Tools::isSubmit('submittrexleRefund'))
		{
			require_once('classes/trexleapi.php');
			$id_order = Tools::getValue('id_order');
			$order = new Order($id_order);
			$payments = $order->getOrderPaymentCollection();
			$transaction_id = '';
			if (count($payments))
			{
				foreach ($payments as $payment)
				{
					if ($payment->transaction_id != '')
						$transaction_id = $payment->transaction_id;
				}
			}

			$livemode = (Configuration::get('TREXLE_ENVIRONMENT') == 1 ? 1 : 0);
			$private_key = ($livemode == 1 ? Configuration::get('TREXLE_PRIVATE_KEY_LIVE') : Configuration::get('TREXLE_PRIVATE_KEY_TEST'));
			$publishable_key = ($livemode == 1 ? Configuration::get('TREXLE_PUBLISHABLE_KEY_LIVE') : Configuration::get('TREXLE_PUBLISHABLE_KEY_TEST'));
			$debug = Configuration::get('TREXLE_DEBUG');
			$currencyObj = new Currency($order->id_currency, $order->id_lang, $order->id_shop);

			$TREXLE_object = new TREXLE_transaction($livemode, $private_key, $publishable_key, $debug);
			$refund_id = $TREXLE_object->processCreditRefund($order->id_cart, $order->total_paid, $transaction_id, $currencyObj->iso_code);


			if (!$refund_id)
			{
				$result = $this->l('An error occured during refund process.');
				$status = 'refundError';
			}
			else
			{
				$result = sprintf( $this->l('Refunded $%s - Refund ID: %s' ), $order->total_paid, $refund_id);
				$status = 'refundOk';
				$history = new OrderHistory();
				$history->id_order = (int)$id_order;
				$history->id_employee = 0;
				$history->id_order_state = (int)Configuration::get('TREXLE_REFUND_STATUS');
				$history->add();
			}
			//log results
			if (isset($result))
			{
				$msg = new Message();
				$msg->message = $result;
				$msg->id_order = (int)$id_order;
				$msg->private = 1;
				$msg->add();
			}
			Tools::redirectAdmin(AdminController::$currentIndex.'&id_order='.(int)$id_order.'&vieworder&trexle='.$status.'&token='.Tools::getValue('token'));
		}
	}

	private function canRefund($id_order)
	{
		if (!(int)$id_order)
			return false;

		$order = new Order($id_order);
		// don't display refund option if pre-auth is required and transaction is pending capture
		if (!Configuration::get('TREXLE_CHARGE')) {

			if ($order->current_state == Configuration::get('TREXLE_PREAUTH_STATUS')) return false;
		}

		$payments = $order->getOrderPaymentCollection();

		$transaction_id = '';

		if (count($payments))
		{
				foreach ($payments as $payment)
				{

					if ($payment->transaction_id != '')
						$transaction_id = $payment->transaction_id;
				}
		}

		if ($order->current_state == (int)Configuration::get('TREXLE_REFUND_STATUS') || $transaction_id == '')
			return false;

		return true;
	}

	private function postCaptureProcess()
	{
		if (Tools::isSubmit('submittrexleCapture'))
		{
			require_once('classes/trexleapi.php');
			$id_order = Tools::getValue('id_order');
			$order = new Order($id_order);
			$payments = $order->getOrderPaymentCollection();
			$transaction_id = '';
			if (count($payments))
			{
				foreach ($payments as $payment)
				{
					if ($payment->transaction_id != '')
						$transaction_id = $payment->transaction_id;
				}
			}

			$livemode = (Configuration::get('TREXLE_ENVIRONMENT') == 1 ? 1 : 0);
			$private_key = ($livemode == 1 ? Configuration::get('TREXLE_PRIVATE_KEY_LIVE') : Configuration::get('TREXLE_PRIVATE_KEY_TEST'));
			$publishable_key = ($livemode == 1 ? Configuration::get('TREXLE_PUBLISHABLE_KEY_LIVE') : Configuration::get('TREXLE_PUBLISHABLE_KEY_TEST'));
			$debug = Configuration::get('TREXLE_DEBUG');
			$currencyObj = new Currency($order->id_currency, $order->id_lang, $order->id_shop);

			$TREXLE_object = new TREXLE_transaction($livemode, $private_key, $publishable_key, $debug);
			$capture_id = $TREXLE_object->processCapture($order->id_cart, $order->total_paid, $transaction_id, $currencyObj->iso_code);

			if (!$capture_id)
			{
				$result = $this->l('An error occured during capture process.');
				$status = 'captureError';
			}
			else
			{
				$result = sprintf( $this->l('Transaction Captured $%s - Capture ID: %s' ), $order->total_paid, $capture_id);
				$status = 'captureOk';
				$history = new OrderHistory();
				$history->id_order = (int)$id_order;
				$history->id_employee = 0;
				$history->id_order_state = _PS_OS_PAYMENT_;
				$history->add();
			}

			//log results
			if (isset($result))
			{
				$msg = new Message();
				$msg->message = $result;
				$msg->id_order = (int)$id_order;
				$msg->private = 1;
				$msg->add();
			}
			Tools::redirectAdmin(AdminController::$currentIndex.'&id_order='.(int)$id_order.'&vieworder&trexle='.$status.'&token='.Tools::getValue('token'));
		}
	}


	private function canCapture($id_order)
	{
		if (!(int)$id_order)
			return false;

		// don't display capture option if module is not configured for preauhtorisation
		if (Configuration::get('TREXLE_CHARGE')) return false;

		$order = new Order($id_order);
		if ( $order->current_state == _PS_OS_PAYMENT_) return false;

		// don't display capture option if order state = refunded
		if ( $order->current_state == Configuration::get('TREXLE_REFUND_STATUS') || $order->current_state == 7) return false;

		$payments = $order->getOrderPaymentCollection();
		$transaction_id = '';

		if (count($payments))
		{
				foreach ($payments as $payment)
				{
					if ($payment->transaction_id != '')
						$transaction_id = $payment->transaction_id;
				}
		}

		if ($transaction_id == '')
		  return false;

		return true;
	}

	 /**
    * Function to check the Supported Currency
    * @param Cart $cart
    * @return bool
    * since 1.0.0
    */
    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }

	public function hookDisplayPaymentTop()
    {
        if (!$this->active) {
            return null;
        }

		// assign smarty values
		if (Tools::getValue('cc_error') != '') {

		  $this->context->smarty->assign( "cc_error", Tools::getValue('cc_error'));

		  return $this->context->smarty->fetch($this->local_path.'/views/templates/hook/displaypaymenttop.tpl');

		}
    }

}
