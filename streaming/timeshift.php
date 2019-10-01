<?php

function get_next_file($file)
{
	$filename = basename($file);
	list($date, $hour_str) = explode(":", $filename);
	list($hour, $junk) = explode(".", $hour_str);
	$hour = sprintf("%02d", ++$hour);

	if (23 < $hour) {
		$next_file = date("Y-m-d", strtotime("+1 day", strtotime($date))) . ":00.ts";
	}
	else {
		$next_file = $date . ":$hour.ts";
	}

	return file_exists(dirname($file) . "/" . $next_file) ? dirname($file) . "/" . $next_file : false;
}

function file_in_current_hour($file)
{
	$filename = basename($file);
	$filename = substr($filename, 0, strpos($filename, "."));
	return $filename == date("Ymd-H");
}

function get_content_length($queue)
{
	$length = 0;

	foreach ($queue as $item ) {
		$length += $item["size"];
	}

	return $length;
}

function shutdown()
{
	global $ipTV_db;
	global $activity_id;
	global $connection_speed_file;
	global $user_info;

	if ($activity_id !== false) {
		$ipTV_db->db_connect();
		ipTV_Stream::CloseAndTransfer($activity_id);

		if (file_exists($connection_speed_file)) {
			unlink($connection_speed_file);
		}
	}

	if (!empty($user_info)) {
		usleep(mt_rand(500000, 2000000));
	}

	fastcgi_finish_request();
	posix_kill(getmypid(), 9);
}

