<?php

ini_set("display_errors", TRUE);
error_reporting(E_ALL);

$companyLogin = 'company_login';
$publicKey = 'public_key';
$secretKey = 'secret_key';
$apiUrl = 'https://user-api.simplybook.me';

$dir = dirname(__FILE__) . '/';
$dbFile = $dir . 'database.sqlite';

use Junior\Client;
include_once $dir . 'json-rpc/src/autoload.php';


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

logData($data);

try {

	if ( $data ) {
		/**
		 * Using Simplybook API methods require an authentication.
		 * To authorize in Simplybook API you need to get an access key — access-token.
		 * In order to get this access-token you should call the JSON-RPC method getToken on https://user-api.simplybook.me/login
		 * service passing your personal API-key. You can copy your API-key at admin interface: go to the 'Custom Features'
		 * link and select API custom feature 'Settings'.
		 */
		$loginClient = new Client( $apiUrl . '/login' );
		$token = $loginClient->getToken( $companyLogin, $publicKey );

		/**
		 * You have just received auth token. Now you need to create JSON RPC Client,
		 * set http headers and then use this client to get data from Simplybook server.
		 * To get booking details use getBookingDetails() function.
		 */
		$client = new Client( $apiUrl . '/');
		$client->setHeaderParam('X-Company-Login', $companyLogin);
		$client->setHeaderParam('X-Token', $token);


		//For this function signature is required. (md5($bookingId . $bookingHash . $secretKey))
		$sign = md5($data['booking_id'] . $data['booking_hash']. $secretKey);
		$bookingInfo = $client->getBookingDetails($data['booking_id'], $sign);

		logData($bookingInfo);

		//Init database
		$db = new SQLite3($dbFile);

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

		$db->query($tableCreateSql);

		//insert booking data
		$insertSql = "
			INSERT INTO bookings (
			  booking_id, booking_hash, notification_type, booking_code, client_id, client_name, start_date_time, end_date_time 
			) VALUES ( 
				'{$data['booking_id']}' ,
				'{$data['booking_hash']}' ,
				'{$data['notification_type']}' ,
				'{$bookingInfo['code']}' ,
				'{$bookingInfo['client_id']}' ,
				'{$bookingInfo['client_name']}' ,
				'{$bookingInfo['start_date_time']}' ,
				'{$bookingInfo['end_date_time']}'
			);
		";

		$insert = $db->prepare($insertSql);

		if (!$insert) {
			throw new Exception('sql error');
		}

		$insert->execute();

		echo 'OK';
	}

} catch (Exception $e){
	echo "Error : " . $e->getMessage();
} catch (\Junior\Clientside\Exception $e){
	echo "API Error : " . $e->getMessage();
}



function logData($var, $logfile = null){
	$dir = dirname(__FILE__) . '/';
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

	$fh = fopen($dir . $logfile . '.txt', 'a');
	fwrite($fh, $logContent);
	fclose($fh);
}
