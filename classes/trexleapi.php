<?php
/**
 * trexleapi.php:
 *
 * Contains a class for sending transactions to Trexle API
 *
 * This class requires cURL to be available to PHP
 *
 * @author Trexle (support@trexle.com)
 * @date 22-Sep-2018
 */

/* Modes */
define('TREXLE_MODE_TEST',			 0);
define('TREXLE_MODE_LIVE',			 1);

/* Server URLs */
define('TREXLE_URL_TEST', 			'https://sanbox.trexle.com');
define('TREXLE_URL_LIVE', 			'https://sanbox.trexle.com');

/* Transaction types. */
define('TREXLE_TXN_CHARGE',		  0);
define('TREXLE_TXN_REFUND',		  5);
define('TREXLE_TXN_PREAUTH', 		 10);
define('TREXLE_TXN_CAPTURE', 		 15);

/* Currencies */
define('TREXLE_CURRENCY_DEFAULT',	'USD');

/**
 * trexleapi
 *
 * This class handles Trexle transactions
 *
 * It supports the following tranactions:
 * 		Credit Payment (standard)
 *		Credit Refund
 *		Credit Preauthorisation
 *
 *
 * @param int mode - The kind of transaction object you would like to open. i.e. TREXLE_TREXLE_MODE_TEST
 * @param string merchantID - The merchant's Secure Key, received from Trexle
 * @param string merchantPW - The merchant's Publishable Key, received from Trexle
 * @param string identifier - Support identifier
 *
 * @notes
 *		Here are the key public functions:
 *			__construct()					""
 *			processCreditCharge()			$txnid/false
 *			processCreditPreauth()			$preauthid/false
 *			processCreditRefund()			        $txnid/false
 *
 */

class trexle_transaction
{
	const TIMEOUT="60";

	const TREXLE_ERROR_OBJECT_INVALID = "The Gateway Object is invalid";
	const TREXLE_ERROR_CURL_ERROR = "CURL failed and reported the following error";
	const TREXLE_ERROR_INVALID_CCNUMBER = "Parameter Check failure: Invalid credit card number";
	const TREXLE_ERROR_INVALID_CCEXPIRY = "Parameter Check failure: Invalid credit card expiry date";
	const TREXLE_ERROR_INVALID_CC_CVC = "Parameter Check failure: Invalid credit card verification code";
	const TREXLE_ERROR_INVALID_TXN_AMT = "Parameter Check failure: Invalid transaction amount";
	const TREXLE_ERROR_INVALID_REF_ID = "Parameter Check failure: Invalid transaction reference number";
	const TREXLE_ERROR_INVALID_REQUEST = "Request failure: Tried to pass Periodic payment through Payment trexle or vice versa";
	const TREXLE_ERROR_INVALID_ACCOUNTNUMBER = "Parameter Check failure: Invalid account number";
	const TREXLE_ERROR_INVALID_ACCOUNTNAME = "Parameter Check failure: Invalid account name";
	const TREXLE_ERROR_INVALID_ACCOUNTBSB = "Parameter Check failure: Invalid BSB";
	const TREXLE_ERROR_RESPONSE_ERROR = "A general response error was detected";
	const TREXLE_ERROR_RESPONSE_INVALID = "A unspecified error was detected in the response content";
	const TREXLE_ERROR_XML_PARSE_FAILED = "The response message could not be parsed (invalid XML?)";
	const TREXLE_ERROR_RESPONSE_XML_MESSAGE_ERROR = "An unspecified error was found in the response message (missing field?)";
	const TREXLE_ERROR_TREXLE_STATUS = "The remote Gateway reported the following status error";
	const TREXLE_ERROR_TXN_DECLINED = "Transaction Declined";
	const DEBUG_FILE = 'log.txt';

	/* Common */
	private $txnReference, $amount;
	private $bankTxnID = 0;
	private $errorString;
	private $trexleObjectValid = true;
	private $debug = false;
	private $endPointUrl, $privateKey, $publishableKey, $email, $card_name, $address1, $address2, $city, $postcode, $country;
	private $responseArray = array();
	private $txnType = 0;

