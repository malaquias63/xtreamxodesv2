<?php

class 308x4_k_
{
	/**
     * Input parameters
     *
     * @var		array
     */
	static 	public $request = array();
	/**
     * Database Instance
     *
     * @var		instance
     */
	static 	public $ipTV_db;
	/**
     * Settings
     *
     * @var		array
     */
	static 	public $settings = array();
	/**
     * Settings for Licence
     *
     * @var		array
     */
	static 	public $GetXtreamInfo = array();
	/**
     * Servers
     *
     * @var		array
     */
	static 	public $StreamingServers = array();
	static 	public $SegmentsSettings = array();
	static 	public $countries = array();

	static public function init()
	{
		if (!empty($_GET)) {
			self::cleanGlobals($_GET);
		}

		if (!empty($_POST)) {
			self::cleanGlobals($_POST);
		}

		if (!empty($_SESSION)) {
			self::cleanGlobals($_SESSION);
		}

		if (!empty($_COOKIE)) {
			self::cleanGlobals($_COOKIE);
		}

		$input = @self::parseIncomingRecursively($_GET, array());
		self::$request = @self::parseIncomingRecursively($_POST, $input);
		self::GetSettings();
		ini_set("date.timezone", self::$settings["default_timezone"]);
		self::GetXtreamInfo();
		self::$StreamingServers = self::GetServers();
		self::$SegmentsSettings = self::calculateSegNumbers();
		crontab_refresh();
	}

	static public function calculateSegNumbers()
	{
		$segments_settings = array();
		$segments_settings["seg_time"] = 10;
		$segments_settings["seg_list_size"] = 6;
		return $segments_settings;
	}

	static public function isValidMAC($mac)
	{
		return preg_match("/^([a-fA-F0-9]{2}:){5}[a-fA-F0-9]{2}$/", $mac) == 1;
	}

	static public function GetSettings()
	{
		self::$ipTV_db->query("SELECT * FROM `settings`");
		$rows = self::$ipTV_db->get_row();

		foreach ($rows as $key => $val ) {
			self::$settings[$key] = $val;
		}

		self::$settings["allow_countries"] = json_decode(self::$settings["allow_countries"], true);

		if (array_key_exists("bouquet_name", self::$settings)) {
			self::$settings["bouquet_name"] = str_replace(" ", "_", self::$settings["bouquet_name"]);
		}
	}

	static public function GetServers()
	{
		self::$ipTV_db->query("SELECT * FROM `streaming_servers`");
		$servers = array();

		foreach (self::$ipTV_db->get_rows() as $row ) {
			if (!empty($row["vpn_ip"]) && (inet_pton($row["vpn_ip"]) !== false)) {
				$url = $row["vpn_ip"];
			}
			else if (empty($row["domain_name"])) {
				$url = $row["server_ip"];
			}
			else {
				$url = str_replace(array("http://", "/"), "", $row["domain_name"]);
			}

			$row["api_url"] = "http://" . $url . ":" . $row["http_broadcast_port"] . "/api.php?password=" . ipTV_lib::$settings["live_streaming_pass"];
			$row["site_url"] = "http://" . $url . ":" . $row["http_broadcast_port"] . "/";
			$row["api_url_ip"] = "http://" . $row["server_ip"] . ":" . $row["http_broadcast_port"] . "/api.php?password=" . ipTV_lib::$settings["live_streaming_pass"];
			$row["site_url_ip"] = "http://" . $row["server_ip"] . ":" . $row["http_broadcast_port"] . "/";
			$row["ssh_password"] = self::mc_decrypt($row["ssh_password"], md5(self::$settings["unique_id"]));
			$servers[$row["id"]] = $row;
		}

		return $servers;
	}

