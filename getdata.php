<?php

if (php_sapi_name() === 'cli') {
	echo '==========' . PHP_EOL . '[' . date('Y-m-d H:i:s') . '] Start getting data' . PHP_EOL . '==========' . PHP_EOL;
}	

$_SERVER['DOCUMENT_ROOT'] = dirname(__FILE__);	
if (!is_dir($_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . 'storage')) {
	if (!mkdir($_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . 'storage')) {
		die('Cannot create storage');
	}
}
$storageCampaigns = $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . 'storage/campaigns.json';
$storageDate = $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . 'storage/data.json';
ini_set('max_execution_time', 12 * 3600);
ini_set('memory_limit', '4096M');

require_once($_SERVER['DOCUMENT_ROOT'] . '/api/ActiveTrail.php');
$token = '0XEE1020FBC5B72A26140B8B5EB0E2A22BD6661DC096BCF1BB20D339822E4EB8AAA0EAFF531531B61A794950935B49ECD5';

$activeTrail = new ActiveTrail();
$activeTrail->setApiToken($token);

$dates = [
	'FromDate' => date('Y-m-d', strtotime('-1 months')),
	'ToDate' => date('Y-m-d', strtotime('+1 days'))
];

// Current campaigns
$currentCampaigns = [];
if (file_exists($storageCampaigns)) {
	$currentCampaigns = json_decode(file_get_contents($storageCampaigns), true);
}

// Current data
$currentData = [];
if (file_exists($storageDate)) {
	$currentData = json_decode(file_get_contents($storageDate), true);
}

// Get contacts
$params = array_merge([
	'Limit' => 100,
	'CustomerStates' => '-1'
]);
$allContacts = [];
$page = 0;
do {
	$page++;
	$params['Page'] = $page;
	$contacts = $activeTrail->getContacts($params);
	if ($contacts != false) {
		foreach ($contacts as $contact) {
			if (isset($contact['email']) && $contact['email'] != '') {
				$allContacts[$contact['email']] = [
					'id' => $contact['id'],
					'client_id' => $contact['ext6']
				];
			}
		}
	}
} while ($contacts);

// Get actual campaigns
$params = array_merge([
	'Limit' => 100,
], $dates);
$allCampaigns = [];
$allData = [];
$page = 0;
do {
	$page++;
	$params['Page'] = $page;
	$campaigns = $activeTrail->getCampaignReports($params);
	if ($campaigns != false) {
		$allCampaigns = array_merge($allCampaigns, $campaigns);
	}
} while ($campaigns);

$existSentCampaigns = [];
foreach ($allCampaigns as $allCampaign) {
	foreach ($currentCampaigns as $currentCampaign) {
		if ($currentCampaign['campaign_id'] == $allCampaign['campaign_id'] && $currentCampaign['last_sent_date'] == $allCampaign['last_sent_date']) {
			$existSentCampaigns[] = $allCampaign['campaign_id'];
			break;
		}
	}
}

foreach ($allCampaigns as $allCampaign) {

	if (php_sapi_name() === 'cli') {
		echo '==========' . PHP_EOL . '[' . date('Y-m-d H:i:s') . '] Start Campaign ID ' . $allCampaign['campaign_id'] . PHP_EOL . '==========' . PHP_EOL;
	}	

	$allData[$allCampaign['campaign_id']] = [];	
	if (in_array($allCampaign['campaign_id'], $existSentCampaigns) && isset($currentData[$allCampaign['campaign_id']])) {
		$allData[$allCampaign['campaign_id']] = $currentData[$allCampaign['campaign_id']];
	} else {
		$params = array_merge([
			'Limit' => 100,
		], $dates);
		$page = 0;
		do {
			$page++;
			$params['Page'] = $page;
			$reports = $activeTrail->getReportSent($allCampaign['campaign_id'], $params);
			if ($reports != false) {
				foreach ($reports as $report) {
					if (isset($report['email']) && $report['email'] != '') {
						$client_id = '';
						if (isset($allContacts[$report['email']])) {
							$client_id = $allContacts[$report['email']]['client_id'];
							if (!$client_id && $allContacts[$report['email']]['id']) {
								$contact = $activeTrail->getContact($allContacts[$report['email']]['id']);
								if ($contact) {
									$client_id = $contact['ext6'];
								}
							}
						}
						$allData[$allCampaign['campaign_id']][$report['email']] = [
							'campaign_id' => $allCampaign['campaign_id'],
							'campaign_name' => $allCampaign['campaign_name'],
							'email' => $report['email'],
							'first_name' => isset($report['first_name']) ? $report['first_name'] : '',
							'last_name' => isset($report['last_name']) ? $report['last_name'] : '',
							'client_id' => $client_id,
							'sent_date' => date('Y-m-d H:i:s', strtotime($allCampaign['last_sent_date'])),
							'open_date' => null,
							'click_date' => null
						];
					}
				}
			}
		} while ($reports);	
	}

	// Get campaign clicks
	$allReportClicks = [];
	$params = array_merge([
		'Limit' => 100,
	], $dates);
	$page = 0;	
	do {
		$page++;
		$params['Page'] = $page;
		$urls = $activeTrail->getReportClicks($allCampaign['campaign_id'], $params);
		if ($urls != false) {
			$allReportClicks = array_merge($allReportClicks, $urls);
		}
	} while ($urls);	
	foreach ($allReportClicks as $url) {
		$params = array_merge([
			'Limit' => 100,
		], $dates);
		$page = 0;
		do {
			$page++;
			$params['Page'] = $page;
			$clicked = $activeTrail->getReportClick($allCampaign['campaign_id'], $url['link_id'], $params);
			if ($clicked != false) {
				foreach ($clicked as $contact) {
					if (isset($contact['email']) && $contact['email'] != '') {
						if (isset($allData[$allCampaign['campaign_id']][$contact['email']])) {
							if (!isset($allData[$allCampaign['campaign_id']][$contact['email']]['click_date']) || is_null($allData[$allCampaign['campaign_id']][$contact['email']]['click_date'])) {
								$allData[$allCampaign['campaign_id']][$contact['email']]['click_date'] = date('Y-m-d H:i:s', strtotime($contact['click_date']));
							}
						}
					}										
				}
			}
		} while ($clicked);
	}
	
	// Get campaign opens
	$params = array_merge([
		'Limit' => 100,
	], $dates);
	$page = 0;
	do {
		$page++;
		$params['Page'] = $page;
		$opens = $activeTrail->getReportOpensEmail($allCampaign['campaign_id'], $params);
		if ($opens != false) {
			foreach ($opens as $open) {
				if (isset($open['email_address']) && $open['email_address'] != '') {
					if (isset($allData[$allCampaign['campaign_id']][$open['email_address']])) {
						if (!isset($allData[$allCampaign['campaign_id']][$open['email_address']]['open_date']) || is_null($allData[$allCampaign['campaign_id']][$open['email_address']]['open_date'])) {
							$allData[$allCampaign['campaign_id']][$open['email_address']]['open_date'] = date('Y-m-d H:i:s', strtotime($open['open_date']));
						}
					}
				}
			}
		}
	} while ($opens);

	if (php_sapi_name() === 'cli') {
		echo '==========' . PHP_EOL . '[' . date('Y-m-d H:i:s') . '] End Campaign ID ' . $allCampaign['campaign_id'] . PHP_EOL . '==========' . PHP_EOL;
	}	
}

file_put_contents($storageCampaigns, json_encode($allCampaigns));
file_put_contents($storageDate, json_encode($allData));
