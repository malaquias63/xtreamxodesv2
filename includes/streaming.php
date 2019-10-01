<?php

class zuw6qkv78i8
{
	/**
     * Database Instance
     *
     * @var		instance
     */
	static 	public $ipTV_db;
	static 	public $AllowedIPs = array();

	static public function getAllowedIPsAdmin($reg_users = true)
	{
		if (!empty(self::$AllowedIPs)) {
			return self::$AllowedIPs;
		}

		$ips = array("127.0.0.1", $_SERVER["SERVER_ADDR"]);

		foreach (ipTV_lib::$StreamingServers as $server_id => $server_info ) {
			$ips[] = gethostbyname($server_info["server_ip"]);
		}

		if ($reg_users) {
			self::$ipTV_db->query("SELECT `ip` FROM `reg_users` WHERE `member_group_id` = 1 AND `last_login` >= '%d'", strtotime("-2 hour"));
			$ips = array_merge($ips, ipTV_lib::array_values_recursive(self::$ipTV_db->get_rows()));
		}

		if (!empty(ipTV_lib::$settings["allowed_ips_admin"])) {
			$ips = array_merge($ips, explode(",", ipTV_lib::$settings["allowed_ips_admin"]));
		}

		if (!empty(ipTV_lib::$GetXtreamInfo["root_ip"])) {
			$ips[] = ipTV_lib::$GetXtreamInfo["root_ip"];
		}

		if (!file_exists(TMP_DIR . "cloud_ips") || (900 <= time() - filemtime(TMP_DIR . "cloud_ips"))) {
			$contents = ipTV_lib::SimpleWebGet("http://xtream-codes.com/cloud_ips");

			if (!empty($contents)) {
				file_put_contents(TMP_DIR . "cloud_ips", $contents);
			}
		}

		if (file_exists(TMP_DIR . "cloud_ips")) {
			$ips = array_filter(array_merge($ips, array_map("trim", file(TMP_DIR . "cloud_ips"))));
		}

		self::$AllowedIPs = $ips;
		return array_unique($ips);
	}

	static public function FileParser($FileName)
	{
		if (!file_exists($FileName)) {
			return false;
		}

		$streams = array();
		$need_stream_url = false;
		$fp = fopen($FileName, "r");

		while (!feof($fp)) {
			$line = urldecode(trim(fgets($fp)));

			if (empty($line)) {
				continue;
			}

			if (stristr($line, "#EXTM3U")) {
				continue;
			}

			if (!stristr($line, "#EXTINF") && $need_stream_url) {
				$streams[$stream_name] = json_encode(array($line));
				$need_stream_url = false;
				continue;
			}

			if (stristr($line, "#EXTINF")) {
				$stream_name = trim(end(explode(",", $line)));
				$need_stream_url = true;
			}
		}

		return $streams;
	}

	static public function CanServerStream($server_id, $stream_id, $type = "live", $extension = NULL)
	{
		if ($type == "live") {
			self::$ipTV_db->query("\n                    SELECT *\n                    FROM `streams` t1\n                    INNER JOIN `streams_types` t4 ON t4.type_id = t1.type\n                    INNER JOIN `streams_sys` t2 ON t2.stream_id = t1.id AND t2.pid IS NOT NULL AND t2.server_id = '%d'\n                    WHERE t1.`id` = '%d'", $server_id, $stream_id);
		}
		else {
			self::$ipTV_db->query("\n                    SELECT * \n                    FROM `streams` t1\n                    INNER JOIN `streams_sys` t2 ON t2.stream_id = t1.id AND t2.pid IS NOT NULL AND t2.server_id = '%d' AND t2.stream_status = 0 AND t2.to_analyze = 0 AND t2.pid IS NOT NULL\n                    INNER JOIN `movie_containers` t3 ON t3.container_id = t1.target_container_id AND t3.container_extension = '%s'\n                    WHERE t1.`id` = '%d'", $server_id, $extension, $stream_id);
		}

		if (self::$ipTV_db->num_rows()) {
			$stream_info = self::$ipTV_db->get_row();
			return $stream_info;
		}

		return false;
	}

