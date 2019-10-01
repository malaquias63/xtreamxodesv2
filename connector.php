<?php

require "init.php";

if (empty(ipTV_lib::$request["query"])) {
	exit();
}

set_time_limit(0);
ini_set("memory_limit", -1);
$query = ipTV_lib::$request["query"];
$unbuffered = (empty(ipTV_lib::$request["unbuffered"]) ? false : true);
if (!$unbuffered && is_array($query)) {
	$output = array();

	foreach ($query as $qr ) {
		$arg_list = (!empty($qr["arguments"]) ? $qr["arguments"] : array());
		$next_arg_list = array();

		for ($i = 0; $i < count($arg_list); $i++) {
			$next_arg_list[] = $ipTV_db->escape($arg_list[$i]);
		}

		$qr["query"] = vsprintf($qr["query"], $next_arg_list);
		$ipTV_db->simple_query($qr["query"]);

		if (!empty($qr["key"])) {
			$output[$qr["key"]] = array("mysql_insert_id" => $ipTV_db->last_insert_id(), "results" => $ipTV_db->get_rows(), "num_rows" => $ipTV_db->num_rows(), "mysqli_num_fields" => $ipTV_db->num_fields(), "affected_rows" => $ipTV_db->affected_rows());
		}
		else {
			$output[] = array("mysql_insert_id" => $ipTV_db->last_insert_id(), "results" => $ipTV_db->get_rows(), "num_rows" => $ipTV_db->num_rows(), "mysqli_num_fields" => $ipTV_db->num_fields(), "affected_rows" => $ipTV_db->affected_rows());
		}
	}

	echo json_encode($output);
}
else if (!is_array($query)) {
	$query = mysqli_query($ipTV_db->dbh, ipTV_lib::$request["query"], MYSQLI_USE_RESULT);

	while ($row = mysqli_fetch_assoc($query)) {
		echo json_encode(array_map("encodeToUtf8", $row)) . "\n";
	}
}

?>