	/* cc */
	private $ccNumber, $cvc, $ccExpiryMonth, $ccExpiryYear;
	private $currency=TREXLE_CURRENCY_DEFAULT;

	/**
	 * __construct
	 *
	 * @param integer $trexlemode One of TREXLE_TREXLE_MODE*
	 * @param string $setup_merchantID
	 * @param string $setup_merchantPW
	 */
	public function __construct($mode, $private_key, $publishable_key, $debug)
	{

		$this->trexleObjectValid = true;

		switch ($mode)
		{
			case TREXLE_MODE_TEST:
				$this->endPointUrl = TREXLE_URL_TEST;
				break;
			case TREXLE_MODE_LIVE:
				$this->endPointUrl = TREXLE_URL_LIVE;
				break;
			default:
				$this->trexleObjectValid = false;
				return;
		}

		if (strlen($private_key) == 0)
		{
			$this->trexleObjectValid = false;
			return;
		}

		if (strlen($publishable_key) == 0)
		{
			$this->trexleObjectValid = false;
			return;
		}

		$this->setMode($mode);
		$this->setPrivateKey($private_key);
		$this->setPublishableKey($publishable_key);
		$this->setDebug($debug);

		return;
	}

	/**
	 * reset
	 *
	 * Clears response variables, preventing mismatched results in certain failure cases.
	 * This is called before each transaction, so be sure to check these values between transactions.
	 */
	public function reset()
	{
		$this->errorString = NULL;
		$this->responseArray = array();
		$this->bankTxnID = 0;
		$this->txnType = 0;
	}

	public function isGatewayObjectValid() { return $this->trexleObjectValid; }

	public function getAmount() { return $this->amount; }

	/**
	 * setAmount
	 *
	 * Takes amount as a decimal; requires currency to be set
	 *
	 * @param float amount
	 */
	public function setAmount($amount)
	{
		if($this->getCurrency() == 'JPY')
		{
		   $this->amount = $amount;
		}
		else
		{
			$this->amount = round($amount*100,0);
		}
		return;
	}

	public function getCurrency() { return $this->currency; }
	public function setCurrency($cur) { $this->currency = $cur; }

	public function getTxnReference() { return $this->txnReference; }
	public function setTxnReference($ref) { $this->txnReference = $ref; }

	public function getTxnType() { return $this->txnType; }
	public function setTxnType($type) { $this->txnType = $type; }

	public function getPreauthID() { return $this->preauthID; }
	public function setPreauthID($id) { $this->preauthID = $id; }

	public function getCCNumber() { return $this->ccNumber; }
	public function setCCNumber($ccNumber) { $this->ccNumber = $ccNumber; }

	public function getCVC() { return $this->cvc; }
	public function setCVC($ver) { $this->cvc = $ver; }

	/* @return string month MM*/
	public function getCCExpiryMonth() { return $this->ccExpiryMonth; }

	/* @return string year YY*/
	public function getCCExpiryYear() { return $this->ccExpiryYear; }

	/* @param string/int month MM or month M - If there are leading zeros, type needs to be string*/
	public function setCCExpiryMonth($month)
	{
		$l = strlen(trim($month));
		if($l == 1)
		{
			$this->ccExpiryMonth = sprintf("%02d",ltrim($month,'0'));
		}
		else
		{
			$this->ccExpiryMonth = $month;
		}

		return;
	}

	/* @param string/int year YY or year Y or year YYYY - If there are leading zeros, type needs to be string*/
	public function setCCExpiryYear($year)
	{
		$y = ltrim(trim((string)$year),"0");
		$l = strlen($y);
		if($l==4)
		{
			$this->ccExpiryYear = substr($y,2);
		}
		else if($l>=5)
		{
			$this->ccExpiryYear = 0;
		}
		else if($l==1)
		{
			$this->ccExpiryYear = sprintf("%02d",$y);
		}
		else
		{
			$this->ccExpiryYear = $year;
		}
		return;
	}

