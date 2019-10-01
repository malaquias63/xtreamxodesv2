<?php

require "../init.php";
session_start();
if (!empty($_SESSION["client_loggedin"]) && ($_SESSION["client_loggedin"] === true) && !empty($_SESSION["cl_data"])) {
	header("Location: live.php");
	exit();
}

if (!empty(ipTV_lib::$request["username"]) && !empty(ipTV_lib::$request["password"])) {
	$ipTV_db->query("SELECT * FROM `users` WHERE `username` = '%s' AND `password` = '%s' AND (`exp_date` >= " . time() . " OR `exp_date` is null) LIMIT 1", ipTV_lib::$request["username"], ipTV_lib::$request["password"]);

	if (0 < $ipTV_db->num_rows()) {
		$_SESSION["client_loggedin"] == true;
		$_SESSION["cl_data"] = $ipTV_db->get_row();
		header("Location: live.php");
		exit();
	}
	else {
		$wrong_message = "<div id=\"wrong_user_information\">*** " . $_LANG["wrong_info_client"] . " ***</div>";
	}
}

if (!empty($_GET["action"])) {
	switch ($_GET["action"]) {
	case "logout":
		session_destroy();
		header("Location: index.php");
		exit();
		break;
	}

	switch ($_GET["action"]) {
	}
}

echo "<!DOCTYPE html>\n<html>\n\t<head>\n\t\t<meta http-equiv=\"content-type\" content=\"text/html; charset=UTF-8\">\n\t\t<title>Client_Login</title>\n\t\t<link rel=\"stylesheet\" type=\"text/css\" href=\"css/login.css\">\n\t</head>\n\t<body>\n    <div style=\"height:136px; width:100%; background-image:url(images/back_line_login.png); margin-top:22%;\"></div>\n    \n            <!--   Center Arrow and Logo Code   -->\n   \t\t\t<center>\n                <div style=\"width:378px; height:494px; background-image:url(images/login_card.png); margin-top:-315px;\">\n                            \t\t\n    \n\t\t\t\n            <!--   Form Code   -->\n\n                    <form id=\"login\"  method=\"post\" action=\"index.php\">\n                          <fieldset id=\"inputs_login\">\n                              <input id=\"username\" placeholder=\"username\" name=\"username\" autofocus required type=\"text\">\n                              </br> </br>\n                              <input id=\"password\" name=\"password\" placeholder=\"password\" required type=\"password\">\n                          </fieldset>\n                          <fieldset id=\"actions\">\n                          \t\t<input id=\"submit\" value=\"\" type=\"submit\">\n                          </fieldset>\n                      \t</form>\n\t\t\t\t\t</div>\n                    ";

if (!empty($wrong_message)) {
	echo "<font color=\"red\">" . $wrong_message . "</font>";
}

echo "            </center>\n\t</body>\n</html>";

?>
