<?php
$runScript = true;
if (!$runScript)
	die("The script is not allowed to run");

$_SERVER['DOCUMENT_ROOT'] = dirname(__FILE__);	
ini_set('max_execution_time', 7200);

$fromDate = null;
$toDate = null;
$all = null;
if (!empty($argv)) {
	foreach ($argv as $key => $value) {
		if ($key == 0)
			continue;
		$value = explode("=", $value);

		if (in_array($value[0], ['-filename', '--filename'])) {
			$filename = $value[1];
		}

		if (in_array($value[0], ['-all', '--all'])) {
			$all = 'all';
		}

		if (in_array($value[0], ['-from', '--from'])) {
			$fromDate = $value[1];
		}

		if (in_array($value[0], ['-to', '--to'])) {
			$toDate = $value[1];
		}
	}
}
if (isset($_GET['all']))
	$all = 'all';

if (isset($_GET['from'])) {
	$fromDate = $_GET['from'];
}

if (isset($_GET['to'])) {
	$toDate = $_GET['to'];
}

require_once($_SERVER['DOCUMENT_ROOT'] . '/api/ActiveTrail.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/libs/vendor/autoload.php');
$token = '0XEE1020FBC5B72A26140B8B5EB0E2A22BD6661DC096BCF1BB20D339822E4EB8AAA0EAFF531531B61A794950935B49ECD5';

$activeTrail = new ActiveTrail();
$activeTrail->setApiToken($token);
$params = [
	'Limit' => 100,
];
if (empty($all)) {
	if (is_null($fromDate) && is_null($toDate)) {
		$days = "-1 days";
		$dates = [
			'FromDate' => date('Y-m-d', strtotime($days)),
			'ToDate' => date('Y-m-d'),
		];
	} else {
		if (!is_null($fromDate)) {
			if (!isset($dates)) {
				$dates = [];
			}
			$dates['FromDate'] = $fromDate;
		}
		if (!is_null($toDate)) {
			if (!isset($dates)) {
				$dates = [];
			}
			$dates['ToDate'] = $toDate;
		}
	}
}

$allCampaigns = [];
$page = 0;
do {
	$page++;
	$params['Page'] = $page;
	$campaigns = $activeTrail->getCampaignReports($params);
	if ($campaigns != false) {
		$allCampaigns = array_merge($allCampaigns, $campaigns);
	}
} while ($campaigns);

// for test campaign_id 13887198
// $allCampaigns = array_filter($allCampaigns, function($innerArray) {
// 	return ($innerArray['campaign_id'] == '13887198');
// });
// for test

$allReportSents = [];
if (!empty($allCampaigns)) {
	foreach ($allCampaigns as $item) {
		$params = [
			'Limit' => 100,
		];
		if (!empty($dates)) {
			$params = array_merge($params, $dates);
		}
		$page = 0;
		do {
			$page++;
			$params['Page'] = $page;
			$reports = $activeTrail->getReportSent($item['campaign_id'], $params);
			if ($reports != false) {
				if (!empty($allReportSents[$item['campaign_id']])) {
					$allReportSents[$item['campaign_id']] = array_merge($allReportSents[$item['campaign_id']], $reports);
				} else {
					$allReportSents[$item['campaign_id']] = $reports;
				}
			}
		} while ($reports);
	}
}

$allReportOpens = [];
if (!empty($allCampaigns)) {
	foreach ($allCampaigns as $item) {
		$params = [
			'Limit' => 100
		];
		if (!empty($dates)) {
			$params = array_merge($params, $dates);
		}
		$page = 0;
		do {
			$page++;
			$params['Page'] = $page;
			$opens = $activeTrail->getReportOpensEmail($item['campaign_id'], $params);
			if ($opens != false) {
				if (!empty($allReportOpens[$item['campaign_id']])) {
					$allReportOpens[$item['campaign_id']] = array_merge($allReportOpens[$item['campaign_id']], $opens);
				} else {
					$allReportOpens[$item['campaign_id']] = $opens;
				}
			}
		} while ($opens);
	}
}

$allReportClicks = [];
if (!empty($allCampaigns)) {
	foreach ($allCampaigns as $item) {
		$params = [
			'Limit' => 100
		];
		if (!empty($dates)) {
			$params = array_merge($params, $dates);
		}
		$page = 0;
		do {
			$page++;
			$params['Page'] = $page;
			$urls = $activeTrail->getReportClicks($item['campaign_id'], $params);
			if ($urls != false) {
				if (!empty($allReportClicks[$item['campaign_id']])) {
					$allReportClicks[$item['campaign_id']] = array_merge($allReportClicks[$item['campaign_id']], $urls);
				} else {
					$allReportClicks[$item['campaign_id']] = $urls;
				}
			}
		} while ($urls);
	}
}

