<?php

	require "vendor/autoload.php";
	use \Firebase\JWT\JWT;

	if (isset($_SERVER["HTTP_ORIGIN"])) {
		header("Access-Control-Allow-Origin: {$_SERVER["HTTP_ORIGIN"]}");
		header("Access-Control-Allow-Credentials: true");
		header("Access-Control-Max-Age: 86400");
	}
	
	if ($_SERVER["REQUEST_METHOD"] == "OPTIONS") {
		if (isset($_SERVER["HTTP_ACCESS_CONTROL_REQUEST_METHOD"]))
			header("Access-Control-Allow-Methods: DELETE, GET, OPTIONS, POST");		 
		if (isset($_SERVER["HTTP_ACCESS_CONTROL_REQUEST_HEADERS"]))
			header("Access-Control-Allow-Headers: {$_SERVER["HTTP_ACCESS_CONTROL_REQUEST_HEADERS"]}");
		exit(0);
	}

	$GLOBALS["key"] = "example_secure_key";
	$GLOBALS["iv"] = "example_initialization_vector";
	$GLOBALS["password"] = password_hash("example_password", PASSWORD_DEFAULT); // Save this as a hashed string

	// https://gist.github.com/joashp/a1ae9cb30fa533f4ad94
	function encrypt_decrypt($action, $string) {
		$output = false;
		$encrypt_method = "AES-256-CBC";
		$key = hash("sha256", $GLOBALS["key"]);
		$iv = substr(hash("sha256", $GLOBALS["iv"]), 0, 16);
		if ($action == "encrypt") {
			$output = openssl_encrypt($string, $encrypt_method, $key, 0, $iv);
			$output = base64_encode($output);
		} else if($action == "decrypt") {
			$output = openssl_decrypt(base64_decode($string), $encrypt_method, $key, 0, $iv);
		}
		return $output;
	}

	Flight::route("POST /", function() {
		header("Content-Type: application/json");
		$data = json_decode(file_get_contents("php://input"));
		if (json_last_error() === JSON_ERROR_NONE) {
			if (password_verify($data->password, $GLOBALS["password"])) {
				$token = [
					"expires" => (new DateTime("now + 25 hours"))->format("Y-m-d H:i:s")
				];
				echo json_encode([
					"api" => "words",
					"version" => "4.1",
					"token" => JWT::encode($token, $GLOBALS["key"]),
					"expires" => (new DateTime("now + 25 hours"))->format("Y-m-d H:i:s")
				], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
			} else {
				echo json_encode([
					"api" => "words",
					"version" => "4.1",
					"error" => "Invalid password"
				], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
			}
		}
	});

	Flight::route("GET /posts", function() {
		header("Content-Type: application/json");
		if (json_last_error() === JSON_ERROR_NONE) {
			$decoded = JWT::decode(isset($_SERVER["HTTP_TOKEN"]) ? $_SERVER["HTTP_TOKEN"] : null, $GLOBALS["key"], array("HS256"));
			if ($decoded && ($decoded->expires > (new DateTime("now"))->format("Y-m-d H:i:s"))) {
				$files = [];
				$metas = array_diff(scandir("./words"), array(".", ".."));
				foreach ($metas as $meta => $val) {
					array_push($files, [
						"title" => json_decode(file_get_contents("./words/$val"))->title,
						"date" => json_decode(file_get_contents("./words/$val"))->date,
					]);
				}
				echo json_encode([
					"api" => "words",
					"version" => "4.1",
					"result" => $files,
				], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
			} else {
				echo json_encode([
					"api" => "words",
					"version" => "4.1",
					"error" => "Unauthenticated"
				], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
			}
		}
	});

	Flight::route("PUT /posts", function() {
		header("Content-Type: application/json");
		if (json_last_error() === JSON_ERROR_NONE) {
			$decoded = JWT::decode(isset($_SERVER["HTTP_TOKEN"]) ? $_SERVER["HTTP_TOKEN"] : null, $GLOBALS["key"], array("HS256"));
			if ($decoded && ($decoded->expires > (new DateTime("now"))->format("Y-m-d H:i:s"))) {
				$data = json_decode(file_get_contents("php://input"));
				$error = false;
				if (!isset($data->title)) {
					$error = true;
				}
				if (!isset($data->body)) {
					$error = true;
				}
				if (!$error) {
					$currentDate = (new DateTime("now"))->format("Y-m-d H:i:s");
					$post = [
						"title" => encrypt_decrypt("encrypt", $data->title),
						"date" => $currentDate,
						"body" => encrypt_decrypt("encrypt", $data->body)
					];
					file_put_contents("./words/" . $currentDate . "-" . substr(md5($currentDate), 0, 10) . ".json", json_encode($post, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
					echo json_encode([
						"api" => "words",
						"version" => "4.1",
						"created" => true,
						"post" => $post,
					], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
				} else {
					echo json_encode([
						"api" => "words",
						"version" => "4.1",
						"error" => "field"
					], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
				}
			} else {
				echo json_encode([
					"api" => "words",
					"version" => "4.1",
					"error" => "Unauthenticated"
				], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
			}
		}
	});

	Flight::start();

?>