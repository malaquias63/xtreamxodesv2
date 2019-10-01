<?php

function shutdown()
{
	global $ipTV_db;
	fastcgi_finish_request();
}

register_shutdown_function("shutdown");
set_time_limit(0);
require "../init.php";
$access = false;
$bytes = 0;
$activity_id = 0;
$user_ip = ipTV_Stream::getUserIP();

if (!in_array($user_ip, ipTV_Stream::getAllowedIPsAdmin())) {
	exit();
}

if (empty(ipTV_lib::$request["stream"]) || empty(ipTV_lib::$request["extension"]) || empty(ipTV_lib::$request["password"]) || (ipTV_lib::$settings["live_streaming_pass"] != ipTV_lib::$request["password"])) {
	exit();
}

$password = ipTV_lib::$settings["live_streaming_pass"];
$stream_id = intval(ipTV_lib::$request["stream"]);
$extension = ipTV_lib::$request["extension"];
$ipTV_db->query("\r\n                    SELECT * \r\n                    FROM `streams` t1\r\n                    INNER JOIN `streams_sys` t2 ON t2.stream_id = t1.id AND t2.server_id = '%d'\r\n                    INNER JOIN `streams_types` t4 ON t4.type_id = t1.type AND t4.type_output = 'live'\r\n                    WHERE t1.`id` = '%d' AND t2.stream_status = 0", SERVER_ID, $stream_id);
header("X-Accel-Buffering: no");

if (0 < $ipTV_db->num_rows()) {
	$channel_info = $ipTV_db->get_row();
	$playlist = STREAMS_PATH . $stream_id . "_.m3u8";

	switch ($extension) {
	case "m3u8":
		if (ipTV_Stream::IsValidStream($playlist, $channel_info["pid"])) {
			if (empty(ipTV_lib::$request["segment"])) {
				if ($source = ipTV_Stream::GeneratePlayListWithAuthentication($playlist, "", $password, $stream_id)) {
					header("Content-Type: application/vnd.apple.mpegurl");
					header("Content-Length: " . strlen($source));
					ob_end_flush();
					echo $source;
				}
			}
			else {
				$segment = STREAMS_PATH . str_replace(array("\\", "/"), "", urldecode(ipTV_lib::$request["segment"]));

				if (file_exists($segment)) {
					$bytes = filesize($segment);
					header("Content-Length: " . $bytes);
					header("Content-Type: video/mp2t");
					ob_end_flush();
					$ipTV_db->close_mysql();
					readfile($segment);
				}
			}
		}

		break;

	default:
		$ipTV_db->close_mysql();
		$waited = 0;

		do {
			sleep(1);
			$waited++;
			$check_running = intval(shell_exec("ps ux | grep {$stream_id}_.m3u8 | grep -v grep | wc -l"));
		} while (($check_running == 0) && ($waited < 5));

		if ($check_running == 0) {
			exit();
		}

		if ($channel_info["type"] == 1) {
			header("Content-Type: video/mp2t");
		}
		else {
			header("Content-Type: audio/ogg");
		}

		$total_failed_tries = ipTV_lib::$SegmentsSettings["seg_time"] * 2;
		$last_segment = basename(trim(shell_exec("ls -p -t " . STREAMS_PATH . "{$stream_id}_*.ts | head -1")));
		preg_match("/_(.*)\./", $last_segment, $current_segment);
		$current = $current_segment[1] - 1;

		while ($fails <= $total_failed_tries) {
			$segment_file = sprintf("%d_%d.ts", $channel_info["id"], $current + 1);
			$nextsegment_file = sprintf("%d_%d.ts", $channel_info["id"], $current + 2);

			if (!file_exists(STREAMS_PATH . $segment_file)) {
				sleep(1);
				$fails++;
				continue;
			}

			$fails = 0;
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

			echo stream_get_line($fp, filesize(STREAMS_PATH . $segment_file) - ftell($fp));
			fclose($fp);
			$fails = 0;
			$current++;
		}
	}
}

?>
