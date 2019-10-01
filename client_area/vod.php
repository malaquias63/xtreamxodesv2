<?php

require "../init.php";
session_start();
if (empty($_SESSION["client_loggedin"]) && ($_SESSION["client_loggedin"] != true) && empty($_SESSION["cl_data"])) {
	header("Location: index.php");
	exit();
}

$user_data = ipTV_Stream::GetUserInfo(NULL, $_SESSION["cl_data"]["username"], $_SESSION["cl_data"]["password"], true, true, true, array("movie"));
$categories = array();
$categories_ids = array();
$streams = array();
$ch_ids = array();
$req_cat_id = "";

foreach ($user_data["channels"] as $movies ) {
	$vod_category_name = ($movies["category_name"] == NULL ? "Uncategorized" : $movies["category_name"]);
	$categories[$vod_category_name][] = $movies;

	if (!in_array($movies["category_id"], $categories_ids)) {
		$categories_ids[$vod_category_name] = $movies["category_id"];
	}
}

$categories_ids["All"] = 0;
$isMobile = isMobileDevice();
echo "<!DOCTYPE html>\n<html>\n<head>\n    <meta charset=\"utf-8\">\n    <title>Live_TV</title>\n    <link rel=\"stylesheet\" href=\"css/main.css\" type=\"text/css\" />\n    <link rel=\"stylesheet\" type=\"text/css\" href=\"css/greedynav.css\">\n    <link rel=\"stylesheet\" type=\"text/css\" href=\"css/reset.min.css\">\n    <link href=\"https://noraesae.github.io/perfect-scrollbar/perfect-scrollbar.min.css\" rel=\"stylesheet\">\n    <link rel=\"stylesheet\" href=\"css/jquery.mobile.min.css\" />\n\n    <script src=\"//ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js\"></script>\n\n    <script src=\"js/jquery.mobile.min.js\"></script>\n    <script type=\"text/javascript\" src=\"js/jquery.min.js\"></script>\n    <script type=\"text/javascript\" src=\"js/greedynav.js\"></script>\n    <script src=\"https://noraesae.github.io/perfect-scrollbar/perfect-scrollbar.min.js\"></script>\n    <script src=\"https://noraesae.github.io/perfect-scrollbar/bootstrap.min.js\"></script>\n    <script src=\"https://noraesae.github.io/perfect-scrollbar/prettify.js\"></script>  \n    <script>    \n    function post(path, params, method) {\n        method = method || \"post\";\n        var form = document.createElement(\"form\");\n        form.setAttribute(\"method\", method);\n        form.setAttribute(\"action\", path);\n    \n        for(var key in params) {\n            if(params.hasOwnProperty(key)) {\n                var hiddenField = document.createElement(\"input\");\n                hiddenField.setAttribute(\"type\", \"hidden\");\n                hiddenField.setAttribute(\"name\", key);\n                hiddenField.setAttribute(\"value\", params[key]);\n    \n                form.appendChild(hiddenField);\n             }\n        }\n    \n        document.body.appendChild(form);\n        form.submit();\n    }\n    </script>\n    <script>\n      $(function() {\n        prettyPrint();\n      });\n    </script>\n         \n</head>\n<body>\n    \t\t<!-- header -->\n            \n            <div class=\"header\">\n            \n           \t  <div class=\"logo\"></div>\n                    \n                   <div class=\"button_Live\">\n                        <img  src=\"images/live_btn.png\"onmouseover=\"this.src='images/live_btn_hover.png'\" onmouseout=\"this.src='images/live_btn.png'\" onClick=\"parent.location='live.php'\" />\n       \t\t  </div>\n                     \n              <div class=\"button_Movies\">\n                            <img  src=\"images/videos_btn_hover.png\"/>\n              </div>\n              <div class=\"button_Radio\">\n                            <img  src=\"images/radio_btn.png\"onmouseover=\"this.src='images/radio_btn_hover.png'\" onmouseout=\"this.src='images/radio_btn.png'\" onClick=\"parent.location='radio.php'\" />\n              </div>\n            <div class=\"User\"><img src=\"images/user_icon.png\"><a style=\"margin-left:10px; color:#C60;\">";
echo $_SESSION["cl_data"]["username"];
echo "</a>\n            <div style=\"width:3px; height:103px;position:absolute; margin-top:-40px; margin-left:-10px;\"><img src=\"images/Header_default_line.png\"></div>\n            <div style=\"width:3px; height:103px;position:absolute; margin-top:-40px; margin-left:140px;\"><img src=\"images/Header_default_line.png\"></div>\n            <ul>\n            <li><a style=\" color:#c60; font-size:12px;\">Expire Date:</a><a style=\"margin-left:10px; color:#fff; font-size:12px;\">";

if (empty($_SESSION["cl_data"]["exp_date"])) {
	echo "Unlimited";
}
else {
	echo date("d/m/Y H:i", $_SESSION["cl_data"]["exp_date"]);
}

