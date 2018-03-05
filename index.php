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

	$metadata = json_decode(file_get_contents("./meta.json"));
	$GLOBALS["post_directory"] = $metadata->files;
	$GLOBALS["key"] = $metadata->key;
	$GLOBALS["iv"] = $metadata->iv;
	// The password in `meta.json` is a result of password_hash("example_password", PASSWORD_DEFAULT)
	$GLOBALS["password"] = $metadata->password;

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
				$metas = array_diff(scandir($GLOBALS["post_directory"]), array(".", ".."));
				foreach ($metas as $meta => $val) {
					array_push($files, [
						"id" => $val,
						"title" => encrypt_decrypt("decrypt", json_decode(file_get_contents($GLOBALS["post_directory"] . "$val"))->title),
						"date" => json_decode(file_get_contents($GLOBALS["post_directory"] . "$val"))->date,
					]);
				}
				echo json_encode([
					"api" => "words",
					"version" => "4.1",
					"posts" => $files
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

	Flight::route("GET /post/@post_url", function($post_url) {
		header("Content-Type: application/json");
		if (!isset($_SERVER["HTTP_TOKEN"])) {
			exit();
		}
		$decoded = JWT::decode(isset($_SERVER["HTTP_TOKEN"]) ? $_SERVER["HTTP_TOKEN"] : null, $GLOBALS["key"], array("HS256"));
		if ($decoded && ($decoded->expires > (new DateTime("now"))->format("Y-m-d H:i:s"))) {
			$post = json_decode(file_get_contents($GLOBALS["post_directory"] . $post_url));
			echo json_encode([
				"api" => "words",
				"version" => "4.1",
				"post" => [
					"title" => encrypt_decrypt("decrypt", $post->title),
					"date" => $post->date,
					"body" => encrypt_decrypt("decrypt", $post->body),
				],
			], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
		} else {
			echo json_encode([
				"api" => "words",
				"version" => "4.1",
				"error" => "Unauthenticated"
			], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
		}
	});

	Flight::route("DELETE /post/@post_url", function($post_url) {
		header("Content-Type: application/json");
		if (!isset($_SERVER["HTTP_TOKEN"])) {
			exit();
		}
		$decoded = JWT::decode(isset($_SERVER["HTTP_TOKEN"]) ? $_SERVER["HTTP_TOKEN"] : null, $GLOBALS["key"], array("HS256"));
		if ($decoded && ($decoded->expires > (new DateTime("now"))->format("Y-m-d H:i:s"))) {
			if (file_exists($GLOBALS["post_directory"] . $post_url)) {
				unlink($GLOBALS["post_directory"] . $post_url);
				echo json_encode([
					"api" => "words",
					"version" => "4.1",
					"deleted" => true
				], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
			} else {
				echo json_encode([
					"api" => "words",
					"version" => "4.1",
					"error" => "No such file"
				], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
			}
		} else {
			echo json_encode([
				"api" => "words",
				"version" => "4.1",
				"error" => "Unauthenticated"
			], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
		}
	});

	Flight::route("PUT /post/@post_url", function($post_url) {
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
					if (file_exists($GLOBALS["post_directory"] . $post_url)) {
						$currentDate = (new DateTime("now"))->format("YmdHis");
						$post = [
							"title" => encrypt_decrypt("encrypt", $data->title),
							"date" => (new DateTime("now"))->format("Y-m-d H:i:s"),
							"body" => encrypt_decrypt("encrypt", $data->body)
						];
						file_put_contents($GLOBALS["post_directory"] . $post_url, json_encode($post, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
						echo json_encode([
							"api" => "words",
							"version" => "4.1",
							"updated" => true,
							"id" => $post_url,
						], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
					} else {
						echo json_encode([
							"api" => "words",
							"version" => "4.1",
							"error" => "No such file"
						], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
					}
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
					$currentDate = (new DateTime("now"))->format("YmdHis");
					$post = [
						"title" => encrypt_decrypt("encrypt", $data->title),
						"date" => (new DateTime("now"))->format("Y-m-d H:i:s"),
						"body" => encrypt_decrypt("encrypt", $data->body)
					];
					file_put_contents($GLOBALS["post_directory"] . "" . $currentDate . substr(md5($currentDate), 0, 10) . ".json", json_encode($post, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
					echo json_encode([
						"api" => "words",
						"version" => "4.1",
						"created" => true,
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