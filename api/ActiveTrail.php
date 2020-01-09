<?php

class ActiveTrail {

    private $apiToken = ""; # 0XEE1020FBC5B72A26140B8B5EB0E2A22BD6661DC096BCF1BB20D339822E4EB8AAA0EAFF531531B61A794950935B49ECD5
    private $endPoint = "http://webapi.mymarketing.co.il/api/"; # http://webapi.mymarketing.co.il/api/docs/

    public function __construct($apiToken = '') {
        $this->apiToken = $apiToken;
    }

	public function setApiToken($apiToken = '') {
		$this->apiToken = $apiToken;
	}

    public function query($function, $params = []) {
		$return = false;
        if (!is_array($params)) {
            $params = [];
        }
        $params = http_build_query($params);
        $url = !empty($params) ? $this->endPoint . $function . "?" . $params : $this->endPoint . $function;
		$headers = [
			'Authorization: ' . $this->apiToken,
			'Accept: application/json',
			'Content-Type: application/json; charset=utf-8'
		];
		$curlOptions = [
			CURLOPT_URL => $url,
//			CURLOPT_HEADER => 1,
			CURLOPT_HTTPHEADER => $headers,
            CURLOPT_VERBOSE => 1,
            CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_FOLLOWLOCATION => 1,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSL_VERIFYHOST => false,
//            CURLOPT_REFERER => "http://" . $_SERVER['SERVER_NAME']
		];
		$ch = curl_init();
        curl_setopt_array($ch, $curlOptions);
        $response = curl_exec($ch);
		if (curl_getinfo($ch, CURLINFO_HTTP_CODE) == 200) {
			if (!$return = json_decode($response, true)) {
				$return = false;
			}
		}
        curl_close($ch);

		return $return;
    }

    public function getReportOpens($id, $params = []) {
        $return = false;
		if (!is_array($params)) {
			$params = [];
		}
		$result = $this->query("campaignreports/$id/opens", $params);
		if ($result !== false) {
			$return = $result;
		}
        return $return;
    }

	public function getReportOpensEmail($id, $params = []) {
		$return = false;
		if (!is_array($params)) {
			$params = [];
		}
		$result = $this->query("campaignreports/$id/emailactivity", $params);
		if ($result !== false && !empty($result['emails'])) {
			$return = $result['emails'];
		}
		return $return;
	}

	public function getReportSent($id, $params = []) {
		$return = false;
		if (!is_array($params)) {
			$params = [];
		}
		$result = $this->query("campaignreports/$id/sent", $params);
		if ($result !== false && !empty($result['campaign_sent'])) {
			$return = $result['campaign_sent'];
		}
		return $return;
	}

	public function getReportClicks($id, $params = []) {
		$return = false;
		if (!is_array($params)) {
			$params = [];
		}
		$result = $this->query("campaignreports/$id/clickdetails", $params);
		if ($result !== false && !empty($result['urls_clicked'])) {
			$return = $result['urls_clicked'];
		}
		return $return;
	}

	public function getReportClick($id, $linkId, $params = []) {
		$return = false;
		if (!is_array($params)) {
			$params = [];
		}
		$result = $this->query("campaignreports/$id/clickdetails/$linkId", $params);
		if ($result !== false && !empty($result['urls_clicked'])) {
			$return = $result['urls_clicked'];
		}
		return $return;
	}

	public function getCampaigns($params = []) {
		$return = false;
		if (!is_array($params)) {
			$params = [];
		}
		$result = $this->query("campaigns", $params);
		if ($result !== false) {
			$return = $result;
		}
		return $return;
	}

	public function getCampaignReports($params = []) {
		$return = false;
		if (!is_array($params)) {
			$params = [];
		}
		$result = $this->query("campaignreports", $params);
		if ($result !== false) {
			$return = $result;
		}
		return $return;
	}

	public function getContactActivity($id, $params = []) {
		$return = false;
		if (!is_array($params)) {
			$params = [];
		}
		$result = $this->query("contacts/$id/activity", $params);
		if ($result !== false) {
			$return = $result;
		}
		return $return;
	}

	public function getContacts($params = []) {
		$return = false;
		if (!is_array($params)) {
			$params = [];
		}
		$result = $this->query("contacts", $params);
		if ($result !== false) {
			$return = $result;
		}
		return $return;
	}

}

?>