	static public function Redirect($user_info, $USER_IP, $user_country_code, $external_device, $type)
	{
		if ((count(ipTV_lib::$StreamingServers) <= 1) || !array_key_exists(SERVER_ID, ipTV_lib::$StreamingServers)) {
			return false;
		}

		parse_str($_SERVER["QUERY_STRING"], $query);
		$available_servers = array();

		if ($type == "live") {
			$stream_id = $query["stream"];
			$extension = $query["extension"];

			if ($extension == "m3u8") {
				self::$ipTV_db->query("SELECT * FROM `user_activity_now` WHERE container = 'hls' AND `user_id` = '%d' AND `stream_id` = '%d' LIMIT 1", $user_info["id"], $stream_id);

				if (0 < self::$ipTV_db->num_rows()) {
					$activity_info = self::$ipTV_db->get_row();

					if ($activity_info["server_id"] == SERVER_ID) {
						return false;
					}

					if ($channel_info[$activity_info["server_id"]] = self::CanServerStream($activity_info["server_id"], $stream_id, $type, isset($extension) ? $extension : NULL)) {
						$valid_time = 0;
						$md5_key = md5(ipTV_lib::$settings["live_streaming_pass"] . ipTV_lib::$StreamingServers[$activity_info["server_id"]]["server_ip"] . $USER_IP . $stream_id . $query["username"] . $query["password"] . $valid_time);
						header("Location: " . ipTV_lib::$StreamingServers[$activity_info["server_id"]]["site_url"] . $_SERVER["PHP_SELF"] . "?" . $_SERVER["QUERY_STRING"] . "&hash=" . $md5_key . "&time=" . $valid_time . "&external_device=" . $external_device . "&pid=" . $channel_info[$activity_info["server_id"]]["pid"]);
						ob_end_flush();
						exit();
					}
				}
			}
		}
		else {
			$stream = pathinfo($query["stream"]);
			$stream_id = intval($stream["filename"]);
			$extension = $stream["extension"];
		}

		$channel_info = array();

		foreach (ipTV_lib::$StreamingServers as $serverID => $server_info ) {
			if (ipTV_lib::$StreamingServers[$serverID]["status"] != 1) {
				continue;
			}

			if (isset($query["stream"])) {
				if ($channel_info[$serverID] = self::CanServerStream($serverID, $stream_id, $type, isset($extension) ? $extension : NULL)) {
					$available_servers[] = $serverID;
				}
			}
		}

		if (empty($available_servers)) {
			return false;
		}

		self::$ipTV_db->query("SELECT a.server_id, SUM(ISNULL(a.date_end)) AS online_clients FROM `user_activity_now` a WHERE a.server_id IN (" . implode(",", $available_servers) . ") GROUP BY a.server_id ORDER BY online_clients ASC");
		$CanAcceptCons = array();

		foreach (self::$ipTV_db->get_rows() as $row ) {
			if ($row["online_clients"] < ipTV_lib::$StreamingServers[$row["server_id"]]["total_clients"]) {
				$CanAcceptCons[$row["server_id"]] = $row["online_clients"];
			}
			else {
				$CanAcceptCons[$row["server_id"]] = false;
			}
		}

		foreach (array_keys(ipTV_lib::$StreamingServers) as $server_id ) {
			if (in_array($server_id, $available_servers)) {
				if (!array_key_exists($server_id, $CanAcceptCons)) {
					if (0 < ipTV_lib::$StreamingServers[$server_id]["total_clients"]) {
						$CanAcceptCons[$server_id] = 0;
					}
					else {
						$CanAcceptCons[$server_id] = false;
					}
				}
			}
		}

		$CanAcceptCons = array_filter($CanAcceptCons, "is_numeric");

		foreach (array_keys($CanAcceptCons) as $server_id ) {
			if ($server_id == SERVER_ID) {
				continue;
			}

			if (ipTV_lib::$StreamingServers[$server_id]["status"] != 1) {
				unset($CanAcceptCons[$server_id]);
			}
		}

		if (!empty($CanAcceptCons)) {
			$split_clients = ipTV_lib::$settings["split_clients"];

			if ($split_clients == "equal") {
				$keys = array_keys($CanAcceptCons);
				$values = array_values($CanAcceptCons);
				array_multisort($values, SORT_ASC, $keys, SORT_ASC);
				$CanAcceptCons = array_combine($keys, $values);
			}
			else {
				$keys = array_keys($CanAcceptCons);
				$values = array_values($CanAcceptCons);
				array_multisort($values, SORT_ASC, $keys, SORT_DESC);
				$CanAcceptCons = array_combine($keys, $values);
				end($CanAcceptCons);
			}

			foreach (array_keys($CanAcceptCons) as $server_id ) {
				if (empty(ipTV_lib::$StreamingServers[$server_id]["geoip_countries"])) {
					$geoip_countries = array();
				}
				else {
					$geoip_countries = json_decode(ipTV_lib::$StreamingServers[$server_id]["geoip_countries"], true);
				}

				if ((ipTV_lib::$StreamingServers[$server_id]["enable_geoip"] == 1) && in_array($user_country_code, $geoip_countries)) {
					$redirect_id = $server_id;
					break;
				}
			}

			if (!isset($redirectid)) {
				$redirect_id = key($CanAcceptCons);
			}

			if (($user_info["force_server_id"] != 0) && array_key_exists($user_info["force_server_id"], $CanAcceptCons)) {
				$redirect_id = $user_info["force_server_id"];
			}

			if ($redirect_id != SERVER_ID) {
				if ($extension == "m3u8") {
					$valid_time = 0;
				}
				else {
					$valid_time = time() + 10;
				}

				$md5_key = md5(ipTV_lib::$settings["live_streaming_pass"] . ipTV_lib::$StreamingServers[$redirect_id]["server_ip"] . $USER_IP . $stream_id . $query["username"] . $query["password"] . $valid_time);
				header("Location: " . ipTV_lib::$StreamingServers[$redirect_id]["site_url"] . $_SERVER["PHP_SELF"] . "?" . $_SERVER["QUERY_STRING"] . "&hash=" . $md5_key . "&time=" . $valid_time . "&pid=" . $channel_info[$redirect_id]["pid"] . "&external_device=" . $external_device);
				ob_end_flush();
				exit();
			}
		}

		return false;
	}

