<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
$_SERVER['DOCUMENT_ROOT'] = dirname(__FILE__);	
if (!is_dir($_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . 'storage')) {
	if (!mkdir($_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . 'storage')) {
		die('Cannot create storage');
	}
}
$storageDate = $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . 'storage/data.json';
$storageDir = $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . 'storage/';
ini_set('max_execution_time', 12 * 3600);
ini_set('memory_limit', '4096M');

require_once($_SERVER['DOCUMENT_ROOT'] . '/libs/vendor/autoload.php');

if (php_sapi_name() === 'cli') {
	echo '==========' . PHP_EOL . '[' . date('Y-m-d H:i:s') . '] Start generate file' . PHP_EOL . '==========' . PHP_EOL;
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$spreadsheet = new Spreadsheet();

$spreadsheet->getProperties()
	->setTitle('ActiveTrail')
	->setSubject('ActiveTrail')
	->setDescription('ActiveTrail Export')
	->setCreator('iQDesk')
	->setLastModifiedBy('iQDesk');

$sheet = 0;
$spreadsheet->setActiveSheetIndex($sheet)
	->setTitle('Report ' . ($sheet + 1))
	->setCellValue('A1', 'Campaign ID')
	->setCellValue('B1', 'Campaign Name')
	->setCellValue('C1', 'Email')
	->setCellValue('D1', 'First Name')
	->setCellValue('E1', 'Last Name')
	->setCellValue('F1', 'Client ID')
	->setCellValue('G1', 'Sent Date')
	->setCellValue('H1', 'Open Date')
	->setCellValue('I1', 'Click Date');
foreach(range('A','I') as $columnID) {
	$spreadsheet->getActiveSheet()->getColumnDimension($columnID)->setAutoSize(true);
}

// Current data
$currentData = [];
if (file_exists($storageDate)) {
	$currentData = json_decode(file_get_contents($storageDate), true);
}
$campaignsList = [];
foreach ($currentData as $campaignId => $campaign) {
	$campaignsList[] = $campaignId;
	$file = $storageDir . 'campaign_' . $campaignId . '.json';
	file_put_contents($file, json_encode($campaign));
}
unset($currentData);

$dc = 2;

foreach ($campaignsList as $campaignId) {

	if (php_sapi_name() === 'cli') {
		echo '==========' . PHP_EOL . '[' . date('Y-m-d H:i:s') . '] Start process campaign ' . $campaignId . PHP_EOL . '==========' . PHP_EOL;
	}
	
	$campaignData = [];
	$file = $storageDir . 'campaign_' . $campaignId . '.json';
	if (file_exists($file)) {
		$campaignData = json_decode(file_get_contents($file), true);
		unlink($file);
	}
	foreach ($campaignData as $row) {
		$spreadsheet->getActiveSheet()->setCellValue('A' . $dc, $row['campaign_id']);
		$spreadsheet->getActiveSheet()->setCellValue('B' . $dc, $row['campaign_name']);
		$spreadsheet->getActiveSheet()->setCellValue('C' . $dc, $row['email']);
		$spreadsheet->getActiveSheet()->setCellValue('D' . $dc, $row['first_name']);
		$spreadsheet->getActiveSheet()->setCellValue('E' . $dc, $row['last_name']);
		$spreadsheet->getActiveSheet()->setCellValue('F' . $dc, $row['client_id']);
		$spreadsheet->getActiveSheet()->setCellValue('G' . $dc, $row['sent_date']);
		$spreadsheet->getActiveSheet()->setCellValue('H' . $dc, $row['open_date']);
		$spreadsheet->getActiveSheet()->setCellValue('I' . $dc, $row['click_date']);
		$dc++;
	}

	if (php_sapi_name() === 'cli') {
		echo '==========' . PHP_EOL . '[' . date('Y-m-d H:i:s') . '] End process campaign ' . $campaignId . PHP_EOL . '==========' . PHP_EOL;
	}

}

try {
	$writer = new Xlsx($spreadsheet);
	$file = $_SERVER['DOCUMENT_ROOT'] . '/activetrail_export.xlsx';
	$remoteFile = 'ronen/activetrail_export.xlsx';
	$writer->save($file);

	$connectionId = ftp_connect('192.116.49.57');
	if ($connectionId) {
		$loginResult = ftp_login($connectionId, 'ftp', 'ftp');
		if ($loginResult) {
			ftp_put($connectionId, $remoteFile, $file, FTP_BINARY);
		}
		ftp_close($connectionId);
	}

} catch (PhpOffice\PhpSpreadsheet\Writer\Exception $e) {
	echo $e->getMessage();
}

if (php_sapi_name() === 'cli') {
	echo '==========' . PHP_EOL . '[' . date('Y-m-d H:i:s') . '] End generate file' . PHP_EOL . '==========' . PHP_EOL;
}
