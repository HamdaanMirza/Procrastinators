<?php
header("Content-Type: application/json");
require_once("config.php");

class API{
    private static $obj = null;
    private $connection;
    private $attempts_file = "login_attempts.json";
    private $max_attempts = 5;
    private $time_window = 300; 
    private $lockout_duration = 900;

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

    private function checkRateLimit($ip, $email) {
        // Load existing attempts from file
        $attempts_data = ["attempts" => []];
        if (file_exists($this->attempts_file)) {
            $content = file_get_contents($this->attempts_file);
            if ($content !== false) {
                $attempts_data = json_decode($content, true) ?: ["attempts" => []];
            }
        }

        $current_time = time();
        $key = $ip . "|" . $email;
        $attempts = &$attempts_data["attempts"];

        foreach ($attempts as $index => $attempt) 
            if ($attempt["last_attempt"] < $current_time - $this->time_window && empty($attempt["lockout_until"]))
                unset($attempts[$index]);

        $attempts = array_values($attempts); // Reindex array

        // Find existing attempt record
        $attempt_index = null;
        foreach($attempts as $index => $attempt){
            if($attempt["ip"] === $ip && $attempt["email"] === $email){
                $attempt_index = $index;
                break;
            }
        }

        // Check for lockout
        if($attempt_index !== null && !empty($attempts[$attempt_index]["lockout_until"])){
            if($attempts[$attempt_index]["lockout_until"] > $current_time){
                $remaining = $attempts[$attempt_index]["lockout_until"] - $current_time;
                $this->sendErrorResponse("Too many login attempts. Try again in $remaining seconds.", 429);
            }
            else{
                // Lockout expired, reset attempt count
                $attempts[$attempt_index] = [
                    "ip" => $ip,
                    "email" => $email,
                    "count" => 0,
                    "last_attempt" => $current_time,
                    "lockout_until" => null
                ];
            }
        }

        // Update or create attempt record
        if($attempt_index === null){
            $attempts[] = [
                "ip" => $ip,
                "email" => $email,
                "count" => 1,
                "last_attempt" => $current_time,
                "lockout_until" => null
            ];
        }
        else{
            $attempts[$attempt_index]["count"]++;
            $attempts[$attempt_index]["last_attempt"] = $current_time;

            // Check if max attempts exceeded
            if($attempts[$attempt_index]["count"] >= $this->max_attempts){
                $attempts[$attempt_index]["lockout_until"] = $current_time + $this->lockout_duration;
                $this->sendErrorResponse("Too many login attempts. Try again in {$this->lockout_duration} seconds.", 429);
            }
        }

        // Save updated attempts to file
        file_put_contents($this->attempts_file, json_encode($attempts_data, JSON_PRETTY_PRINT));
    }

    public function handleRequest(){
        $data = json_decode(file_get_contents("php://input"), true);
        if(!$data)
            $this->sendErrorResponse("No data was received.", 400);
        if(!isset($data["Type"]))
            $this->sendErrorResponse("Request type not specified.", 400);
        switch($data["Type"]){
            case "Register":
                $this->handleRegistration($data);
                break;
            case "AddRetailer":
                $this->handleAddRetailer($data);
                break;
            case "UpdateRetailer":
                $this->handleUpdateRetailer($data);
                break;
            case "DeleteRetailer":
                $this->handleDeleteRetailer($data);
                break;
            case "Login":
                $this->handleLogin($data);
                break;
            case "DeleteUser":
                $this->handleDeleteUser($data);
                break;
            case "InsertReview":
                $this->handleInsertReview($data);
                break;
            case "UpdateReview":
                $this->handleUpdateReview($data);
                break;
            case "AddProduct":
                $this->handleAddProduct($data);
                break;
            case "EditProduct":
                $this->handleEditProduct($data);
                break;
            case "DeleteProduct":
                $this->handleDeleteProduct($data);
                break;
            case "GetTopRated":
                $this->handleGetTopRated();
                break;
            case "GetMostReviewed":
                $this->handleGetMostReviewed();
                break;
            case "GetRecentlyListed":
                $this->handleGetRecentlyListed();
                break;
            case "GetMostListed":
                $this->handleGetMostListed();
                break;
            case "GetAverageRating":
                $this->handleGetAverageRating($data);
                break;
            case "FilterProducts":
                $this->handleFilterProducts($data);
                break;
            case "GetProduct":
                $this->handleGetProduct($data);
                break;
            case "GetCategories":
                $this->handleGetCategories();
                break;
            case "ManageCategory":
                $this->handleManageCategory($data);
                break;
            case "ManageRetailer":
                $this->handleManageRetailer($data);
                break;
            case "CheckReview":
                $this->handleCheckReview($data);
                break;
            case "GetBrands":
                $this->handleGetBrands();
                break;
            default:
                $this->sendErrorResponse("Endpoint not found", 404);
                break;
        }
    }