	static public function GetUserInfo($user_id = NULL, $username = NULL, $password = NULL, $get_ChannelIDS = false, $getBouquetInfo = false, $get_cons = false, $type = array(), $parse_adults = false)
	{
		if (empty($user_id)) {
			self::$ipTV_db->query("SELECT * FROM `users` WHERE `username` = '%s' AND `password` = '%s' LIMIT 1", $username, $password);
		}
		else {
			self::$ipTV_db->query("SELECT * FROM `users` WHERE `id` = '%d'", $user_id);
		}

		if (0 < self::$ipTV_db->num_rows()) {
			$user_info = self::$ipTV_db->get_row();
			$user_info["bouquet"] = json_decode($user_info["bouquet"], true);
			$user_info["allowed_ips"] = json_decode($user_info["allowed_ips"], true);
			$user_info["allowed_ua"] = json_decode($user_info["allowed_ua"], true);

			if ($get_cons) {
				self::$ipTV_db->query("SELECT COUNT(`activity_id`) FROM `user_activity_now` WHERE `user_id` = '%d'", $user_info["id"]);
				$user_info["active_cons"] = self::$ipTV_db->get_col();
				$user_info["pair_line_info"] = array();
				if (!is_null($user_info["pair_id"]) && RowExists("users", "id", $user_info["pair_id"])) {
					self::$ipTV_db->query("SELECT COUNT(`activity_id`) FROM `user_activity_now` WHERE `user_id` = '%d'", $user_info["pair_id"]);
					$user_info["pair_line_info"]["active_cons"] = self::$ipTV_db->get_col();
					self::$ipTV_db->query("SELECT max_connections FROM `users` WHERE `id` = '%d'", $user_info["pair_id"]);
					$user_info["pair_line_info"]["max_connections"] = self::$ipTV_db->get_col();
				}
			}
			else {
				$user_info["active_cons"] = "N/A";
			}

			if ($user_info["is_mag"] == 1) {
				self::$ipTV_db->query("SELECT * FROM `mag_devices` WHERE `user_id` = '%d' LIMIT 1", $user_info["id"]);

				if (0 < self::$ipTV_db->num_rows()) {
					$user_info["mag_device"] = self::$ipTV_db->get_row();
				}
			}

			self::$ipTV_db->query("SELECT *\n                                    FROM `access_output` t1\n                                    INNER JOIN `user_output` t2 ON t1.access_output_id = t2.access_output_id\n                                    WHERE t2.user_id = '%d'", $user_info["id"]);
			$user_info["output_formats"] = self::$ipTV_db->get_rows(true, "output_ext");

			if ($get_ChannelIDS) {
				$channel_ids = array();
				self::$ipTV_db->query("SELECT `bouquet_channels` FROM `bouquets` WHERE `id` IN (" . implode(",", $user_info["bouquet"]) . ")");

				foreach (self::$ipTV_db->get_rows() as $row ) {
					$channel_ids = array_merge($channel_ids, json_decode($row["bouquet_channels"], true));
				}

				$user_info["channel_ids"] = array_unique($channel_ids);
				$user_info["channels"] = array();
				if ($getBouquetInfo && !empty($user_info["channel_ids"])) {
					$get_scat = "";

					if (!empty($type)) {
						$get_scat = " AND (";

						foreach ($type as $tp ) {
							$get_scat .= " t2.type_key = '" . self::$ipTV_db->escape($tp) . "' OR";
						}

						$get_scat = substr($get_scat, 0, -2);
						$get_scat .= ")";
					}

					self::$ipTV_db->query("SELECT t1.*,t2.*,t3.category_name,t4.*\n                                            FROM `streams` t1 \n                                            LEFT JOIN  `stream_categories` t3 on t3.id = t1.category_id\n                                            INNER JOIN `streams_types` t2 ON t2.type_id = t1.type $get_scat\n                                            LEFT JOIN `movie_containers` t4 ON t4.container_id = t1.target_container_id\n                                            WHERE t1.`id` IN(" . implode(",", $user_info["channel_ids"]) . ") \n                                            ORDER BY FIELD(t1.id, " . implode(",", $user_info["channel_ids"]) . ");");
					$user_info["channels"] = self::$ipTV_db->get_rows();

					if ($parse_adults) {
						$total_adults = 0;

						foreach ($user_info["channels"] as $key => $stream ) {
							$user_info["channels"][$key]["is_adult"] = (strtolower($stream["category_name"]) == "for adults" ? 1 : 0);
						}
					}
				}
			}

			return $user_info;
		}

		return false;
	}