$allReportClicksContact = [];
if (!empty($allReportClicks)) {
	foreach ($allReportClicks as $campaignID => $campaign) {
		$params = [
			'Limit' => 100
		];
		if (!empty($dates)) {
			$params = array_merge($params, $dates);
		}
		foreach ($campaign as $url) {
			$page = 0;
			do {
				$page++;
				$params['Page'] = $page;
				$clicked = $activeTrail->getReportClick($campaignID, $url['link_id'], $params);
				if ($clicked != false) {
					foreach ($clicked as $contact) {
						$sign = $contact['contact_id'];
						if (empty($allReportClicksContact[$campaignID][$sign])) {
							$allReportClicksContact[$campaignID][$sign] = [
								'email' => $contact['email'],
								'first_name' => $contact['first_name'],
								'last_name' => $contact['last_name'],
								'total_links' => 1,
								'total_clicks' => 1,
								'clicked_links' => [
									$contact['link_id']
								]
							];
						} else {
							$allReportClicksContact[$campaignID][$sign]['total_links']++;
							if (!in_array($contact['link_id'], $allReportClicksContact[$campaignID][$sign]['clicked_links'])) {
								$allReportClicksContact[$campaignID][$sign]['clicked_links'][] = $contact['link_id'];
								$allReportClicksContact[$campaignID][$sign]['total_clicks']++;
							}
						}
					}
				}
			} while ($clicked);
		}
	}
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$spreadsheet = new Spreadsheet();

$spreadsheet->getProperties()
	->setTitle('ActiveTrail')
	->setSubject('ActiveTrail')
	->setDescription('Who sent to, who is opened the campaign and who is clicked')
	->setCreator('ActiveTrail')
	->setLastModifiedBy('ActiveTrail');

$sheet = 0;
$spreadsheet->setActiveSheetIndex($sheet)
	->setTitle('Sent') # ReportSent
	->setCellValue('A1', 'Campaign ID')
	->setCellValue('B1', 'First Name')
	->setCellValue('C1', 'Last Name')
	->setCellValue('D1', 'Email');
foreach(range('A','D') as $columnID) {
	$spreadsheet->getActiveSheet()->getColumnDimension($columnID)->setAutoSize(true);
}
$dc = 2;
if (!empty($allReportSents)) {
	foreach ($allReportSents as $campaignID => $campaign) {
		foreach ($campaign as $item) {
			$spreadsheet->getActiveSheet()->setCellValue('A' . $dc, $campaignID);
			$spreadsheet->getActiveSheet()->setCellValue('B' . $dc, $item['first_name']);
			if (!empty($item['last_name'])) {
				$spreadsheet->getActiveSheet()->setCellValue('C' . $dc, $item['last_name']);
			}
			$spreadsheet->getActiveSheet()->setCellValue('D' . $dc, $item['email']);
			$dc++;
		}
	}
}

$sheet++;
$spreadsheet->createSheet();
$spreadsheet->setActiveSheetIndex($sheet)
	->setTitle('Opened') # ReportOpen
	->setCellValue('A1', 'Campaign ID')
	->setCellValue('B1', 'First Name')
	->setCellValue('C1', 'Last Name')
	->setCellValue('D1', 'Email')
	->setCellValue('E1', 'Open Date')
	->setCellValue('F1', 'Total Opens');
foreach(range('A','F') as $columnID) {
	$spreadsheet->getActiveSheet()->getColumnDimension($columnID)->setAutoSize(true);
}
if (!empty($allReportOpens)) {
	$dc = 2;
	foreach ($allReportOpens as $campaignID => $campaign) {
		foreach ($campaign as $item) {
			$spreadsheet->getActiveSheet()->setCellValue('A' . $dc, $campaignID);
			$spreadsheet->getActiveSheet()->setCellValue('B' . $dc, $item['first_name']);
			if (!empty($item['last_name'])) {
				$spreadsheet->getActiveSheet()->setCellValue('C' . $dc, $item['last_name']);
			}
			$spreadsheet->getActiveSheet()->setCellValue('D' . $dc, $item['email']);
			$spreadsheet->getActiveSheet()->setCellValue('E' . $dc, date("Y-m-d H:i:s",strtotime($item['open_date'])));
			$spreadsheet->getActiveSheet()->setCellValue('F' . $dc, $item['total_opens']);
			$dc++;
		}
	}
}

$sheet++;
$spreadsheet->createSheet();
$spreadsheet->setActiveSheetIndex($sheet)
	->setTitle('Clicked') # ReportTotalClicks
	->setCellValue('A1', 'Campaign ID')
	->setCellValue('B1', 'First Name')
	->setCellValue('C1', 'Last Name')
	->setCellValue('D1', 'Email')
	->setCellValue('E1', 'Total Clicks')
	->setCellValue('F1', 'Total Links');

foreach(range('A','F') as $columnID) {
	$spreadsheet->getActiveSheet()->getColumnDimension($columnID)->setAutoSize(true);
}
if (!empty($allReportClicksContact)) {
	$dc = 2;
	foreach ($allReportClicksContact as $campaignID => $campaign) {
		foreach ($campaign as $item) {
			$spreadsheet->getActiveSheet()->setCellValue('A' . $dc, $campaignID);
			$spreadsheet->getActiveSheet()->setCellValue('B' . $dc, $item['first_name']);
			if (!empty($item['last_name'])) {
				$spreadsheet->getActiveSheet()->setCellValue('C' . $dc, $item['last_name']);
			}
			$spreadsheet->getActiveSheet()->setCellValue('D' . $dc, $item['email']);
			$spreadsheet->getActiveSheet()->setCellValue('E' . $dc, $item['total_clicks']);
			$spreadsheet->getActiveSheet()->setCellValue('F' . $dc, $item['total_links']);
			$dc++;
		}
	}
}

try {
	$writer = new Xlsx($spreadsheet);
	if (!empty($all)) {
		$file = $_SERVER['DOCUMENT_ROOT'] . "/ActiveTrail_all.xlsx";
	} else {
		$dateSign = '';
		if (isset($dates['FromDate'])) {
			$dateSign .= ($dateSign == '' ? '' : '_') . $dates['FromDate'];
		}
		if (isset($dates['ToDate'])) {
			$dateSign .= ($dateSign == '' ? '' : '_') . $dates['ToDate'];
		}
		$file = $_SERVER['DOCUMENT_ROOT'] . "/ActiveTrail_" . $dateSign . ".xlsx";
	}
//	$writer = IOFactory::createWriter($spreadsheet, "Xlsx");
	$writer->save($file);
} catch (PhpOffice\PhpSpreadsheet\Writer\Exception $e) {
	echo $e->getMessage();
}