    // registering users
    /*
    Example API call:
    {
    "Type": "Register",
    "UserName": "TestUser",
    "Email": "test@user.com",
    "Password": "TestUser1!"
    }
    */
    private function handleRegistration($data){
        //making sure we have all needed fields
        $required = ["UserName", "Email", "Password"];
        foreach($required as $field)
            if(empty($data[$field]))
                $this->sendErrorResponse("$field is required.", 400);
        //validating email using regex
        $emailRegex = "/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/"; // got from mailtrap.io
        if(!preg_match($emailRegex, $data["Email"]))
            $this->sendErrorResponse("Invaild email address.", 400);
        //password validation
        $check1 = strlen($data["Password"]) < 8;
        $check2 = !preg_match("/[A-Z]/", $data["Password"]);
        $check3 = !preg_match("/[a-z]/", $data["Password"]);
        $check4 = !preg_match("/[0-9]/", $data["Password"]);
        $check5 = !preg_match("/[^A-Za-z0-9]/", $data["Password"]);
        if($check1 || $check2 || $check3 || $check4 || $check5)
            $this->sendErrorResponse("Password must be at least 8 characters with uppercase, lowercase, number and special character", 400);

        // checking if the email received is already in the table
        $mysql_statement = $this->connection->prepare("SELECT UserID FROM user WHERE Email = ?");
        $mysql_statement->bind_param("s", $data["Email"]);
        $mysql_statement->execute();
        $mysql_statement->store_result();
        if($mysql_statement->num_rows > 0)
            $this->sendErrorResponse("Email already registered", 400);
        $mysql_statement->close();

        //password hashing and api key generation
        $salt = bin2hex(random_bytes(16)); 
        $hashedPassword = hash("sha512", $data["Password"] . $salt);
        $Apikey = bin2hex(random_bytes(16));
        $role = "Customer";

        $mysql_statement = $this->connection->prepare("INSERT INTO user (UserName, Email, Password, Salt, Role, Apikey)
        VALUES (?, ?, ?, ?, ?, ?)");
        $mysql_statement->bind_param("ssssss", $data["UserName"], $data["Email"], $hashedPassword, $salt, $role, $Apikey);
        if($mysql_statement->execute()){
            $userId = $this->connection->prepare("SELECT UserID FROM user WHERE UserName = ?");
            $userId->bind_param("s", $data["UserName"]);
            $userId->execute();
            $result = $userId->get_result();
            $user = $result->fetch_assoc();
            $mysql_statement2 = $this->connection->prepare("INSERT INTO customer (UserID, UserName) VALUES (?, ?)");
            $mysql_statement2->bind_param("ss", $user["UserID"], $data["UserName"]);
            if($mysql_statement2->execute())
                $this->sendSuccessResponse(["Apikey"=>$Apikey]);
            else
                $this->sendErrorResponse("Added to users but not to customers." ,400);
        }
        else
            $this->sendErrorResponse("Registration failed.", 500);
        $mysql_statement->close();
    }


    /*
    {
    Example API call:
    "Type": "AddRetailer",
    "RetailerName": "Makro",
    "RetailerURL": "makro.com",
    "Country": "South Africa",
    "City": "Pretoria",
    "Street": "12 Makro St."
    }
    */
    // inserts a retailer into the database
    private function handleAddRetailer($data){
        //checks if the required fields are present
        $required = ["RetailerName", "RetailerURL", "Country", "City", "Street"];
        foreach($required as $field)
            if(empty($data[$field]))
                $this->sendErrorResponse("$field is required.", 400);
        //checking for multiple retailers with same naem
        $mysql_statement = $this->connection->prepare("SELECT RetailerID FROM retailer WHERE RetailerName = ?");
        $mysql_statement->bind_param("s", $data["RetailerName"]);
        $mysql_statement->execute();
        $mysql_statement->store_result();
        if($mysql_statement->num_rows > 0)
            $this->sendErrorResponse("Retailer already present in database", 400);
        $mysql_statement = $this->connection->prepare("INSERT INTO retailer (RetailerName, RetailerURL, Country, City, Street)
        VALUES (?, ?, ?, ?, ?)");
        // adding to the database
        $mysql_statement->bind_param("sssss", $data["RetailerName"], $data["RetailerURL"], $data["Country"], $data["City"], $data["Street"]);
        $RetailerName = $data["RetailerName"];
        if($mysql_statement->execute())
            $this->sendSuccessResponse("Retailer $RetailerName was added successfully to the database.");
        else
            $this->sendErrorResponse("Failed to add retailer to the database.", 500);
        $mysql_statement->close();
    }

    /*
    Example of API call.
    {
    "Type": "UpdateRetailer",
    "RetailerID": 1,
    "City": "Pretoria"
    }
    */

    private function handleUpdateRetailer($data){
        if(!isset($data["RetailerID"]))
            $this->sendErrorResponse("RetailerID is required.", 400);
        $retailerID = (int)$data["RetailerID"];
        try {
            //getting current data of the retailer from db
            $query = $this->connection->prepare("SELECT * FROM retailer WHERE RetailerID = ?");
            $query->bind_param("i", $retailerID);
            $query->execute();
            $result = $query->get_result();
            
            
            if($result->num_rows === 0)
                $this->sendErrorResponse("No retailer found with the specified ID.", 404);
            
            $currentData = $result->fetch_assoc();
            $updateFields = [];
            $updateValues = [];
            $types = "";
            $fields = [
                "RetailerName" => "s",
                "RetailerURL" => "s",
                "Country" => "s",
                "City" => "s",
                "Street" => "s"
            ];

            foreach($fields as $field => $type){
                if (isset($data[$field])) {
                    $updateFields[] = "$field = ?";
                    $bindValues[] = trim($data[$field]);
                    $types .= $type;
                }
            }
        
            if (empty($updateFields))
                $this->sendErrorResponse("No fields provided for update.", 400);
            
            $bindValues[] = $retailerID;
            $types .= "i";
            
            $query = "UPDATE retailer SET " . implode(", ", $updateFields) . " WHERE RetailerID = ?";
            $stmt = $this->connection->prepare($query);
            $stmt->bind_param($types, ...$bindValues);
            $stmt->execute();
            
            if ($stmt->affected_rows === 0)
                $this->sendErrorResponse("No changes were made to the retailer.", 200);
            
            $this->sendSuccessResponse([
                "message" => "Retailer updated successfully.",
                "retailer_id" => $retailerID,
                "changes_made" => count($updateFields)
            ]);
        }
        catch(Exception $e){
            $this->sendErrorResponse("Failed to update retailer: " . $e->getMessage(), 500);
        }
    }

    /*
    Example of API call:
    {
    "Type": "DeleteRetailer",
    "RetailerID": 149
    }
    */

    private function handleDeleteRetailer($data){
        if(!isset($data["RetailerID"]))
            $this->sendErrorResponse("RetailerID is required.", 400);
        $retailerID = (int)$data["RetailerID"];
        try{
            // checking if retailer exists before deleting 
            $query = $this->connection->prepare("SELECT RetailerID FROM retailer WHERE RetailerID = ?");
            $query->bind_param("i", $retailerID);
            $query->execute();
            $result = $query->get_result();
            if($result->num_rows === 0)
                $this->sendErrorResponse("No retailer found with given ID.", 404);
            //actually deleting the relevant retailer
            $query = $this->connection->prepare("DELETE FROM retailer WHERE RetailerID = ?");
            $query->bind_param("i", $retailerID);
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

    /*Example of login api call:
    {
    "Type": "Login",
    "UserName": "TestUser",
    "Email": "test@user.com",
    "Password": "TestUser1!"
    }
    */
    private function handleLogin($data) {
        if(!isset($data["Email"]) || !isset($data["Password"]))
            $this->sendErrorResponse("Email and password are required", 400);

        $ip = $_SERVER["REMOTE_ADDR"];
        $this->checkRateLimit($ip, $data["Email"]);

        $stmt = $this->connection->prepare("SELECT UserID, UserName, Email, Password, Salt, Apikey FROM user WHERE Email = ?");
        $stmt->bind_param("s", $data["Email"]);
        $stmt->execute();
        $result = $stmt->get_result();

        if($result->num_rows === 0)
            $this->sendErrorResponse("User not found", 401);

        $user = $result->fetch_assoc();

        $hashedInputPassword = hash("sha512", $data["Password"] . $user["Salt"]);
        if ($hashedInputPassword !== $user["Password"]) {
            $this->sendErrorResponse("Invalid credentials", 401);
        }

        unset($user["Password"]);
        unset($user["Salt"]);

        $attempts_data = ["attempts" => []];
        if(file_exists($this->attempts_file)){
            $content = file_get_contents($this->attempts_file);
            if ($content !== false)
                $attempts_data = json_decode($content, true) ?: ["attempts" => []];
        }
        $attempts = &$attempts_data["attempts"];
        foreach ($attempts as $index => $attempt) {
            if ($attempt["ip"] === $ip && $attempt["Email"] === $data["Email"]) {
                $attempts[$index] = [
                    "ip" => $ip,
                    "email" => $data["Email"],
                    "count" => 0,
                    "last_attempt" => time(),
                    "lockout_until" => null
                ];
                file_put_contents($this->attempts_file, json_encode($attempts_data, JSON_PRETTY_PRINT));
                break;
            }
        }

        $isAdmin = false;
        $stmt = $this->connection->prepare("SELECT AccessLevel FROM admin JOIN user ON admin.userID = user.userID WHERE admin.userID = ?");
        $stmt->bind_param("i", $user["UserID"]);
        $stmt->execute();
        $result = $stmt->get_result();

        if($result->num_rows === 1){
            $isAdmin = true;
        }
            
        $this->sendSuccessResponse([
            "message" => "Login successful",
            "user" => $user,
            "isAdmin" => $isAdmin
        ]);
    }

    private function diagnoseUserReferences($userID) {
        $references = [];
        $tables = ["review", "customer", "admin", "cart", "orders", "wishlist"];
        foreach($tables as $table){
            try{
                $stmt = $this->connection->prepare("SELECT COUNT(*) as count FROM $table WHERE UserID = ?");
                $stmt->bind_param("i", $userID);
                $stmt->execute();
                $result = $stmt->get_result();
                $count = $result->fetch_assoc()["count"];
                if($count > 0)
                    $references[$table] = $count;
            }
            catch(Exception $e){
                continue;
            }
        }
        return $references;
    }

    /*
    Example of API call:
    {
    "Type": "DeleteUser",
    "UserID": 198
    }
    */

    private function handleDeleteUser($data) {
        $userID = $data["UserID"] ?? null;
        
        if(!$userID)
            $this->sendErrorResponse("User ID is required", 400);
        $references = $this->diagnoseUserReferences($userID);
        $this->connection->begin_transaction();
        try{
            $stmt = $this->connection->prepare("SELECT Role FROM user WHERE UserID = ?");
            $stmt->bind_param("i", $userID);
            $stmt->execute();
            $roleResult = $stmt->get_result();
            if($roleResult->num_rows === 0){
                $this->connection->rollback();
                $this->sendErrorResponse("User not found", 404);
            }
            $user = $roleResult->fetch_assoc();
            foreach(array_keys($references) as $table){
                try{
                    $stmt = $this->connection->prepare("DELETE FROM $table WHERE UserID = ?");
                    $stmt->bind_param("i", $userID);
                    $stmt->execute();
                }
                catch(Exception $e){
                    error_log("Failed to delete from $table: " . $e->getMessage());
                }
            }
            if($user["Role"] === "Customer"){
                $stmt = $this->connection->prepare("DELETE FROM customer WHERE UserID = ?");
                $stmt->bind_param("i", $userID);
                $stmt->execute();
            }
            else if ($user["Role"] === "Admin"){
                $stmt = $this->connection->prepare("DELETE FROM admin WHERE UserID = ?");
                $stmt->bind_param("i", $userID);
                $stmt->execute();
            }
            $stmt = $this->connection->prepare("DELETE FROM user WHERE UserID = ?");
            $stmt->bind_param("i", $userID);
            if(!$stmt->execute())
                throw new Exception("Failed to delete user record: " . $this->connection->error);
            if($stmt->affected_rows === 0)
                throw new Exception("No user was deleted - user may not exist");
            
            $this->connection->commit();
            
            $this->sendSuccessResponse([
                "message" => "User deleted successfully",
                "references_found" => $references
            ]);
            
        }
        catch (Exception $e) {
            $this->connection->rollback();
            $this->sendErrorResponse("Failed to delete user: " . $e->getMessage() . ". References found in: " . json_encode($references), 500);
        }
    }

    /*
    {
    "Type": "InsertReview",
    "ProductID": 346,
    "UserID": "200",
    "Rating": 4,
    "Comment": "Great for cleaning."
    }
*/
    private function handleInsertReview($data) {
        if (!isset($data["ProductID"]) || !isset($data["Rating"]) || !isset($data["UserID"])) 
            $this->sendErrorResponse("Product ID and rating are required", 400);
        
        $userID = $data["UserID"];
        $comment = $data["Comment"] ?? null;
        $currentDate = date("Y-m-d");
        
        if($data["Rating"] < 1 || $data["Rating"] > 5) 
            $this->sendErrorResponse("Rating must be between 1 and 5", 400);
        
        try{
            $stmt = $this->connection->prepare("SELECT ProductID FROM product WHERE ProductID = ?");
            $stmt->bind_param("i", $data["ProductID"]);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) 
                $this->sendErrorResponse("Product not found with ID: " . $data["ProductID"], 404);
            
            if ($userID !== null) {
                $stmt = $this->connection->prepare("SELECT UserID FROM user WHERE UserID = ?");
                $stmt->bind_param("i", $userID);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 0) 
                    $this->sendErrorResponse("User not found with ID: " . $userID, 404);
            }
            if($userID !== null){
                $stmt = $this->connection->prepare("SELECT ReviewID FROM review WHERE ProductID = ? AND UserID = ?");
                $stmt->bind_param("ii", $data["ProductID"], $userID);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) 
                    $this->sendErrorResponse("User has already reviewed this product", 400);
            }
            
            $stmt = $this->connection->prepare("INSERT INTO review (ProductID, UserID, Date, Rating, Comment) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iisis", $data["ProductID"], $userID, $currentDate, $data["Rating"], $comment);
            
            if (!$stmt->execute()) 
                throw new Exception($this->connection->error);
            
            $reviewID = $this->connection->insert_id;
            
            $this->sendSuccessResponse([
                "message" => "Review created successfully",
                "reviewID" => $reviewID,
                "productID" => $data["ProductID"],
                "userID" => $userID,
                "rating" => $data["Rating"]
            ]);
            
        }
        catch (Exception $e) {
            $this->sendErrorResponse("Failed to create review: " . $e->getMessage(), 500);
        }
    }

    /*
    Exampel of a call to this endpoint:
    {
    "Type": "UpdateReview",
    "ProductID": 587,
    "UserID": 23,
    "Rating": 4,
    "Comment": "Good product. Almost flawless."
    }
    */

    private function handleUpdateReview($data) {
        if (!isset($data["UserID"]) || !isset($data["ProductID"]) || !isset($data["Rating"]))
            $this->sendErrorResponse("UserID, ProductID, and Rating are required.", 400);

        $productID = (int)$data["ProductID"];
        $userID = (int)$data["UserID"];
        $rating = (int)$data["Rating"];
        $comment = $data["Comment"] ?? null;
        $currentDate = date("Y-m-d"); 
        if ($rating < 1 || $rating > 5)
            $this->sendErrorResponse("Rating must be between 1 and 5.", 400);

        try {
            $stmt = $this->connection->prepare("SELECT ReviewID FROM review WHERE ProductID = ? AND UserID = ?");
            $stmt->bind_param("ii", $productID, $userID);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0)
                $this->sendErrorResponse("No review found for the specified ProductID and UserID.", 404);
            $review = $result->fetch_assoc();
            $reviewID = $review["ReviewID"];

            $updateFields = ["Date = ?", "Rating = ?"];
            $updateValues = [$currentDate, $rating];
            $types = "si";

            if ($comment !== null) {
                $updateFields[] = "Comment = ?";
                $updateValues[] = $comment;
                $types .= "s";
            }

            $updateValues[] = $reviewID;
            $types .= "i";

            $query = "UPDATE review SET " . implode(", ", $updateFields) . " WHERE ReviewID = ?";
            $stmt = $this->connection->prepare($query);
            $stmt->bind_param($types, ...$updateValues);

            if(!$stmt->execute())
                throw new Exception($this->connection->error);
            if($stmt->affected_rows === 0){
                $this->sendSuccessResponse([
                    "message" => "No changes were made to the review (data might be the same).",
                    "productID" => $productID,
                    "userID" => $userID,
                    "rating" => $rating
                ]);
            }
            else {
                $this->sendSuccessResponse([
                    "message" => "Review updated successfully",
                    "reviewID" => $reviewID,
                    "productID" => $productID,
                    "userID" => $userID,
                    "rating" => $rating,
                    "comment" => $comment,
                    "date" => $currentDate
                ]);
            }
        }
        catch (Exception $e) {
            $this->sendErrorResponse("Failed to update review: " . $e->getMessage(), 500);
        }
    }

