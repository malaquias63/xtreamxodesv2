<?php

function isMobileDevice()
{
	$aMobileUA = array("/iphone/i" => "iPhone", "/ipod/i" => "iPod", "/ipad/i" => "iPad", "/android/i" => "Android", "/blackberry/i" => "BlackBerry", "/webos/i" => "Mobile");

	foreach ($aMobileUA as $sMobileKey => $sMobileOS ) {
		if (preg_match($sMobileKey, $_SERVER["HTTP_USER_AGENT"])) {
			return true;
		}
	}

	return false;
}

function CronChecking($file_name, $time = 600)
{
	if (file_exists($file_name)) {
		$pid = trim(file_get_contents($file_name));

		if (file_exists("/proc/" . $pid)) {
			if ((time() - filemtime($file_name)) < $time) {
				exit("Running...");
			}

			posix_kill($pid, 9);
		}
	}

	file_put_contents($file_name, getmypid());
	return false;
}

function BlockIP($ip, $reason)
{
	global $ipTV_db;

	if (in_array($ip, ipTV_Stream::getAllowedIPsAdmin(true))) {
		return NULL;
	}

	$ipTV_db->query("INSERT INTO `blocked_ips` (`ip`,`notes`,`date`) VALUES('%s','%s','%d')", $ip, $reason, time());

	if (0 < $ipTV_db->affected_rows()) {
		Servers::RunCommandServer(array_keys(ipTV_lib::$StreamingServers), "sudo /sbin/iptables -A INPUT -s $ip -j DROP");
	}
}

function CheckFlood()
{
	global $ipTV_db;
	$user_ip = ipTV_Stream::getUserIP();

	if (empty($user_ip)) {
		return NULL;
	}

	if ((ipTV_lib::$settings["flood_limit"] == 0) || in_array($user_ip, ipTV_Stream::getAllowedIPsAdmin(true))) {
		return NULL;
	}

	$restreamers = array_filter(array_unique(explode(",", ipTV_lib::$settings["flood_ips_exclude"])));

	if (in_array($user_ip, $restreamers)) {
		return NULL;
	}

	$user_activity_now = TMP_DIR . "user_activity_now.ips";
	$user_ip_file = TMP_DIR . $user_ip . ".flood";
	if (!file_exists($user_activity_now) || (20 <= time() - filemtime($user_activity_now))) {
		$ipTV_db->query("SELECT DISTINCT `user_ip`,t2.is_restreamer FROM `user_activity_now` t1 INNER JOIN `users` t2 ON t2.id = t1.user_id");
		$connected_ips = $ipTV_db->get_rows(true, "user_ip");
		file_put_contents($user_activity_now, json_encode($connected_ips));
	}
	else {
		$connected_ips = json_decode(file_get_contents($user_activity_now), true);
	}

	if (array_key_exists($user_ip, $connected_ips)) {
		if ($connected_ips[$user_ip]["is_restreamer"] == 0) {
			if (ipTV_lib::$settings["flood_apply_clients"] != 1) {
				return NULL;
			}
		}

		if ($connected_ips[$user_ip]["is_restreamer"] == 1) {
			if (ipTV_lib::$settings["flood_apply_restreamers"] != 1) {
				return NULL;
			}
		}
	}

	if (file_exists($user_ip_file)) {
		$flood_row = json_decode(file_get_contents($user_ip_file), true);
		$frequency_settings = ipTV_lib::$settings["flood_seconds"];
		$limit_attempts = ipTV_lib::$settings["flood_max_attempts"];
		$flood_limit = ipTV_lib::$settings["flood_limit"];

		if ($limit_attempts <= $flood_row["attempts"]) {
			$ipTV_db->query("INSERT INTO `blocked_ips` (`ip`,`notes`,`date`) VALUES('%s','%s','%d')", $user_ip, "FLOOD ATTACK", time());
			Servers::RunCommandServer(array_keys(ipTV_lib::$StreamingServers), "sudo /sbin/iptables -A INPUT -s $user_ip -j DROP");
			unlink($user_ip_file);
			return NULL;
		}

		if ((time() - $flood_row["last_request"]) <= $frequency_settings) {
			++$flood_row["requests"];

			if ($flood_limit <= $flood_row["requests"]) {
				++$flood_row["attempts"];
				$flood_row["requests"] = 0;
			}

			$flood_row["last_request"] = time();
			file_put_contents($user_ip_file, json_encode($flood_row), LOCK_EX);
		}
		else {
			$flood_row["attempts"] = $flood_row["requests"] = 0;
			$flood_row["last_request"] = time();
			file_put_contents($user_ip_file, json_encode($flood_row), LOCK_EX);
		}
	}
	else {
		file_put_contents($user_ip_file, json_encode(array("requests" => 0, "attempts" => 0, "last_request" => time())), LOCK_EX);
	}
}