	public function getClearCCNumber()
	{
		$t = $this->getCCNumber();
		$this->setCCNumber("0");
		return $t;
	}

	public function getClearCVC()
	{
		$t = $this->getCVC();
		$this->setCVC(0);
		return $t;
	}

	public function getMode() { return $this->mode; }
	public function setMode($mode) { $this->mode = $mode; }

	public function getPrivateKey() { return $this->privateKey; }
	public function setPrivateKey($key) { $this->privateKey = $key; }

	public function getPublishableKey() { return $this->publishableKey; }
	public function setPublishableKey($key) { $this->publishableKey = $key; }

	public function getDebug() { return $this->debug; }
	public function setDebug($debug) { $this->debug = $debug; }

	public function getEmail() { return $this->email; }
	public function setEmail($email) { $this->email = $email; }

	public function getCardName() { return $this->card_name; }
	public function setCardName($name) { $this->card_name = $name; }

	public function getAddress1() { return $this->address1; }
	public function setAddress1($address)
	{
		$this->address1 = str_replace(array('\'', '"'), '', $address);

	}

	public function getAddress2() { return $this->address2; }
	public function setAddress2($address)
	{
		$this->address2 = str_replace(array('\'', '"'), '', $address);

	}

	public function getCity() { return $this->city; }
	public function setCity($city) { $this->city = $city; }

	public function getPostCode() { return $this->post_code; }
	public function setPostCode($post_code) { $this->post_code = $post_code; }

	public function getState() { return $this->state; }
	public function setState($state) { $this->state = $state; }

	public function getCountry() { return $this->country; }
	public function setCountry($country) { $this->country = $country; }

	public function getBankTxnID () { return $this->bankTxnID; }
	public function setBankTxnID ($id) { $this->bankTxnID = $id; }

	public function getClientID () { return $this->clientID; }
	public function setClientID ($t) { $this->clientID = $t; }

	public function getNumberOfPayments () { return $this->numberOfPayments; }
	public function setNumberOfPayments ($t) { $this->numberOfPayments = $t; }

	public function getErrorString () { return $this->errorString; }

	public function getResultArray () { return $this->responseArray; }

	public function getResultByKeyName ($keyName)
	{
		if (array_key_exists($keyName, $this->responseArray) === true)
		{
			return $this->responseArray[$keyName];
		}
		return false;
	}

	public function getTxnWasSuccesful()
	{
		if (array_key_exists("txnResult", $this->responseArray)	&& $this->responseArray["txnResult"] === true)
		{
			return true;
		}
		return false;
	}


	public function processCreditCharge($amount, $reference, $card_name, $address1, $address2, $city, $postcode, $state, $country, $email, $card_number, $card_month, $card_year, $card_cvc, $currency=TREXLE_CURRENCY_DEFAULT)
	{

		$this->reset();

		if(!$this->getTxnType())
		{
			$this->setTxnType(TREXLE_TXN_CHARGE);
		}

		if($currency)
		{
			$this->setCurrency($currency);
		}

		$this->setAmount($amount);
		$this->setEmail($email);
		$this->setTxnReference($reference);
		$this->setCCNumber($card_number);
		$this->setCVC($card_cvc);
		$this->setCCExpiryYear($card_year);
		$this->setCCExpiryMonth($card_month);
		$this->setCardName($card_name);
		$this->setAddress1($address1);
		$this->setAddress2($address2);
		$this->setCity($city);
		$this->setPostCode($postcode);
		$this->setState($state);
	    $this->setCountry($country);

		return $this->processTransaction();
	}

