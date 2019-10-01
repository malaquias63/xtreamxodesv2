<?php

require "../init.php";
session_start();
if (empty($_SESSION["client_loggedin"]) && ($_SESSION["client_loggedin"] != true) && empty($_SESSION["cl_data"])) {
	header("Location: index.php");
	exit();
}

$user_data = ipTV_Stream::GetUserInfo(NULL, $_SESSION["cl_data"]["username"], $_SESSION["cl_data"]["password"], true, true, true, array("radio_streams"));
$categories = array();
$categories_ids = array();
$streams = array();
$ch_ids = array();
$req_cat_id = "";
echo "<!DOCTYPE html>\n<html>\n<head>\n       <meta charset=\"utf-8\">\n        <title>Live_TV</title>\n        <link rel=\"stylesheet\" href=\"css/main.css\" type=\"text/css\" />\n        <link rel=\"stylesheet\" type=\"text/css\" href=\"css/greedynav.css\">\n        <link rel=\"stylesheet\" type=\"text/css\" href=\"css/reset.min.css\">\n    <script src=\"//ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js\"></script>\n\t<link rel=\"stylesheet\" href=\"css/jquery.mobile.min.css\" />\n    <script src=\"js/jquery.mobile.min.js\"></script>\n         \n         \n         \n         \n \t\t <script type=\"text/javascript\" src=\"js/jquery.min.js\"></script>\n \t\t <script type=\"text/javascript\" src=\"js/greedynav.js\"></script>\n</head>\n<body>\n    \t\t<!-- header -->\n            <div class=\"header\">\n            \n           \t  <div class=\"logo\"></div>\n                    \n                   <div class=\"button_Live\">\n                        <img  src=\"images/live_btn.png\"onmouseover=\"this.src='images/live_btn_hover.png'\" onmouseout=\"this.src='images/live_btn.png'\" onClick=\"parent.location='live.php'\" />\n       \t\t  </div>\n                     \n              <div class=\"button_Movies\">\n                            <img  src=\"images/videos_btn.png\" onmouseover=\"this.src='images/videos_btn_hover.png'\" onmouseout=\"this.src='images/videos_btn.png'\" onClick=\"parent.location='vod.php'\" />\n              </div>\n              <div class=\"button_Radio\">\n                            <img  src='images/radio_btn_hover.png'/>\n              </div>\n            <div class=\"User\"><img src=\"images/user_icon.png\"><a style=\"margin-left:10px; color:#C60;\">";
echo $_SESSION["cl_data"]["username"];
echo "</a>\n            <div style=\"width:3px; height:103px;position:absolute; margin-top:-40px; margin-left:-10px;\"><img src=\"images/Header_default_line.png\"></div>\n            <div style=\"width:3px; height:103px;position:absolute; margin-top:-40px; margin-left:140px;\"><img src=\"images/Header_default_line.png\"></div>\n            <ul>\n            <li><a style=\" color:#c60; font-size:12px;\">Expire Date:</a><a style=\"margin-left:10px; color:#fff; font-size:12px;\">";

if (empty($_SESSION["cl_data"]["exp_date"])) {
	echo "Unlimited";
}
else {
	echo date("d/m/Y H:i", $_SESSION["cl_data"]["exp_date"]);
}

echo "</a></li>\n            <li style=\"margin-left:30px;\"><img  src=\"images/logout_btn.png\"onmouseover=\"this.src='images/logout_btn_hover.png'\" onmouseout=\"this.src='images/logout_btn.png'\" onClick=\"parent.location='index.php?action=logout'\"/></li>\n            \n            </div>\n            </div>\n                \n    \t\t<!-- /header -->\n    <div class=\"wrapper\"> \n    <div data-role=\"listview\" data-inset=\"true\" data-filter=\"true\" data-filter-placeholder=\"search\">       \n            <center>\n<nav class='greedy-nav'>\n<button><div class=\"hamburger\"></div></button>\n  <ul class='visible-links'>\n  </ul>\n  <ul class='hidden-links hidden'></ul>\n</nav>\n\t\t\t<!--channels-->\n<radio>\n    ";

foreach ($user_data["channels"] as $radios ) {
	$url = ipTV_lib::$StreamingServers[SERVER_ID]["site_url"] . "live/{$_SESSION["cl_data"]["username"]}/{$_SESSION["cl_data"]["password"]}/{$radios["id"]}.ts";
	echo "<div class=\"Radio_Frame\">\n            \t\t<div class=\"Radio_Icon\">";
	if (!empty($radios["stream_icon"]) && @getimagesize(radios["stream_icon"])) {
		echo "<img src=\"" . $radios["stream_icon"] . "\"></div>";
	}
	else {
		echo "<img width=\"100\" height=\"100\" src=\"images/no_radio.png\"></div>";
	}

	echo " <div class=\"Radio_Line\"></div><div class=\"Radio_Live_Now\"></br><p>" . $radios["stream_display_name"] . "</div><div class=\"Radio_Line\"></div><a href=\"" . $url . "\" </a><div class=\"Play_Radio_Button\"></div></div>";
}

echo "\t\t\t</div>\n          </center>\n    </div> \n    </div>     \n</radio>          \n          \n\t\t\t<!--/channels-->\n             \n   \t\t\t <!--footer-->\n\t\t\t\t</br></br></br>\n              \t<div class=\"footer\"><a><img style=\"float:right;\" src=\"images/footer.png\"></a>\n            </div>\t\t\t\t\n      \t\t \n             <!--/footer-->\n\t</body>\n</html>";

?>