    /*
    {
    "Type": "AddProduct",
    "ProductName": "Soap",
    "Brand": "Soap brand",
    "Description": "Its a bar of soap",
    "ImageURL": "www.product.com"
    }
    */

    private function handleAddProduct($data) {
        if (!isset($data["ProductName"]))
            $this->sendErrorResponse("Product name is required", 400);
        
        $brand = $data["Brand"] ?? null;
        $description = $data["Description"] ?? null;
        $imageURL = $data["ImageURL"] ?? null;
        $categories = $data["Categories"] ?? [];
        
        $this->connection->begin_transaction();
        
        try{
            $stmt = $this->connection->prepare("INSERT INTO product (ProductName, Brand, Description, ImageURL) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $data["ProductName"], $brand, $description, $imageURL);
            
            if (!$stmt->execute()) 
                throw new Exception($this->connection->error);
            
            $productID = $this->connection->insert_id;
            
            if(!empty($categories)){
                $stmt = $this->connection->prepare("INSERT INTO ProductCategory (ProductID, CategoryID) VALUES (?, ?)");
                foreach ($categories as $categoryID){
                    $stmt->bind_param("ii", $productID, $categoryID);
                    if (!$stmt->execute()) 
                        throw new Exception($this->connection->error);
                }
            }
            
            $this->connection->commit();
            
            $this->sendSuccessResponse([
                "message" => "Product created successfully",
                "productID" => $productID
            ]);
            
        }
        catch (Exception $e){
            $this->connection->rollback();
            $this->sendErrorResponse("Failed to create product: " . $e->getMessage(), 500);
        }
    }

    /*
    {
    "Type": "EditProduct",
    "ProductID": 346,
    "Description": "Its a robot vacuum cleaner. It cleans the floor.",
    "Brand": "EcoCleaner"
    }
    */
    private function handleEditProduct($data){
        if (!isset($data["ProductID"]))
            $this->sendErrorResponse("Product ID is required", 400);
        
        $this->connection->begin_transaction();
        
        try {
            $stmt = $this->connection->prepare("SELECT ProductID FROM product WHERE ProductID = ?");
            $stmt->bind_param("i", $data["ProductID"]);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $this->connection->rollback();
                $this->sendErrorResponse("Product not found", 404);
            }
            
            $updateFields = [];
            $updateValues = [];
            $types = "";
            
            if (isset($data["ProductName"])) {
                $updateFields[] = "ProductName = ?";
                $updateValues[] = $data["ProductName"];
                $types .= "s";
            }
            
            if (isset($data["Brand"])) {
                $updateFields[] = "Brand = ?";
                $updateValues[] = $data["Brand"];
                $types .= "s";
            }
            
            if (isset($data["Description"])) {
                $updateFields[] = "Description = ?";
                $updateValues[] = $data["Description"];
                $types .= "s";
            }
            
            if (isset($data["ImageURL"])) {
                $updateFields[] = "ImageURL = ?";
                $updateValues[] = $data["ImageURL"];
                $types .= "s";
            }
            
            if (!empty($updateFields)) {
                $updateValues[] = $data["ProductID"];
                $types .= "i";
                
                $query = "UPDATE product SET " . implode(", ", $updateFields) . " WHERE ProductID = ?";
                $stmt = $this->connection->prepare($query);
                $stmt->bind_param($types, ...$updateValues);
                
                if (!$stmt->execute()) 
                    throw new Exception($this->connection->error);
            }
            
            if (isset($data["Categories"])) {
                $stmt = $this->connection->prepare("DELETE FROM productcategory WHERE ProductID = ?");
                $stmt->bind_param("i", $data["ProductID"]);
                
                if (!$stmt->execute()) 
                    throw new Exception($this->connection->error);
                
                $stmt = $this->connection->prepare("INSERT INTO productcategory (ProductID, CategoryID) VALUES (?, ?)");
                foreach ($data["Categories"] as $categoryID) {
                    $stmt->bind_param("ii", $data["ProductID"], $categoryID);
                    if (!$stmt->execute()) 
                        throw new Exception($this->connection->error);
                }
            }
            
            $this->connection->commit();
            $this->sendSuccessResponse(["message" => "Product updated successfully"]);
            
        }
        catch (Exception $e) {
            $this->connection->rollback();
            $this->sendErrorResponse("Failed to update product: " . $e->getMessage(), 500);
        }
    }

