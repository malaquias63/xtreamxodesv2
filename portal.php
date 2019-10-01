<?php

function GetVodOrderedList($category_id = NULL, $fav = NULL, $orderby = NULL)
{
	global $dev;
	global $player;
	global $_LANG;
	global $movie_categories;
	$page = (!empty(ipTV_lib::$request["p"]) ? ipTV_lib::$request["p"] : 0);
	$page_items = 14;
	$default_page = false;
	$streams = GetStreamsFromUser($dev["total_info"]["user_id"], "movie", $category_id, $orderby);
	$series_exists = false;

	foreach ($movie_categories as $movie_category ) {
		if ($movie_category["parent_id"] == 0) {
			continue;
		}

		if (!empty($category_id) && ($movie_category["parent_id"] != $category_id)) {
			continue;
		}

		$series_exists = true;
		$movie_series = explode(",", $movie_category["series"]);
		$streams["streams"][] = array("id" => $movie_category["last_stream_id"], "stream_display_name" => $movie_category["category_name"], "movie_propeties" => !empty($movie_category["movie_propeties"]) ? json_decode($movie_category["movie_propeties"], true) : array(), "added" => $movie_category["added"], "category_id" => $movie_category["parent_id"], "sub_category_id" => $movie_category["id"], "container_extension" => "", "series" => range(1, count($movie_series)));
	}

	$counter = count($streams["streams"]);
	$ch_idx = 0;

	if ($page == 0) {
		$default_page = true;
		$page = ceil($ch_idx / $page_items);

		if ($page == 0) {
			$page = 1;
		}
	}

	$streams = array_slice($streams["streams"], ($page - 1) * $page_items, $page_items);
	$epgInfo = "";
	$channel["channel_type_id"] = "1";
	$channel["xmltv_id"] = "1";
	$channel["logo_file"] = "";
	$datas = array();

	if ($series_exists) {
		switch ($orderby) {
		case "name":
			uasort($streams, "sortArrayStreamName");
			break;

		case "top":
			uasort($streams, "sortArrayStreamRating");
			break;

		case "rating":
			uasort($streams, "sortArrayStreamRating");
			break;

		case "added":
			uasort($streams, "sortArrayStreamAdded");
			break;

		case NULL:
			break;

		default:
			uasort($streams, "sortArrayStreamAdded");
			break;
		}
	}

	foreach ($streams as $movie ) {
		if (!is_null($fav) && ($fav == 1)) {
			if (!in_array($movie["id"], $dev["fav_channels"]["movie"])) {
				continue;
			}
		}

		if (!empty($movie["series_no"])) {
			continue;
		}

		$stream_url = "";

		if (!empty($movie["direct_source"])) {
			list($stream_url) = json_decode($movie["stream_source"], true);
		}

		$movie_properties = (!is_array($movie["movie_propeties"]) ? json_decode($movie["movie_propeties"], true) : $movie["movie_propeties"]);
		$data_to_post = array("username" => $dev["total_info"]["username"], "password" => $dev["total_info"]["password"], "movie_display_name" => $movie["stream_display_name"], "movie_id" => $movie["id"], "direct_source_url" => $stream_url, "category_id" => $movie["category_id"], "sub_category_id" => empty($movie["sub_category_id"]) ? "" : $movie["sub_category_id"], "movie_container" => $movie["container_extension"]);
		$this_mm = date("m");
		$this_dd = date("d");
		$this_yy = date("Y");

		if (mktime(0, 0, 0, $this_mm, $this_dd, $this_yy) < $movie["added"]) {
			$added_key = "today";
			$added_val = $_LANG["today"];
		}
		else if (mktime(0, 0, 0, $this_mm, $this_dd - 1, $this_yy) < $movie["added"]) {
			$added_key = "yesterday";
			$added_val = $_LANG["yesterday"];
		}
		else if (mktime(0, 0, 0, $this_mm, $this_dd - 7, $this_yy) < $movie["added"]) {
			$added_key = "week_and_more";
			$added_val = $_LANG["last_week"];
		}
		else {
			$added_key = "week_and_more";
			$added_val = date("F", $movie["added"]) . " " . date("Y", $movie["added"]);
		}

		$duration = (isset($movie_properties["duration_secs"]) ? $movie_properties["duration_secs"] : 60);
		$datas[] = array("id" => $movie["id"], "age" => "", "cmd" => base64_encode(json_encode($data_to_post)), "genres_str" => $movie_properties["genre"], "for_rent" => 0, "lock" => $movie["is_adult"], "sd" => 0, "hd" => 1, "screenshots" => 1, "comments" => "", "low_quality" => 0, "country" => "", "rating_mpaa" => "", $added_key => $added_val, "high_quality" => 0, "last_played" => "", "rating_last_update" => "", "rating_count_imdb" => "", "rating_imdb" => $movie_properties["rating"], "rating_count_kinopoisk" => "", "kinopoisk_id" => "", "rating_kinopoisk" => $movie_properties["rating"], "for_sd_stb" => 0, "last_rate_update" => NULL, "rate" => NULL, "vote_video_good" => 0, "vote_video_bad" => 0, "vote_sound_bad" => 0, "vote_sound_good" => 0, "count_first_0_5" => 0, "accessed" => 1, "status" => 1, "disable_for_hd_devices" => 0, "count" => 0, "added" => date("Y-m-d H:i:s", $movie["added"]), "owner" => "", "actors" => $movie_properties["cast"], "director" => $movie_properties["director"], "year" => $movie_properties["releasedate"], "cat_genre_id_4" => 0, "cat_genre_id_3" => 0, "cat_genre_id_2" => 0, "cat_genre_id_1" => 0, "genre_id_4" => 0, "genre_id_3" => 0, "genre_id_2" => 0, "genre_id_1" => 0, "category_id" => $movie["category_id"], "name" => $movie["stream_display_name"], "o_name" => $movie["stream_display_name"], "old_name" => "", "fname" => "", "description" => $movie_properties["plot"], "pic" => "", "screenshot_uri" => $movie_properties["movie_image"], "cost" => 0, "time" => intval($duration / 60), "file" => "", "path" => "", "fav" => in_array($movie["id"], $dev["fav_channels"]["movie"]) && empty($movie["series"]) ? 1 : 0, "protocol" => "http", "rtsp_url" => "", "censored" => 0, "series" => !empty($movie["series"]) ? $movie["series"] : array(), "volume_correction" => 0);
	}

	if ($default_page) {
		$cur_page = $page;
		$selected_item = $ch_idx - (($page - 1) * $page_items);
	}
	else {
		$cur_page = 0;
		$selected_item = 0;
	}

	$output = array(
		"js" => array("total_items" => $counter, "max_page_items" => $page_items, "selected_item" => $selected_item, "cur_page" => $cur_page, "data" => $datas)
		);
	return json_encode($output);
}

function GetStreamsFromUser($user_id, $type = "live", $category_id = NULL, $orderby = NULL)
{
	global $dev;
	global $player;
	$user_info = ipTV_Stream::GetUserInfo($user_id, NULL, NULL, true, true, false, array(), true);
	$streams = array();
	$streams["streams"] = array();

	if (!empty($user_info)) {
		switch ($orderby) {
		case "name":
			uasort($user_info["channels"], "sortArrayStreamName");
			break;

		case "top":
			uasort($user_info["channels"], "sortArrayStreamRating");
			break;

		case "rating":
			uasort($user_info["channels"], "sortArrayStreamRating");
			break;

		case "added":
			uasort($user_info["channels"], "sortArrayStreamAdded");
			break;
		}

		foreach ($user_info["channels"] as $stream ) {
			if (!is_null($category_id) && ($stream["category_id"] != $category_id)) {
				continue;
			}

			if (empty($category_id) && ($stream["is_adult"] == 1)) {
				continue;
			}

			if (!is_null($type) && ($stream["type_key"] != $type)) {
				continue;
			}

			$streams["streams"][$stream["id"]] = $stream;
		}
	}

	return $streams;
}

