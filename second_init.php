<?php

@ini_set("user_agent", "Mozilla/5.0 (Windows NT 6.1; WOW64; rv:9.0) Gecko/20100101 Firefox/9.0");
@ini_set("default_socket_timeout", 10);
define("IN_SCRIPT", true);
define("SOFTWARE", "iptv");
define("SCRIPT_NAME", "ipTV Panel");
define("SCRIPT_AUTHOR", "by Xtream-Codes");
define("SCRIPT_VERSION", "2.0.0");
define("IPTV_PANEL_DIR", MAIN_DIR . "iptv_xtream_codes/");
define("BIN_PATH", IPTV_PANEL_DIR . "bin/");
define("FFMPEG_PATH", file_exists(BIN_PATH . "ffmpeg") ? BIN_PATH . "ffmpeg" : "/usr/bin/ffmpeg");
define("FFPROBE_PATH", file_exists(BIN_PATH . "ffprobe") ? BIN_PATH . "ffprobe" : "/usr/bin/ffprobe");
define("STREAMS_PATH", IPTV_PANEL_DIR . "streams/");
define("MOVIES_IMAGES", IPTV_PANEL_DIR . "wwwdir/images/");
define("MOVIES_PATH", IPTV_PANEL_DIR . "movies/");
define("CREATED_CHANNELS", IPTV_PANEL_DIR . "created_channels/");
define("CRON_PATH", IPTV_PANEL_DIR . "crons/");
define("PHP_BIN", "/home/xtreamcodes/iptv_xtream_codes/php/bin/php");
define("ASYNC_DIR", IPTV_PANEL_DIR . "async_incs/");
define("TMP_DIR", IPTV_PANEL_DIR . "tmp/");
define("IPTV_CLIENT_AREA", IPTV_PANEL_DIR . "wwwdir/client_area/");
define("IPTV_CLIENT_AREA_TEMPLATES_PATH", IPTV_CLIENT_AREA . "templates/");
define("TV_ARCHIVE", IPTV_PANEL_DIR . "tv_archive/");
require IPTV_INCLUDES_PATH . "functions.php";
require IPTV_INCLUDES_PATH . "lib.php";
require IPTV_INCLUDES_PATH . "mysql.php";
require IPTV_INCLUDES_PATH . "streaming.php";
require IPTV_INCLUDES_PATH . "servers.php";
require IPTV_ROOT_PATH . "langs/English.php";
$_INFO = array();

if (file_exists(IPTV_PANEL_DIR . "config")) {
	$_INFO = json_decode(file_get_contents(IPTV_PANEL_DIR . "config"), true);
	define("SERVER_ID", $_INFO["server_id"]);
}
else {
	exit("no config found");
}

$ipTV_db = new ipTV_db($_INFO["db_user"], $_INFO["db_pass"], $_INFO["db_name"], $_INFO["host"]);
ipTV_lib::$ipTV_db = &$ipTV_db;
ipTV_Stream::$ipTV_db = &$ipTV_db;
ipTV_lib::init();
CheckFlood();

?>