echo "</a></li>\n            <li style=\"margin-left:30px;\"><img  src=\"images/logout_btn.png\"onmouseover=\"this.src='images/logout_btn_hover.png'\" onmouseout=\"this.src='images/logout_btn.png'\" onClick=\"parent.location='index.php?action=logout'\"/></li>\n            \n            </div>\n            </div>\n                \n    \t\t<!-- /header -->\n    <div class=\"wrapper\">  \n     <div data-role=\"listview\" data-inset=\"true\" data-filter=\"true\" data-filter-placeholder=\"search\">   \n            <center>\n<nav class='greedy-nav'>\n<button><div class=\"hamburger\"></div></button>\n  <ul class='visible-links'>\n    ";
$req_cat_id = false;
if (isset($_GET["cat_id"]) && is_numeric($_GET["cat_id"])) {
	$req_cat_id = intval($_GET["cat_id"]);
}

$categories["All"] = array();

foreach ($categories as $category_name => $stream_from_cat ) {
	$cat_id = (empty($categories_ids[$category_name]) ? 0 : $categories_ids[$category_name]);
	$count_streams = count($stream_from_cat);

	if ("$cat_id" === "$req_cat_id") {
		echo "<li><a href=\"#\" style=\"color:#F60\" onClick=\"window.location='vod.php?cat_id=$cat_id'\">$category_name ( $count_streams )</a></li>";
	}
	else {
		echo "<li><a href=\"#\" onClick=\"window.location='vod.php?cat_id=$cat_id'\">$category_name ( $count_streams )</a></li>";
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

echo "                \n  </ul>\n  <ul class='hidden-links hidden'></ul>\n</nav>\n            </center>\n            <!--movies-->\n       \t\t<poster>\n            \n            ";

foreach ($streams as $stream ) {
	$container = $stream["container_extension"];
	$url = ipTV_lib::$StreamingServers[SERVER_ID]["site_url"] . "movie/{$_SESSION["cl_data"]["username"]}/{$_SESSION["cl_data"]["password"]}/{$stream["id"]}.$container";
	$movies_properties = json_decode($stream["movie_propeties"], true);
	$target_link = (!$isMobile ? "post('player.php',{link:'$url',display_name:'{$stream["stream_display_name"]}'});" : "window.location.href='$url'");
	echo "<div class=\"movie_Frame\"><div class=\"movie_thump\">";

	if (!empty($movies_properties["movie_image"])) {
		echo "<img width=\"214\" height=\"317\" src=\"{$movies_properties["movie_image"]}\" onmouseover=\"this.src='images/movie_thump_hover.png'\" onmouseout=\"this.src='{$movies_properties["movie_image"]}'\" onclick=\"$target_link\" ></div>";
	}
	else {
		echo "<img width=\"214\" height=\"317\" src=\"images/no_poster.jpg\" onmouseover=\"this.src='images/movie_thump_hover.png'\" onmouseout=\"this.src='images/no_poster.jpg'\" onclick=\"$target_link\"></div>";
	}

	echo "<div class=\"movie_Line\"></div>";
	echo "<center><p>" . $stream["stream_display_name"] . "</p></center>";
	echo "<div class=\"rating_star\" target=\"_blank\"><a></a><h1>" . $movies_properties["rating"] . "</h1>";
	echo "<div class=\"Demo\" id=\"scroll_" . $stream["id"] . "\"><ul style=\"width:150px;\">";
	echo "<li style=\"margin-left:5px; width:180px; position:relative;\"><a style=\"color:#FC6;\">Genre:&nbsp;</a>" . $movies_properties["genre"] . "</li>";
	echo "<li style=\"margin-top:10px; margin-left:5px; width:180px; position:relative;\"><a style=\"color:#FC6;\">Cast:&nbsp;</a>" . $movies_properties["cast"] . "</li>";
	echo "<li style=\"margin-top:10px; margin-left:5px; width:180px;\"><a style=\"color:#FC6; position:relative;\">Director:&nbsp;Director:&nbsp;</a>" . $movies_properties["director"] . "</li>";
	echo "<li style=\"margin-top:10px; margin-left:5px; width:180px;\"><a style=\"color:#FC6; position:relative;\">Release Date:&nbsp;</a>" . $movies_properties["releasedate"] . "</li></ul>";
	echo "<li style=\"margin-top:10px; margin-left:5px; width:180px;\"><a style=\"color:#FC6; position:relative;\">Plot:&nbsp;</a>" . $movies_properties["plot"] . "</li></div>&nbsp;</div></div>";
	echo "<script>Ps.initialize(document.getElementById('scroll_" . $stream["id"] . "'));</script>";
}

echo "            \n            </poster>\n            </div>\n    </div> \n    </div>    \n\t\t\t<!--/movies-->\n             \n   \t\t\t <!--footer-->\n             \n              \t<div class=\"footer\"><a><img style=\"float:right;\" src=\"images/footer.png\"></a>\n            </div>\t\t\t\t\n      \t\t \n             <!--/footer-->\n\t</body>\n</html>";

?>
