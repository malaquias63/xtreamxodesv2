<?php

function fit_it($string, $length)
{
	if ($length < strlen($string)) {
		return substr($string, 0, -$length) . "<br/>" . substr($string, -$length);
	}
	else {
		return $string;
	}
}

require "../init.php";
session_start();
if (empty($_SESSION["client_loggedin"]) && ($_SESSION["client_loggedin"] != true) && empty($_SESSION["cl_data"])) {
	header("Location: index.php");
	exit();
}

$user_data = ipTV_Stream::GetUserInfo(NULL, $_SESSION["cl_data"]["username"], $_SESSION["cl_data"]["password"], true, true, true, array("live", "created_live"));
$categories = array();
$categories_ids = array();
$streams = array();
$ch_ids = array();
$req_cat_id = "";

foreach ($user_data["channels"] as $live_streams ) {
	$live_category_name = ($live_streams["category_name"] == NULL ? "Uncategorized" : $live_streams["category_name"]);
	$categories[$live_category_name][] = $live_streams;

	if (!in_array($live_streams["category_id"], $categories_ids)) {
		$categories_ids[$live_category_name] = $live_streams["category_id"];
	}
}

$isMobile = isMobileDevice();
echo "<!DOCTYPE html>\n<html>\n<head>\n        <meta charset=\"utf-8\">\n        <title>Live_TV</title>\n        <link rel=\"stylesheet\" href=\"css/main.css\" type=\"text/css\" />\n        <link rel=\"stylesheet\" type=\"text/css\" href=\"css/greedynav.css\">\n        <link rel=\"stylesheet\" type=\"text/css\" href=\"css/reset.min.css\">\n    <script src=\"//ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js\"></script>\n\t<link rel=\"stylesheet\" href=\"css/jquery.mobile.min.css\" />\n    <script src=\"js/jquery.mobile.min.js\"></script>\n         \n         \n         \n         \n \t\t <script type=\"text/javascript\" src=\"js/jquery.min.js\"></script>\n \t\t <script type=\"text/javascript\" src=\"js/greedynav.js\"></script>\n    <script>    \n    function post(path, params, method) {\n    method = method || \"post\";\n    var form = document.createElement(\"form\");\n    form.setAttribute(\"method\", method);\n    form.setAttribute(\"action\", path);\n\n    for(var key in params) {\n        if(params.hasOwnProperty(key)) {\n            var hiddenField = document.createElement(\"input\");\n            hiddenField.setAttribute(\"type\", \"hidden\");\n            hiddenField.setAttribute(\"name\", key);\n            hiddenField.setAttribute(\"value\", params[key]);\n\n            form.appendChild(hiddenField);\n         }\n    }\n\n    document.body.appendChild(form);\n    form.submit();\n}\n\n</script>\n</head>\n<body>\n    \t\t<!-- header -->\n            \n            <div class=\"header\">\n            \n           \t  <div class=\"logo\"></div>\n                    \n                    <div class=\"button_Live\">\n                        <img  src=\"images/live_btn_hover.png\"/>\n       \t\t  </div>\n                     \n              <div class=\"button_Movies\">\n                            <img  src=\"images/videos_btn.png\" onmouseover=\"this.src='images/videos_btn_hover.png'\" onmouseout=\"this.src='images/videos_btn.png'\" onClick=\"parent.location='vod.php'\" />\n              </div>\n              <div class=\"button_Radio\">\n                            <img  src=\"images/radio_btn.png\"onmouseover=\"this.src='images/radio_btn_hover.png'\" onmouseout=\"this.src='images/radio_btn.png'\" onClick=\"parent.location='radio.php'\" />\n              </div>\n            <div class=\"User\"><img src=\"images/user_icon.png\"><a style=\"margin-left:10px; color:#C60;\">";
echo $_SESSION["cl_data"]["username"];
echo "</a>\n            <div style=\"width:3px; height:103px;position:absolute; margin-top:-40px; margin-left:-10px;\"><img src=\"images/Header_default_line.png\"></div>\n            <div style=\"width:3px; height:103px;position:absolute; margin-top:-40px; margin-left:140px;\"><img src=\"images/Header_default_line.png\"></div>\n            <ul>\n            <li><a style=\" color:#c60; font-size:12px;\">Expire Date:</a><a style=\"margin-left:10px; color:#fff; font-size:12px;\">";

if (empty($_SESSION["cl_data"]["exp_date"])) {
	echo "Unlimited";
}
else {
	echo date("d/m/Y H:i", $_SESSION["cl_data"]["exp_date"]);
}

echo "</a></li>\n            <li style=\"margin-left:30px;\"><img  src=\"images/logout_btn.png\"onmouseover=\"this.src='images/logout_btn_hover.png'\" onmouseout=\"this.src='images/logout_btn.png'\" onClick=\"parent.location='index.php?action=logout'\"/></li>\n            \n            </div>\n            </div>\n    \t\t<!-- /header -->\n     <div class=\"wrapper\"> \n     <div data-role=\"listview\" data-inset=\"true\" data-filter=\"true\" data-filter-placeholder=\"search\">\n            <center>\n        <nav class='greedy-nav'>\n        <button><div class=\"hamburger\"></div></button>\n          <ul class='visible-links'>\n            ";
if (isset($_GET["cat_id"]) && is_numeric($_GET["cat_id"])) {
	echo "<li><a href=\"#\" onClick=\"window.location='live.php'\">All</a></li>";
	$req_cat_id = intval($_GET["cat_id"]);
}
else {
	echo "<li><a href=\"#\" style=\"color:#F60\" onClick=\"window.location='live.php'\">All</a></li>";
}