    /*
    {
    "Type": "DeleteProduct",
    "ProductID": 347
    }
    */

    private function handleDeleteProduct($data) {
        $productID = $data["ProductID"] ?? null;
        
        if (!$productID) {
            $this->sendErrorResponse("Product ID is required", 400);
        }
        
        try {
            $stmt = $this->connection->prepare("SELECT ProductID FROM product WHERE ProductID = ?");
            $stmt->bind_param("i", $productID);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $this->sendErrorResponse("Product not found", 404);
            }
            
            $stmt = $this->connection->prepare("DELETE FROM product WHERE ProductID = ?");
            $stmt->bind_param("i", $productID);
            
            if (!$stmt->execute()) 
                throw new Exception($this->connection->error);
            
            $this->sendSuccessResponse(["message" => "Product deleted successfully"]);
            
        }
        catch (Exception $e) {
            $this->sendErrorResponse("Failed to delete product: " . $e->getMessage(), 500);
        }
    }

    // Example of endpoint
    /*
    {
    "Type": "GetTopRated"
    }
    */

    private function handleGetTopRated() {
        $sql = "
            SELECT P.*, AVG(R.Rating) AS AverageRating, C.CategoryID
            FROM product P
            JOIN productcategory PC ON P.ProductID = PC.ProductID
            JOIN category C ON PC.CategoryID = C.CategoryID
            JOIN review R ON P.ProductID = R.ProductID
            GROUP BY P.ProductID
            ORDER BY AverageRating DESC
        ";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result();
        $this->sendSuccessResponse($result->fetch_all(MYSQLI_ASSOC));
    }

    private function handleGetMostReviewed() {
        $sql = "
            SELECT P.*, AVG(R.Rating) AS AverageRating, COUNT(R.Rating) AS ReviewCount, PC.CategoryID
            FROM product P
            JOIN productcategory PC ON P.ProductID = PC.ProductID
            JOIN review R ON P.ProductID = R.ProductID
            GROUP BY P.ProductID
            ORDER BY ReviewCount DESC
        ";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result();
        $this->sendSuccessResponse($result->fetch_all(MYSQLI_ASSOC));
    }

        private function handleGetRecentlyListed() {
        $sql = "
            SELECT P.*, AVG(R.Rating) AS AverageRating, MAX(L.Date) AS LatestListing, PC.CategoryID
            FROM product P
            JOIN productcategory PC ON P.ProductID = PC.ProductID
            JOIN listing L ON P.ProductID = L.ProductID
            JOIN review R ON P.ProductID = R.ProductID
            GROUP BY P.ProductID
            ORDER BY LatestListing DESC
        ";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result();
        $this->sendSuccessResponse($result->fetch_all(MYSQLI_ASSOC));
    }

        private function handleGetMostListed() {
        $sql = "
            SELECT P.*, AVG(R.Rating) AS AverageRating, COUNT(L.Price) AS NumListing, PC.CategoryID
            FROM product P
            JOIN productcategory PC ON P.ProductID = PC.ProductID
            JOIN listing L ON P.ProductID = L.ProductID
            JOIN review R ON P.ProductID = R.ProductID
            GROUP BY P.ProductID
            ORDER BY NumListing DESC
        ";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result();
        $this->sendSuccessResponse($result->fetch_all(MYSQLI_ASSOC));
    }

    /*
    {
    "Type": "GetAverageRating",
    "ProductID": 346
    }
    */

    private function handleGetAverageRating($data) {
        if(!isset($data["ProductID"]))
            $this->sendErrorResponse("ProductID is required.", 400);
        $productID = (int)$data["ProductID"];
        $sql = "
            SELECT AVG(Rating) AS AverageRating 
            FROM review
            WHERE ProductID = ?
        ";
        $stmt = $this->connection->prepare($sql);
        $stmt->bind_param("i", $productID);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $avg = $result ? floatval($result["AverageRating"]) : null;
        $this->sendSuccessResponse($avg);
    }

    /*
    {
    "Type": "GetProduct",
    "ProductID": 346
    }
    */

    private function handleGetProduct($data){
        if(!isset($data["ProductID"]))
            $this->sendErrorResponse("ProductID is required.", 400);
        $productID = (int)$data["ProductID"];
        try{
            $sql = "SELECT ProductID, ProductName, Brand, Description, ImageURL FROM product
            WHERE product.ProductID = ?";
            $query = $this->connection->prepare($sql);
            $query->bind_param("i", $productID);
            $query->execute();
            $result = $query->get_result();
            if($result->num_rows === 0)
                $this->sendErrorResponse("Product not found.", 400);
            $product = $result->fetch_assoc();

            $sql = "SELECT listing.Date, listing.Price FROM listing JOIN retailer ON listing.RetailerID = retailer.RetailerID WHERE ProductID = ?";
            $query = $this->connection->prepare($sql);
            $query->bind_param("i", $productID);
            $query->execute();
            $listings = $query->get_result()->fetch_all(MYSQLI_ASSOC);

            $sql = "SELECT c.CategoryID, c.CategoryName FROM productcategory pc JOIN category c ON pc.CategoryID = c.CategoryID WHERE pc.ProductID = ?";
            $query = $this->connection->prepare($sql);
            $query->bind_param("i", $ProductID);
            $query->execute();
            $categories = $query->get_result()->fetch_all(MYSQLI_ASSOC);
            $product["Listings"] = $listings;
            $product["Categories"] = $categories;
            $this->sendSuccessResponse($product);


        }
        catch(Exception $e){
            $this->sendErrorResponse("Failed to retreive products: " . $e->getMessage(), 500);
        }
    }

    //works
    private function handleGetCategories(){
        $sql = "SELECT * FROM category";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result();
        $this->sendSuccessResponse($result->fetch_all(MYSQLI_ASSOC));
    }

    private function handleManageCategory($data) {
        $action = $data["action"];
        $name = isset($data["name"]) ? $this->connection->real_escape_string($data["name"]) : "";
        $id = isset($data["id"]) ? (int)$data["id"] : 0;
        if ($action === "add") {
            $sql = "INSERT INTO category (CategoryName) VALUES ('$name')";
        } elseif ($action === "edit") {
            $sql = "UPDATE category SET CategoryName = '$name' WHERE CategoryID = $id";
        } elseif ($action === "delete") {
            $sql = "DELETE FROM category WHERE CategoryID = $id";
        } else {
            $this->sendErrorResponse("Invalid category action", 400);
            return;
        }
        if ($this->connection->query($sql)) {
            $this->sendSuccessResponse("Action was executed successfully.");
        } else {
            $this->sendErrorResponse("Category DB error: " . $this->connection->error, 500);
        }
    }

    private function handleManageRetailer($data) {
        $action = $data["action"];
        $name = isset($data["name"]) ? $this->connection->real_escape_string($data["name"]) : "";
        $id = isset($data["id"]) ? (int)$data["id"] : 0;

        if ($action === "add") {
            $sql = "INSERT INTO retailer (RetailerName) VALUES ('$name')";
        } elseif ($action === "edit") {
            $sql = "UPDATE retailer SET RetailerName = '$name' WHERE RetailerID = $id";
        } elseif ($action === "delete") {
            $sql = "DELETE FROM retailer WHERE RetailerID = $id";
        } else {
            $this->sendErrorResponse("Invalid retailer action", 400);
            return;
        }
        if ($this->connection->query($sql)) {
            $this->sendSuccessResponse("Action was executed successfully.");
        }
        else {
            $this->sendErrorResponse("Retailer DB error: " . $this->connection->error, 500);
        }
    }

    private function handleCheckReview($data){
        if(!isset($data["UserID"]) || !isset($data["ProductID"]))
            $this->sendErrorResponse("UserID and ProductID are required.");
        $userID = $data["UserID"];
        $productID = $data["ProductID"];
        $sql = "SELECT ReviewID FROM review WHERE UserID = ? AND ProductID = ?";
        $query = $this->connection->prepare($sql);
        $query->bind_param("ii", $userID, $productID);
        $query->execute();
        $result = $query->get_result();
        if($result->num_rows === 0){
            echo json_encode([
                "status" => "error",
                "timestamp" => time(),
                "data" => "Review not found."
            ]);
            exit;
        }
        $this->sendSuccessResponse("Review found.");
    }

    private function handleGetBrands(){
        $sql = "SELECT DISTINCT Brand 
                FROM product 
                ORDER BY Brand"
                ; 
        $stmt = $this->connection->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result();
        $this->sendSuccessResponse($result->fetch_all(MYSQLI_ASSOC));
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