function GetEPGs()
{
	global $ipTV_db;
	$ipTV_db->query("\n                    SELECT t1.*,COUNT(DISTINCT t2.`id`) as total_rows\n                    FROM `epg` t1\n                    LEFT  JOIN `epg_data` t2 ON t1.id = t2.epg_id\n                    GROUP BY t1.id\n                    ORDER BY t1.id DESC\n                    ");
	return 0 < $ipTV_db->num_rows() ? $ipTV_db->get_rows() : array();
}

function GetEPGStream($stream_id, $from_now = false)
{
	global $ipTV_db;
	$ipTV_db->query("SELECT `type`,`movie_propeties`,`epg_id`,`channel_id`FROM `streams` WHERE `id` = '%d'", $stream_id);

	if (0 < $ipTV_db->num_rows()) {
		$data = $ipTV_db->get_row();

		if ($data["type"] != 2) {
			if ($from_now) {
				$ipTV_db->query("SELECT * FROM `epg_data` WHERE `epg_id` = '%d' AND `channel_id` = '%s' AND `end` >= '%d'", $data["epg_id"], $data["channel_id"], time());
			}
			else {
				$ipTV_db->query("SELECT * FROM `epg_data` WHERE `epg_id` = '%d' AND `channel_id` = '%s'", $data["epg_id"], $data["channel_id"]);
			}

			return $ipTV_db->get_rows();
		}
		else {
			return $data["movie_propeties"];
		}
	}

	return array();
}

function GetTotalCPUsage()
{
	$total_cpu = intval(shell_exec("ps aux|awk 'NR > 0 { s +=\$3 }; END {print s}'"));
	$cores = intval(shell_exec("grep --count processor /proc/cpuinfo"));
	return intval($total_cpu / $cores);
}

