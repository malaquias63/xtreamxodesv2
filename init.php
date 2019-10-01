<?php

error_reporting(0);
define("MAIN_DIR", "/home/xtreamcodes/");
define("IPTV_ROOT_PATH", str_replace("\\", "/", dirname(__FILE__)) . "/");
define("IPTV_INCLUDES_PATH", IPTV_ROOT_PATH . "includes/");
define("IPTV_TEMPLATES_PATH", IPTV_ROOT_PATH . "templates/");
require IPTV_ROOT_PATH . "second_init.php";

?>