	static public function GetMagInfo($mag_id = NULL, $mac = NULL, $get_ChannelIDS = false, $getBouquetInfo = false, $get_cons = false)
	{
		if (empty($mag_id)) {
			self::$ipTV_db->query("SELECT * FROM `mag_devices` WHERE `mac` = '%s'", base64_encode($mac));
		}
		else {
			self::$ipTV_db->query("SELECT * FROM `mag_devices` WHERE `mag_id` = '%d'", $mag_id);
		}

		if (0 < self::$ipTV_db->num_rows()) {
			$maginfo = array();
			$maginfo["mag_device"] = self::$ipTV_db->get_row();
			$maginfo["mag_device"]["mac"] = base64_decode($maginfo["mag_device"]["mac"]);
			$maginfo["mag_device"]["ver"] = base64_decode($maginfo["mag_device"]["ver"]);
			$maginfo["mag_device"]["device_id"] = base64_decode($maginfo["mag_device"]["device_id"]);
			$maginfo["mag_device"]["device_id2"] = base64_decode($maginfo["mag_device"]["device_id2"]);
			$maginfo["mag_device"]["hw_version"] = base64_decode($maginfo["mag_device"]["hw_version"]);
			$maginfo["user_info"] = array();

			if ($user_info = self::GetUserInfo($maginfo["mag_device"]["user_id"], NULL, NULL, $get_ChannelIDS, $getBouquetInfo, $get_cons)) {
				$maginfo["user_info"] = $user_info;
			}

			$maginfo["pair_line_info"] = array();

			if (!empty($maginfo["user_info"])) {
				$maginfo["pair_line_info"] = array();

				if (!is_null($maginfo["user_info"]["pair_id"])) {
					if ($user_info = self::GetUserInfo($maginfo["user_info"]["pair_id"], NULL, NULL, $get_ChannelIDS, $getBouquetInfo, $get_cons)) {
						$maginfo["pair_line_info"] = $user_info;
					}
				}
			}

			return $maginfo;
		}

		return false;
	}

