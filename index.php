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

	$GLOBALS["key"] = "example_key";

	Flight::route("POST /", function() {
		header("Content-Type: application/json");
		$data = json_decode(file_get_contents("php://input"));
		if (json_last_error() === JSON_ERROR_NONE) {
			if ($data->password === "password") {
				$token = [
					"expires" => (new DateTime("now + 25 hours"))->format("Y-m-d H:i:s")
				];
				echo json_encode([
					"api" => "words",
					"version" => "4.1",
					"token" => JWT::encode($token, $GLOBALS["key"]),
					"expires" => (new DateTime("now + 25 hours"))->format("Y-m-d H:i:s")
				], JSON_PRETTY_PRINT);
			} else {
				echo json_encode([
					"api" => "words",
					"version" => "4.1",
					"error" => "Invalid password"
				], JSON_PRETTY_PRINT);
			}
		}
	});

	Flight::route("POST /posts", function() {
		header("Content-Type: application/json");
		$data = json_decode(file_get_contents("php://input"));
		if (json_last_error() === JSON_ERROR_NONE) {
			$decoded = JWT::decode($data->token, $GLOBALS["key"], array("HS256"));
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
					"result" => $files
				], JSON_PRETTY_PRINT);
			} else {
				echo json_encode([
					"api" => "words",
					"version" => "4.1",
					"error" => "Unauthenticated"
				], JSON_PRETTY_PRINT);
			}
		}
	});

	Flight::start();

?>