function portal_auth($sn, $mac, $ver, $stb_type, $image_version, $device_id, $device_id2, $hw_version, $req_ip)
{
	global $ipTV_db;
	$ipTV_db->query("SELECT * FROM `mag_devices` WHERE `mac` = '%s'", $mac);

	if (0 < $ipTV_db->num_rows()) {
		$mag_info_db = $ipTV_db->get_row();
		$ipTV_db->query("SELECT * FROM `users` WHERE `id` = '%d' AND `is_mag` = 1", $mag_info_db["user_id"]);

		if (0 < $ipTV_db->num_rows()) {
			$user_info_db = $ipTV_db->get_row();
			$user_info_db["allowed_ips"] = json_decode($user_info_db["allowed_ips"], true);
		}

		$total_info = array_merge($mag_info_db, $user_info_db);
		$ipTV_db->query("UPDATE `mag_devices` SET `ip` = '%s' WHERE `mag_id` = '%d'", $req_ip, $total_info["mag_id"]);
		if ((empty($total_info["stb_type"]) && !empty($stb_type)) || (empty($total_info["sn"]) && !empty($sn)) || (empty($total_info["ver"]) && !empty($ver)) || (empty($total_info["image_version"]) && !empty($image_version)) || (empty($total_info["device_id"]) && !empty($device_id)) || (empty($total_info["device_id2"]) && !empty($device_id2)) || (empty($total_info["hw_version"]) && !empty($hw_version))) {
			if (empty($total_info["stb_type"]) && !empty($stb_type)) {
				$ipTV_db->query("UPDATE `mag_devices` SET `stb_type` = '%s' WHERE `mag_id` = '%d'", $stb_type, $total_info["mag_id"]);
				$total_info["stb_type"] = $stb_type;
			}

			if (empty($total_info["sn"]) && !empty($sn)) {
				$ipTV_db->query("UPDATE `mag_devices` SET `sn` = '%s' WHERE `mag_id` = '%d'", $sn, $total_info["mag_id"]);
				$total_info["sn"] = $sn;
			}

			if (empty($total_info["ver"]) && !empty($ver)) {
				$ipTV_db->query("UPDATE `mag_devices` SET `ver` = '%s' WHERE `mag_id` = '%d'", $ver, $total_info["mag_id"]);
				$total_info["ver"] = $ver;
			}

			if (empty($total_info["image_version"]) && !empty($image_version)) {
				$ipTV_db->query("UPDATE `mag_devices` SET `image_version` = '%s' WHERE `mag_id` = '%d'", $image_version, $total_info["mag_id"]);
				$total_info["image_version"] = $image_version;
			}

			if (empty($total_info["device_id"]) && !empty($device_id)) {
				$ipTV_db->query("UPDATE `mag_devices` SET `device_id` = '%s' WHERE `mag_id` = '%d'", $device_id, $total_info["mag_id"]);
				$total_info["device_id"] = $device_id;
			}

			if (empty($total_info["device_id2"]) && !empty($device_id2)) {
				$ipTV_db->query("UPDATE `mag_devices` SET `device_id2` = '%s' WHERE `mag_id` = '%d'", $device_id2, $total_info["mag_id"]);
				$total_info["device_id"] = $device_id2;
			}

			if (empty($total_info["hw_version"]) && !empty($hw_version)) {
				$ipTV_db->query("UPDATE `mag_devices` SET `hw_version` = '%s' WHERE `mag_id` = '%d'", $hw_version, $total_info["mag_id"]);
				$total_info["hw_version"] = $hw_version;
			}

			return array("total_info" => prepair_mag_cols($total_info), "mag_info_db" => prepair_mag_cols($mag_info_db), "fav_channels" => empty($mag_info_db["fav_channels"]) ? array() : json_decode($mag_info_db["fav_channels"], true));
		}
		else {
			if (($total_info["sn"] == $sn) && ($total_info["hw_version"] == $hw_version) && ($total_info["device_id2"] == $device_id2) && ($total_info["device_id"] == $device_id) && ($total_info["image_version"] == $image_version) && ($total_info["ver"] == $ver)) {
				return array("total_info" => prepair_mag_cols($total_info), "mag_info_db" => prepair_mag_cols($mag_info_db), "fav_channels" => empty($mag_info_db["fav_channels"]) ? array() : json_decode($mag_info_db["fav_channels"], true));
			}
		}
	}

	return false;
}

function get_from_cookie($cookie, $type)
{
	if (!empty($cookie)) {
		$explode = explode(";", $cookie);

		foreach ($explode as $data ) {
			$data = explode("=", $data);
			$output[trim($data[0])] = trim($data[1]);
		}

		switch ($type) {
		case "mac":
			if (array_key_exists("mac", $output)) {
				return base64_encode(strtoupper(urldecode($output["mac"])));
			}
		}
	}

	return false;
}

function prepair_mag_cols($array)
{
	$output = array();

	foreach ($array as $key => $value ) {
		if (($key == "mac") || ($key == "ver") || ($key == "hw_version")) {
			$output[$key] = base64_decode($value);
		}

		$output[$key] = $value;
	}

	unset($output["fav_channels"]);
	return $output;
}