	static public function GetFFmpegArguments($parse_StreamArguments = array(), $add_default = true)
	{
		global $_LANG;
		self::$ipTV_db->query("SELECT * FROM `streams_arguments`");
		$rows = array();

		if (0 < self::$ipTV_db->num_rows()) {
			foreach (self::$ipTV_db->get_rows() as $row ) {
				if (array_key_exists($row["id"], $parse_StreamArguments)) {
					if (count($parse_StreamArguments[$row["id"]]) == 2) {
						$value = $parse_StreamArguments[$row["id"]]["val"];
					}
					else {
						$value = $parse_StreamArguments[$row["id"]]["value"];
					}
				}
				else {
					$value = ($add_default ? $row["argument_default_value"] : "");
				}

				if ($row["argument_type"] == "radio") {
					if (is_null($value) || (0 < $value)) {
						$no = false;
						$yes = true;
					}
					else {
						$no = true;
						$yes = false;
					}

					if ($yes) {
						$mode = "<input type=\"radio\" name=\"arguments[" . $row["id"] . "]\" value=\"1\" checked/> " . $_LANG["yes"] . " <input type=\"radio\" name=\"arguments[" . $row["id"] . "]\" value=\"0\" /> . " . $_LANG["no"];
					}
					else {
						$mode = "<input type=\"radio\" name=\"arguments[" . $row["id"] . "]\" value=\"1\" /> " . $_LANG["yes"] . " <input type=\"radio\" name=\"arguments[" . $row["id"] . "]\" value=\"0\" checked/> . " . $_LANG["no"];
					}
				}
				else if ($row["argument_type"] == "text") {
					$mode = "<input type=\"text\" name=\"arguments[" . $row["id"] . "]\" value=\"" . $value . "\" />";
				}

				$row["mode"] = $mode;
				$rows[$row["id"]] = $row;
			}
		}

		return $rows;
	}