	static public function CloseLastCon($user_id)
	{
		self::$ipTV_db->query("SELECT activity_id,server_id,pid FROM `user_activity_now` WHERE `user_id` = '%d' ORDER BY activity_id DESC LIMIT 1", $user_id);

		if (0 < self::$ipTV_db->num_rows()) {
			$info = self::$ipTV_db->get_row();
			Servers::RunCommandServer($info["server_id"], "kill -9 {$info["pid"]}");
			self::CloseAndTransfer($info["activity_id"]);
			return true;
		}

		return false;
	}

	static public function GetChannelsByBouquet($bouquet_ids)
	{
		if (!is_array($bouquet_ids) || empty($bouquet_ids)) {
			return array();
		}

		$bouquet_ids = array_map("intval", $bouquet_ids);
		$bouquet_channels_ids = array();
		self::$ipTV_db->query("SELECT bouquet_channels FROM `bouquets` WHERE `id` IN (" . implode(",", $bouquet_ids) . ")");

		foreach (self::$ipTV_db->get_rows() as $row ) {
			$bouquet_channels_ids = array_merge($bouquet_channels_ids, json_decode($row["bouquet_channels"], true));
		}

		$bouquet_channels_ids = array_unique($bouquet_channels_ids);
		sort($bouquet_channels_ids);
		self::$ipTV_db->query("SELECT * FROM `streams` WHERE `id` IN (" . implode(",", $bouquet_channels_ids) . ") ORDER BY `stream_display_name` ASC");
		return self::$ipTV_db->get_rows();
	}

	static public function MAGLog($MAG_ID, $action)
	{
		if (!is_numeric($MAG_ID) || empty($MAG_ID)) {
			$MAG_ID = "NULL";
		}

		self::$ipTV_db->query("INSERT INTO `mag_logs` (`mag_id`,`action`) VALUES(%s,'%s')", $MAG_ID, $action);
	}

	static public function ClientLog($stream_id, $userid, $action, $userip, $data = "")
	{
		$user_agent = (!empty($_SERVER["HTTP_USER_AGENT"]) ? htmlentities($_SERVER["HTTP_USER_AGENT"]) : "");
		$query_string = (empty($_SERVER["QUERY_STRING"]) ? "" : $_SERVER["QUERY_STRING"]);
		$data = array("user_id" => $userid, "stream_id" => $stream_id, "action" => $action, "query_string" => htmlentities($_SERVER["QUERY_STRING"]), "user_agent" => $user_agent, "user_ip" => $userip, "time" => time(), "extra_data" => $data);
		file_put_contents(TMP_DIR . "client_request.log", base64_encode(json_encode($data)) . "\n", FILE_APPEND);
	}

