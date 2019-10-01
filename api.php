<?php

set_time_limit(0);
require "init.php";
if (empty(ipTV_lib::$request["password"]) || (ipTV_lib::$request["password"] != ipTV_lib::$settings["live_streaming_pass"])) {
	exit();
}

$user_ip = ipTV_Stream::getUserIP();

if (!in_array($user_ip, ipTV_Stream::getAllowedIPsAdmin())) {
	exit();
}

$ipTV_db->close_mysql();
header("Access-Control-Allow-Origin: *");
header("X-Accel-Buffering: no");
$action = (!empty(ipTV_lib::$request["action"]) ? ipTV_lib::$request["action"] : "");

switch ($action) {
case "FFprobe":
	if (!empty(ipTV_lib::$request["ffprobe"])) {
		shell_exec("rm -f " . TMP_DIR . "*.ffprobe");
		shell_exec("pkill -9 ffprobe");
		$output = array();
		$ffprobe_commands = ipTV_lib::$request["ffprobe"];

		foreach ($ffprobe_commands as $stream_id => $stream_array ) {
			if (empty($stream_array["SourceURL"])) {
				continue;
			}

			if (0 < $stream_array["parent"]) {
				continue;
			}

			shell_exec("(" . $stream_array["cmd"] . " ) > " . TMP_DIR . $stream_id . ".ffprobe 2>/dev/null &");
		}

		$time_wait = 0;

		do {
			sleep(1);
			$time_wait += 1;
			$check_ffprobe = intval(shell_exec("ps ux | grep ffprobe | grep -v grep | wc -l"));
		} while (($check_ffprobe != 0) && ($time_wait < 40));

		foreach (array_keys($ffprobe_commands) as $stream_id ) {
			if (file_exists(TMP_DIR . $stream_id . ".ffprobe")) {
				$output[$stream_id] = array("ffprobe_output" => json_decode(file_get_contents(TMP_DIR . $stream_id . ".ffprobe"), true), "protocol" => $ffprobe_commands[$stream_id]["protocol"], "SourceURL" => $ffprobe_commands[$stream_id]["SourceURL"], "parent" => $ffprobe_commands[$stream_id]["parent"]);
			}
			else {
				$output[$stream_id] = array(
					"protocol"       => $ffprobe_commands[$stream_id]["protocol"],
					"ffprobe_output" => array(),
					"SourceURL"      => $ffprobe_commands[$stream_id]["SourceURL"],
					"parent"         => $ffprobe_commands[$stream_id]["parent"]
					);
			}
		}

		echo json_encode($output);
		exit();
	}

	break;

case "stats":
	$json = array();
	$json["cpu"] = intval(GetTotalCPUsage());
	$json["mem"] = intval(return memory_usage()[0]["percent"]);
	$json["uptime"] = get_boottime();
	$json["total_running_streams"] = shell_exec("ps ax | grep -v grep | grep -c " . FFMPEG_PATH);
	$int = ipTV_lib::$StreamingServers[SERVER_ID]["network_interface"];
	$json["bytes_sent"] = 0;
	$json["bytes_received"] = 0;

	if (file_exists("/sys/class/net/$int/statistics/tx_bytes")) {
		$bytes_sent_old = trim(file_get_contents("/sys/class/net/$int/statistics/tx_bytes"));
		$bytes_received_old = trim(file_get_contents("/sys/class/net/$int/statistics/rx_bytes"));
		sleep(1);
		$bytes_sent_new = trim(file_get_contents("/sys/class/net/$int/statistics/tx_bytes"));
		$bytes_received_new = trim(file_get_contents("/sys/class/net/$int/statistics/rx_bytes"));
		$total_bytes_sent = round((($bytes_sent_new - $bytes_sent_old) / 1024) * 0.0078125, 2);
		$total_bytes_received = round((($bytes_received_new - $bytes_received_old) / 1024) * 0.0078125, 2);
		$json["bytes_sent"] = $total_bytes_sent;
		$json["bytes_received"] = $total_bytes_received;
	}

	echo json_encode($json);
	exit();
	break;

case "BackgroundCLI":
	if (!empty(ipTV_lib::$request["cmds"])) {
		$cmds = ipTV_lib::$request["cmds"];
		$output = array();

		foreach ($cmds as $key => $cmd ) {
			if (!is_array($cmd)) {
				$output[$key] = shell_exec($cmd);
			}
			else {
				foreach ($cmd as $k2 => $cm ) {
					$output[$key][$k2] = shell_exec($cm);
				}
			}
		}

		echo json_encode($output);
	}

	exit();
	break;

case "getDiskInfo":
	$varlib = 0;
	$xtreamcodes = 0;
	$ram_free_space = 0;
	$disk_free_space = disk_free_space("/var/lib");

	if ($disk_free_space < 1000000000) {
		$varlib = 1;
	}

	$disk_free_space = disk_free_space("/home/xtreamcodes");

	if ($disk_free_space < 1000000000) {
		$xtreamcodes = 1;
	}

	$ram_free_space = disk_free_space("/home/xtreamcodes/iptv_xtream_codes/streams");

	if ($ram_free_space < 100000000) {
		$ram_free_space = 1;
	}

	echo json_encode(array("varlib" => $varlib, "xtreamcodes" => $xtreamcodes, "ramdisk" => $ram_free_space));
	exit();
	break;

case "getCurrentTime":
	echo json_encode(time());
	break;

case "getDiff":
	if (!empty(ipTV_lib::$request["main_time"])) {
		$main_time = ipTV_lib::$request["main_time"];
		echo json_encode($main_time - time());
		exit();
	}

	break;

case "pidsAreRunning":
	if (!empty(ipTV_lib::$request["pids"]) && is_array(ipTV_lib::$request["pids"]) && !empty(ipTV_lib::$request["program"])) {
		$pids = array_map("intval", ipTV_lib::$request["pids"]);
		$exe = ipTV_lib::$request["program"];
		$output = array();

		foreach ($pids as $pid ) {
			$output[$pid] = false;
			if (file_exists("/proc/" . $pid) && is_readable("/proc/" . $pid . "/exe") && (basename(readlink("/proc/" . $pid . "/exe")) == basename($exe))) {
				$output[$pid] = true;
			}
		}

		echo json_encode($output);
		exit();
	}

	break;

case "getFile":
	if (!empty(ipTV_lib::$request["filename"])) {
		$filename = ipTV_lib::$request["filename"];
		if (file_exists($filename) && is_readable($filename)) {
			header("X-Accel-Buffering: yes");
			header("Transfer-encoding: chunked");
			header("Content-Description: File Transfer");
			header("Content-Type: application/octet-stream");
			header("Content-Length: " . filesize($filename));
			header("Content-Disposition: attachment; filename=" . basename($filename));
			ob_end_flush();
			readfile($filename);
		}

		exit();
	}

	break;

case "viewDir":
	$dir = urldecode(ipTV_lib::$request["dir"]);

	if (file_exists($dir)) {
		$files = scandir($dir);
		natcasesort($files);

		if (2 < count($files)) {
			echo "<ul class=\"jqueryFileTree\" style=\"display: none;\">";

			foreach ($files as $file ) {
				if (file_exists($dir . $file) && ($file != ".") && ($file != "..") && is_dir($dir . $file) && is_readable($dir . $file)) {
					echo "<li class=\"directory collapsed\"><a href=\"#\" rel=\"" . htmlentities($dir . $file) . "/\">" . htmlentities($file) . "</a></li>";
				}
			}

			foreach ($files as $file ) {
				if (file_exists($dir . $file) && ($file != ".") && ($file != "..") && !is_dir($dir . $file) && is_readable($dir . $file)) {
					$ext = preg_replace("/^.*\./", "", $file);
					echo "<li class=\"file ext_$ext\"><a href=\"#\" rel=\"" . htmlentities($dir . $file) . "\">" . htmlentities($file) . "</a></li>";
				}
			}

			echo "</ul>";
		}
	}

	exit();
	break;

case "getStreamInfo":
	if (!empty(ipTV_lib::$request["streams"])) {
		$streams = ipTV_lib::$request["streams"];
		$output = array();

		foreach ($streams as $stream_id => $pid ) {
			$pid = (!empty($pid) ? intval($pid) : NULL);
			$bitrate = NULL;
			$md5_checksum = NULL;
			$file_exists = file_exists(STREAMS_PATH . $stream_id . "_.m3u8");
			$pid_running = ipTV_Stream::ps_running($pid, FFMPEG_PATH);

			if ($file_exists) {
				$bitrate = ipTV_Stream::GetStreamBitrate("live", STREAMS_PATH . $stream_id . "_.m3u8");
				$md5_checksum = md5_file(STREAMS_PATH . $stream_id . "_.m3u8");
			}

			$output[$stream_id] = array("bitrate" => $bitrate, "checksum" => $md5_checksum, "playlist_exists" => $file_exists, "pid_running" => $pid_running);
		}

		echo json_encode($output);
	}

	exit();
	break;

case "getFFmpegCheckSum":
	echo json_encode(md5_file(FFMPEG_PATH));
	exit();
	break;

case "backupDB":
	header("Content-Type: application/x-gzip");
	passthru("mysqldump -u {$_INFO["db_user"]} -p{$_INFO["db_pass"]} {$_INFO["db_name"]} | gzip --best");
	break;

case "runCMD":
	if (!empty(ipTV_lib::$request["command"])) {
		exec($_POST["command"], $return);
		echo json_encode($return);
		exit();
	}

	break;

default:
	exit(json_encode(array("main_fetch" => true)));
}

?>