	static public function mc_encrypt($encrypt, $key)
	{
		$encrypt = serialize($encrypt);
		$iv = mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_CBC), MCRYPT_DEV_URANDOM);
		$key = pack("H*", $key);
		$mac = hash_hmac("sha256", $encrypt, substr(bin2hex($key), -32));
		$passcrypt = mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $key, $encrypt . $mac, MCRYPT_MODE_CBC, $iv);
		$encoded = base64_encode($passcrypt) . "|" . base64_encode($iv);
		return $encoded;
	}

	static public function mc_decrypt($decrypt, $key)
	{
		$decrypt = explode("|", $decrypt . "|");
		$decoded = base64_decode($decrypt[0]);
		$iv = base64_decode($decrypt[1]);

		if (strlen($iv) !== mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_CBC)) {
			return false;
		}

		$key = pack("H*", $key);
		$decrypted = trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $key, $decoded, MCRYPT_MODE_CBC, $iv));
		$mac = substr($decrypted, -64);
		$decrypted = substr($decrypted, 0, -64);
		$calcmac = hash_hmac("sha256", $decrypted, substr(bin2hex($key), -32));

		if ($calcmac !== $mac) {
			return false;
		}

		$decrypted = unserialize($decrypted);
		return $decrypted;
	}

	static public function formatOffset($offset)
	{
		$hours = $offset / 3600;
		$remainder = $offset % 3600;
		$sign = (0 < $hours ? "+" : "-");
		$hour = (int) abs($hours);
		$minutes = (int) abs($remainder / 60);
		if (($hour == 0) && ($minutes == 0)) {
			$sign = " ";
		}

		return $sign . str_pad($hour, 2, "0", STR_PAD_LEFT) . ":" . str_pad($minutes, 2, "0");
	}

	static public function GetTimeZones($current = NULL)
	{
		$utc = new DateTimeZone("UTC");
		$dt = new DateTime("now", $utc);
		$timezones = array();

		foreach (DateTimeZone::listIdentifiers() as $tz ) {
			$current_tz = new DateTimeZone($tz);
			$offset = $current_tz->getOffset($dt);
			$transition = $current_tz->getTransitions($dt->getTimestamp(), $dt->getTimestamp());
			$abbr = $transition[0]["abbr"];
			if (!is_null($current) && ($current == $tz)) {
				$timezones[] = "<option value=\"" . $tz . "\" selected>" . $tz . " [" . $abbr . " " . self::formatOffset($offset) . "]</option>";
			}
			else {
				$timezones[] = "<option value=\"" . $tz . "\">" . $tz . " [" . $abbr . " " . self::formatOffset($offset) . "]</option>";
			}
		}

		return $timezones;
	}

	static public function GetCurrentTimeOffset()
	{
		$utc = new DateTimeZone("UTC");
		$dt = new DateTime("now", $utc);
		$current_timezone = ipTV_lib::$settings["default_timezone"];
		$current_tz = new DateTimeZone($current_timezone);
		$offset = $current_tz->getOffset($dt);
		return self::formatOffset($offset);
	}

	static public function SimpleWebGet($url, $save_cache = false)
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
		$res = curl_exec($ch);
		curl_close($ch);

		if ($res !== false) {
			if ($save_cache) {
				$unique_id = uniqid();
				file_put_contents(TMP_DIR . $unique_id, $res);
				return TMP_DIR . $unique_id;
			}
		}

		return trim($res);
	}

	static public function curlMultiRequest($urls, $callback = NULL, $array_key = "raw")
	{
		if (empty($urls)) {
			return array();
		}

		$ch = array();
		$results = array();
		$mh = curl_multi_init();

		foreach ($urls as $key => $val ) {
			$ch[$key] = curl_init();
			curl_setopt($ch[$key], CURLOPT_URL, $val["url"]);
			curl_setopt($ch[$key], CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch[$key], CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($ch[$key], CURLOPT_CONNECTTIMEOUT, 120);
			curl_setopt($ch[$key], CURLOPT_TIMEOUT, 120);
			curl_setopt($ch[$key], CURLOPT_MAXREDIRS, 10);

			if ($val["postdata"] != NULL) {
				curl_setopt($ch[$key], CURLOPT_POST, true);
				curl_setopt($ch[$key], CURLOPT_POSTFIELDS, http_build_query($val["postdata"]));
			}

			curl_multi_add_handle($mh, $ch[$key]);
		}

		$running = NULL;

		do {
			curl_multi_exec($mh, $running);
		} while (0 < $running);

		foreach ($ch as $key => $val ) {
			$results[$key] = curl_multi_getcontent($val);

			if ($callback != NULL) {
				$results[$key] = call_user_func($callback, $results[$key], true);

				if (isset($results[$key][$array_key])) {
					$results[$key] = $results[$key][$array_key];
				}
			}

			if (!$results[$key]) {
				$results[$key] = array();
				ipTV_lib::SaveLog("Server [$key] is DOWN!");
			}

			curl_multi_remove_handle($mh, $val);
		}

		curl_multi_close($mh);
		return $results;
	}

	static public function cleanGlobals(&$data, $iteration = 0)
	{
		if (10 <= $iteration) {
			return NULL;
		}

		foreach ($data as $k => $v ) {
			if (is_array($v)) {
				self::cleanGlobals($data[$k], ++$iteration);
			}
			else {
				$v = str_replace(chr("0"), "", $v);
				$v = str_replace("\000", "", $v);
				$v = str_replace("\000", "", $v);
				$v = str_replace("../", "&#46;&#46;/", $v);
				$v = str_replace("&#8238;", "", $v);
				$data[$k] = $v;
			}
		}
	}

	static public function parseIncomingRecursively(&$data, $input = array(), $iteration = 0)
	{
		if (20 <= $iteration) {
			return $input;
		}

		if (!is_array($data)) {
			return $input;
		}

		foreach ($data as $k => $v ) {
			if (is_array($v)) {
				$input[$k] = self::parseIncomingRecursively($data[$k], array(), $iteration + 1);
			}
			else {
				$k = self::parseCleanKey($k);
				$v = self::parseCleanValue($v);
				$input[$k] = $v;
			}
		}

		return $input;
	}

	static public function parseCleanKey($key)
	{
		if ($key === "") {
			return "";
		}

		$key = htmlspecialchars(urldecode($key));
		$key = str_replace("..", "", $key);
		$key = preg_replace("/\_\_(.+?)\_\_/", "", $key);
		$key = preg_replace("/^([\w\.\-\_]+)$/", "\$1", $key);
		return $key;
	}

	static public function parseCleanValue($val)
	{
		if ($val == "") {
			return "";
		}

		$val = str_replace("&#032;", " ", stripslashes($val));
		$val = str_replace(array("\r\n", "\n\r", "\r"), "\n", $val);
		$val = str_replace("<!--", "&#60;&#33;--", $val);
		$val = str_replace("-->", "--&#62;", $val);
		$val = str_ireplace("<script", "&#60;script", $val);
		$val = preg_replace("/&amp;#([0-9]+);/s", "&#\1;", $val);
		$val = preg_replace("/&#(\d+?)([^\d;])/i", "&#\1;\2", $val);
		return trim($val);
	}

	static public function SaveLog($msg)
	{
		self::$ipTV_db->query("INSERT INTO `panel_logs` (`log_message`,`date`) VALUES('%s','%d')", $msg, time());
	}

	static public function GetXtreamInfo()
	{
		self::$ipTV_db->query("SELECT * from `xtream_main` WHERE `id` = 1");

		if (0 < self::$ipTV_db->num_rows()) {
			self::$GetXtreamInfo = self::$ipTV_db->get_row();
		}
	}

	static public function IsEmail($email)
	{
		$isValid = true;
		$atIndex = strrpos($email, "@");
		if (is_bool($atIndex) && !$atIndex) {
			$isValid = false;
		}
		else {
			$domain = substr($email, $atIndex + 1);
			$local = substr($email, 0, $atIndex);
			$localLen = strlen($local);
			$domainLen = strlen($domain);
			if (($localLen < 1) || (64 < $localLen)) {
				$isValid = false;
			}
			else {
				if (($domainLen < 1) || (255 < $domainLen)) {
					$isValid = false;
				}
				else {
					if (($local[0] == ".") || ($local[$localLen - 1] == ".")) {
						$isValid = false;
					}
					else if (preg_match("/\.\./", $local)) {
						$isValid = false;
					}
					else if (!preg_match("/^[A-Za-z0-9\-\.]+$/", $domain)) {
						$isValid = false;
					}
					else if (preg_match("/\.\./", $domain)) {
						$isValid = false;
					}
					else if (!preg_match("/^(\\\\.|[A-Za-z0-9!#%&`_=\/$'*+?^{}|~.-])+$/", str_replace("\\\\", "", $local))) {
						if (!preg_match("/^\"(\\\\\"|[^\"])+\"$/", str_replace("\\\\", "", $local))) {
							$isValid = false;
						}
					}
				}
			}

			if ($isValid && !checkdnsrr($domain, "MX") || checkdnsrr($domain, "A")) {
				$isValid = false;
			}
		}

		return $isValid;
	}

	static public function GenerateString($length = 10)
	{
		$chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789qwertyuiopasdfghjklzxcvbnm";
		$str = "";
		$max = strlen($chars) - 1;

		for ($i = 0; $i < $length; $i++) {
			$str .= $chars[rand(0, $max)];
		}

		return $str;
	}

	static public function array_values_recursive($array)
	{
		$arrayValues = array();

		foreach ($array as $value ) {
			if (is_scalar($value) || is_resource($value)) {
				$arrayValues[] = $value;
			}
			else if (is_array($value)) {
				$arrayValues = array_merge($arrayValues, self::array_values_recursive($value));
			}
		}

		return $arrayValues;
	}

	static public function BuildTreeArray($servers)
	{
		$tree = array();

		foreach ($servers as $server ) {
			if (!isset($tree[$server["parent_id"]])) {
				$tree[$server["parent_id"]] = array();
			}
			else {
				continue;
			}

			foreach ($servers as $second_parse_servers ) {
				if ($second_parse_servers["parent_id"] == $server["parent_id"]) {
					$tree[$server["parent_id"]][] = $second_parse_servers["server_id"];
				}
			}
		}

		ksort($tree);
		return $tree;
	}

	static public function PrintTree($array, $index = 0)
	{
		$out = "";
		if (isset($array[$index]) && is_array($array[$index])) {
			$out = "<ul>";

			foreach ($array[$index] as $track ) {
				$out .= "<li><a href=\"#\">" . ipTV_lib::$StreamingServers[$track]["server_name"] . "</a>";
				$out .= self::PrintTree($array, $track);
				$out .= "</li>";
			}

			$out .= "</ul>";
		}

		return $out;
	}

	static public function add_quotes_string($string)
	{
		return "\"" . $string . "\"";
	}

	static public function valid_ip_cidr($cidr, $must_cidr = false)
	{
		if (!preg_match("/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}(\/[0-9]{1,2})?$/", $cidr)) {
			$return = false;
		}
		else {
			$return = true;
		}

		if ($return == true) {
			$parts = explode("/", $cidr);
			$ip = $parts[0];
			$netmask = $parts[1];
			$octets = explode(".", $ip);

			foreach ($octets as $octet ) {
				if (255 < $octet) {
					$return = false;
				}
			}

			if ((($netmask != "") && (32 < $netmask) && !$must_cidr) || ((($netmask == "") || (32 < $netmask)) && $must_cidr)) {
				$return = false;
			}
		}

		return $return;
	}
}


?>
