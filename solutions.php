<?php
die("Read the code then run it; be carefully: this script not include validations and will try to write a file named 'geoip.xls' on current folder.");

$startTime = microtime(true);

// =============================================
// ====  1. Retrieve ip info from freegeoip ====
// =============================================

/**
 * @param $ip
 * @return mixed|string
 */
function getGeoInfo($ip) {

	// https://freegeoip.app/{format}/{IP_or_hostname}

	$curl = curl_init();

	curl_setopt_array($curl,
		array(
			CURLOPT_URL => "https://freegeoip.app/json/" . $ip,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => "",
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => "GET",
			CURLOPT_HTTPHEADER => array("accept: application/json", "content-type: application/json"),));

	$response = curl_exec($curl);
	$err = curl_error($curl);

	curl_close($curl);

	if ($err) {
		return $err;
	} else {
		return json_decode($response);
	}
}

$fields = array(
	'A'=>array('IP','ip'),
	'B'=>array('Country','country_name'),
	'C'=>array('Region','region_name'),
	'D'=>array('City','city'),
);

require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

$row=1;
foreach ($fields as $column => $field) {
	$sheet->setCellValue($column.$row, $field[0]);
}

$fh = fopen('geoip.txt','r');

while ($line = fgets($fh)) {

	$ip=trim($line);

	if($ip != '') {

		$data = getGeoInfo($ip);

		$row++;

		$sheet->setCellValue('A' . $row, $ip);

		foreach ($fields as $column => $field) {
			$sheet->setCellValue($column . $row, $data->{$field[1]});
		}
	}
}

fclose($fh);

$writer = new Xls($spreadsheet);
$writer->save('geoip.xls');

echo "xls file probably created (with PhpSpreadsheet) <br />";

// =============================================
// ====  2. Play with parentheses validation ===
// =============================================


$string = 'a(b(x)d)efghijkl'; // ok
//$string = '(123).)(qw(e)'; // ko

// ==== Basic

/**
 * @param $string
 * @return bool
 */
function validateOpenedParentheses($string) {

	$stack = array();

	for ($i = 0; $i < strlen($string); $i++) {

		$char = $string[$i];

		if( $char === '(') {

			array_push($stack, $char);

		} else if( $char === ')') {

			array_pop($stack);
		}

	}

	return empty($stack);

}

var_dump(validateOpenedParentheses($string));

// ==== With closed unopened

$string = 'a(b(x)d)efg)hijkl)'; // ko

/**
 * @param $string
 * @return bool
 */
function validateParentheses($string) {

	if((substr_count($string,'(') != substr_count($string,')') ) ) return false;

	$stack = array();

	for ($i = 0; $i < strlen($string); $i++) {

		$char = $string[$i];

		if( $char === '(') {

			array_push($stack, $char);

		} else if( $char === ')') {

			array_pop($stack);
		}

	}

	return empty($stack);

}

var_dump(validateParentheses($string));


// =============================================
// ====  3. Play with duplicated transactions ==
// =============================================

$transactions = '[
{
id: 1,
sourceAccount: "A",
targetAccount: "B",
amount: 100,
time: "2018-03-02T10:33:00.000Z"
},
{
id: 2,
sourceAccount: "A",
targetAccount: "B",
amount: 100,
time: "2018-03-02T10:33:50.000Z"
},
{
id: 3,
sourceAccount: "A",
targetAccount: "B",
amount: 100,
time: "2018-03-02T10:34:30.000Z"
},
{
id: 4,
sourceAccount: "A",
targetAccount: "B",
amount: 100,
time: "2018-03-02T10:36:00.000Z"
},
{
id: 5,
sourceAccount: "A",
targetAccount: "C",
amount: 250,
time: "2018-03-02T10:33:00.000Z"
},
{
id: 6,
sourceAccount: "A",
targetAccount: "C",
amount: 250,
time: "2018-03-02T10:33:05.000Z"
}
]
';

// have to be already ordered by transactions pairs and time from api, as in example
// if not already ordered or huge dataset, change the approach (ie play with sequential file reading to temp table)

// not a json, try to play with json
$transactions = preg_replace("!\n([a-zA-Z]+):!",'"$1":',$transactions);
$transactions = preg_replace("! |\n!",'',$transactions);

$transactions = json_decode($transactions);
var_dump(json_last_error());
///var_dump($transactions);

$oldHash = '';
$newTime = 0;
$time = 0;
$key = 0;
foreach($transactions as $transaction)  {

	$hash = md5($transaction->sourceAccount.$transaction->targetAccount.$transaction->amount);

	$time = strtotime($transaction->time);

	if($hash!=$oldHash) {

		$oldHash = $hash;

		unset($transactions[$key]);

	} else {

		if( ( $time - $newTime ) >= 60)  unset($transactions[$key]);

	}

	if($time != $newTime) $newTime = $time;

	$key++;

}

var_dump($transactions); // contains only duplicates
$transactions = json_encode($transactions);



// =============================================
// ====  4. Fix 'frozen' application ====
// =============================================

// Difficult to say, depending on real application case
// Research on: network connection, client & server processes duration
// Keywords: logs, traceroute, top, slow queries, memory test, hardware issues, table lock, file lock, developer tools, break points, low resources, concurrential access etc
// Mostly this happening due slow db queries with sync requests without the immediate feedback to the user

echo "End in ".(microtime(true) - $startTime);