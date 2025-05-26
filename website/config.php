<?php
    require_once __DIR__ . '/vendor/autoload.php';
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();

    function getDBConnection() {
        static $conn = null;
        if ($conn === null) {
            $conn = new mysqli($_ENV['DB_HOST'], $_ENV['DB_USER'], $_ENV['DB_PASS'], $_ENV['DB_NAME']);
            if($conn->connect_error){
                error_log("Database connection failed: " . $conn->connect_error);
                die(json_encode(["status" => "error", "message" => "Database connection error"]));
            }
        }
        return $conn;
    }
?>