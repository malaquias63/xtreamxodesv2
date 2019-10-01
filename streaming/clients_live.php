<?php

function shutdown()
{
	global $ipTV_db;
	global $activity_id;
	global $close_connection;
	global $connection_speed_file;
	global $user_info;
	global $extension;
	if (($activity_id != 0) && $close_connection) {
		$ipTV_db->db_connect();
		ipTV_Stream::CloseAndTransfer($activity_id);

		if (file_exists($connection_speed_file)) {
			unlink($connection_speed_file);
		}
	}

	fastcgi_finish_request();

	if ($extension != "m3u8") {
		posix_kill(getmypid(), 9);
	}
}

register_shutdown_function("shutdown");
set_time_limit(0);
require "../init.php";

if (isset(ipTV_lib::$request["qs"])) {
	if (stristr(ipTV_lib::$request["qs"], ":p=")) {
		$d = explode(":p=", ipTV_lib::$request["qs"]);
		ipTV_lib::$request["password"] = $d[1];
		iptv_lib::$request["username"] = substr($d[0], 2);
	}
}

if (!isset(ipTV_lib::$request["extension"]) || !isset(ipTV_lib::$request["username"]) || !isset(ipTV_lib::$request["password"]) || !isset(ipTV_lib::$request["stream"])) {
	exit("Missing parameters.");
}

$activity_id = 0;
$close_connection = true;
$connection_speed_file = NULL;
$user_ip = ipTV_Stream::getUserIP();
$user_agent = (empty($_SERVER["HTTP_USER_AGENT"]) ? "" : trim($_SERVER["HTTP_USER_AGENT"]));
$external_device = NULL;
ipTV_Stream::CheckGlobalBlockUA($user_agent);
$username = ipTV_lib::$request["username"];
$password = ipTV_lib::$request["password"];
$stream_id = intval(ipTV_lib::$request["stream"]);
$extension = ipTV_lib::$request["extension"];

if (ipTV_lib::$settings["nginx_buffering"] == 0) {
	header("X-Accel-Buffering: no");
}

header("Access-Control-Allow-Origin: *");
$api_isp_desc = $api_as_number = "";