function sortArrayStreamRating($a, $b)
{
	if (!isset($a["movie_propeties"]) || !isset($b["movie_propeties"])) {
		return 0;
	}

	if (!is_array($a["movie_propeties"])) {
		$a = json_decode($a["movie_propeties"], true);
	}
	else {
		$a = $a["movie_propeties"];
	}

	if (!is_array($b["movie_propeties"])) {
		$b = json_decode($b["movie_propeties"], true);
	}
	else {
		$b = $b["movie_propeties"];
	}

	if ($a["rating"] == $b["rating"]) {
		return 0;
	}

	return $b["rating"] < $a["rating"] ? -1 : 1;
}

function sortArrayStreamAdded($a, $b)
{
	if (!is_numeric($a["added"])) {
		$a["added"] = strtotime($a["added"]);
	}

	if (!is_numeric($b["added"])) {
		$b["added"] = strtotime($b["added"]);
	}

	if ($a["added"] == $b["added"]) {
		return 0;
	}

	return $b["added"] < $a["added"] ? -1 : 1;
}

function sortArrayStreamName($a, $b)
{
	if ($a["stream_display_name"] == $b["stream_display_name"]) {
		return 0;
	}

	return $a["stream_display_name"] < $b["stream_display_name"] ? -1 : 1;
}

function GetRadioOrderedList($category_id = NULL, $fav = NULL, $orderby = NULL)
{
	global $dev;
	global $player;
	global $ipTV_db;
	$page = (isset($_REQUEST["p"]) ? intval($_REQUEST["p"]) : 0);
	$page_items = 14;
	$default_page = false;
	$streams = getstreamsfromuser($dev["total_info"]["user_id"], "radio_streams", $category_id, $orderby);
	$counter = count($streams["streams"]);
	$ch_idx = 0;

	if ($page == 0) {
		$default_page = true;
		$page = ceil($ch_idx / $page_items);

		if ($page == 0) {
			$page = 1;
		}
	}

	$streams = array_slice($streams["streams"], ($page - 1) * $page_items, $page_items);
	$datas = array();
	$i = 1;

	foreach ($streams as $order_id => $stream ) {
		if (!is_null($fav) && ($fav == 1)) {
			if (!in_array($stream["id"], $dev["fav_channels"]["radio_streams"])) {
				continue;
			}
		}

		if ($stream["direct_source"] == 0) {
			$stream_url = ipTV_lib::$StreamingServers[SERVER_ID]["site_url"] . "live/{$dev["total_info"]["username"]}/{$dev["total_info"]["password"]}/{$stream["id"]}." . ipTV_lib::$settings["mag_container"];
		}
		else {
			list($stream_url) = json_decode($stream["stream_source"], true);
		}

		$datas[] = array("id" => $stream["id"], "name" => $stream["stream_display_name"], "number" => $i++, "cmd" => $stream_url, "count" => 0, "status" => 1, "volume_correction" => 0, "fav" => in_array($stream["id"], $dev["fav_channels"]["radio_streams"]) ? 1 : 0);
	}

	if ($default_page) {
		$cur_page = $page;
		$selected_item = $ch_idx - (($page - 1) * $page_items);
	}
	else {
		$cur_page = 0;
		$selected_item = 0;
	}

	$output = array(
		"js" => array("total_items" => $counter, "max_page_items" => $page_items, "selected_item" => $selected_item, "cur_page" => $cur_page, "data" => $datas)
		);
	return json_encode($output);
}

function GetOrderedList($category_id = NULL, $all = false, $fav = NULL, $orderby = NULL)
{
	global $dev;
	global $player;
	global $ipTV_db;
	$page = (isset($_REQUEST["p"]) ? intval($_REQUEST["p"]) : 0);
	$page_items = 14;
	$default_page = false;
	$streams = getstreamsfromuser($dev["total_info"]["user_id"], "live", $category_id, $orderby);
	$counter = count($streams["streams"]);
	$ch_idx = 0;

	if ($page == 0) {
		$default_page = true;
		$page = ceil($ch_idx / $page_items);

		if ($page == 0) {
			$page = 1;
		}
	}

	if (!$all) {
		$streams = array_slice($streams["streams"], ($page - 1) * $page_items, $page_items);
	}
	else {
		$streams = $streams["streams"];
	}

	$epgInfo = "";
	$datas = array();
	$i = 1;

	foreach ($streams as $order_id => $stream ) {
		if (!is_null($fav) && ($fav == 1)) {
			if (!in_array($stream["id"], $dev["fav_channels"]["live"])) {
				continue;
			}
		}

		if ($stream["direct_source"] == 0) {
			$stream_url = ipTV_lib::$StreamingServers[SERVER_ID]["site_url"] . "live/{$dev["total_info"]["username"]}/{$dev["total_info"]["password"]}/{$stream["id"]}." . ipTV_lib::$settings["mag_container"];
		}
		else {
			list($stream_url) = json_decode($stream["stream_source"], true);
		}

		$datas[] = array(
			"id"                          => $stream["id"],
			"name"                        => $stream["stream_display_name"],
			"number"                      => (string) (($page - 1) * $page_items) + $i++,
			"censored"                    => "0",
			"cmd"                         => $player . $stream_url,
			"cost"                        => "0",
			"count"                       => "0",
			"status"                      => "1",
			"tv_genre_id"                 => $stream["category_id"],
			"base_ch"                     => "1",
			"hd"                          => "0",
			"xmltv_id"                    => !empty($stream["channel_id"]) ? $stream["channel_id"] : "",
			"service_id"                  => "",
			"bonus_ch"                    => "0",
			"volume_correction"           => "0",
			"use_http_tmp_link"           => "0",
			"mc_cmd"                      => 1,
			"enable_tv_archive"           => 0 < $stream["tv_archive_duration"] ? 1 : 0,
			"wowza_tmp_link"              => "0",
			"wowza_dvr"                   => "0",
			"monitoring_status"           => "1",
			"enable_monitoring"           => "0",
			"enable_wowza_load_balancing" => "0",
			"cmd_1"                       => "",
			"cmd_2"                       => "",
			"cmd_3"                       => "",
			"logo"                        => $stream["stream_icon"],
			"correct_time"                => "0",
			"allow_pvr"                   => "",
			"allow_local_pvr"             => "",
			"modified"                    => "",
			"allow_local_timeshift"       => "1",
			"nginx_secure_link"           => "0",
			"tv_archive_duration"         => 0 < $stream["tv_archive_duration"] ? $stream["tv_archive_duration"] * 24 : 0,
			"lock"                        => $stream["is_adult"],
			"fav"                         => in_array($stream["id"], $dev["fav_channels"]["live"]) ? 1 : 0,
			"archive"                     => 0 < $stream["tv_archive_duration"] ? 1 : 0,
			"genres_str"                  => "",
			"cur_playing"                 => "[No channel info]",
			"epg"                         => "",
			"open"                        => 1,
			"cmds"                        => array(
				array("id" => $stream["id"], "ch_id" => $stream["id"], "priority" => "0", "url" => $player . ipTV_lib::$StreamingServers[SERVER_ID]["site_url"] . "live/{$dev["total_info"]["username"]}/{$dev["total_info"]["password"]}/{$stream["id"]}." . ipTV_lib::$settings["mag_container"], "status" => "1", "use_http_tmp_link" => "0", "wowza_tmp_link" => "0", "user_agent_filter" => "", "use_load_balancing" => "0", "changed" => "", "enable_monitoring" => "0", "enable_balancer_monitoring" => "0", "nginx_secure_link" => "0", "flussonic_tmp_link" => "0")
				),
			"use_load_balancing"          => 0,
			"pvr"                         => 0
			);
	}

	if ($default_page) {
		$cur_page = $page;
		$selected_item = $ch_idx - (($page - 1) * $page_items);
	}
	else {
		$cur_page = 0;
		$selected_item = 0;
	}

	$output = array(
		"js" => array("total_items" => $counter, "max_page_items" => $page_items, "selected_item" => $all ? 0 : $selected_item, "cur_page" => $all ? 0 : $cur_page, "data" => $datas)
		);
	return json_encode($output);
}