register_shutdown_function("shutdown");
set_time_limit(0);
error_reporting(0);
require "../init.php";
$stream_id = (!empty(ipTV_lib::$request["stream_id"]) ? intval(ipTV_lib::$request["stream_id"]) : false);
$start_timestamp = (!empty(ipTV_lib::$request["start"]) ? intval(ipTV_lib::$request["start"]) : false);
$end_timestamp = (!empty(ipTV_lib::$request["end"]) ? intval(ipTV_lib::$request["end"]) : false);
$duration = $end_timestamp - $start_timestamp;
$user_info = array();
$activity_id = false;
$connection_speed_file = false;
$user_ip = ipTV_Stream::getUserIP();
$api_isp_desc = $api_as_number = "";
$user_agent = (empty($_SERVER["HTTP_USER_AGENT"]) ? "" : trim($_SERVER["HTTP_USER_AGENT"]));
if (isset(ipTV_lib::$request["username"]) && isset(ipTV_lib::$request["password"])) {
	$username = ipTV_lib::$request["username"];
	$password = ipTV_lib::$request["password"];

	if ($user_info = ipTV_Stream::GetUserInfo(NULL, $username, $password, true, false, true)) {
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
				ipTV_Stream::ClientLog($stream_id, $user_info["id"], "COUNTRY_DISALLOW", $user_ip);
				exit();
			}

			if (!$force_country && !in_array($user_country_code, ipTV_lib::$settings["allow_countries"])) {
				ipTV_Stream::ClientLog($stream_id, $user_info["id"], "COUNTRY_DISALLOW", $user_ip);
				exit();
			}
		}

		if (!empty($user_info["allowed_ua"]) && !in_array($user_agent, $user_info["allowed_ua"])) {
			ipTV_Stream::ClientLog($stream_id, $user_info["id"], "USER_AGENT_BAN", $user_ip);
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
			if (!empty($user_info["pair_line_info"])) {
				if ($user_info["pair_line_info"]["max_connections"] != 0) {
					if (($user_info["pair_line_info"]["max_connections"] <= $user_info["pair_line_info"]["active_cons"]) && ($extension != "m3u8")) {
						ipTV_Stream::CloseLastCon($user_info["pair_id"]);
					}
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
	}
	else {
		header("HTTP/1.1 400 Bad Request");
		exit();
	}
}
else if (!in_array($user_ip, ipTV_Stream::getAllowedIPsAdmin())) {
	header("HTTP/1.1 400 Bad Request");
	exit();
}

$queue = array();
$file = TV_ARCHIVE . $stream_id . "/" . date("Y-m-d:H", $start_timestamp) . ".ts";
if (empty($stream_id) || empty($start_timestamp) || empty($end_timestamp)) {
	header("HTTP/1.1 400 Bad Request");
	exit();
}

if (!file_exists($file) || !is_readable($file)) {
	header("HTTP/1.1 404 Not Found");
	exit();
}

$start_time = date("i:s", $start_timestamp);
list($i, $s) = explode(":", $start_time);
$start_time = ($i * 60) + $s;
while ((0 < $duration) && ($file !== false)) {
	if (file_in_current_hour($file)) {
		$chunk_size = (intval(date("i")) * 60) + intval(date("s"));
	}
	else {
		$chunk_size = 3600;
	}

	$filesize = filesize($file);
	$from_byte = intval(($start_time * $filesize) / $chunk_size);

	if ($chunk_size <= $duration + $start_time) {
		$to_byte = $filesize;
		$duration -= $chunk_size - $start_time;
		$start_time = 0;
	}
	else {
		$to_byte = intval((($start_time + $duration) * $filesize) / $chunk_size);
		$duration = 0;
	}

	$queue[] = array("filename" => $file, "from_byte" => $from_byte, "to_byte" => $to_byte, "size" => $to_byte - $from_byte);
	$start_time = 0;
	$file = get_next_file($file);
}

$size = get_content_length($queue);

if (isset($_SERVER["HTTP_RANGE"])) {
	list($size_unit, $range_orig) = explode("=", $_SERVER["HTTP_RANGE"], 2);

	if ($size_unit == "bytes") {
		list($range, $extra_ranges) = explode(",", $range_orig, 2);
	}
	else {
		$range = "";
	}
}
else {
	$range = "";
}

list($seek_start, $seek_end) = explode("-", $range, 2);
$seek_end = (empty($seek_end) ? $size - 1 : min(abs(intval($seek_end)), $size - 1));
$seek_start = (empty($seek_start) || ($seek_end < abs(intval($seek_start))) ? 0 : max(abs(intval($seek_start)), 0));

if (isset($_SERVER["HTTP_RANGE"])) {
	header("HTTP/1.1 206 Partial Content");
}

header("Content-Type: video/mpeg");
header("Content-Length: " . (($seek_end - $seek_start) + 1));
header("Content-Range: bytes " . $seek_start . "-" . $seek_end . "/" . $size);
$offset = 0;

if (!empty($queue)) {
	if (!empty($user_info)) {
		if (($user_info["max_connections"] == 0) || ($user_info["active_cons"] < $user_info["max_connections"])) {
			$ipTV_db->query("INSERT INTO `user_activity_now` (`user_id`,`stream_id`,`server_id`,`user_agent`,`user_ip`,`container`,`pid`,`date_start`,`geoip_country_code`,`isp`,`external_device`) VALUES('%d','%d','%d','%s','%s','%s','%d','%d','%s','%s','%s')", $user_info["id"], $stream_id, SERVER_ID, $user_agent, $user_ip, "TV Archive", getmypid(), time(), $user_country_code, $api_isp_desc, "");
			$activity_id = $ipTV_db->last_insert_id();
			$connection_speed_file = TMP_DIR . $activity_id . ".con";
			$ipTV_db->close_mysql();
		}
		else {
			exit();
		}
	}

	foreach ($queue as $item ) {
		if (($offset + $item["from_byte"] + $seek_start) <= $offset + filesize($item["filename"])) {
			$is_first_file = !isset($fp);
			$fp = fopen($item["filename"], "r");
			fseek($fp, $item["from_byte"] + $seek_start);
			if ($is_first_file && ($seek_start == 0)) {
				$skipped = 0;

				while (($char = fgetc($fp)) != "G") {
					$skipped++;
				}

				header("Content-Length: " . ((($seek_end - $seek_start) + 1) - $skipped));
				header("Content-Range: bytes " . $seek_start . "-" . ($seek_end - $skipped) . "/" . ($size - $skipped));
				fseek($fp, ftell($fp) - 1);
			}
		}
		else {
			$offset += filesize($item["filename"]);
			$seek_start -= filesize($item["filename"]) - $item["from_byte"];
			continue;
		}

		$time_start = time();
		$total_bytes_sent = 0;

		while (!feof($fp)) {
			$buf_size = 1024 * 8;
			$pos = ftell($fp);

			if ($item["to_byte"] <= $pos) {
				fclose($fp);
				break;
			}

			if ($item["to_byte"] < ($pos + $buf_size)) {
				$buf_size = $item["to_byte"] - $pos;
			}

			if (0 < $buf_size) {
				$data = fread($fp, $buf_size);
				$total_bytes_sent += strlen($data);
				echo $data;
			}

			if (10 <= time() - $time_start) {
				file_put_contents($connection_speed_file, intval($total_bytes_sent / 1024 / (time() - $time_start)));
				$time_start = time();
				$total_bytes_sent = time();
			}
		}

		if (is_resource($fp)) {
			fclose($fp);
		}

		$offset += filesize($item["filename"]);
		$seek_start = 0;
	}
}

?>