function GetCategories($type = NULL, $remove_empty = false)
{
	global $ipTV_db;

	if ($remove_empty) {
		$query_join = "INNER";
	}
	else {
		$query_join = "LEFT";
	}

	$query = "SELECT \n\tt1.id, \n\tt1.category_name, \n\tt1.category_type, \n\tt1.parent_id, \n\tmostrecent.total_streams, \n\tmostrecent.series, \n\tt2.movie_propeties, \n\tt2.added, \n\tt2.id as last_stream_id \nFROM \n\t`stream_categories` t1 \n\tLEFT JOIN (\n\t\tSELECT \n\t\t\tcategory_id, \n\t\t\tMAX(id) as max_id, \n\t\t\tCOUNT(*) as total_streams, \n\t\t\tGROUP_CONCAT(series_no) AS series \n\t\tFROM \n\t\t\tstreams \n\t\tWHERE \n\t\t\tlength(movie_propeties) > 5 \n\t\tGROUP BY \n\t\t\tcategory_id \n\t\torder by \n\t\t\tadded DESC\n\t) mostrecent ON mostrecent.category_id = t1.id \n\t$query_join JOIN `streams` t2 ON t2.category_id = mostrecent.category_id \n\tAND t2.id = mostrecent.max_id ";

	switch ($type) {
	case NULL:
		break;

	case "live":
		$query .= " WHERE t1.category_type = 'live'";
		break;

	case "movie":
		$query .= " WHERE t1.category_type = 'movie'";
		break;
	}

	$query .= " GROUP BY \n\tt1.category_name, \n\tt1.category_type \nORDER BY \n\tCONCAT(\n\t\tIF(t1.parent_id = 0, '', t1.parent_id), \n\t\tt1.id\n\t)";
	$ipTV_db->query($query);
	return 0 < $ipTV_db->num_rows() ? $ipTV_db->get_rows(true, "id") : array();
}

function GenerateUniqueCode()
{
	return substr(md5(ipTV_lib::$settings["unique_id"]), 0, 15);
}

function encodeToUtf8($string)
{
	return mb_convert_encoding($string, "UTF-8", mb_detect_encoding($string, "UTF-8, ISO-8859-1, ISO-8859-15", true));
}