foreach ($categories as $category_name => $stream_from_cat ) {
	$cat_id = (empty($categories_ids[$category_name]) ? 0 : $categories_ids[$category_name]);
	$count_streams = count($stream_from_cat);

	if ("$cat_id" === "$req_cat_id") {
		echo "<li><a href=\"#\" style=\"color:#F60\" onClick=\"window.location='live.php?cat_id=$cat_id'\">$category_name ( $count_streams )</a></li>";
	}
	else {
		echo "<li><a href=\"#\" onClick=\"window.location='live.php?cat_id=$cat_id'\">$category_name ( $count_streams )</a></li>";
	}

	foreach ($stream_from_cat as $str ) {
		if (!empty($req_cat_id) && ($cat_id != $req_cat_id)) {
			continue;
		}

		if (($req_cat_id === 0) && ($cat_id != $req_cat_id)) {
			continue;
		}

		$streams[] = $str;

		if (!in_array($str["channel_id"], $ch_ids)) {
			$ch_ids[] = $str["channel_id"];
		}
	}
}

echo "                </ul>\n            <ul class='hidden-links hidden'></ul>\n            </nav>\n            </center>\n            </br>\n            <div class=\"live_now\">\n            \t<a style=\"color:#FFF; font-size:15px; font-family:Tahoma, Geneva, sans-serif; margin-left:120px; top:5px; position:relative; font-style:italic;\">Live Now...</a>\n            </div>\n                <div class=\"coming_next\">\n                            \t<a style=\"color:#252525; font-size:15px; font-family:Tahoma, Geneva, sans-serif; margin-left:45%; top:5px; position:relative; font-style:italic;\">Coming Next...</a>\n\n                </div>\n            <!--channels-->\n            ";
$ch_ids = "'" . implode("','", array_unique($ch_ids)) . "'";
$ipTV_db->query("SELECT * from `epg_data` WHERE `end` >= '%d' AND `end` <= '%d' AND channel_id IN ($ch_ids)", time(), strtotime("+12 hours"));
$channel_epgs = $ipTV_db->get_rows(true, "channel_id", false);
$i = 0;

foreach ($streams as $stream ) {
	if ($i === 0) {
		echo "<center><div class=\"channel_Frame\"><div class=\"channel_Icon\">";
	}
	else {
		echo "<center><div class=\"channel_Frame\"><div style=\"margin-top:15px;\" class=\"channel_Icon\">";
	}

	echo "<p>" . fit_it($stream["stream_display_name"], "15") . "</p>";

	if (!empty($stream["stream_icon"])) {
		echo "<img src=\"" . $stream["stream_icon"] . "\" width=\"100\" height=\"40\"></div>";
	}
	else {
		echo "</div>";
	}

	$d = 0;

	if (empty($channel_epgs[$stream["channel_id"]])) {
		$url = ipTV_lib::$StreamingServers[SERVER_ID]["site_url"] . "live/{$_SESSION["cl_data"]["username"]}/{$_SESSION["cl_data"]["password"]}/{$stream["id"]}.m3u8";
		$target_link = (!$isMobile ? "post('player.php',{link:'$url',display_name:'{$stream["stream_display_name"]}'});" : "window.location.href='$url'");
		echo "<div class=\"channel_Line\"></div>\n                   <div class=\"channel_Live_Now\"></br><p>No Data</p><p><br/></p>\n                   <div class=\"Play_Live_Button\"  onclick=\"$target_link\">\n                   </div></div>\n                   <div class=\"channel_Line\"></div>";
		echo "<div class=\"channel_Coming_Next\"></br><p>No Data</p><p><br/></p></div><div class=\"channel_Line\"></div>";
		echo "<div class=\"channel_Coming_Next\"></br><p>No Data</p><p><br/></p></div><div class=\"channel_Line\"></div>";
		echo "<div class=\"channel_Coming_Next\"></br><p>No Data</p><p><br/></p></div>";
	}

	foreach ($channel_epgs[$stream["channel_id"]] as $channel_epg ) {
		if (3 < $d) {
			break;
		}

		if ($d === 0) {
			$url = ipTV_lib::$StreamingServers[SERVER_ID]["site_url"] . "live/{$_SESSION["cl_data"]["username"]}/{$_SESSION["cl_data"]["password"]}/{$stream["id"]}.m3u8";
			$target_link = (!$isMobile ? "post('player.php',{link:'$url',display_name:'{$stream["stream_display_name"]}'});" : "window.location.href='$url'");
			echo "<div class=\"channel_Line\"></div>\n                   <div class=\"channel_Live_Now\"><p style=\"margin-top:10px;\">" . date("H:i", $channel_epg["start"]) . " - " . date("H:i", $channel_epg["end"]) . "</p><now><p>" . base64_decode($channel_epg["title"]) . "</p>\n                   <div class=\"Play_Live_Button\"  onclick=\"$target_link\">\n                   </div></div>\n                   <div class=\"channel_Line\"></div></now>";
		}
		else {
			echo "<div class=\"channel_Coming_Next\"><p style=\"margin-top:10px;\">" . date("H:i", $channel_epg["start"]) . " - " . date("H:i", $channel_epg["end"]) . "</p><next><p>" . base64_decode($channel_epg["title"]) . "</p></div></next>";
		}

		if ($d !== 3) {
			echo "<div class=\"channel_Line\"></div>";
		}

		++$d;
	}

	echo "</div></center>";
	++$i;
}

echo "</div>\n    </div> \n        \n          \n          \n          \n\t\t\t<!--/channels-->\n             \n   \t\t\t <!--footer-->\n\t\t\t\t</br></br></br>\n              \t<div class=\"footer\"><a><img style=\"float:right;\" src=\"images/footer.png\"></a>\n            </div>\t\t\t\t\n      \t\t \n             <!--/footer-->\n\t</body>\n</html>\n\n";

?>