function getDataTable()
{
	global $dev;
	global $ipTV_db;
	$page = intval($_REQUEST["p"]);
	$ch_id = intval($_REQUEST["ch_id"]);
	$from = $_REQUEST["from"];
	$to = $_REQUEST["to"];
	$default_page = false;
	$page_items = 10;
	$streams = getstreamsfromuser($dev["total_info"]["user_id"], "live");
	$all_user_ids = array_keys($streams["streams"]);
	$all_user_channels_info = array_values($streams["streams"]);
	$dvb_channels = array();
	$dvb_ch_idx = NULL;
	$ipTV_db->query("SELECT * FROM `streams` WHERE `id` = '%d'", $ch_id);
	$channel = $ipTV_db->get_row();

	if (empty($channel)) {
		foreach ($dvb_channels as $dvb_channel ) {
			if ($dvb_channel["id"] == $ch_id) {
				$channel = $dvb_channel;
				break;
			}
		}

		for ($i = 0; $i < count($dvb_channels); $i++) {
			if ($dvb_channels[$i]["id"] == $ch_id) {
				$channel = $dvb_channels[$i];
				$dvb_ch_idx = $i;
			}
		}

		if ($dvb_ch_idx != NULL) {
			$dvb_ch_idx++;
		}
	}

	$total_channels = count($all_user_ids);
	$total_iptv_channels = $total_channels;
	$total_channels += count($dvb_channels);
	$ch_idx = array_search($channel["id"], $all_user_ids);
	$ch_idx += $dvb_ch_idx;

	if ($ch_idx === false) {
		$ch_idx = 0;
	}

	if ($page == 0) {
		$default_page = true;
		$page = ceil($ch_idx / $page_items);

		if ($page == 0) {
			$page == 1;
		}
	}

	$ch_idx = $ch_idx - (($page - 1) * $page_items);
	$user_channels = array_slice($all_user_channels_info, ($page - 1) * $page_items, $page_items);
	$total_iptv_pages = ceil($total_iptv_channels / $page_items);

	if (count($user_channels) < $page_items) {
		if ($page == $total_iptv_pages) {
			$dvb_part_length = $page_items - ($total_iptv_channels % $page_items);
		}
		else {
			$dvb_part_length = $page_items;
		}

		if ($total_iptv_pages < $page) {
			$dvb_part_offset = (($page - $total_iptv_pages - 1) * $page_items) + ($page_items - ($total_iptv_channels % $page_items));
		}
		else {
			$dvb_part_offset = 0;
		}

		if (isset($_REQUEST["p"])) {
			$dvb_channels = array_splice($dvb_channels, $dvb_part_offset, $dvb_part_length);
		}

		$user_channels = array_merge($user_channels, $dvb_channels);
	}

	$display_channels_ids = array();

	for ($i = 0; $i < count($user_channels); $i++) {
		$display_channels_ids[] = $user_channels[$i]["id"];
	}

	$ipTV_db->query("\n                SELECT t1.id as stream_id,t2.*\n                FROM `streams` t1\n                LEFT JOIN `epg_data` t2 ON t1.channel_id = t2.channel_id AND t1.epg_lang = t2.lang AND t2.`start` >= '%d' AND t2.`end` <= '%d'\n                WHERE t1.id IN(" . implode(",", $display_channels_ids) . ")", strtotime($from), strtotime($to));
	$raw_epg = $ipTV_db->get_rows();
	$result = array();
	$i = 0;
	$output = array();
	$key = 0;

	foreach ($display_channels_ids as $stream_id ) {
		$channel = $streams["streams"][$stream_id];
		$result[$key] = array("ch_id" => $stream_id, "name" => $channel["stream_display_name"], "number" => $i++, "ch_type" => isset($channel["type"]) && ($channel["type"] == "dvb") ? "dvb" : "iptv", "dvb_id" => isset($channel["type"]) && ($channel["type"] == "dvb") ? $channel["dvb_id"] : NULL, "epg_container" => 1);
		$epg_dat = array();
		$epg_key = 0;

		foreach (epg_search($raw_epg, "stream_id", $stream_id) as $epg ) {
			if (!empty($epg["epg_id"])) {
				$epg_dat[$epg_key]["id"] = $epg["id"];
				$epg_dat[$epg_key]["ch_id"] = $epg["stream_id"];
				$epg_dat[$epg_key]["time"] = date("Y-m-d H:i:s", $epg["start"]);
				$epg_dat[$epg_key]["time_to"] = date("Y-m-d H:i:s", $epg["end"]);
				$epg_dat[$epg_key]["duration"] = $epg["end"] - $epg["start"];
				$epg_dat[$epg_key]["name"] = base64_decode($epg["title"]);
				$epg_dat[$epg_key]["descr"] = base64_decode($epg["description"]);
				$epg_dat[$epg_key]["real_id"] = $epg["stream_id"] . "_" . $epg["start"];
				$epg_dat[$epg_key]["category"] = "";
				$epg_dat[$epg_key]["director"] = "";
				$epg_dat[$epg_key]["actor"] = "";
				$epg_dat[$epg_key]["start_timestamp"] = $epg["start"];
				$epg_dat[$epg_key]["stop_timestamp"] = $epg["end"];
				$epg_dat[$epg_key]["t_time"] = date("h:i A", $epg["start"]);
				$epg_dat[$epg_key]["t_time_to"] = date("h:i A", $epg["end"]);
				$epg_dat[$epg_key]["display_duration"] = $epg["end"] - $epg["start"];
				$epg_dat[$epg_key]["larr"] = 0;
				$epg_dat[$epg_key]["rarr"] = 0;
				$epg_dat[$epg_key]["mark_rec"] = 0;
				$epg_dat[$epg_key]["mark_memo"] = 0;
				$epg_dat[$epg_key]["mark_archive"] = 0;
				$epg_dat[$epg_key++]["on_date"] = date("l d.m.Y", $epg["start"]);
			}
		}

		$result[$key++]["epg"] = $epg_dat;
	}

	$time_marks = array();
	$from_ts = strtotime($from);
	$to_ts = strtotime($to);
	$time_marks[] = date(_("H:i"), $from_ts);
	$time_marks[] = date(_("H:i"), $from_ts + 1800);
	$time_marks[] = date(_("H:i"), $from_ts + (2 * 1800));
	$time_marks[] = date(_("H:i"), $from_ts + (3 * 1800));

	if (!in_array($ch_id, $display_channels_ids)) {
		$ch_idx = 0;
		$page = 0;
	}
	else {
		$ch_idx = array_search($ch_id, $display_channels_ids) + 1;
	}

	return array("total_items" => $total_channels, "max_page_items" => $page_items, "cur_page" => $page, "selected_item" => $ch_idx, "time_marks" => $time_marks, "from_ts" => $from_ts, "to_ts" => $to_ts, "data" => $result);
}

