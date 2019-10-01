<?php

function shutdown()
{
	global $ipTV_db;
	global $bytes;
	global $activity_id;
	global $connection_speed_file;
	global $user_info;

	if ($activity_id != 0) {
		$ipTV_db->db_connect();
		ipTV_Stream::CloseAndTransfer($activity_id);
	}

	if (file_exists($connection_speed_file)) {
		unlink($connection_speed_file);
	}

	fastcgi_finish_request();
}

register_shutdown_function("shutdown");
set_time_limit(0);
require "../init.php";
if (!isset(ipTV_lib::$request["username"]) || !isset(ipTV_lib::$request["password"]) || !isset(ipTV_lib::$request["stream"])) {
	exit("Missing parameters.");
}

$bytes = 0;
$activity_id = 0;
$connection_speed_file = NULL;
$user_ip = ipTV_Stream::getUserIP();
$user_agent = (empty($_SERVER["HTTP_USER_AGENT"]) ? "" : trim($_SERVER["HTTP_USER_AGENT"]));
ipTV_Stream::CheckGlobalBlockUA($user_agent);
$username = ipTV_lib::$request["username"];
$password = ipTV_lib::$request["password"];
$stream = pathinfo(ipTV_lib::$request["stream"]);
$stream_id = intval($stream["filename"]);
$extension = $stream["extension"];
header("X-Accel-Buffering: no");
header("Access-Control-Allow-Origin: *");
$api_isp_desc = $api_as_number = "";

