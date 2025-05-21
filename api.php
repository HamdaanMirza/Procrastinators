<?php
header("Content-Type: application/json");
require_once("config.php");
//long 
class API{
    private static $obj = null;
    private $connection;

    private function __construct(){
        $this->connection = getDBConnection();
            if($this->connection->connect_error)
                $this->sendErrorResponse("Database connection failed", 500);
    }

    public static function getInstance(){
        if(self::$obj == null)
            self::$obj = new API();
        return self::$obj;
    }

    public function handleRequest(){
        $data = json_decode(file_get_contents("php://input"), true);
        if($data)
            $this->sendErrorResponse("No data was received.", 400);
        if(!isset($data["type"]))
            $this->sendErrorResponse("Request type not specified.", 400);
        switch($data["type"]){
            case "Register":
                $this->handelRegistration($data);
                break;
            case "AddRetailer":
                $this->handelAddRetailer($data);
                break;
            case "UpdateRetailer":
                $this->handelUpdateRetailer($data);
                break;
            case "DeleteRetailer":
                $this->handelDeleteRetailer($data);
                break;
        }
    }

    // registering users
    private function handelRegistration($data){
        //making sure we have all needed fields
        $required = ["username", "email", "password", "role"];
        foreach($required as $field)
            if(empty($data[$field]))
                $this->sendErrorResponse("$field is required.", 400);
        //validating email using regex
        $emailRegex = "/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/"; // got from mailtrap.io
        if(!preg_match($emailRegex, $data["email"]))
            $this->sendErrorResponse("Invaild email address.", 400);
        //password validation
        $check1 = strlen($data["password"]) < 8;
        $check2 = !preg_match("/[A-Z]/", $data["password"]);
        $check3 = !preg_match("/[a-z]/", $data["password"]);
        $check4 = !preg_match("/[0-9]/", $data["password"]);
        $check5 = !preg_match("/[^A-Za-z0-9]/", $data["password"]);
        if($check1 || $check2 || $check3 || $check4 || $check5)
            $this->sendErrorResponse("Password must be at least 8 characters with uppercase, lowercase, number and special character", 400);
        $vaildRoles = ["Customer", "Admin"];
        if(!in_array($data["role"], $vaildRoles))
            $this->sendErrorResponse("Invalid user role.", 400);

        // checking if the email received is already in the table
        $mysql_statement = $this->connection->prepare("SELECT UserID FROM Users WHERE Email = ?");
        $mysql_statement->bind_param("s", $data["email"]);
        $mysql_statement->execute();
        $mysql_statement->store_result();
        if($mysql_statement->num_rows > 0)
            $this->sendErrorResponse("Email already registered", 400);
        $mysql_statement->close();

        //password hashing and api key generation
        $salt = bin2hex(random_bytes(16)); 
        $hashedPassword = hash("sha512", $data["password"] . $salt);
        $apikey = bin2hex(random_bytes(16));

        $mysql_statement = $this->connection->prepare("INSERT INTO Users (UserName, Email, Password, salt, Role, apikey)
        VALUES (?, ?, ?, ?, ?, ?)");
        $mysql_statement->bind_param("ssssss", $data["username"], $data["email"], $hashedPassword, $salt, $data["role"], $apikey);
        if($mysql_statement->execute())
            $this->sendSuccessResponse(["apikey"=>$apikey]);
        else
            $this->sendErrorResponse("Registration failed.", 500);
        $mysql_statement->close();
    }

    // inserts a retailer into the database
    private function handelAddRetailer($data){
        //checks if the required fields are present
        $required = ["RetailerName", "RetailerURL", "Country", "City", "Street"];
        foreach($required as $field)
            if(empty($data[$field]))
                $this->sendErrorResponse("$field is required.", 400);
        //checking for multiple retailers with same naem
        $mysql_statement = $this->connection->prepare("SELECT RetailerID FROM Retailer WHERE RetailerName = ?");
        $mysql_statement->bind_param("s", $data["RetailerName"]);
        $mysql_statement->execute();
        $mysql_statement->store_result();
        if($mysql_statement->num_rows > 0)
            $this->sendErrorResponse("Retailer already present in database", 400);
        $mysql_statement = $this->connection->prepare("INSERT INTO Retailer (RetailerName, RetailerURL, Country, City, Street)
        VALUES (?, ?, ?, ?, ?)");
        // adding to the database
        $mysql_statement->bind_param("ssssss", $data["RetailerName"], $data["RetailerURL"], $data["Country"], $data["City"], $data["Street"]);
        $RetailerName = $data["RetailerName"];
        if($mysql_statement->execute())
            $this->sendSuccessResponse("Retailer $RetailerName was added successfully to the database.");
        else
            $this->sendErrorResponse("Failed to add retailer to the database.", 500);
        $mysql_statement->close();
    }

    private function handelUpdateRetailer($data){
        if(!isset($data["RetailerID"]))
            $this->sendErrorResponse("RetailerID is required.", 400);
        $retailerID = (int)$data["RetailerID"];
        try {
            //getting current data of the retailer from db
            $query = $this->connection->prepare("SELECT * FROM Retailer WHERE RetailerID = ?");
            $query->bind_param("i", $retailerID);
            $query->execute();
            $result = $query->get_result();
            $currentData = $result->fetch_assoc();
            
            if($result->num_rows === 0)
                $this->sendErrorResponse("No retailer found with the specified ID.", 404);
            $updateFields = [];
            $updateValues = [];
            $types = "";

            // Checking each field and updating it only if the attribute was provided
            if(isset($data["RetailerName"])){
                $updateFields[] = "RetailerName = ?";
                $updateValues[] = trim($data["RetailerName"]);
                $types .= "s";
            }
            else{
                $updateValues[] = $currentData["RetailerName"];
                $types .= "s";
            }

            if(isset($data["RetailerURL"])){
                $updateFields[] = "RetailerURL = ?";
                $updateValues[] = trim($data["RetailerURL"]);
                $types .= "s";
            }
            else{
                $updateValues[] = $currentData["RetailerURL"];
                $types .= "s";
            }

            if(isset($data["Country"])){
                $updateFields[] = "Country = ?";
                $updateValues[] = trim($data["Country"]);
                $types .= "s";
            }
            else{
                $updateValues[] = $currentData["Country"];
                $types .= "s";
            }

            if(isset($data["City"])) {
                $updateFields[] = "City = ?";
                $updateValues[] = trim($data["City"]);
                $types .= "s";
            }
            else{
                $updateValues[] = $currentData["City"];
                $types .= "s";
            }

            if(isset($data["Street"])){
                $updateFields[] = "Street = ?";
                $updateValues[] = trim($data["Street"]);
                $types .= "s";
            }
            else{
                $updateValues[] = $currentData["Street"];
                $types .= "s";
            }
            //updating the database
            $updateValues[] = $retailerID;
            $types .= "i";
            $query = "UPDATE Retailer SET " . implode(", ", $updateFields) . " WHERE RetailerID = ?";
            $query = $this->connection->prepare($query);
            $query->bind_param($types, ...$updateValues); // i checked this is supported by php 7.4
            $query->execute();
            //no updates made
            if($query->affected_rows === 0)
                $this->sendErrorResponse("No changes were made to the retailer.", 200);
            //successfully updated
                $this->sendSuccessResponse([
                "message" => "Retailer updated successfully.",
                "retailer_id" => $retailerID,
                "changes_made" => count($updateFields)
            ]);
        }
        catch (Exception $e) {
            $this->sendErrorResponse("Failed to update retailer: " . $e->getMessage(), 500);
        }
    }

    private function handelDeleteRetailer($data){
        if(!isset($data["RetailerID"]))
            $this->sendErrorResponse("RetailerID is required.", 400);
        $retailerID = (int)$data["RetailerID"];
        try{
            // checking if retailer exists before deleting 
            $query = $this->connection->prepare("SELECT RetailerID FROM Retailer WHERE RetailerID = ?");
            $query->bind_param("i", $retailerID);
            $query->execute();
            $result = $query->get_result();
            if($result->num_rows === 0)
                $this->sendErrorResponse("No retailer found with given ID.", 404);
            //actually deleting the relevant retailer
            $query = $this->connection->prepare("DELETE FROM Retailer WHERE RetailerID = ?");
            $query->execute();
            if($query->affected_rows === 0)
                $this->sendErrorResponse("Failed to delete user.", 500);
            $this->sendSuccessResponse([
                "message" => "Retailer deleted successfully",
                "RetailerID" => $retailerID
            ]);
        }
        catch(Exception $e){
            $this->sendErrorResponse("Failed to delete retailer: " . $e->getMessage(), 500);
        }
    }

    private function sendSuccessResponse($data){
        echo json_encode([
            "status" => "success",
            "timestamp" => time(),
            "data" => $data
        ]);
        exit;
    }

    private function sendErrorResponse($message, $httpCode = 400){
        http_response_code($httpCode);
        echo json_encode([
            "status" => "error",
            "timestamp" => time(),
            "data" => $message
        ]);
        exit;
    }
}

$api = API::getInstance();
$api->handleRequest();
?>