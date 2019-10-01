<?php

function shutdown()
{
	fastcgi_finish_request();
	posix_kill(getmypid(), 9);
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

if (empty(ipTV_lib::$request["stream"]) || empty(ipTV_lib::$request["password"]) || (ipTV_lib::$settings["live_streaming_pass"] != ipTV_lib::$request["password"])) {
	exit();
}

$stream = pathinfo(ipTV_lib::$request["stream"]);
$stream_id = intval($stream["filename"]);
$extension = $stream["extension"];
$ipTV_db->query("\r\n                    SELECT t1.*,t3.*\r\n                    FROM `streams` t1\r\n                    INNER JOIN `streams_sys` t2 ON t2.stream_id = t1.id AND t2.pid IS NOT NULL AND t2.server_id = '%d'\r\n                    INNER JOIN `streams_types` t4 ON t4.type_id = t1.type AND t4.type_key = 'movie'\r\n                    INNER JOIN `movie_containers` t3 ON t3.container_id = t1.target_container_id AND t3.container_extension = '%s'\r\n                    WHERE t1.`id` = '%d'", SERVER_ID, $extension, $stream_id);
header("X-Accel-Buffering: no");

if (0 < $ipTV_db->num_rows()) {
	$info = $ipTV_db->get_row();
	$ipTV_db->close_mysql();
	$request = MOVIES_PATH . $stream_id . "." . $extension;

	if (file_exists($request)) {
		if (!empty($info["container_header"])) {
			header("Content-type: " . $info["container_header"]);
		}
		else {
			header("Content-Type: application/octet-stream");
		}

		ob_end_flush();
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
		$buffer = 1024 * 8;
		while (!feof($fp) && (($p = ftell($fp)) <= $end)) {
			$response = stream_get_line($fp, $buffer);
			echo $response;
		}

		fclose($fp);
		exit();
	}
}

?>