	public function processCreditRefund($order_id, $amount, $transaction_id, $currency=TREXLE_CURRENCY_DEFAULT )
	{
		$this->reset();

        if (!$transaction_id )
		{
			// log error;
			if ($this->getDebug())
				Trexle::log( "Error missing transaction id: ".$transaction_id );
            return false;
        }

		if($currency)
			$this->setCurrency($currency);
		$this->setAmount($amount);

		$amt = $this->getAmount($amount);
		$fields = array(
			'amount' => $amt
		);

		//Send request
		$response = $this->sendRequestPost($this->endPointUrl . '/api/v1/charges'. $transaction_id .'/refunds', $fields);

		//if ($this->getDebug())
		//	Trexle::log( $this->getTxnReference(). ' Response: ' . print_r( $response, true ) );

		//Remove the request from memory
		unset($fields);

		if(	!empty($response['response']['status_message']) && $response['response']['status_message'] == 'Pending' ) {
			if ($this->getDebug())
			  Trexle::log( "Refund Successful. Refund ID: ".$response['response']['token'] );
			return $response['response']['token'];
		}
		else { // log error
			if ($this->getDebug())
				Trexle::log("Error Refunding Order ID: ".$order_id. " Error Description: ".$response['error_description']);
			    return false;
		}

	}

	public function processCreditPreauth($amount, $reference, $card_name, $address1, $address2, $city, $postcode, $state, $country, $email, $card_number, $card_month, $card_year, $card_cvc, $currency=TREXLE_CURRENCY_DEFAULT)
	{

		$this->reset();

		if(!$this->getTxnType())
		{
			$this->setTxnType(TREXLE_TXN_PREAUTH);
		}

		if($currency)
		{
			$this->setCurrency($currency);
		}

		$this->setAmount($amount);
		$this->setEmail($email);
		$this->setTxnReference($reference);
		$this->setCCNumber($card_number);
		$this->setCVC($card_cvc);
		$this->setCCExpiryYear($card_year);
		$this->setCCExpiryMonth($card_month);
		$this->setCardName($card_name);
		$this->setAddress1($address1);
		$this->setAddress2($address2);
		$this->setCity($city);
		$this->setPostCode($postcode);
		$this->setState($state);
	    $this->setCountry($country);

		return $this->processTransaction();
	}

	public function processCapture($order_id, $amount, $transaction_id, $currency=TREXLE_CURRENCY_DEFAULT)
	{
		$this->reset();

		if(!$this->getTxnType())
		{
			$this->setTxnType(TREXLE_TXN_CAPTURE);
		}

        if (!$transaction_id )
		{
			// log error;
			if ($this->getDebug())
				Trexle::log( "Error missing transaction id: ".$transaction_id );
            return false;
        }

		if($currency)
			$this->setCurrency($currency);
		$this->setAmount($amount);

		$amt = $this->getAmount($amount);
		$fields = array(
			'amount' => $amt
		);

		if ($this->getDebug())
		{
				//Trexle::log( "Here in processCaptur function. Fields: ".print_r($fields, true) );
				Trexle::log( "Amount: ".$amt );
				Trexle::log( "Curency: ".$currency );
		}

		//Send capture request
		$response = $this->sendRequestPut($this->endPointUrl . '/api/v1/charges'. $transaction_id .'/capture', $fields);

		//if ($this->getDebug())
		//	Trexle::log(' Capture Response: ' . print_r( $response, true ) );

		//Remove the request from memory
		unset($fields);

		if(	!empty($response['response']['success']) && $response['response']['success'] == 1 )
		{
			//if ($this->getDebug())
			//  Trexle::log( "Capture Successful. Capture ID: ".$response['response']['token'] );
			return $response['response']['token'];
		}
		else { // log error
			if ($this->getDebug())
				Trexle::log("Error Capturing payment for order id: ".$order_id. " Error Description: ".$response['error_description']);
			    return false;
		}

	}

