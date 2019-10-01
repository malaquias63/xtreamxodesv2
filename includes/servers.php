<?php

class eqjdus7
{
	static public function RunCommandServer($serverIDS, $cmd, $type = "array")
	{
		$output = array();

		if (!is_array($serverIDS)) {
			$serverIDS = array(intval($serverIDS));
		}

		if (empty($cmd)) {
			foreach ($serverIDS as $server_id ) {
				$output[$server_id] = "";
			}

			return $output;
		}

		foreach ($serverIDS as $server_id ) {
			if ($server_id == SERVER_ID) {
				exec($cmd, $return);
				$output[$server_id] = ($type == "array" ? $return : implode("\n", $return));
				continue;
			}

			if (!array_key_exists($server_id, ipTV_lib::$StreamingServers)) {
				continue;
			}

			$response = self::ServerSideRequest($server_id, ipTV_lib::$StreamingServers[$server_id]["api_url_ip"] . "&action=runCMD", array("command" => $cmd));

			if ($response) {
				$result = json_decode($response, true);
				$output[$server_id] = ($type == "array" ? $result : implode("\n", $result));
			}
			else {
				$output[$server_id] = false;
			}
		}

		return $output;
	}

	static public function ServerSideRequest($server_id, $URL, $PostData = array(), $force = false)
	{
		if ($force) {
			$status_ok = array(1, 4);

			if (!in_array(ipTV_lib::$StreamingServers[$server_id]["status"], $status_ok)) {
				return false;
			}
		}

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $URL);
		curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.1; WOW64; rv:9.0) Gecko/20100101 Firefox/9.0");
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 7);
		curl_setopt($ch, CURLOPT_TIMEOUT, 7);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

		if (!empty($PostData)) {
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($PostData));
		}

		$output = curl_exec($ch);
		@curl_close($ch);
		return $output;
	}
}


?>