require "./init.php";
include "./mag_data.php";
@header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
@header("Cache-Control: post-check=0, pre-check=0", false);
@header("Pragma: no-cache");
@header("Content-type: text/javascript");
$timestamp = time();
$req_ip = (!empty($_SERVER["REMOTE_ADDR"]) ? $_SERVER["REMOTE_ADDR"] : NULL);
$req_type = (!empty($_REQUEST["type"]) ? $_REQUEST["type"] : NULL);
$req_action = (!empty($_REQUEST["action"]) ? $_REQUEST["action"] : NULL);
$sn = (!empty($_REQUEST["sn"]) ? $_REQUEST["sn"] : NULL);
$stb_type = (!empty($_REQUEST["stb_type"]) ? $_REQUEST["stb_type"] : NULL);
$mac = (!empty($_REQUEST["mac"]) ? $_REQUEST["mac"] : NULL);
$ver = (!empty($_REQUEST["ver"]) ? $_REQUEST["ver"] : NULL);
$user_agent = (!empty($_SERVER["HTTP_X_USER_AGENT"]) ? $_SERVER["HTTP_X_USER_AGENT"] : NULL);
$image_version = (!empty($_REQUEST["image_version"]) ? $_REQUEST["image_version"] : NULL);
$device_id = (!empty($_REQUEST["device_id"]) ? $_REQUEST["device_id"] : NULL);
$device_id2 = (!empty($_REQUEST["device_id2"]) ? $_REQUEST["device_id2"] : NULL);
$hw_version = (!empty($_REQUEST["hw_version"]) ? $_REQUEST["hw_version"] : NULL);
$gmode = (!empty($_REQUEST["gmode"]) ? intval($_REQUEST["gmode"]) : NULL);
$continue = false;
$debug = false;
$live_categories = GetCategories("live");
$movie_categories = GetCategories("movie");
if (($req_type == "stb") && ($req_action == "handshake")) {
	$output["js"]["token"] = strtoupper(md5(mktime(1) . uniqid()));
	exit(json_encode($output));
}

$dev = array();

if ($dev = portal_auth($sn, $mac, $ver, $stb_type, $image_version, $device_id, $device_id2, $hw_version, $req_ip)) {
	$continue = true;
}
else {
	if (!empty($_SERVER["HTTP_COOKIE"]) || $debug) {
		if ($debug) {
			$mac = base64_encode("00:1A:79:24:EC:F5");
		}
		else {
			$mac = get_from_cookie($_SERVER["HTTP_COOKIE"], "mac");
		}

		if (!empty($mac)) {
			$ipTV_db->query("SELECT * FROM `mag_devices` WHERE `mac` = '%s' LIMIT 1", $mac);

			if (0 < $ipTV_db->num_rows()) {
				$row = $ipTV_db->get_row();
				$dev["mag_info_db"] = prepair_mag_cols($row);
				$dev["fav_channels"] = json_decode($row["fav_channels"], true);

				if (empty($dev["fav_channels"])) {
					$dev["fav_channels"] = array();
					$dev["fav_channels"]["live"] = array();
					$dev["fav_channels"]["movie"] = array();
					$dev["fav_channels"]["radio_streams"] = array();
				}

				$ipTV_db->query("SELECT * FROM `users` WHERE `id` = '%d'", $dev["mag_info_db"]["user_id"]);
				$dev["total_info"] = $ipTV_db->get_row();
				$dev["total_info"]["allowed_ips"] = json_decode($dev["total_info"]["allowed_ips"], true);
				$dev["total_info"] = array_merge($dev["mag_info_db"], $dev["total_info"]);
				$continue = true;
			}
		}
	}
	else {
		exit();
	}
}

$dev["mag_info_db"] = (empty($dev["mag_info_db"]) ? array() : $dev["mag_info_db"]);
$dev["total_info"] = (empty($dev["total_info"]) ? array() : $dev["total_info"]);
$dev["total_info"]["exp_date"] = (empty($dev["total_info"]["exp_date"]) ? array() : $dev["total_info"]["exp_date"]);
$portal_status = (!empty($dev["total_info"]) && !empty($dev["mag_info_db"]) && (empty($dev["total_info"]["allowed_ips"]) || in_array($req_ip, $dev["total_info"]["allowed_ips"])) && ($dev["total_info"]["admin_enabled"] == 1) && ($dev["total_info"]["enabled"] == 1) && (is_null($dev["total_info"]["exp_date"]) || (time() < $dev["total_info"]["exp_date"])) ? 0 : 1);

switch ($req_type) {
case "stb":
	switch ($req_action) {
	case "get_profile":
		$total = array_merge($_MAG_DATA["get_profile"], $dev["mag_info_db"]);
		$total["status"] = $portal_status;
		$total["update_url"] = ipTV_lib::$settings["update_url"];
		$total["test_download_url"] = ipTV_lib::$settings["test_download_url"];
		$total["default_timezone"] = ipTV_lib::$settings["default_timezone"];
		$total["default_locale"] = ipTV_lib::$settings["default_locale"];
		$total["allowed_stb_types"] = json_decode(ipTV_lib::$settings["allowed_stb_types"], true);
		$total["expires"] = $dev["total_info"]["exp_date"];
		$total["storages"] = array();
		exit(json_encode(array("js" => $total)));
		break;

	case "get_localization":
		exit(json_encode(array("js" => $_MAG_DATA["get_localization"])));
		break;

	case "log":
		exit(json_encode(array("js" => 1)));
		break;

	case "get_modules":
		$modules = array(
			"js" => array("all_modules" => $_MAG_DATA["all_modules"], "switchable_modules" => $_MAG_DATA["switchable_modules"], "disabled_modules" => $_MAG_DATA["disabled_modules"], "restricted_modules" => $_MAG_DATA["restricted_modules"], "template" => $_MAG_DATA["template"])
			);
		exit(json_encode($modules));
		break;
	}

	break;

case "watchdog":
	$ipTV_db->query("UPDATE `mag_devices` SET `last_watchdog` = '%d' WHERE `mag_id` = '%d'", time(), $dev["total_info"]["mag_id"]);

	switch ($req_action) {
	case "get_events":
		$ipTV_db->query("SELECT * FROM `mag_events` WHERE `mag_device_id` = '%d' AND `status` = 0 ORDER BY `id` ASC LIMIT 1", $dev["total_info"]["mag_id"]);

		if (0 < $ipTV_db->num_rows()) {
			$events = $ipTV_db->get_row();
			$ipTV_db->query("SELECT count(*) FROM `mag_events` WHERE `mag_device_id` = '%d' AND `status` = 0 ", $dev["total_info"]["mag_id"]);
			$msgs = $ipTV_db->get_col();
			$data = array(
				"data" => array(
					"msgs"                   => $msgs,
					"id"                     => $events["id"],
					"event"                  => $events["event"],
					"need_confirm"           => $events["need_confirm"],
					"msg"                    => $events["msg"],
					"reboot_after_ok"        => $events["reboot_after_ok"],
					"auto_hide_timeout"      => $events["auto_hide_timeout"],
					"send_time"              => date("d-m-Y H:i:s", $events["send_time"]),
					"additional_services_on" => $events["additional_services_on"],
					"updated"                => array("anec" => $events["anec"], "vclub" => $events["vclub"])
					)
				);
			$auto_status = array("reboot", "reload_portal", "play_channel", "cut_off");

			if (in_array($events["event"], $auto_status)) {
				$ipTV_db->query("UPDATE `mag_events` SET `status` = 1 WHERE `id` = '%d'", $events["id"]);
			}

			exit(json_encode(array("js" => $data)));
		}

		break;

	case "confirm_event":
		if (!empty(ipTV_lib::$request["event_active_id"])) {
			$event_active_id = ipTV_lib::$request["event_active_id"];
			$ipTV_db->query("UPDATE `mag_events` SET `status` = 1 WHERE `id` = '%d'", $event_active_id);

			if (0 < $ipTV_db->affected_rows()) {
				exit(json_encode(array(
	"js" => array("data" => "ok")
	)));
			}
		}

		break;
	}
}