function GenerateList($user_id, $device_key, $output_key = "", $force_download = false)
{
	global $ipTV_db;

	if (!RowExists("users", "id", $user_id)) {
		return false;
	}

	if (empty($device_key)) {
		return false;
	}

	if (empty($output_key)) {
		$ipTV_db->query("SELECT t1.output_ext FROM `access_output` t1 INNER JOIN `devices` t2 ON t2.default_output = t1.access_output_id AND `device_key` = '%s'", $device_key);
		$output_ext = $ipTV_db->get_col();
	}
	else {
		$ipTV_db->query("SELECT t1.output_ext FROM `access_output` t1 WHERE `output_key` = '%s'", $output_key);
		$output_ext = $ipTV_db->get_col();
	}

	if (empty($output_ext)) {
		return false;
	}

	$user_info = ipTV_Stream::GetUserInfo($user_id, NULL, NULL, true, true, false);

	if (empty($user_info)) {
		return false;
	}

	if (!empty($user_info["exp_date"]) && ($user_info["exp_date"] <= time())) {
		return false;
	}

	$ipTV_db->query("SELECT t1.*,t2.*\n                              FROM `devices` t1\n                              LEFT JOIN `access_output` t2 ON t2.access_output_id = t1.default_output\n                              WHERE t1.device_key = '%s' LIMIT 1", $device_key);
	$domain_name = ipTV_lib::$StreamingServers[SERVER_ID]["site_url"];

	if (0 < $ipTV_db->num_rows()) {
		$device_info = $ipTV_db->get_row();
		$data = "";

		if ($device_key == "starlivev5") {
			$output_array = array();
			$output_array["iptvstreams_list"] = array();
			$output_array["iptvstreams_list"]["@version"] = 1;
			$output_array["iptvstreams_list"]["group"] = array();
			$output_array["iptvstreams_list"]["group"]["name"] = "IPTV";
			$output_array["iptvstreams_list"]["group"]["channel"] = array();

			foreach ($user_info["channels"] as $channel_info ) {
				if ($channel_info["direct_source"] == 0) {
					$url = $domain_name . "{$channel_info["type_output"]}/{$user_info["username"]}/{$user_info["password"]}/";

					if ($channel_info["live"] == 0) {
						$url .= $channel_info["id"] . "." . $channel_info["container_extension"];
						$movie_propeties = json_decode($channel_info["movie_propeties"], true);

						if (!empty($movie_propeties["movie_image"])) {
							$icon = $movie_propeties["movie_image"];
						}
					}
					else {
						$url .= $channel_info["id"] . "." . $output_ext;
						$icon = $channel_info["stream_icon"];
					}
				}
				else {
					list($url) = json_decode($channel_info["stream_source"], true);
				}

				$channel = array();
				$channel["name"] = $channel_info["stream_display_name"];
				$icon = "";
				$channel["icon"] = $icon;
				$channel["stream_url"] = $url;
				$channel["stream_type"] = 0;
				$output_array["iptvstreams_list"]["group"]["channel"][] = $channel;
			}

			$data = json_encode((object) $output_array);
		}
		else {
			if (!empty($device_info["device_header"])) {
				$data = str_replace(array("{BOUQUET_NAME}", "{USERNAME}", "{PASSWORD}", "{SERVER_URL}", "{OUTPUT_KEY}"), array(ipTV_lib::$settings["bouquet_name"], $user_info["username"], $user_info["password"], $domain_name, $output_key), $device_info["device_header"]) . "\n";
			}

			if (!empty($device_info["device_conf"])) {
				if (preg_match("/\{URL\#(.*?)\}/", $device_info["device_conf"], $matches)) {
					$url_encoded_charts = str_split($matches[1]);
					$url_pattern = $matches[0];
				}
				else {
					$url_encoded_charts = array();
					$url_pattern = "{URL}";
				}

				foreach ($user_info["channels"] as $channel ) {
					if ($channel["direct_source"] == 0) {
						$url = $domain_name . "{$channel["type_output"]}/{$user_info["username"]}/{$user_info["password"]}/";
						$icon = "";

						if ($channel["live"] == 0) {
							$url .= $channel["id"] . "." . $channel["container_extension"];
							$movie_propeties = json_decode($channel["movie_propeties"], true);

							if (!empty($movie_propeties["movie_image"])) {
								$icon = $movie_propeties["movie_image"];
							}
						}
						else {
							$url .= $channel["id"] . "." . $output_ext;
							$icon = $channel["stream_icon"];
						}
					}
					else {
						list($url) = json_decode($channel["stream_source"], true);
					}

					$esr_id = ($channel["live"] == 1 ? 1 : 4097);
					$sid = (!empty($channel["custom_sid"]) ? $channel["custom_sid"] : ":0:1:0:0:0:0:0:0:0:");
					$data .= str_replace(array($url_pattern, "{ESR_ID}", "{SID}", "{CHANNEL_NAME}", "{CHANNEL_ID}", "{CATEGORY}", "{CHANNEL_ICON}"), array(str_replace($url_encoded_charts, array_map("urlencode", $url_encoded_charts), $url), $esr_id, $sid, $channel["stream_display_name"], $channel["channel_id"], $channel["category_name"], $icon), $device_info["device_conf"]) . "\r\n";
				}

				$data .= $device_info["device_footer"];
				$data = trim($data);
			}
		}

		if ($force_download === true) {
			header("Content-Description: File Transfer");
			header("Content-Type: application/octet-stream");
			header("Expires: 0");
			header("Cache-Control: must-revalidate");
			header("Pragma: public");
			header("Content-Disposition: attachment; filename=\"" . str_replace("{USERNAME}", $user_info["username"], $device_info["device_filename"]) . "\"");
			header("Content-Length: " . strlen($data));
			echo $data;
			exit();
		}

		return $data;
	}

	return false;
}