	static public function ClientConnected()
	{
		if ((connection_status() != CONNECTION_NORMAL) || connection_aborted()) {
			return false;
		}

		return true;
	}

	static public function GetSegmentsOfPlaylist($playlist, $prebuffer = 0)
	{
		if (file_exists($playlist)) {
			$source = file_get_contents($playlist);

			if (preg_match_all("/(.*?).ts/", $source, $matches)) {
				if (0 < $prebuffer) {
					$total_segs = intval($prebuffer / 10);
					return array_slice($matches[0], -$total_segs);
				}

				return $matches[0];
			}
		}

		return false;
	}

	static public function GeneratePlayListWithAuthentication($m3u8_playlist, $username = "", $password = "", $streamID)
	{
		if (file_exists($m3u8_playlist)) {
			$source = file_get_contents($m3u8_playlist);

			if (preg_match_all("/(.*?)\.ts/", $source, $matches)) {
				foreach ($matches[0] as $match ) {
					$source = str_replace($match, "http://{$_SERVER["HTTP_HOST"]}{$_SERVER["SCRIPT_NAME"]}?extension=m3u8&username=$username&password=$password&stream=$streamID&type=hls&segment=$match", $source);
				}

				return $source;
			}

			return false;
		}
	}

	static public function CheckGlobalBlockUA($user_agent)
	{
		$user_agent = self::$ipTV_db->escape($user_agent);
		self::$ipTV_db->simple_query("SELECT * FROM `blocked_user_agents` WHERE (exact_match = 1 AND user_agent = '$user_agent') OR (exact_match = 0 AND INSTR('$user_agent',user_agent) > 0)");

		if (0 < self::$ipTV_db->num_rows()) {
			$info = self::$ipTV_db->get_row();
			self::$ipTV_db->query("UPDATE `blocked_user_agents` SET `attempts_blocked` = `attempts_blocked`+1 WHERE `id` = '%d'", $info["id"]);
			exit();
		}
	}

	static public function ps_running($pid, $exe)
	{
		if (empty($pid)) {
			return false;
		}

		if (file_exists("/proc/" . $pid) && is_readable("/proc/" . $pid . "/exe") && (basename(readlink("/proc/" . $pid . "/exe")) == basename($exe))) {
			return true;
		}

		return false;
	}

	static public function ShowVideo($is_restreamer = 0, $video_id_setting, $video_path_id)
	{
		if (($is_restreamer == 0) && (ipTV_lib::$settings[$video_id_setting] == 1)) {
			header("Content-Type: video/mp2t");
			readfile(ipTV_lib::$settings[$video_path_id]);
		}

		exit();
	}

	static public function CloseConnection($activity_id)
	{
		self::$ipTV_db->query("SELECT * FROM `user_activity_now` WHERE `activity_id` = '%d'", $activity_id);

		if (0 < self::$ipTV_db->num_rows()) {
			$info = self::$ipTV_db->get_row();

			if (!is_null($info["pid"])) {
				Servers::RunCommandServer($info["server_id"], "kill -9 " . $info["pid"]);
				self::CloseAndTransfer($activity_id);
			}
		}
	}

	static public function CloseAndTransfer($activity_id)
	{
		if (empty($activity_id)) {
			return false;
		}

		if (!is_array($activity_id)) {
			$activity_id = array(intval($activity_id));
		}

		foreach ($activity_id as $id ) {
			self::$ipTV_db->query("INSERT INTO `user_activity` SELECT NULL,`user_id`,`stream_id`,`server_id`,`user_agent`,`user_ip`,`container`,NULL,`date_start`,'" . time() . "',`geoip_country_code`,`isp`,`external_device`,`divergence`,NULL,NULL FROM `user_activity_now` WHERE `activity_id` = '%d'", $id);
			self::$ipTV_db->query("DELETE FROM `user_activity_now` WHERE `activity_id` = '%d'", $id);
		}
	}