if (!$continue) {
	if (ipTV_lib::$settings["portal_block"] == 1) {
		BlockIP($_SERVER["REMOTE_ADDR"], "FLOODING PORTAL");
	}
}

if ($portal_status == 1) {
	if (ipTV_lib::$settings["portal_block"] == 1) {
		BlockIP($_SERVER["REMOTE_ADDR"], "FLOODING PORTAL");
	}
}

if ($dev["total_info"]["mag_player"]) {
	$player = $dev["total_info"]["mag_player"] . " ";
}
else {
	$player = "";
}

$player = "ffmpeg ";

switch ($req_type) {
case "stb":
	switch ($req_action) {
	case "get_preload_images":
		switch ($gmode) {
		case "720":
			exit(json_encode(array("js" => $_MAG_DATA["gmode_720"])));
			break;

		case "480":
			exit(json_encode(array("js" => $_MAG_DATA["gmode_480"])));
			break;

		default:
			exit(json_encode(array("js" => $_MAG_DATA["gmode_default"])));
		}

		break;

	case "get_settings_profile":
		$ipTV_db->query("SELECT * FROM `mag_devices` WHERE `mag_id` = '%d'", $dev["total_info"]["mag_id"]);
		$settings_info = $ipTV_db->get_row();
		$_MAG_DATA["settings_array"]["js"]["parent_password"] = $settings_info["parent_password"];
		$_MAG_DATA["settings_array"]["js"]["update_url"] = ipTV_lib::$settings["update_url"];
		$_MAG_DATA["settings_array"]["js"]["test_download_url"] = ipTV_lib::$settings["test_download_url"];
		$_MAG_DATA["settings_array"]["js"]["playback_buffer_size"] = $settings_info["playback_buffer_size"];
		$_MAG_DATA["settings_array"]["js"]["screensaver_delay"] = $settings_info["screensaver_delay"];
		$_MAG_DATA["settings_array"]["js"]["plasma_saving"] = $settings_info["plasma_saving"];
		$_MAG_DATA["settings_array"]["js"]["spdif_mode"] = $settings_info["spdif_mode"];
		$_MAG_DATA["settings_array"]["js"]["ts_enabled"] = $settings_info["ts_enabled"];
		$_MAG_DATA["settings_array"]["js"]["ts_enable_icon"] = $settings_info["ts_enable_icon"];
		$_MAG_DATA["settings_array"]["js"]["ts_path"] = $settings_info["ts_path"];
		$_MAG_DATA["settings_array"]["js"]["ts_max_length"] = $settings_info["ts_max_length"];
		$_MAG_DATA["settings_array"]["js"]["ts_buffer_use"] = $settings_info["ts_buffer_use"];
		$_MAG_DATA["settings_array"]["js"]["ts_action_on_exit"] = $settings_info["ts_action_on_exit"];
		$_MAG_DATA["settings_array"]["js"]["ts_delay"] = $settings_info["ts_delay"];
		$_MAG_DATA["settings_array"]["js"]["hdmi_event_reaction"] = $settings_info["hdmi_event_reaction"];
		$_MAG_DATA["settings_array"]["js"]["pri_audio_lang"] = $_MAG_DATA["get_profile"]["pri_audio_lang"];
		$_MAG_DATA["settings_array"]["js"]["show_after_loading"] = $settings_info["show_after_loading"];
		$_MAG_DATA["settings_array"]["js"]["sec_audio_lang"] = $_MAG_DATA["get_profile"]["sec_audio_lang"];
		$_MAG_DATA["settings_array"]["js"]["pri_subtitle_lang"] = $_MAG_DATA["get_profile"]["pri_subtitle_lang"];
		$_MAG_DATA["settings_array"]["js"]["sec_subtitle_lang"] = $_MAG_DATA["get_profile"]["sec_subtitle_lang"];
		exit(json_encode($_MAG_DATA["settings_array"]));
		break;

	case "get_locales":
		$ipTV_db->query("SELECT `locale` FROM `mag_devices` WHERE `mag_id` = '%d'", $dev["total_info"]["mag_id"]);
		$selected = $ipTV_db->get_row();
		$output = array();

		foreach ($_MAG_DATA["get_locales"] as $country => $code ) {
			$selected = ($selected["locale"] == $code ? 1 : 0);
			$output[] = array("label" => $country, "value" => $code, "selected" => $selected);
		}

		exit(json_encode(array("js" => $output)));
		break;

	case "get_countries":
		exit(json_encode(array("js" => true)));
		break;

	case "get_timezones":
		exit(json_encode(array("js" => true)));
		break;

	case "get_cities":
		exit(json_encode(array("js" => true)));
		break;

	case "get_tv_aspects":
		if (!empty($dev["mag_info_db"]["aspect"])) {
			exit($dev["mag_info_db"]["aspect"]);
		}
		else {
			exit(json_encode($dev["mag_info_db"]["aspect"]));
		}

		break;

	case "set_volume":
		$volume = ipTV_lib::$request["vol"];

		if (!empty($volume)) {
			$ipTV_db->query("UPDATE `mag_devices` SET `volume` = '%d' WHERE `mag_id` = '%d'", $volume, $dev["mag_info_db"]["mag_id"]);

			if (0 < $ipTV_db->affected_rows()) {
				exit(json_encode(array("data" => true)));
			}
		}

		break;

	case "set_aspect":
		$ch_id = ipTV_lib::$request["ch_id"];
		$req_aspect = ipTV_lib::$request["aspect"];
		$current_aspect = $dev["mag_info_db"]["aspect"];

		if (empty($current_aspect)) {
			$ipTV_db->query("UPDATE `mag_devices` SET `aspect` = '%s' WHERE mag_id = '%d'", json_encode(array(
	"js" => array($ch_id => $req_aspect)
	)), $dev["mag_info_db"]["mag_id"]);
		}
		else {
			$current_aspect = json_decode($current_aspect, true);
			$current_aspect["js"][$ch_id] = $req_aspect;
			$ipTV_db->query("UPDATE `mag_devices` SET `aspect` = '%s' WHERE mag_id = '%d'", json_encode($current_aspect), $dev["mag_info_db"]["mag_id"]);
			exit(json_encode(array("js" => true)));
		}

		exit("Identification failed");
		break;

	case "set_stream_error":
		exit(json_encode(array("js" => true)));
		break;

	case "set_screensaver_delay":
		if (!empty($_SERVER["HTTP_COOKIE"])) {
			$screensaver_delay = intval($_REQUEST["screensaver_delay"]);
			$ipTV_db->query("UPDATE `mag_devices` SET `screensaver_delay` = '%d' WHERE `mag_id` = '%d'", $screensaver_delay, $dev["total_info"]["mag_id"]);
			exit(json_encode(array("js" => true)));
		}
		else {
			exit("Identification failed");
		}

		break;

	case "set_playback_buffer":
		if (!empty($_SERVER["HTTP_COOKIE"])) {
			$playbacl_budder_bytes = intval($_REQUEST["playback_buffer_bytes"]);
			$playback_buffer_size = intval($_REQUEST["playback_buffer_size"]);
			$ipTV_db->query("UPDATE `mag_devices` SET `playback_buffer_bytes` = '%d' , `playback_buffer_size` = '%d' WHERE `mag_id` = '%d'", $playbacl_budder_bytes, $playback_buffer_size, $dev["total_info"]["mag_id"]);
			exit(json_encode(array("js" => true)));
		}
		else {
			exit("Identification failed");
		}

		break;

	case "set_plasma_saving":
		if (!empty($_SERVER["HTTP_COOKIE"])) {
			$plasma_saving = intval($_REQUEST["plasma_saving"]);
			$ipTV_db->query("UPDATE `mag_devices` SET `plasma_saving` = '%d' WHERE `mag_id` = '%d'", $plasma_saving, $dev["total_info"]["mag_id"]);
			exit(json_encode(array("js" => true)));
		}
		else {
			exit("Identification failed");
		}

		break;

	case "set_parent_password":
		if (!empty($_SERVER["HTTP_COOKIE"]) && isset($_REQUEST["parent_password"]) && isset($_REQUEST["pass"]) && isset($_REQUEST["repeat_pass"]) && ($_REQUEST["pass"] == $_REQUEST["repeat_pass"])) {
			$ipTV_db->query("SELECT `parent_password` FROM `mag_devices` WHERE `mag_id` = '%d'", $dev["total_info"]["mag_id"]);

			if (0 < $ipTV_db->num_rows()) {
				$pass = $_REQUEST["pass"];
				$repeat_pass = $_REQUEST["repeat_pass"];
				$ipTV_db->query("UPDATE `mag_devices` SET `parent_password` = '%s' WHERE `mag_id` = '%d'", $pass, $dev["total_info"]["mag_id"]);
				exit(json_encode(array("js" => true)));
			}
		}
		else {
			exit("Identification failed");
		}

		break;

	case "set_locale":
		if (!empty($_SERVER["HTTP_COOKIE"])) {
			exit(json_encode(array("js" => true)));
		}
		else {
			exit("Identification failed");
		}

		break;

	case "set_hdmi_reaction":
		if (!empty($_SERVER["HTTP_COOKIE"]) && isset($_REQUEST["data"])) {
			$hdmi_event_reaction = $_REQUEST["data"];
			$ipTV_db->query("UPDATE `mag_devices` SET `hdmi_event_reaction` = '%s' WHERE `mag_id` = '%d'", $hdmi_event_reaction, $dev["total_info"]["mag_id"]);
			exit(json_encode(array("js" => true)));
		}
		else {
			exit("Identification failed");
		}
	}

	break;

case "itv":
	switch ($req_action) {
	case "set_fav":
		$fav_channels = (empty($_REQUEST["fav_ch"]) ? "" : $_REQUEST["fav_ch"]);
		$fav_channels = array_filter(array_map("intval", explode(",", $fav_channels)));
		$dev["fav_channels"]["live"] = $fav_channels;
		$ipTV_db->query("UPDATE `mag_devices` SET `fav_channels` = '%s' WHERE `mag_id` = '%d'", json_encode($dev["fav_channels"]), $dev["total_info"]["mag_id"]);
		exit(json_encode(array("js" => true)));
		break;

	case "get_fav_ids":
		echo json_encode(array("js" => $dev["fav_channels"]["live"]));
		exit();
		break;

	case "get_all_channels":
		exit(GetOrderedList(NULL, true));
		break;

	case "get_ordered_list":
		$fav = (!empty($_REQUEST["fav"]) ? 1 : NULL);
		$sortby = (!empty($_REQUEST["sortby"]) ? $_REQUEST["sortby"] : NULL);
		$genre = (empty($_REQUEST["genre"]) || !is_numeric($_REQUEST["genre"]) ? NULL : intval($_REQUEST["genre"]));
		exit(GetOrderedList($genre, false, $fav, $sortby));
		break;

	case "get_all_fav_channels":
		$genre = (empty($_REQUEST["genre"]) || !is_numeric($_REQUEST["genre"]) ? NULL : intval($_REQUEST["genre"]));
		exit(GetOrderedList($genre, true, 1));
		break;

	case "get_epg_info":
		$streams = GetStreamsFromUser($dev["total_info"]["user_id"], "live");
		$epg = array(
			"js" => array()
			);

		foreach ($streams["streams"] as $order_id => $stream ) {
			$ipTV_db->query("SELECT * FROM `epg_data` WHERE `start` >= '%d' AND `channel_id` = '%s' AND `lang` = '%s' ORDER BY `start` ASC LIMIT 10", $timestamp, $stream["channel_id"], $stream["epg_lang"]);
			$general_epg_datas = $ipTV_db->get_rows();

			for ($i = 0; $i < count($general_epg_datas); $i++) {
				$epg["js"]["data"][$stream["id"]][$i]["id"] = $general_epg_datas[$i]["id"];
				$epg["js"]["data"][$stream["id"]][$i]["ch_id"] = $stream["id"];
				$epg["js"]["data"][$stream["id"]][$i]["time"] = date("Y-m-d H:i:s", $general_epg_datas[$i]["start"]);
				$epg["js"]["data"][$stream["id"]][$i]["time_to"] = date("Y-m-d H:i:s", $general_epg_datas[$i]["end"]);
				$epg["js"]["data"][$stream["id"]][$i]["duration"] = $general_epg_datas[$i]["end"] - $general_epg_datas[$i]["start"];
				$epg["js"]["data"][$stream["id"]][$i]["name"] = base64_decode($general_epg_datas[$i]["title"]);
				$epg["js"]["data"][$stream["id"]][$i]["descr"] = base64_decode($general_epg_datas[$i]["description"]);
				$epg["js"]["data"][$stream["id"]][$i]["real_id"] = $stream["id"] . "_" . $general_epg_datas[$i]["start"];
				$epg["js"]["data"][$stream["id"]][$i]["category"] = "";
				$epg["js"]["data"][$stream["id"]][$i]["director"] = "";
				$epg["js"]["data"][$stream["id"]][$i]["actor"] = "";
				$epg["js"]["data"][$stream["id"]][$i]["start_timestamp"] = $general_epg_datas[$i]["start"];
				$epg["js"]["data"][$stream["id"]][$i]["stop_timestamp"] = $general_epg_datas[$i]["end"];
				$epg["js"]["data"][$stream["id"]][$i]["t_time"] = date("h:i A", $general_epg_datas[$i]["start"]);
				$epg["js"]["data"][$stream["id"]][$i]["t_time_to"] = date("h:i A", $general_epg_datas[$i]["end"]);
				$epg["js"]["data"][$stream["id"]][$i]["display_duration"] = $general_epg_datas[$i]["end"] - $general_epg_datas[$i]["start"];
				$epg["js"]["data"][$stream["id"]][$i]["larr"] = 0;
				$epg["js"]["data"][$stream["id"]][$i]["rarr"] = 0;
				$epg["js"]["data"][$stream["id"]][$i]["mark_rec"] = 0;
				$epg["js"]["data"][$stream["id"]][$i]["mark_memo"] = 0;
				$epg["js"]["data"][$stream["id"]][$i]["mark_archive"] = 0;
				$epg["js"]["data"][$stream["id"]][$i]["on_date"] = date("l d.m.Y", $general_epg_datas[$i]["start"]);
			}
		}

		exit(json_encode($epg));
		break;

	case "set_fav_status":
		exit(json_encode(array(
	"js" => array()
	)));
		break;

	case "get_short_epg":
		if (!empty($_REQUEST["ch_id"])) {
			$ch_id = $_REQUEST["ch_id"];
			$epg = array(
				"js" => array()
				);
			$ipTV_db->query("SELECT `channel_id`,`epg_lang`,`epg_id` FROM `streams` WHERE `id` = '%d' AND epg_id IS NOT NULL", $ch_id);

			if (0 < $ipTV_db->num_rows()) {
				$epg_data = $ipTV_db->get_row();
				$ipTV_db->query("SELECT * FROM `epg_data` WHERE `epg_id` = '%d' AND `channel_id` = '%s' AND `lang` = '%s' AND `end` >= '%d' ORDER BY `start` ASC LIMIT 4", $epg_data["epg_id"], $epg_data["channel_id"], $epg_data["epg_lang"], time());

				if (0 < $ipTV_db->num_rows()) {
					$epg_dats = $ipTV_db->get_rows();

					for ($i = 0; $i < count($epg_dats); $i++) {
						$epg["js"][$i]["id"] = $epg_dats[$i]["id"];
						$epg["js"][$i]["ch_id"] = $ch_id;
						$epg["js"][$i]["time"] = date("Y-m-d H:i:s", $epg_dats[$i]["start"]);
						$epg["js"][$i]["time_to"] = date("Y-m-d H:i:s", $epg_dats[$i]["end"]);
						$epg["js"][$i]["duration"] = $epg_dats[$i]["end"] - $epg_dats[$i]["start"];
						$epg["js"][$i]["name"] = base64_decode($epg_dats[$i]["title"]);
						$epg["js"][$i]["descr"] = base64_decode($epg_dats[$i]["description"]);
						$epg["js"][$i]["real_id"] = $ch_id . "_" . $epg_dats[$i]["start"];
						$epg["js"][$i]["category"] = "";
						$epg["js"][$i]["director"] = "";
						$epg["js"][$i]["actor"] = "";
						$epg["js"][$i]["start_timestamp"] = $epg_dats[$i]["start"];
						$epg["js"][$i]["stop_timestamp"] = $epg_dats[$i]["end"];
						$epg["js"][$i]["t_time"] = date("h:i A", $epg_dats[$i]["start"]);
						$epg["js"][$i]["t_time_to"] = date("h:i A", $epg_dats[$i]["end"]);
						$epg["js"][$i]["mark_memo"] = 0;
						$epg["js"][$i]["mark_archive"] = 0;
					}
				}
			}

			exit(json_encode($epg));
		}

		exit();
		break;

	case "set_played":
		exit(json_encode(array("js" => true)));
		break;

	case "set_last_id":
		exit(json_encode(array("js" => true)));
		break;

	case "get_genres":
		$output = array();

		if (ipTV_lib::$settings["show_all_category_mag"] == 1) {
			$output["js"][] = array("id" => "*", "title" => "All", "alias" => "All");
		}

		foreach ($live_categories as $live_category_id => $live_category ) {
			$output["js"][] = array("id" => $live_category["id"], "title" => $live_category["category_name"], "alias" => $live_category["category_name"]);
		}

		exit(json_encode($output));
		break;
	}

	break;

case "remote_pvr":
	switch ($req_action) {
	case "get_active_recordings":
		exit(json_encode(array(
	"js" => array()
	)));
		break;
	}

	break;

case "media_favorites":
	switch ($req_action) {
	case "get_all":
		exit(json_encode(array(
	"js" => array()
	)));
		break;
	}

	break;

case "tvreminder":
	switch ($req_action) {
	case "get_all_active":
		exit(json_encode(array(
	"js" => array()
	)));
		break;
	}

	break;

case "vod":
	switch ($req_action) {
	case "set_fav":
		if (!empty($_REQUEST["video_id"])) {
			$video_id = intval($_REQUEST["video_id"]);

			if (!in_array($video_id, $dev["fav_channels"]["movie"])) {
				$dev["fav_channels"]["movie"][] = $video_id;
			}

			$ipTV_db->query("UPDATE `mag_devices` SET `fav_channels` = '%s' WHERE `mag_id` = '%d'", json_encode($dev["fav_channels"]), $dev["total_info"]["mag_id"]);
		}

		exit(json_encode(array("js" => true)));
		break;

	case "del_fav":
		if (!empty($_REQUEST["video_id"])) {
			$video_id = intval($_REQUEST["video_id"]);

			foreach ($dev["fav_channels"]["movie"] as $key => $val ) {
				if ($val == $video_id) {
					unset($dev["fav_channels"]["movie"][$key]);
					break;
				}
			}

			$ipTV_db->query("UPDATE `mag_devices` SET `fav_channels` = '%s' WHERE `mag_id` = '%d'", json_encode($dev["fav_channels"]), $dev["total_info"]["mag_id"]);
			break;
		}

		exit(json_encode(array("js" => true)));
		break;

	case "get_categories":
		$output = array();
		$output["js"] = array();

		if (ipTV_lib::$settings["show_all_category_mag"] == 1) {
			$output["js"][] = array("id" => "*", "title" => "All", "alias" => "All");
		}

		foreach ($movie_categories as $movie_category_id => $movie_category ) {
			if ($movie_category["parent_id"] == 0) {
				$output["js"][] = array("id" => $movie_category["id"], "title" => $movie_category["category_name"], "alias" => $movie_category["category_name"]);
			}
		}

		exit(json_encode($output));
		break;

	case "get_genres_by_category_alias":
		$output = array();
		$output["js"][] = array("id" => "*", "title" => "*");

		foreach ($movie_categories as $movie_category_id => $movie_category ) {
			if ($movie_category["parent_id"] == 0) {
				$output["js"][] = array("id" => $movie_category["id"], "title" => $movie_category["category_name"]);
			}
		}

		exit(json_encode($output));
		break;

	case "get_years":
		exit(json_encode($_MAG_DATA["get_years"]));
		break;

	case "get_ordered_list":
		$category = (!empty(ipTV_lib::$request["category"]) && is_numeric(ipTV_lib::$request["category"]) ? ipTV_lib::$request["category"] : NULL);
		$fav = (!empty($_REQUEST["fav"]) ? 1 : NULL);
		$sortby = (!empty($_REQUEST["sortby"]) ? $_REQUEST["sortby"] : "added");
		exit(GetVodOrderedList($category, $fav, $sortby));
		break;

	case "create_link":
		$data = json_decode(base64_decode($_REQUEST["cmd"]), true);
		$series = (empty(ipTV_lib::$request["series"]) ? false : intval(ipTV_lib::$request["series"]));

		if (!empty($series)) {
			$ipTV_db->query("SELECT t1.id,t2.container_extension,t1.direct_source,t1.stream_source\n                                        FROM streams t1\n                                        INNER JOIN `movie_containers` t2 ON t1.target_container_id = t2.container_id\n                                     WHERE t1.`category_id` = '%d' AND t1.`series_no` = '%d' LIMIT 1", $data["sub_category_id"], $series);
			$row = $ipTV_db->get_row();
			$data["movie_id"] = $row["id"];
			$data["movie_container"] = $row["container_extension"];

			if ($row["direct_source"] == 1) {
				list($data["direct_source_url"]) = json_decode($row["stream_source"], true);
			}
		}

		if (empty($data["direct_source_url"])) {
			$url = ipTV_lib::$StreamingServers[SERVER_ID]["site_url"] . "movie/{$dev["total_info"]["username"]}/{$dev["total_info"]["password"]}/{$data["movie_id"]}." . $data["movie_container"];
		}
		else {
			$url = $data["direct_source_url"];
		}

		$output = array(
			"js" => array("id" => $data["movie_id"], "cmd" => $url, "load" => 0, "error" => "", "from_cache" => 1)
			);
		exit(json_encode($output));
		break;

	case "log":
		exit(json_encode(array("js" => 1)));
		break;

	case "get_abc":
		exit(json_encode($_MAG_DATA["get_abc"]));
		break;
	}

	break;

case "downloads":
	switch ($req_action) {
	case "get_all":
		exit(json_encode(array("js" => "\"\"")));
		break;

	case "get_all":
		exit(json_encode(array("js" => true)));
		break;
	}

	break;

case "weatherco":
	switch ($req_action) {
	case "get_current":
		exit(json_encode(array("js" => false)));
		break;
	}

	break;

case "course":
	switch ($req_action) {
	case "get_data":
		exit(json_encode(array("js" => true)));
		break;
	}

	break;

case "account_info":
	switch ($req_action) {
	case "get_terms_info":
		exit(json_encode(array("js" => true)));
		break;

	case "get_payment_info":
		exit(json_encode(array("js" => true)));
		break;

	case "get_main_info":
		exit(json_encode(array("js" => true)));
		break;

	case "get_demo_video_parts":
		exit(json_encode(array("js" => true)));
		break;

	case "get_agreement_info":
		exit(json_encode(array("js" => true)));
		break;
	}

	break;

case "radio":
	switch ($req_action) {
	case "get_ordered_list":
		$fav = (!empty($_REQUEST["fav"]) ? 1 : NULL);
		$sortby = (!empty($_REQUEST["sortby"]) ? $_REQUEST["sortby"] : "added");
		exit(GetRadioOrderedList(NULL, $fav, $sortby));
		break;

	case "get_all_fav_radio":
		exit(GetRadioOrderedList(NULL, 1, NULL));
		break;

	case "set_fav":
		$fav_radio = (empty($_REQUEST["fav_radio"]) ? "" : $_REQUEST["fav_radio"]);
		$fav_radio = array_filter(array_map("intval", explode(",", $fav_radio)));
		$dev["fav_channels"]["radio_streams"] = $fav_radio;
		$ipTV_db->query("UPDATE `mag_devices` SET `fav_channels` = '%s' WHERE `mag_id` = '%d'", json_encode($dev["fav_channels"]), $dev["total_info"]["mag_id"]);
		exit(json_encode(array("js" => true)));
		break;

	case "get_fav_ids":
		exit(json_encode(array("js" => $dev["fav_channels"]["radio_streams"])));
		break;
	}

	break;

case "tv_archive":
	switch ($req_action) {
	case "create_link":
		$cmd = (empty($_REQUEST["cmd"]) ? "" : $_REQUEST["cmd"]);
		$epg_id = intval(basename($cmd));
		$ipTV_db->query("SELECT t2.tv_archive_server_id,t1.start,t1.end,t2.id as stream_id\n                                    FROM epg_data t1\n                                    INNER JOIN `streams` t2 ON t2.epg_id = t1.epg_id AND t2.channel_id = t1.channel_id\n                                    WHERE t1.id = '%d' AND t2.tv_archive_server_id IS NOT NULL", $epg_id);

		if (0 < $ipTV_db->num_rows()) {
			$row = $ipTV_db->get_row();
			$url = $player . ipTV_lib::$StreamingServers[$row["tv_archive_server_id"]]["site_url"] . "streaming/timeshift.php?username={$dev["total_info"]["username"]}&password={$dev["total_info"]["password"]}&start={$row["start"]}&end={$row["end"]}&stream_id={$row["stream_id"]}";
			$output["js"] = array("id" => 0, "cmd" => $url, "storage_id" => "", "load" => 0, "error" => "", "download_cmd" => $url, "to_file" => "");
			exit(json_encode($output));
		}

		break;
	}

	break;

case "epg":
	switch ($req_action) {
	case "get_week":
		$k = -3;
		$i = 0;
		$epg_week = array();
		$curDate = strtotime(date("Y-m-d"));

		while ($k < 10) {
			$thisDate = $curDate + ($k * 86400);
			$epg_week["js"][$i]["f_human"] = date("D d F", $thisDate);
			$epg_week["js"][$i]["f_mysql"] = date("Y-m-d", $thisDate);
			$epg_week["js"][$i]["today"] = ($k == 0 ? 1 : 0);
			$k++;
			$i++;
		}

		exit(json_encode($epg_week));
		break;

	case "get_data_table":
		if (!empty($_REQUEST["ch_id"])) {
			exit(json_encode(array("js" => getDataTable())));
		}

		exit();
		break;

	case "get_simple_data_table":
		if (!empty($_REQUEST["ch_id"]) && !empty($_REQUEST["date"])) {
			$ch_id = $_REQUEST["ch_id"];
			$req_date = $_REQUEST["date"];
			$date = explode("-", $req_date);
			$page = intval($_REQUEST["p"]);
			$page_items = 10;
			$default_page = false;
			$ipTV_db->query("SELECT `tv_archive_duration`,`channel_id`,`epg_lang`,`epg_id` FROM `streams` WHERE `id` = '%d' AND epg_id IS NOT NULL", $ch_id);
			$simple_data_epgs = array();
			$total_items = 0;
			$ch_idx = 0;

			if (0 < $ipTV_db->num_rows()) {
				$stream_row = $ipTV_db->get_row();
				$start_up_limit = mktime(0, 0, 0, $date[1], $date[2], $date[0]);
				$start_dn_limit = mktime(23, 59, 59, $date[1], $date[2], $date[0]);
				$ipTV_db->query("SELECT * FROM `epg_data` WHERE `epg_id` = '%d' AND `channel_id` = '%s' AND `lang` = '%s' AND `start` >= '%d' AND `start` <= '%d' ORDER BY `start` ASC", $stream_row["epg_id"], $stream_row["channel_id"], $stream_row["epg_lang"], $start_up_limit, $start_dn_limit);

				if (0 < $ipTV_db->num_rows()) {
					$simple_data_epgs = $ipTV_db->get_rows();
					$total_items = count($simple_data_epgs);

					foreach ($simple_data_epgs as $key => $epg_data ) {
						if (($epg_data["start"] <= time()) && (time() <= $epg_data["end"])) {
							$ch_idx = $key + 1;
							break;
						}
					}
				}
			}

			if ($page == 0) {
				$default_page = true;
				$page = ceil($ch_idx / $page_items);

				if ($page == 0) {
					$page = 1;
				}

				if ($req_date != date("Y-m-d")) {
					$page = 1;
					$default_page = false;
				}
			}

			$program = array_slice($simple_data_epgs, ($page - 1) * $page_items, $page_items);
			$data = array();

			for ($i = 0; $i < count($program); $i++) {
				$open = 0;

				if (time() <= $program[$i]["end"]) {
					$open = 1;
				}

				$data[$i]["id"] = $program[$i]["id"];
				$data[$i]["ch_id"] = $ch_id;
				$data[$i]["time"] = date("Y-m-d H:i:s", $program[$i]["start"]);
				$data[$i]["time_to"] = date("Y-m-d H:i:s", $program[$i]["end"]);
				$data[$i]["duration"] = $program[$i]["end"] - $program[$i]["start"];
				$data[$i]["name"] = base64_decode($program[$i]["title"]);
				$data[$i]["descr"] = base64_decode($program[$i]["description"]);
				$data[$i]["real_id"] = $ch_id . "_" . $program[$i]["start"];
				$data[$i]["category"] = "";
				$data[$i]["director"] = "";
				$data[$i]["actor"] = "";
				$data[$i]["start_timestamp"] = $program[$i]["start"];
				$data[$i]["stop_timestamp"] = $program[$i]["end"];
				$data[$i]["t_time"] = date("h:i A", $program[$i]["start"]);
				$data[$i]["t_time_to"] = date("h:i A", $program[$i]["end"]);
				$data[$i]["open"] = $open;
				$data[$i]["mark_memo"] = 0;
				$data[$i]["mark_rec"] = 0;
				$data[$i]["mark_archive"] = (!empty($stream_row["tv_archive_duration"]) && ($program[$i]["end"] < time()) && (strtotime("-{$stream_row["tv_archive_duration"]} days") <= $program[$i]["end"]) ? 1 : 0);
			}

			if ($default_page) {
				$cur_page = $page;
				$selected_item = $ch_idx - (($page - 1) * $page_items);
			}
			else {
				$cur_page = 0;
				$selected_item = 0;
			}

			$output = array();
			$output["js"]["cur_page"] = $cur_page;
			$output["js"]["selected_item"] = $selected_item;
			$output["js"]["total_items"] = $total_items;
			$output["js"]["max_page_items"] = $page_items;
			$output["js"]["data"] = $data;
			exit(json_encode($output));
		}

		exit();
		break;

	case "get_data_table":
		$from_ts = $_REQUEST["from_ts"];
		$to_ts = $_REQUEST["to_ts"];
		$from = $_REQUEST["from"];
		$to = $_REQUEST["to"];
		exit();
		break;
	}

	break;
}

?>