function GetServerConnections($end = NULL, $limit = false, $from = 0, $to = 0)
{
	global $ipTV_db;

	switch ($end) {
	case "open":
		$query = "\n                SELECT t1.*,t3.stream_display_name,t4.server_name as source_name,t5.server_name as dest_name\n                FROM `server_activity` t1\n                LEFT JOIN `streams` t3 ON t3.id = t1.stream_id\n                LEFT JOIN `streaming_servers` t4 ON t4.id = t1.source_server_id\n                LEFT JOIN `streaming_servers` t5 ON t5.id = t1.dest_server_id\n                WHERE ISNULL(t1.`date_end`)\n                ORDER BY t1.id DESC ";
		break;

	case "closed":
		$query = "\n                SELECT t1.*,t3.stream_display_name,t4.server_name as source_name,t5.server_name as dest_name\n                FROM `server_activity` t1\n                LEFT JOIN `streams` t3 ON t3.id = t1.stream_id\n                LEFT JOIN `streaming_servers` t4 ON t4.id = t1.source_server_id\n                LEFT JOIN `streaming_servers` t5 ON t5.id = t1.dest_server_id\n                WHERE t1.`date_end` IS NOT NULL\n                ORDER BY t1.id DESC ";
		break;

	default:
		$query = "\n                SELECT t1.*,t3.stream_display_name,t4.server_name as source_name,t5.server_name as dest_name\n                FROM `server_activity` t1\n                LEFT JOIN `streams` t3 ON t3.id = t1.stream_id\n                LEFT JOIN `streaming_servers` t4 ON t4.id = t1.source_server_id\n                LEFT JOIN `streaming_servers` t5 ON t5.id = t1.dest_server_id\n                ORDER BY (t1.`date_end` IS NOT NULL),t1.id DESC ";
	}

	if ($limit === true) {
		$query .= "LIMIT $from,$to";
	}

	$ipTV_db->query($query);
	$activities = array();

	if (0 < $ipTV_db->num_rows()) {
		$activities = $ipTV_db->get_rows();
	}

	return $activities;
}

function GetConnections($end, $server_id = NULL)
{
	global $ipTV_db;
	$extra = "";

	if (!is_null($server_id)) {
		$extra = "WHERE t1.server_id = '" . intval($server_id) . "'";
	}

	switch ($end) {
	case "open":
		$query = "\n                SELECT t1.*,t2.*,t3.*,t4.*,t5.mac,t6.bitrate\n                FROM `user_activity_now` t1\n                LEFT JOIN `users` t2 ON t2.id = t1.user_id\n                LEFT JOIN `streams` t3 ON t3.id = t1.stream_id\n                LEFT JOIN `streaming_servers` t4 ON t4.id = t1.server_id\n                LEFT JOIN `mag_devices` t5 on t5.user_id = t2.id\n                LEFT JOIN `streams_sys` t6 ON t6.stream_id = t1.stream_id AND t6.server_id = t1.server_id\n                $extra\n                ORDER BY t1.activity_id DESC";
		break;

	case "closed":
		$query = "\n                SELECT t1.*,t2.*,t3.*,t4.*,t5.mac,t6.bitrate\n                FROM `user_activity` t1\n                LEFT JOIN `users` t2 ON t2.id = t1.user_id\n                LEFT JOIN `streams` t3 ON t3.id = t1.stream_id\n                LEFT JOIN `streaming_servers` t4 ON t4.id = t1.server_id\n                LEFT JOIN `mag_devices` t5 on t5.user_id = t2.id\n                LEFT JOIN `streams_sys` t6 ON t6.stream_id = t1.stream_id AND t6.server_id = t1.server_id\n                $extra\n                ORDER BY t1.activity_id DESC";
		break;
	}

	$ipTV_db->query($query);
	return $ipTV_db->get_rows();
}

function Is_Running($file_name)
{
	$pid_running = false;

	if (file_exists($file_name)) {
		$data = file($file_name);

		foreach ($data as $pid ) {
			$pid = (int) $pid;
			if ((0 < $pid) && file_exists("/proc/" . $pid)) {
				$pid_running = $pid;
				break;
			}
		}
	}

	if ($pid_running && ($pid_running != getmypid())) {
		if (file_exists($file_name)) {
			file_put_contents($file_name, $pid);
		}

		return true;
	}
	else {
		file_put_contents($file_name, getmypid());
		return false;
	}
}