if ($user_info = ipTV_Stream::GetUserInfo(NULL, $username, $password, true, false, true)) {
	if ($user_info["is_stalker"] == 1) {
		exit();
	}

	if (empty($user_agent) && (ipTV_lib::$settings["disallow_empty_user_agents"] == 1)) {
		ipTV_Stream::ClientLog($stream_id, $user_info["id"], "EMPTY_UA", $user_ip);
		exit();
	}

	$user_country_code = geoip_country_code_by_name($user_ip);
	if (!empty($user_info["allowed_ips"]) && !in_array($user_ip, array_map("gethostbyname", $user_info["allowed_ips"]))) {
		ipTV_Stream::ClientLog($stream_id, $user_info["id"], "IP_BAN", $user_ip);
		exit();
	}

	if (!empty($user_country_code)) {
		$force_country = (!empty($user_info["forced_country"]) ? true : false);
		if ($force_country && ($user_country_code != $user_info["forced_country"])) {
			ipTV_Stream::ClientLog($stream_id, $user_info["id"], "COUNTRY_DISALLOW");
			exit();
		}

		if (!$force_country && !in_array($user_country_code, ipTV_lib::$settings["allow_countries"])) {
			ipTV_Stream::ClientLog($stream_id, $user_info["id"], "COUNTRY_DISALLOW");
			exit();
		}
	}

	if (!empty($user_info["allowed_ua"]) && !in_array($user_agent, $user_info["allowed_ua"])) {
		ipTV_Stream::ClientLog($stream_id, $user_info["id"], "USER_AGENT_BAN", $user_ip);
		exit();
	}

	if (!in_array($stream_id, $user_info["channel_ids"])) {
		ipTV_Stream::ClientLog($stream_id, $user_info["id"], "NOT_IN_BOUQUET", $user_ip);
		exit();
	}

	if (!is_null($user_info["exp_date"]) && ($user_info["exp_date"] <= time())) {
		ipTV_Stream::ClientLog($stream_id, $user_info["id"], "USER_EXPIRED", $user_ip);
		ipTV_Stream::ShowVideo($user_info["is_restreamer"], "show_expired_video", "expired_video_path");
		exit();
	}

	if ($user_info["admin_enabled"] == 0) {
		ipTV_Stream::ClientLog($stream_id, $user_info["id"], "USER_BAN", $user_ip);
		ipTV_Stream::ShowVideo($user_info["is_restreamer"], "show_banned_video", "banned_video_path");
		exit();
	}

	if ($user_info["enabled"] == 0) {
		ipTV_Stream::ClientLog($stream_id, $user_info["id"], "USER_DISABLED", $user_ip);
		ipTV_Stream::ShowVideo($user_info["is_restreamer"], "show_banned_video", "banned_video_path");
		exit();
	}

	if ($user_info["max_connections"] != 0) {
		if ($user_info["pair_line_info"]["max_connections"] != 0) {
			if ($user_info["pair_line_info"]["max_connections"] <= $user_info["pair_line_info"]["active_cons"]) {
				ipTV_Stream::CloseLastCon($user_info["pair_id"]);
			}
		}

		if ($user_info["max_connections"] <= $user_info["active_cons"]) {
			if (ipTV_Stream::CloseLastCon($user_info["id"])) {
				$user_info["active_cons"] -= 1;
			}
		}
	}

	if (ipTV_lib::$settings["reshare_deny_addon"] == 1) {
		$cache = TMP_DIR . md5($user_ip);
		if (file_exists($cache) && ((time() - filemtime($cache)) < 300)) {
			$data = json_decode(file_get_contents(TMP_DIR . md5($user_ip)), true);
			$api_as_number = $data["as_number"];
			$api_isp_desc = $data["isp_desc"];
			$is_server = $data["is_server"];
			if (($is_server == 1) && ($user_info["is_restreamer"] != 1)) {
				ipTV_Stream::ClientLog($stream_id, $user_info["id"], "CON_SVP", $user_ip, $api_isp_desc);

				if (ipTV_lib::$settings["block_svp"] == 1) {
					exit();
				}
			}

			if (($user_info["is_stalker"] == 0) && (($api_as_number != $user_info["as_number"]) || ($user_info["isp_desc"] != $api_isp_desc))) {
				$ipTV_db->query("UPDATE `users` SET `as_number` = '%s',`isp_desc` = '%s' WHERE `id` = '%d'", $api_as_number, $api_isp_desc, $user_info["id"]);
			}
		}
		else {
			$ctx = stream_context_create(array(
	"http" => array("timeout" => 1)
	));
			$isp_lock = json_decode(@file_get_contents("http://api.xtream-codes.com/api.php?key=1&ip=$user_ip&user_agent=" . base64_encode($user_agent), false, $ctx), true);

			if ($isp_lock !== false) {
				$api_as_number = (!empty($isp_lock["isp_info"]["as_number"]) ? trim($isp_lock["isp_info"]["as_number"]) : "");
				$api_isp_desc = (!empty($isp_lock["isp_info"]["description"]) ? trim($isp_lock["isp_info"]["description"]) : "");
				if (!empty($api_as_number) && !empty($api_isp_desc)) {
					$output = array();
					$output["as_number"] = $api_as_number;
					$output["isp_desc"] = $api_isp_desc;
					$output["is_server"] = $isp_lock["isp_info"]["is_server"];
					file_put_contents(TMP_DIR . md5($user_ip), json_encode($output));
					if (($isp_lock["isp_info"]["is_server"] == 1) && ($user_info["is_restreamer"] != 1)) {
						ipTV_Stream::ClientLog($stream_id, $user_info["id"], "CON_SVP", $user_ip, $api_isp_desc);

						if (ipTV_lib::$settings["block_svp"] == 1) {
							exit();
						}
					}

					if ($user_info["is_isplock"] == 1) {
						if (!empty($user_info["as_number"]) && ($api_as_number != $user_info["as_number"])) {
							if (!empty($user_info["isp_desc"]) && ($api_isp_desc != $user_info["isp_desc"])) {
								ipTV_Stream::ClientLog($stream_id, $user_info["id"], "ISP_LOCK_FAILED", $user_ip, $api_isp_desc);
								exit();
							}
						}
					}

					if (($user_info["is_stalker"] == 0) && (($api_as_number != $user_info["as_number"]) || ($user_info["isp_desc"] != $api_isp_desc))) {
						$ipTV_db->query("UPDATE `users` SET `as_number` = '%s',`isp_desc` = '%s' WHERE `id` = '%d'", $api_as_number, $api_isp_desc, $user_info["id"]);
					}
				}
			}
		}
	}

	ipTV_Stream::Redirect($user_info, $user_ip, $user_country_code, "", "movie");

	if ($channel_info = ipTV_Stream::CanServerStream(SERVER_ID, $stream_id, "movie", $extension)) {
		if (($user_info["max_connections"] == 0) || ($user_info["active_cons"] < $user_info["max_connections"])) {
			$ipTV_db->query("INSERT INTO `user_activity_now` (`user_id`,`stream_id`,`server_id`,`user_agent`,`user_ip`,`container`,`pid`,`date_start`,`geoip_country_code`,`isp`) VALUES('%d','%d','%d','%s','%s','%s','%d','%d','%s','%s')", $user_info["id"], $stream_id, SERVER_ID, $user_agent, $user_ip, "movie", getmypid(), time(), $user_country_code, $api_isp_desc);
			$activity_id = $ipTV_db->last_insert_id();
			$connection_speed_file = TMP_DIR . $activity_id . ".con";
			$ipTV_db->close_mysql();

			if (!empty($channel_info["container_header"])) {
				header("Content-type: " . $channel_info["container_header"]);
			}
			else {
				header("Content-Type: application/octet-stream");
			}

			$request = MOVIES_PATH . $stream_id . "." . $extension;

			if (file_exists($request)) {
				$fp = @fopen($request, "rb");
				$size = filesize($request);
				$length = $size;
				$start = 0;
				$end = $size - 1;
				header("Accept-Ranges: 0-$length");

				if (isset($_SERVER["HTTP_RANGE"])) {
					$c_start = $start;
					$c_end = $end;
					list(, $range) = explode("=", $_SERVER["HTTP_RANGE"], 2);

					if (strpos($range, ",") !== false) {
						header("HTTP/1.1 416 Requested Range Not Satisfiable");
						header("Content-Range: bytes $start-$end/$size");
						exit();
					}

					if ($range == "-") {
						$c_start = $size - substr($range, 1);
					}
					else {
						$range = explode("-", $range);
						$c_start = $range[0];
						$c_end = (isset($range[1]) && is_numeric($range[1]) ? $range[1] : $size);
					}

					$c_end = ($end < $c_end ? $end : $c_end);
					if (($c_end < $c_start) || (($size - 1) < $c_start) || ($size <= $c_end)) {
						header("HTTP/1.1 416 Requested Range Not Satisfiable");
						header("Content-Range: bytes $start-$end/$size");
						exit();
					}

					$start = $c_start;
					$end = $c_end;
					$length = ($end - $start) + 1;
					fseek($fp, $start);
					header("HTTP/1.1 206 Partial Content");
				}

				header("Content-Range: bytes $start-$end/$size");
				header("Content-Length: " . $length);
				ob_end_flush();
				$buffer = 1024 * 8;
				$time_start = time();
				$bytes_read = 0;
				while (!feof($fp) && (($p = ftell($fp)) <= $end)) {
					$response = stream_get_line($fp, $buffer);
					echo $response;
					$bytes_read += strlen($response);

					if (30 <= time() - $time_start) {
						file_put_contents($connection_speed_file, intval($bytes_read / 1024 / 30));
						$time_start = time();
						$bytes_read = 0;
					}
				}

				fclose($fp);
				exit();
			}
		}
	}
}
else {
	ipTV_Stream::ClientLog($stream_id, 0, "AUTH_FAILED", $user_ip);

	if (ipTV_lib::$settings["streaming_block"] == 1) {
		BlockIP($_SERVER["REMOTE_ADDR"], "FAILED AUTH");
	}
}

?>