if ($user_info = ipTV_Stream::GetUserInfo(NULL, $username, $password, true, false, true)) {
	if ($user_info["is_stalker"] == 1) {
		if (empty(ipTV_lib::$request["stalker_key"]) || ($extension != "ts")) {
			exit();
		}

		$stalker_key = base64_decode(urldecode(ipTV_lib::$request["stalker_key"]));

		if ($decrypt_key = ipTV_lib::mc_decrypt($stalker_key, md5(ipTV_lib::$settings["live_streaming_pass"]))) {
			$stalker_data = explode("=", $decrypt_key);

			if ($stalker_data[2] != $stream_id) {
				ipTV_Stream::ClientLog($stream_id, $user_info["id"], "STALKER_CHANNEL_MISMATCH", $user_ip);
				exit();
			}

			if ($stalker_data[1] != $user_ip) {
				ipTV_Stream::ClientLog($stream_id, $user_info["id"], "STALKER_IP_MISMATCH", $user_ip);
				exit();
			}

			if ($stalker_data[3] < time()) {
				ipTV_Stream::ClientLog($stream_id, $user_info["id"], "STALKER_KEY_EXPIRED", $user_ip);
				exit();
			}

			$external_device = $stalker_data[0];
		}
		else {
			ipTV_Stream::ClientLog($stream_id, $user_info["id"], "STALKER_DECRYPT_FAILED", $user_ip);
			exit();
		}
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

	if (!array_key_exists($extension, $user_info["output_formats"])) {
		ipTV_Stream::ClientLog($stream_id, $user_info["id"], "USER_DISALLOW_EXT", $user_ip);
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
		if (!empty($user_info["pair_line_info"])) {
			if ($user_info["pair_line_info"]["max_connections"] != 0) {
				if (($user_info["pair_line_info"]["max_connections"] <= $user_info["pair_line_info"]["active_cons"]) && ($extension != "m3u8")) {
					ipTV_Stream::CloseLastCon($user_info["pair_id"]);
				}
			}
		}

		if (($user_info["max_connections"] <= $user_info["active_cons"]) && ($extension != "m3u8")) {
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

	ipTV_Stream::Redirect($user_info, $user_ip, $user_country_code, $external_device, "live");

	if ($channel_info = ipTV_Stream::CanServerStream(SERVER_ID, $stream_id, "live", $extension)) {
		$playlist = STREAMS_PATH . $stream_id . "_.m3u8";

		if (!ipTV_Stream::IsValidStream($playlist, $channel_info["pid"])) {
			ipTV_Stream::ShowVideo($user_info["is_restreamer"], "show_not_on_air_video", "not_on_air_video_path");
		}

		switch ($extension) {
		case "m3u8":
			$cache_file = TMP_DIR . $user_info["id"] . "_hls.cache";

			if (empty(ipTV_lib::$request["segment"])) {
				if ($source = ipTV_Stream::GeneratePlayListWithAuthentication($playlist, $username, $password, $stream_id)) {
					if (!file_exists($cache_file)) {
						file_put_contents($cache_file, json_encode(array("stream_id" => $stream_id, "segment_number" => NULL, "time" => time(), "activity_id" => NULL)));
					}
					else {
						$cache = json_decode(file_get_contents($cache_file), true);
						if (($cache["stream_id"] != $stream_id) || ((ipTV_lib::$SegmentsSettings["seg_time"] * 2) <= time() - $cache["time"])) {
							file_put_contents($cache_file, json_encode(array("stream_id" => $stream_id, "segment_number" => NULL, "time" => time(), "activity_id" => NULL)));

							if ($user_info["max_connections"] != 0) {
								$ipTV_db->query("SELECT activity_id FROM `user_activity_now` WHERE `user_id` = '%d' AND`container` = 'hls'", $user_info["id"]);
								$kill_activities_ids = array();

								foreach ($ipTV_db->get_rows() as $hls_connection ) {
									$kill_activities_ids[] = $hls_connection["activity_id"];
								}

								ipTV_Stream::CloseAndTransfer($kill_activities_ids);
							}
						}
					}

					header("Content-Type: application/x-mpegurl");
					header("Content-Length: " . strlen($source));
					echo $source;
				}

				exit();
			}
			else {
				$segment = STREAMS_PATH . str_replace(array("\\", "/"), "", urldecode(ipTV_lib::$request["segment"]));
				$segments = ipTV_Stream::GetSegmentsOfPlaylist($playlist);
				$current_ts = intval(return explode("_", ipTV_lib::$request["segment"])[1]);
				$activity_id = false;
				if (empty($segments) || !file_exists($segment) || !file_exists($cache_file)) {
					header($_SERVER["SERVER_PROTOCOL"] . " 406 Not Acceptable", true, 406);
					exit();
				}

				if ($user_info["max_connections"] != 0) {
					$cache = json_decode(file_get_contents($cache_file), true);

					if ($cache["stream_id"] != $stream_id) {
						header($_SERVER["SERVER_PROTOCOL"] . " 406 Not Acceptable", true, 406);
						exit();
					}
				}

				if ($user_info["max_connections"] != 0) {
					if (!empty($cache["activity_id"])) {
						$activity_id = $cache["activity_id"];
					}

					if (!empty($cache["segment_number"]) && (($cache["segment_number"] + 1) != $current_ts) && ($cache["segment_number"] != $current_ts)) {
						header($_SERVER["SERVER_PROTOCOL"] . " 406 Not Acceptable", true, 406);
						exit();
					}
				}
				else {
					$ipTV_db->query("SELECT activity_id FROM `user_activity_now` WHERE `user_id` = '%d' AND `stream_id` = '%d' AND `container` = 'hls' AND server_id = '%d'", $user_info["id"], $stream_id, SERVER_ID);

					if (0 < $ipTV_db->num_rows()) {
						$activity_id = $ipTV_db->get_col();
					}
				}

				if (!empty($activity_id)) {
					if ($user_info["max_connections"] != 0) {
						$ipTV_db->query("UPDATE `user_activity_now` SET `last_ts` = '%d',`last_ts_read` = '%d' WHERE `last_ts` = '%d' AND `activity_id` = '%d'", $current_ts, time(), $current_ts - 1, $activity_id);

						if ($ipTV_db->affected_rows() == 0) {
							header($_SERVER["SERVER_PROTOCOL"] . " 406 Not Acceptable", true, 406);
							exit();
						}
					}
					else {
						$ipTV_db->query("UPDATE `user_activity_now` SET `last_ts` = '%d',`last_ts_read` = '%d' WHERE `activity_id` = '%d'", $current_ts, time(), $activity_id);
					}
				}
				else {
					if ($user_info["max_connections"] != 0) {
						if (end($segments) == basename($segment)) {
							header($_SERVER["SERVER_PROTOCOL"] . " 406 Not Acceptable", true, 406);
							exit();
						}
					}

					$ipTV_db->query("INSERT INTO `user_activity_now` (`user_id`,`stream_id`,`server_id`,`user_agent`,`user_ip`,`container`,`pid`,`date_start`,`geoip_country_code`,`isp`,`last_ts_read`,`last_ts`) VALUES('%d','%d','%d','%s','%s','%s','%d','%d','%s','%s','%d','%d')", $user_info["id"], $stream_id, SERVER_ID, $user_agent, $user_ip, "hls", getmypid(), time(), $user_country_code, $api_isp_desc, time(), $current_ts);
					$activity_id = $ipTV_db->last_insert_id();
				}

				file_put_contents($cache_file, json_encode(array("stream_id" => $stream_id, "segment_number" => $current_ts, "time" => time(), "activity_id" => $activity_id)));
				$ipTV_db->close_mysql();
				$connection_speed_file = TMP_DIR . $activity_id . ".con";
				$bytes = filesize($segment);
				header("Content-Length: " . $bytes);
				header("Content-Type: video/mp2t");
				$time_start = time();
				readfile($segment);
				$time_end = time();
				$close_connection = false;
				$total_time = $time_end - $time_start;

				if ($total_time <= 0) {
					$speed = intval($bytes / 1024);
				}
				else {
					$speed = intval($bytes / $total_time / 1024);
				}

				file_put_contents($connection_speed_file, $speed);
			}

			break;

		default:
			if (file_exists($playlist)) {
				if (($user_info["max_connections"] == 0) || ($user_info["active_cons"] < $user_info["max_connections"])) {
					$ipTV_db->query("INSERT INTO `user_activity_now` (`user_id`,`stream_id`,`server_id`,`user_agent`,`user_ip`,`container`,`pid`,`date_start`,`geoip_country_code`,`isp`,`external_device`) VALUES('%d','%d','%d','%s','%s','%s','%d','%d','%s','%s','%s')", $user_info["id"], $stream_id, SERVER_ID, $user_agent, $user_ip, $extension, getmypid(), time(), $user_country_code, $api_isp_desc, $external_device);
					$activity_id = $ipTV_db->last_insert_id();
					$connection_speed_file = TMP_DIR . $activity_id . ".con";
					$ipTV_db->close_mysql();

					if ($channel_info["type"] == 1) {
						header("Content-Type: video/mp2t");
					}
					else {
						header("Content-Type: audio/ogg");
					}

					ob_end_flush();

					if (ipTV_Stream::IsValidStream($playlist, $channel_info["pid"])) {
						$total_failed_tries = ipTV_lib::$SegmentsSettings["seg_time"] * 2;

						if ($segments = ipTV_Stream::GetSegmentsOfPlaylist($playlist, ipTV_lib::$settings["client_prebuffer"])) {
							if ($user_info["is_restreamer"] == 0) {
								$bytes = 0;
								$start_time = time();

								foreach ($segments as $segment ) {
									if (file_exists(STREAMS_PATH . $segment)) {
										$bytes += readfile(STREAMS_PATH . $segment);
									}
									else {
										exit();
									}
								}

								$end_time = time();
								$total_time = $end_time - $start_time;
								file_put_contents($connection_speed_file, intval($bytes / $total_time / 1024));
							}

							$last_segment = array_pop($segments);
							preg_match("/_(.*)\./", $last_segment, $current_segment);
							$current = $current_segment[1];
							$fails = 0;
							$segments_counter = 0;

							while ($fails <= $total_failed_tries) {
								$segment_file = sprintf("%d_%d.ts", $channel_info["id"], $current + 1);
								$nextsegment_file = sprintf("%d_%d.ts", $channel_info["id"], $current + 2);

								if (!file_exists(STREAMS_PATH . $segment_file)) {
									sleep(1);
									$fails++;
									continue;
								}

								$fails = 0;
								$time_start = time();
								$fp = fopen(STREAMS_PATH . $segment_file, "r");
								while (($fails <= $total_failed_tries) && !file_exists(STREAMS_PATH . $nextsegment_file)) {
									$data = stream_get_line($fp, 4096);

									if (empty($data)) {
										sleep(1);
										++$fails;
										continue;
									}

									echo $data;
									$fails = 0;
								}

								$size = filesize(STREAMS_PATH . $segment_file);
								echo stream_get_line($fp, $size - ftell($fp));
								fclose($fp);
								$time_end = time();
								file_put_contents($connection_speed_file, intval($size / 1024 / ($time_end - $time_start)));
								$fails = 0;
								$current++;
							}
						}
					}
				}
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