function crontab_refresh()
{
	if (file_exists(TMP_DIR . "crontab_refresh")) {
		return false;
	}

	$crons = scandir(CRON_PATH);
	$jobs = array();

	foreach ($crons as $cron ) {
		$full_path = CRON_PATH . $cron;

		if (!is_file($full_path)) {
			continue;
		}

		if (pathinfo($full_path, PATHINFO_EXTENSION) != "php") {
			continue;
		}

		$jobs[] = "*/1 * * * * " . PHP_BIN . " " . $full_path . " # Xtream-Codes IPTV Panel";
	}

	$crontab = trim(shell_exec("crontab -l"));

	if (!empty($crontab)) {
		$lines = explode("\n", $crontab);
		$lines = array_map("trim", $lines);

		if ($lines == $jobs) {
			file_put_contents(TMP_DIR . "crontab_refresh", 1);
			return true;
		}

		$counter = count($lines);

		for ($i = 0; $i < $counter; $i++) {
			if (stripos($lines[$i], CRON_PATH)) {
				unset($lines[$i]);
			}
		}

		foreach ($jobs as $job ) {
			array_push($lines, $job);
		}
	}
	else {
		$lines = $jobs;
	}

	shell_exec("crontab -r");
	$tmpfname = tempnam("/tmp", "crontab");
	$handle = fopen($tmpfname, "w");
	fwrite($handle, implode("\r\n", $lines) . "\r\n");
	fclose($handle);
	shell_exec("crontab $tmpfname");
	@unlink($tmpfname);
	file_put_contents(TMP_DIR . "crontab_refresh", 1);
}

function RowExists($table, $search_by, $needle)
{
	global $ipTV_db;
	$ipTV_db->query("SELECT * FROM `$table` WHERE `$search_by` = '%s'", $needle);

	if (0 < $ipTV_db->num_rows()) {
		return true;
	}

	return false;
}

function memory_usage()
{
	$memory_usage = trim(shell_exec("free -m"));

	if (empty($memory_usage)) {
		return false;
	}

	$data = explode("\n", $memory_usage);
	$memory_usage = array();
	$swap_usage = array();

	foreach ($data as $line ) {
		$output = preg_replace("!\s+!", " ", str_replace(":", "", $line));
		if (!strstr($output, "Mem") && !strstr($output, "Swap")) {
			continue;
		}

		$info = explode(" ", $output);

		if ($info[0] == "Mem") {
			$memory_usage["total"] = $info[1];
			$memory_usage["used"] = $info[2] - $info[6];

			if ($memory_usage["used"] < 0) {
				$memory_usage["used"] = $info[2];
			}

			$memory_usage["free"] = $info[3];
			$memory_usage["percent"] = sprintf("%0.2f", ($memory_usage["used"] / $memory_usage["total"]) * 100);
		}
		else {
			$swap_usage["total"] = $info[1];
			$swap_usage["used"] = $info[2];
			$swap_usage["free"] = $info[3];

			if ($swap_usage["total"] != 0) {
				$swap_usage["percent"] = sprintf("%0.2f", ($info[2] / $info[1]) * 100);
			}
			else {
				$swap_usage["percent"] = 0;
			}
		}
	}

	return array($memory_usage, $swap_usage);
}

function get_boottime()
{
	if (file_exists("/proc/uptime") && is_readable("/proc/uptime")) {
		$tmp = explode(" ", file_get_contents("/proc/uptime"));
		return secondsToTime(intval($tmp[0]));
	}

	return "";
}

function secondsToTime($inputSeconds)
{
	$secondsInAMinute = 60;
	$secondsInAnHour = 60 * $secondsInAMinute;
	$secondsInADay = 24 * $secondsInAnHour;
	$days = (int) floor($inputSeconds / $secondsInADay);
	$hourSeconds = $inputSeconds % $secondsInADay;
	$hours = (int) floor($hourSeconds / $secondsInAnHour);
	$minuteSeconds = $hourSeconds % $secondsInAnHour;
	$minutes = (int) floor($minuteSeconds / $secondsInAMinute);
	$remainingSeconds = $minuteSeconds % $secondsInAMinute;
	$seconds = (int) ceil($remainingSeconds);
	$final = "";

	if ($days != 0) {
		$final .= "{$days}d ";
	}

	if ($hours != 0) {
		$final .= "{$hours}h ";
	}

	if ($minutes != 0) {
		$final .= "{$minutes}m ";
	}

	$final .= "{$seconds}s";
	return $final;
}
	public function addCData($cdata_text)
	{
		$node = dom_import_simplexml($this);
		$no = $node->ownerDocument;
		$node->appendChild($no->createCDATASection($cdata_text));
	}
}


?>
