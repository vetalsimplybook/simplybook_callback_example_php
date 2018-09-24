<?php

ini_set("display_errors", TRUE);
error_reporting(E_ALL);

$companyLogin = 'company_login';
$publicKey = 'public_key';
$secretKey = 'secret_key';

use JsonRPC\Client;
include_once dirname(__FILE__) . '/' . 'vendor/autoload.php';


class SimplybookCallback{

	private $_companyLogin;
	private $_publicKey;
	private $_secretKey;

	private $_apiUrl = 'https://user-api.simplybook.me';
	private $_dir;
	private $_dbFile;
	private $_api;
	private $_db;
	private $_token;

	public function __construct($companyLogin, $publicKey, $secretKey) {
		$this->_dir = dirname(__FILE__) . '/';
		$this->_dbFile = $this->_dir . 'database.sqlite';

		$this->_companyLogin = $companyLogin;
		$this->_publicKey = $publicKey;
		$this->_secretKey = $secretKey;
	}

	/**
	 * Converting the received data to PHP Array format
	 *
	 * @return array|null
	 */
	public function getNotificationData(){
		//For example: {"booking_id":"2262","booking_hash":"514ccafaa45aa779ff50e4642c37ba5d","company":"eventdatetime","notification_type":"change"}
		$phpInput = file_get_contents('php://input');
		$data = null;

		if($phpInput){
			/**
			 * Convert JSON to PHP Array
			 *
			 * array (
			 *      'booking_id' => '2262',
			 *      'booking_hash' => '514ccafaa45aa779ff50e4642c37ba5d',
			 *      'company' => 'company_login',
			 *      'notification_type' => 'change',
			 * )
			 */
			$data = json_decode($phpInput, true);
		}
		return $data;
	}


	/**
	 * API initialization, token receiving
	 *
	 * @return Client
	 */
	public function initApi(){
		/**
		 * Using Simplybook API methods require an authentication.
		 * To authorize in Simplybook API you need to get an access key — access-token.
		 * In order to get this access-token you should call the JSON-RPC method getToken on https://user-api.simplybook.me/login
		 * service passing your personal API-key. You can copy your API-key at admin interface: go to the 'Custom Features'
		 * link and select API custom feature 'Settings'.
		 */
		/** @var \JsonRPC\Client $loginClient */
		$loginClient = new Client( $this->_apiUrl . '/login' );
		$this->_token = $loginClient->execute("getToken", array($this->_companyLogin, $this->_publicKey));

		/**
		 * You have just received auth token. Now you need to create JSON RPC Client,
		 * set http headers and then use this client to get data from Simplybook server.
		 * To get booking details use getBookingDetails() function.
		 */
		$this->_api = new Client( $this->_apiUrl . '/');

		return $this->api();
	}

	/**
	 * Creating an Authorization Header to call API functions
	 *
	 * @return array
	 */

	protected function getHeaderParams(){
		return array(
			"X-Company-Login: {$this->_companyLogin}",
			"X-Token: {$this->_token}"
		);
	}

	/**
	 * Get API instance
	 *
	 * @return Client
	 */
	public function api(){
		if(!$this->_api){
			$this->initApi();
		}
		return $this->_api;
	}

	/**
	 * Getting detailed booking information using Company public API
	 *
	 * @param $bookingId
	 * @param $bookingHash
	 *
	 * @return mixed
	 */
	public function getBookingDetails($bookingId, $bookingHash){
		//For this function signature is required. (md5($bookingId . $bookingHash . $secretKey))
		$sign = md5($bookingId . $bookingHash. $this->_secretKey);
		return $this->api()->execute("getBookingDetails", array($bookingId, $sign), array(), null, $this->getHeaderParams());
	}

	/**
	 * SQLite database initialization and creating a table for bookings information
	 */
	public function initDatabase(){
		//Init database
		$this->_db = new SQLite3($this->_dbFile);

		//Create bookings table if not exists
		$tableCreateSql = "
			CREATE TABLE IF NOT EXISTS bookings (
			 id integer PRIMARY KEY,
			 booking_id integer NOT NULL,
			 booking_hash text NOT NULL,
			 notification_type text NOT NULL,
			 booking_code text NOT NULL,
			 client_id integer,
			 client_name text,
			 start_date_time datetime NOT NULL,
			 end_date_time datetime NOT NULL
			);
		";

		$this->_db->query($tableCreateSql);
	}

	/**
	 * Get database instance
	 *
	 * @return SQLite3
	 */
	public function db(){
		if(!$this->_db){
			$this->initDatabase();
		}
		return $this->_db;
	}

	/**
	 * Inserting booking information into database table
	 *
	 * @param $bookingInfo
	 *
	 * @return SQLite3Result
	 * @throws Exception
	 */
	public function saveBookingInfo($bookingInfo){
		//insert booking data
		$insertSql = "
			INSERT INTO bookings (
			  booking_id, booking_hash, notification_type, booking_code, client_id, client_name, start_date_time, end_date_time 
			) VALUES ( 
				'{$bookingInfo['booking_id']}' ,
				'{$bookingInfo['booking_hash']}' ,
				'{$bookingInfo['notification_type']}' ,
				'{$bookingInfo['code']}' ,
				'{$bookingInfo['client_id']}' ,
				'{$bookingInfo['client_name']}' ,
				'{$bookingInfo['start_date_time']}' ,
				'{$bookingInfo['end_date_time']}'
			);
		";

		$insert = $this->db()->prepare($insertSql);

		if (!$insert) {
			throw new Exception('sql error');
		}
		return $insert->execute();
	}


	/**
	 * Log variable to local file
	 *
	 * @param $var
	 * @param null $logfile
	 */
	public function logData($var, $logfile = null){
		$bugtrace = debug_backtrace();

		if(!$logfile){
			$logfile = 'log';
		}
		//dump var to string
		ob_start();
		var_dump( $var );
		$data = ob_get_clean();

		$logContent = "\n\n" .
          "--------------------------------\n" .
          date("d.m.Y H:i:s") . "\n" .
          "{$bugtrace[0]['file']} : {$bugtrace[0]['line']}\n\n" .
          $data . "\n" .
          "--------------------------------\n";

		$fh = fopen($this->_dir . $logfile . '.txt', 'a');
		fwrite($fh, $logContent);
		fclose($fh);
	}

}

//init
$callback = new SimplybookCallback($companyLogin, $publicKey, $secretKey);
//receive callback data
$notificationData = $callback->getNotificationData();
//log data to local log file  (log.txt)
$callback->logData($notificationData);

try {
	if ( $notificationData ) {
		//get information about current booking
		$bookingInfo = $callback->getBookingDetails($notificationData['booking_id'], $notificationData['booking_hash']);
		//log booking information to local log file  (log.txt)
		$callback->logData($bookingInfo);
		//save booking information to database
		$callback->saveBookingInfo(array_merge($bookingInfo, $notificationData));

		echo 'OK';
	}
} catch (Exception $e){
	echo "Error : " . $e->getMessage();
}
