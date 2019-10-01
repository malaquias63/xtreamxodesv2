<?php

require "../init.php";
session_start();
if (empty($_SESSION["client_loggedin"]) && ($_SESSION["client_loggedin"] != true) && empty($_SESSION["cl_data"])) {
	header("Location: index.php");
	exit();
}

$link = (!empty($_POST["link"]) ? $_POST["link"] : "");
$display_name = (!empty($_POST["display_name"]) ? $_POST["display_name"] : "");

if (pathinfo($link, PATHINFO_EXTENSION) != "m3u8") {
	$type = "video/mp4";
	$live = "false";
}
else {
	$type = "application/x-mpegurl";
	$live = "true";
}

echo "<!DOCTYPE html>\n<html>\n<head>\n        <meta charset=\"utf-8\">\n        <title>Videos</title>\n        <link rel=\"stylesheet\" href=\"css/main.css\" type=\"text/css\" />\n           <!-- optimize mobile versions -->\n   <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n\n   <!-- The \"functional\" skin, \"functional.css\", \"minimalist.css\" and \"playful.css\" are available -->\n   <link rel=\"stylesheet\" href=\"css/functional.css\">\n<style>\n#hlsjs {\n  background-image: url(//drive.flowplayer.org/202777/84049-snap.jpg);\n}\n\n.hlsjs-supported {\n  font-weight: bold;\n}\n</style>\n   <!-- Minimal styling for this standalone page (can be removed) -->\n\n   <!-- CSS for this demo -->\n   <link rel=\"stylesheet\" href=\"css/hlsjs.css\">\n   \n   \n   <!-- Flowplayer-->\n   <script src=\"js/flowplayer.min.js\"></script>\n\n\n<script src=\"js/flowplayer.hlsjs.min.js\"></script>\n\n</head>\n<body>\n    \t\t<!-- header \n            \n            <div class=\"header\">\n            \n           \t  <div class=\"logo\"></div>\n                    \n                    <div class=\"button_Live\">\n                        <img  src=\"images/live_btn.png\"onmouseover=\"this.src='images/live_btn_hover.png'\" onmouseout=\"this.src='images/live_btn.png'\" onClick=\"parent.location='live.php'\" />\n       \t\t  </div>\n                     \n              <div class=\"button_Movies\">\n                            <img  src=\"images/videos_btn.png\" onmouseover=\"this.src='images/videos_btn_hover.png'\" onmouseout=\"this.src='images/videos_btn.png'\" onClick=\"parent.location='vod.php'\" />\n              </div>\n                      \n              <div class=\"movie_Title\"><a>You are watching &nbsp;</a><a style=\"color: #FFF;\">";
echo "</a>\n              </div>\n              <div class=\"button_Radio\">\n                            <img  src=\"images/radio_btn.png\"onmouseover=\"this.src='images/radio_btn_hover.png'\" onmouseout=\"this.src='images/radio_btn.png'\" onClick=\"parent.location='radio.php'\" />\n              </div>\n                        \n            </div>\n            \n                -->\n    \t\t<!-- /header -->\n            \n            <!--player-->\n       \t\t<center>\n            <div class=\"player_Frame\">\n            \t<div id=\"content\">\n\n<div id=\"fp-hlsjs\" class=\"is-closeable\"></div>\n\n<script>\nflowplayer(\"#fp-hlsjs\", {\n    splash: true,\n    ratio: 9/16,\n    clip: {\n        title: \"";
echo $display_name;
echo "\",\n        sources: [\n            { type: \"";
echo $type;
echo "\",\n              src:  \"";
echo $link;
echo "\",\n              live: ";
echo $live;
echo "\t\t\t  \n            }\n        ]\n    },\n    embed: false\n\n}).on(\"ready\", function (e, api, video) {\n    document.querySelector(\"#fp-hlsjs .fp-title\").innerHTML =\n            api.engine.engineName + \" engine playing \" + video.type;\n\n});\n</script>\n\t\n            </div>\n            </center>\n<!--/player-->\n             \n   \t\t\t <!--footer-->\n\n              \t<div class=\"footer\"><a><img style=\"float:right;\" src=\"images/footer.png\"></a>\n            </div>\n              \t<p>&nbsp;</p>\t\t\t\t\n      \t\t \n             <!--/footer-->\n\t</body>\n</html>";

?>