	static public function CloseAllConnectionsByUser($user_id)
	{
		self::$ipTV_db->query("SELECT * FROM `user_activity_now` WHERE `user_id` = '%d'", $user_id);

		if (0 < self::$ipTV_db->num_rows()) {
			$rows = self::$ipTV_db->get_rows();
			$activities = array();
			$ids = array();

			foreach ($rows as $row ) {
				if (empty($activities[$row["server_id"]])) {
					$activities[$row["server_id"]] = array();
				}

				$activities[$row["server_id"]][] = $row["pid"];
				$ids[] = $row["activity_id"];
			}

			foreach ($activities as $server_id => $pid ) {
				$command = "kill -9 " . implode(" ", $pid);
				Servers::RunCommandServer($server_id, $command);
			}

			self::CloseAndTransfer($ids);
		}
	}

	static public function CloseAllConnectionsByServer($server_id)
	{
		self::$ipTV_db->query("SELECT * FROM `user_activity_now` WHERE `server_id` = '%d'", $server_id);

		if (0 < self::$ipTV_db->num_rows()) {
			$rows = self::$ipTV_db->get_rows();
			$pids = array();
			$ids = array();

			foreach ($rows as $row ) {
				$pids[] = $row["pid"];
				$ids[] = $row["activity_id"];
			}

			$command = "kill -9 " . implode(" ", $pids);
			Servers::RunCommandServer($server_id, $command);
			self::CloseAndTransfer($ids);
		}
	}

	static public function IsValidStream($playlist, $pid)
	{
		return self::ps_running($pid, FFMPEG_PATH) && file_exists($playlist);
	}

	static public function getUserIP()
	{
		foreach (array("REMOTE_ADDR", "HTTP_INCAP_CLIENT_IP", "HTTP_CF_CONNECTING_IP", "HTTP_CLIENT_IP", "HTTP_X_FORWARDED_FOR", "HTTP_X_FORWARDED", "HTTP_X_CLUSTER_CLIENT_IP", "HTTP_FORWARDED_FOR", "HTTP_FORWARDED") as $key ) {
			if (array_key_exists($key, $_SERVER) === true) {
				foreach (explode(",", $_SERVER[$key]) as $IPaddress ) {
					$IPaddress = trim($IPaddress);

					if (filter_var($IPaddress, FILTER_VALIDATE_IP) !== false) {
						return $IPaddress;
					}
				}
			}
		}
	}

	static public function GetStreamBitrate($type, $path, $force_duration = NULL)
	{
		$birrate = 0;

		if (!file_exists($path)) {
			return $bitrate;
		}

		switch ($type) {
		case "movie":
			if (!is_null($force_duration)) {
				sscanf($force_duration, "%d:%d:%d", $hours, $minutes, $seconds);
				$time_seconds = (isset($seconds) ? ($hours * 3600) + ($minutes * 60) + $seconds : ($hours * 60) + $minutes);
				$bitrate = round((filesize($path) * 0.0080000000000000002) / $time_seconds);
			}

			break;

		case "live":
			$fp = fopen($path, "r");
			$bitrates = array();

			while (!feof($fp)) {
				$line = trim(fgets($fp));

				if (stristr($line, "EXTINF")) {
					list($trash, $seconds) = explode(":", $line);
					$seconds = rtrim($seconds, ",");
					$segment_file = trim(fgets($fp));

					if (!file_exists(dirname($path) . "/" . $segment_file)) {
						break;
					}

					$segment_size_in_kilobits = filesize(dirname($path) . "/" . $segment_file) * 0.0080000000000000002;
					$bitrates[] = $segment_size_in_kilobits / $seconds;

					if (count($bitrates) == ipTV_lib::$settings["client_prebuffer"] / 2) {
						break;
					}
				}
			}

			fclose($fp);
			$bitrate = (0 < count($bitrates) ? round(array_sum($bitrates) / count($bitrates)) : 0);
			break;
		}

		return $bitrate;
	}
}


?>