	/**
	 * processTransaction:
	 *
	 * Attempts to process the transaction using the supplied details
	 *
	 * @return boolean Returns true for succesful (approved) transaction / false for failure (declined) or error
	 */
	private function processTransaction ()
	{
		if ($this->getDebug())
		{
			Trexle::log( "here in api processTransaction 1 " );
		}

		//Check for trexle validity
		if (!$this->trexleObjectValid)
		{
			$this->errorString = self::TREXLE_ERROR_OBJECT_INVALID;
			 return array(
						'success' => 'no',
						'error' => $this->getErrorString());
		}

		if ($this->checkCCParameters() == false)
		{
				  return array(
						'success' => 'no',
						'error' => $this->getErrorString());
		}

		//Check the common parameters eg amount
		if ($this->checkTxnParameters() == false)
		{
			 return array(
						'success' => 'no',
						'error' => $this->getErrorString());
		}

		$capture = 'false';
		if ($this->getTxnType() == TREXLE_TXN_CHARGE)
		  $capture = 'true';

		$fields = array(
						'amount' 		=> $this->getAmount(),
						'currency' 		=> $this->getCurrency(),
						'description' 	=> $this->getTxnReference(),
						'email' 		=> $this->getEmail(),
        				'capture'		=> $capture,
						'ip_address'	=> ! empty( $_SERVER['HTTP_X_FORWARD_FOR'] ) ? $_SERVER['HTTP_X_FORWARD_FOR'] : $_SERVER['REMOTE_ADDR'],
        				'card[number]' 			=> $this->getCCNumber(),
        				'card[expiry_month]' 	=> $this->getCCExpiryMonth(),
        				'card[expiry_year]' 	=> $this->getCCExpiryYear(),
        				'card[cvc]' 			=> $this->getCVC(),
        				'card[name]' 			=> $this->getCardName(),
        				'card[address_line1]' 	=> $this->getAddress1(),
        				'card[address_line2]' 	=> $this->getAddress2(),
        				'card[address_city]' 	=> $this->getCity(),
        				'card[address_postcode]' => $this->getPostCode(),
        				'card[address_state]' 	=> $this->getState(),
        				'card[address_country]' => $this->getCountry()
        	);

		if ($this->getDebug())
		{
			//Trexle::log( $this->getTxnReference(). ' Fields: ' . print_r( $fields, true ) );
			Trexle::log( $this->getTxnReference(). ' End point URL: ' . $this->endPointUrl . '/1/charges');
		}

		//Send request
		$response = $this->sendRequestPost($this->endPointUrl . '/1/charges', $fields);


		//Remove the request from memory
		unset($fields);

		if(	!empty($response['response']['success']) && $response['response']['success'] == 1 ) {
    	if ($this->getDebug())
		   {
			    Trexle::log('Payment Successful');
		   }
				 return array(
						'success' => 'yes',
						'transactionid' => $response['response']['token']);
  }
		else
		{
     	    if ($this->getDebug())
		    {
			     Trexle::log('Payment_error: '.$response['error_description']);
		    }

			return array(
						'success' => 'no',
						'error' => 'Error: '.$response['error_description']);
        }

	}

	/**
	 * checkCCParameters
	 *
	 * Check the input parameters are valid for a credit card transaction
	 *
	 * @return boolean Return TRUE for all checks passed OK, or FALSE if an error is detected
	 */
	private function checkCCParameters()
	{
		//$ccNumber must be numeric, and have between 12 and 19 digits
		if (strlen($this->getCCNumber()) < 12 || strlen($this->getCCNumber()) > 19 || preg_match("/\D/",$this->getCCNumber()))//Regex matches non-digit
		{
			$this->errorString = self::TREXLE_ERROR_INVALID_CCNUMBER;
			return false;
		}

		//$ccExpiryMonth must be numeric between 1 and 12
		if (preg_match("/\D/", $this->getCCExpiryMonth()) || (int) $this->getCCExpiryMonth() < 1 || (int) $this->getCCExpiryMonth() > 12)
		{
			$this->errorString = self::TREXLE_ERROR_INVALID_CCEXPIRY;
			return false;
		}

		//$ccExpiryYear is in YY format, and must be between this year and +12 years from now
		if (preg_match("/\D/", $this->getCCExpiryYear()) || (strlen($this->getCCExpiryYear()) != 2) ||
			(int) $this->getCCExpiryYear() < (int) substr(date("Y"),2) || (int) $this->getCCExpiryYear() > ((int) substr(date("Y"),2) + 12))
		{
			$this->errorString = self::TREXLE_ERROR_INVALID_CCEXPIRY;
			return false;
		}

		//CVC
		//$ccVericationNumber must be numeric between 000 and 9999
		if (preg_match("/\D/", $this->getCVC()) || strlen($this->getCVC()) < 3 || strlen($this->getCVC()) > 4 ||
			(int) $this->getCVC() < 0 || (int) $this->getCVC() > 9999)
		{
			$this->errorString = self::TREXLE_ERROR_INVALID_CC_CVC;
			return false;
		}

		return true;
	}


