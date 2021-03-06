<?php
define("PARSE_MIN_SIZE", 1000000); //bytes
define("ENFORCE_MIN_SIZE", FALSE);

define("LOG_PATH", "/var/log/apache2/access1.log");
define("TMP_LOG_DIR_PATH", "/var/log/apache2/");
define("DATA_PATH", "/var/log/apache2/access2.log");

//define("LOG_PATH", "/home/pi/logdata/access1.log");
//define("TMP_LOG_DIR_PATH", "/home/pi/logdata/");
//define("DATA_PATH", "/home/pi/logdata/access2.log");

define("DB_PATH", "/var/www/db/UserData.db");

class MyDB extends SQLite3
{
	function __construct() {
		$this->open(DB_PATH);
	}
}

function clear_log($source_path, $dest_dir_path = NULL) {
	if (isset($dest_dir_path)) {
		$dest_path = $dest_dir_path . basename($source_path) . ".bak";
	} else {
		$dest_path = $source_path . ".bak";
	}

	copy($source_path, $dest_path);

	//cannot remove file or apache logger will lose pointer to it
	file_put_contents($source_path, "");
	//$handle = fopen($source_path, "w");
	//fclose($handle);

	return $dest_path;
}

function remove_duplicates($source_path, $dest_path) {
	/*
	//entire file at once
	$lines = array_unique(file($source_path));
	file_put_contents($dest_path, implode($lines));
	*/

	/*
	//line by line (large files)
	$handle = fopen($source_path, "r");
	if ($handle) {
		$arr = [];

		while(($line = fgets($handle)) !== false) {
			array_push($arr, $line);
		}

		fclose($handle);
		$lines = array_unique($arr);
		$fp = fopen($dest_path, "w");

		foreach ($lines as $line) {
			fwrite($fp, $line);
		}

		fclose($fp);
	}
	*/

	//linux command
	/*
	$output = shell_exec("sort -u " . $source_path);
	file_put_contents($dest_path, $output);
	*/

	$lines = [];
	exec("sort -u " . $source_path, $lines);
	$file = fopen($dest_path, "w");
	//file_put_contents($dest_path, implode("\n", $lines));

	foreach ($lines as $line) {
		fwrite($file, $line . "\n");
	}

	fclose($file);

	return $dest_path;
}

function process_line($line) {
	$tokens = explode(" ", $line, 3);

	//only handle "GET" requests (content accessed)
	if ($tokens[1] == "GET") {
		$tokens = explode('"', $tokens[2]);
		$content_path = $tokens[0];
		$user_agent = $tokens[1];

		//process content (category and filename)
		$tokens = explode("/", $content_path);
		$main_category = $tokens[3];
		$file_name = $tokens[count($tokens)-1];

		//process user agent (os and browser)
		$ua_arr = get_browser($user_agent, true);
		$browser = $ua_arr['parent'];
		$device_type = $ua_arr['device_type'];
		$os = $ua_arr['platform'];
		//$is_mobile_device = $ua_arr['ismobiledevice'];
		//$is_tablet = $ua_arr['istablet'];

		//String keys
		return ["category"=>$main_category,
			"file"=>$file_name,
			"browser"=>$browser,
			"device"=>$device_type,
			"os"=>$os];

		//No string keys
		/*
		return [$main_category,
			$file_name,
			$browser,
			$device_type,
			$os];
		*/
	}
}

function get_data($source_path) {
	$handle = fopen($source_path, "r");

	if ($handle) {
		$data = [];

		while(($line = fgets($handle)) !== false) {
			if (trim($line) !== "") {
				array_push($data, process_line($line));
			}
		}

		fclose($handle);

		return $data;
	}
}

if (!ENFORCE_MIN_SIZE || (filesize(LOG_PATH) > PARSE_MIN_SIZE)) {
	$temp_log_path = clear_log(LOG_PATH);
	$data_path = remove_duplicates($temp_log_path, DATA_PATH);
	$data = get_data($data_path);

	if (!empty($data)) {
		//print_r($data);
		$db = new MyDB();

		$db->exec("BEGIN;");

		foreach($data as $entry) {
			$statement = $db->prepare("INSERT INTO UserLogInfo (main_category, file_name, browser, device_type, os) VALUES (:category, :file, :browser, :device, :os)");
			$statement->bindValue(":category", $entry["category"]);
			$statement->bindValue(":file", $entry["file"]);
			$statement->bindValue(":browser", $entry["browser"]);
			$statement->bindValue(":device", $entry["device"]);
			$statement->bindValue(":os", $entry["os"]);
			for($i = 0; $i < 1000; $i++){
                $result = $statement->execute()->finalize();
            }
			//$result = $statement->execute();
		}

		$db->exec("COMMIT;");
	}
}
?>
