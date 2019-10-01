<?php

require "./init.php";
if (!isset(ipTV_lib::$request["username"]) || !isset(ipTV_lib::$request["password"])) {
	exit("Missing parameters.");
}

$username = ipTV_lib::$request["username"];
$password = ipTV_lib::$request["password"];
$type = (!empty(ipTV_lib::$request["type"]) ? ipTV_lib::$request["type"] : NULL);
$cat_id = (!empty(ipTV_lib::$request["cat_id"]) ? intval(ipTV_lib::$request["cat_id"]) : NULL);
$scat_id = (!empty(ipTV_lib::$request["scat_id"]) ? intval(ipTV_lib::$request["scat_id"]) : NULL);
$url = (!empty($_SERVER["HTTP_HOST"]) ? "http://" . $_SERVER["HTTP_HOST"] . "/" : ipTV_lib::$StreamingServers[SERVER_ID]["site_url"]);

if ($user_infos = ipTV_Stream::GetUserInfo(NULL, $username, $password, true, true, false)) {
	$live_categories = GetCategories("live");
	$vod_categories = GetCategories("movie");
	$live_streams = array();
	$vod_streams = array();

	foreach ($user_infos["channels"] as $user_info ) {
		if ($user_info["live"] == 0) {
			$vod_streams[] = $user_info;
		}
		else {
			$live_streams[] = $user_info;
		}
	}

	switch ($type) {
	case "get_live_categories":
		$xml = new SimpleXMLExtended("<items/>");
		$xml->addChild("playlist_name", "Live [ " . ipTV_lib::$settings["bouquet_name"] . " ]");
		$category = $xml->addChild("category");
		$category->addChild("category_id", 1);
		$category->addChild("category_title", "Live [ " . ipTV_lib::$settings["bouquet_name"] . " ]");
		$channels = $xml->addChild("channel");
		$channels->addChild("title", base64_encode("All"));
		$channels->addChild("description", base64_encode("Live Streams Category [ ALL ]"));
		$channels->addChild("category_id", 0);
		$cdata = $channels->addChild("playlist_url");
		$cdata->addCData($url . "enigma2.php?username=$username&password=$password&type=get_live_streams&cat_id=0" . $category["id"]);

		foreach ($live_categories as $category ) {
			$channels = $xml->addChild("channel");
			$channels->addChild("title", base64_encode($category["category_name"]));
			$channels->addChild("description", base64_encode("Live Streams Category"));
			$channels->addChild("category_id", $category["id"]);
			$cdata = $channels->addChild("playlist_url");
			$cdata->addCData($url . "enigma2.php?username=$username&password=$password&type=get_live_streams&cat_id=" . $category["id"]);
		}

		header("Content-Type: application/xml; charset=utf-8");
		echo $xml->asXML();
		break;

	case "get_vod_categories":
		$xml = new SimpleXMLExtended("<items/>");
		$xml->addChild("playlist_name", "Movie [ " . ipTV_lib::$settings["bouquet_name"] . " ]");
		$category = $xml->addChild("category");
		$category->addChild("category_id", 1);
		$category->addChild("category_title", "Movie [ " . ipTV_lib::$settings["bouquet_name"] . " ]");
		$channels = $xml->addChild("channel");
		$channels->addChild("title", base64_encode("All"));
		$channels->addChild("description", base64_encode("Movie Streams Category [ ALL ]"));
		$channels->addChild("category_id", 0);
		$cdata = $channels->addChild("playlist_url");
		$cdata->addCData($url . "enigma2.php?username=$username&password=$password&type=get_vod_streams&cat_id=0" . $category["id"]);

		foreach ($vod_categories as $category ) {
			$channels = $xml->addChild("channel");
			$channels->addChild("title", base64_encode($category["category_name"]));
			$channels->addChild("description", base64_encode("Movie Streams Category"));
			$channels->addChild("category_id", $category["id"]);
			$cdata = $channels->addChild("playlist_url");
			$cdata->addCData($url . "enigma2.php?username=$username&password=$password&type=get_vod_streams&cat_id=" . $category["id"]);
		}

		header("Content-Type: application/xml; charset=utf-8");
		echo $xml->asXML();
		break;

	case "get_live_streams":
		if (isset($cat_id) || is_null($cat_id)) {
			$cat_id = (is_null($cat_id) ? 0 : $cat_id);
			$xml = new SimpleXMLExtended("<items/>");
			$xml->addChild("playlist_name", "Live [ " . ipTV_lib::$settings["bouquet_name"] . " ]");
			$category = $xml->addChild("category");
			$category->addChild("category_id", 1);
			$category->addChild("category_title", "Live [ " . ipTV_lib::$settings["bouquet_name"] . " ]");

			foreach ($live_streams as $user_info ) {
				if ($cat_id != 0) {
					if ($cat_id != $user_info["category_id"]) {
						continue;
					}
				}

				$ipTV_db->query("SELECT * FROM `epg_data` WHERE `channel_id` = '%s' AND  `end` >= '%d' LIMIT 2", $user_info["channel_id"], time());
				$channel_epgs = $ipTV_db->get_rows();
				$desc = "";
				$short_epg = "";
				$i = 0;

				foreach ($channel_epgs as $row ) {
					$desc .= "[" . date("H:i", $row["start"]) . "] " . base64_decode($row["title"]) . "\n( " . base64_decode($row["description"]) . ")\n";

					if ($i == 0) {
						$short_epg = "[" . date("H:i", $row["start"]) . " - " . date("H:i", $row["end"]) . "] + " . round(($row["end"] - time()) / 60, 1) . " min   " . base64_decode($row["title"]);
						$i++;
					}
				}

				$channels = $xml->addChild("channel");
				$channels->addChild("title", base64_encode($user_info["stream_display_name"] . " " . $short_epg));
				$channels->addChild("description", base64_encode($desc));
				$desc_channel = $channels->addChild("desc_image");
				$desc_channel->addCData($user_info["stream_icon"]);
				$channels->addChild("category_id", $cat_id);
				$cdata = $channels->addChild("stream_url");

				if ($user_info["direct_source"] == 0) {
					$cdata->addCData($url . "live/$username/$password/{$user_info["id"]}.ts");
				}
				else {
					list($source) = json_decode($user_info["stream_source"], true);
					$cdata->addCData($source);
				}
			}

			header("Content-Type: application/xml; charset=utf-8");
			echo $xml->asXML();
		}

		break;

	case "get_vod_streams":
		if (isset($cat_id) || is_null($cat_id)) {
			$cat_id = (is_null($cat_id) ? 0 : $cat_id);
			$xml = new SimpleXMLExtended("<items/>");
			$xml->addChild("playlist_name", "Movie [ " . ipTV_lib::$settings["bouquet_name"] . " ]");
			$category = $xml->addChild("category");
			$category->addChild("category_id", 1);
			$category->addChild("category_title", "Movie [ " . ipTV_lib::$settings["bouquet_name"] . " ]");

			foreach ($vod_streams as $user_info ) {
				if ($cat_id != 0) {
					if ($cat_id != $user_info["category_id"]) {
						continue;
					}
				}

				$movie_propeties = json_decode($user_info["movie_propeties"], true);
				$channels = $xml->addChild("channel");
				$channels->addChild("title", base64_encode($user_info["stream_display_name"]));
				$desc = "";

				if ($movie_propeties) {
					foreach ($movie_propeties as $key => $movie_property ) {
						if ($key == "movie_image") {
							continue;
						}

						$desc .= strtoupper($key) . ": " . $movie_property . "\n";
					}
				}

				$desc_channel = $channels->addChild("desc_image");
				$desc_channel->addCData($movie_propeties["movie_image"]);
				$channels->addChild("description", base64_encode($desc));
				$channels->addChild("category_id", $cat_id);
				$cdata_url = $channels->addChild("stream_url");

				if ($user_info["direct_source"] == 0) {
					$cdata_url->addCData($url . "movie/$username/$password/{$user_info["id"]}.{$user_info["container_extension"]}");
				}
				else {
					list($source) = json_decode($user_info["stream_source"], true);
					$cdata->addCData($source);
				}
			}

			header("Content-Type: application/xml; charset=utf-8");
			echo $xml->asXML();
		}

		break;

	default:
		$xml = new SimpleXMLExtended("<items/>");
		$xml->addChild("playlist_name", ipTV_lib::$settings["bouquet_name"]);
		$category = $xml->addChild("category");
		$category->addChild("category_id", 1);
		$category->addChild("category_title", ipTV_lib::$settings["bouquet_name"]);

		if (!empty($live_streams)) {
			$channels = $xml->addChild("channel");
			$channels->addChild("title", base64_encode("Live Streams"));
			$channels->addChild("description", base64_encode("Live Streams Category"));
			$channels->addChild("category_id", 0);
			$cdata = $channels->addChild("playlist_url");
			$cdata->addCData($url . "enigma2.php?username=$username&password=$password&type=get_live_categories");
		}

		if (!empty($vod_streams)) {
			$channels = $xml->addChild("channel");
			$channels->addChild("title", base64_encode("Vod"));
			$channels->addChild("description", base64_encode("Video On Demand Category"));
			$channels->addChild("category_id", 1);
			$cdata = $channels->addChild("playlist_url");
			$cdata->addCData($url . "enigma2.php?username=$username&password=$password&type=get_vod_categories");
		}

		header("Content-Type: application/xml; charset=utf-8");
		echo $xml->asXML();
	}
}

?>
