<?php
header("Content-Type: application/json");
require_once("config.php");

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
        if(!$data)
            $this->sendErrorResponse("No data was received.", 400);
        if(!isset($data["Type"]))
            $this->sendErrorResponse("Request type not specified.", 400);
        switch($data["Type"]){
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
            case "Login":
                $this->handleLogin($data);
                break;
            case "DeleteUser":
                $this->handleDeleteUser($data);
                break;
            case "InsertReview":
                $this->handleInsertReview($data);
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
            case "GetAverageRating":
                $this->handleGetAverageRating($data);
                break;
            case "SortProducts":
                $this->handleSortProducts($data);
                break;
            case "FilterProducts":
                $this->handleFilterProducts($data);
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
    private function handelRegistration($data){
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
        $mysql_statement->bind_param("sssss", $data["RetailerName"], $data["RetailerURL"], $data["Country"], $data["City"], $data["Street"]);
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
        if(!isset($data["Email"]) || !isset($data["Password"]) || !isset($data["UserName"]))
            $this->sendErrorResponse("UserName, Email and password are required", 400);
        
        $stmt = $this->connection->prepare("SELECT UserName, Email, Password, Salt, Apikey FROM user WHERE Email = ? AND UserName = ?");
        $stmt->bind_param("ss", $data["Email"], $data["UserName"]);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($result->num_rows === 0)
            $this->sendErrorResponse("User not found", 401);
        
        $user = $result->fetch_assoc();
        
        // Verify password using the same hashing method as registration
        $hashedInputPassword = hash("sha512", $data["Password"] . $user["Salt"]);
        if($hashedInputPassword !== $user["Password"])
            $this->sendErrorResponse("Invalid credentials", 401);
        
        unset($user["Password"]);
        unset($user["Salt"]);
        
        $this->sendSuccessResponse([
            "message" => "Login successful",
            "Apikey" => $user["Apikey"]
        ]);
    }

    private function handleDeleteUser($data) {
        $userID = $data["UserID"] ?? null;
        if(!$userID)
            $this->sendErrorResponse("User ID is required", 400);
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
            
            if($user["Role"] === "Customer"){
                $stmt = $this->connection->prepare("DELETE FROM customer WHERE UserID = ?");
                $stmt->bind_param("i", $userID);
                if (!$stmt->execute()) 
                    throw new Exception("Failed to delete customer record");
            }
            else if($user["Role"] === "Admin"){
                $stmt = $this->connection->prepare("DELETE FROM admin WHERE UserID = ?");
                $stmt->bind_param("i", $userID);
                if (!$stmt->execute()) 
                    throw new Exception("Failed to delete admin record");
            }
            
            $stmt = $this->connection->prepare("DELETE FROM user WHERE UserID = ?");
            $stmt->bind_param("i", $userID);
            if (!$stmt->execute()) 
                throw new Exception("Failed to delete user record");
            
            $this->connection->commit();
            
            $this->sendSuccessResponse(["message" => "User deleted successfully"]);
            
        }
        catch(Exception $e){
            $this->connection->rollback();
            $this->sendErrorResponse("Failed to delete user: " . $e->getMessage(), 500);
        }
    }

    private function handleInsertReview($data) {
        if (!isset($data["ProductID"]) || !isset($data["Rating"]))
            $this->sendErrorResponse("Product ID and rating are required", 400);
        
        $userID = $data["userID"] ?? null;
        $comment = $data["Comment"] ?? null;
        $currentDate = date("Y-m-d");
        
        if($data["Rating"] < 1 || $data["Rating"] > 5)
            $this->sendErrorResponse("Rating must be between 1 and 5", 400);
        
        try{
            $stmt = $this->connection->prepare("INSERT INTO review (ProductID, UserID, Date, Rating, Comment) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iisis", $data["productID"], $userID, $currentDate, $data["Rating"], $comment);
            
            if (!$stmt->execute()) 
                throw new Exception($this->connection->error);
            
            $reviewID = $this->connection->insert_id;
            
            $this->sendSuccessResponse([
                "message" => "Review created successfully",
                "reviewID" => $reviewID
            ]);
            
        }
        catch (Exception $e) {
            $this->sendErrorResponse("Failed to create review: " . $e->getMessage(), 500);
        }
    }

    private function handleAddProduct($data) {
        if (!isset($data["ProductName"])) {
            $this->sendErrorResponse("Product name is required", 400);
        }
        
        $brand = $data["Brand"] ?? null;
        $description = $data["Description"] ?? null;
        $imageURL = $data["ImageURL"] ?? null;
        $categories = $data["Categories"] ?? [];
        
        $this->connection->begin_transaction();
        
        try {
            $stmt = $this->connection->prepare("INSERT INTO product (ProductName, Brand, Description, ImageURL) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $data["ProductName"], $brand, $description, $imageURL);
            
            if (!$stmt->execute()) 
                throw new Exception($this->connection->error);
            
            $productID = $this->connection->insert_id;
            
            if (!empty($categories)) {
                $stmt = $this->connection->prepare("INSERT INTO ProductCategory (ProductID, CategoryID) VALUES (?, ?)");
                foreach ($categories as $categoryID) {
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
        catch (Exception $e) {
            $this->connection->rollback();
            $this->sendErrorResponse("Failed to create product: " . $e->getMessage(), 500);
        }
    }

    // Edit an existing product
    private function handleEditProduct($data) {
        if (!isset($data["ProductID"]))
            $this->sendErrorResponse("Product ID is required", 400);
        
        $this->connection->begin_transaction();
        
        try {
            $stmt = $this->connection->prepare("SELECT ProductID FROM Product WHERE ProductID = ?");
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
                $stmt = $this->connection->prepare("DELETE FROM ProductCategory WHERE ProductID = ?");
                $stmt->bind_param("i", $data["productID"]);
                
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
            
        } catch (Exception $e) {
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
            SELECT P.ProductID, P.ProductName, P.Brand, P.Description, P.ImageURL, AVG(R.Rating) AS AverageRating
            FROM product P
            JOIN review R ON P.ProductID = R.ProductID
            GROUP BY P.ProductID
            ORDER BY AverageRating DESC
            LIMIT 10
        ";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result();
        $this->sendSuccessResponse($result->fetch_all(MYSQLI_ASSOC));
    }

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

    private function handleSortProducts($data) {
        if(!isset($data["Criteria"]))
            $this->sendErrorResponse("Sorting criteria is required.", 400);
        $criteria = $data["Criteria"];
        $sortOptions = [
            "dateListed" => "L.Date",
            "averageRating" => "AverageRating",
            "price" => "L.Price"
        ];
        if (!array_key_exists($criteria, $sortOptions)) {
            $this->sendErrorResponse("Invalid sort criteria.", 400);
        }
        $col = $sortOptions[$criteria];
        $sql = "
            SELECT P.ProductID, P.ProductName, P.Brand, L.Date, L.Price, AVG(R.Rating) as AverageRating
            FROM product P
            JOIN listing L ON P.ProductID = L.ProductID
            LEFT JOIN review R ON P.ProductID = R.ProductID
            GROUP BY P.ProductID, L.Date, L.Price
            ORDER BY $col DESC
        ";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result();
        $this->sendSuccessResponse($result->fetch_all(MYSQLI_ASSOC));
    }

    private function handleFilterProducts($data) {
        if(!isset($data["Criteria"]))
            $this->sendErrorResponse("Filtering criteria is required.", 400);
        if(!isset($data["Value"]))
            $this->sendErrorResponse("Filtering value is required.", 400);
        $criteria = $data["Criteria"];
        $value = $data["Value"];
        
        $sql = "";
        switch ($criteria) {
            case "Retailer":
                $sql = "
                    SELECT DISTINCT P.ProductID, P.ProductName, P.Brand, P.Description, P.ImageURL
                    FROM product P
                    LEFT JOIN listing L ON P.ProductID = L.ProductID
                    LEFT JOIN retailer R ON L.RetailerID = R.RetailerID
                    WHERE R.RetailerName = ?
                ";
                break;
            case "Brand":
                $sql = "
                    SELECT DISTINCT P.ProductID, P.ProductName, P.Brand, P.Description, P.ImageURL
                    FROM product P
                    WHERE P.Brand = ?
                ";
                break;
            case "Category":
                $sql = "
                    SELECT DISTINCT P.ProductID, P.ProductName, P.Brand, P.Description, P.ImageURL
                    FROM product P
                    LEFT JOIN productcategory PC ON P.ProductID = PC.ProductID
                    LEFT JOIN category C ON PC.CategoryID = C.CategoryID
                    WHERE C.CategoryName = ?jsut d
                ";
                break;
            default:
                $this->sendErrorResponse("Invalid filter criteria.", 400);
        }

        $stmt = $this->connection->prepare($sql);
        $stmt->bind_param("s", $value);
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