	/**
	 * checkTxnParameters
	 *
	 * Check that the common values are within requirements
	 *
	 * @param string $txnAmount
	 * @param string $txnReference
	 *
	 * @return TRUE for pass, FALSE for fail
	 */
	private function checkTxnParameters ()
	{
		$amount = $this->getAmount();
		if (preg_match("/^[0-9]/", $amount)==false || (float)$amount < 0)
		{
			$this->errorString = self::TREXLE_ERROR_INVALID_TXN_AMT;
			return false;
		}


		$ref = $this->getTxnReference();

		//Credit transaction references can have any character except space and single quote and need to be less than 60 characters
		if (strlen($ref) == 0 || strlen($ref)>60 ||
			preg_match('/[^ \']/', $ref)==false) //Matches space and '
		{
			$this->errorString = self::TREXLE_ERROR_INVALID_REF_ID;
			return false;
		}


		return true;
	}


	/**
	 * getGMTTimeStamp:
	 *
	 * this function creates a timestamp formatted as per requirement in the
	 * SecureXML documentation
	 *
	 * @return string The formatted timestamp
	 */
	public function getGMTTimeStamp()
	{
		/* Format: YYYYDDMMHHNNSSKKK000sOOO
			YYYY is a 4-digit year
			DD is a 2-digit zero-padded day of month
			MM is a 2-digit zero-padded month of year (January = 01)
			HH is a 2-digit zero-padded hour of day in 24-hour clock format (midnight =0)
			NN is a 2-digit zero-padded minute of hour
			SS is a 2-digit zero-padded second of minute
			KKK is a 3-digit zero-padded millisecond of second
			000 is a Static 0 characters, as SecurePay does not store nanoseconds
			sOOO is a Time zone offset, where s is + or -, and OOO = minutes, from GMT.
		*/
		$tz_minutes = date('Z') / 60;

		if ($tz_minutes >= 0)
		{
			$tz_minutes = '+' . strval($tz_minutes);
		}

		$stamp = date('YdmGis000000') . $tz_minutes;

		return $stamp;
	}

	/**
	 * sendRequest:
	 *
	 * uses cURL to open a Secure Socket connection to the trexle,
	 * sends the transaction request and then returns the response
	 * data
	 *
	 * @param $postURL The URL of the remote trexle to which the request is sent
	 * @param $fields
	 */
	private function sendRequestPost($postURL, $fields)
	{

		$ch = curl_init( $postURL );

		if ($this->getDebug())
		{
			Trexle::log( $this->getTxnReference().' End point URL: ' . $postURL  );
			//Trexle::log( $this->getTxnReference().' Fields: ' . print_r( $fields, true ) );
		}

		curl_setopt($ch, CURLOPT_USERPWD, $this->privateKey . ":" . $this->publishableKey );
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));

		$response = curl_exec($ch);

		if(!curl_errno($ch)){
			$info = curl_getinfo($ch);
		}

		curl_close($ch);

		return json_decode($response, true);
	}

	private function sendRequestPut($postURL, $fields)
	{

		$ch = curl_init( $postURL );

		if ($this->getDebug())
		{
			Trexle::log( $this->getTxnReference().' End point URL: ' . $postURL  );
			//Trexle::log( $this->getTxnReference().' Fields: ' . print_r( $fields, true ) );
		}

		curl_setopt($ch, CURLOPT_USERPWD, $this->privateKey . ":" . $this->publishableKey );
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_PUT, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));

		$response = curl_exec($ch);

		if(!curl_errno($ch)){
			$info = curl_getinfo($ch);
		}

		curl_close($ch);

		return json_decode($response, true);
	}

}
