<?php
header("Content-Type: application/json");
require_once("config.php");

class API {
    private static $obj = null;
    private $connection;

    private function __construct() {
        $this->connection = getDBConnection();
        if ($this->connection->connect_error)
            $this->sendErrorResponse("Database connection failed", 500);
        
        $this->connection->set_charset("utf8");
    }

    public static function getInstance() {
        if (self::$obj == null)
            self::$obj = new API();
        return self::$obj;
    }

    public function handleRequest() {
        $data = json_decode(file_get_contents("php://input"), true);
        if($data)
            $this->sendErrorResponse("No data was received.", 400);
        if(!isset($data["type"]))
            $this->sendErrorResponse("Request type not specified.", 400);
        switch($endpoint) {
            case 'loginUser':
                $this->handleLogin();
                break;
            case 'deleteUser':
                $this->handleDeleteUser();
                break;
            case 'insertReview':
                $this->handleInsertReview();
                break;
            case 'addProduct':
                $this->handleAddProduct();
                break;
            case 'editProduct':
                $this->handleEditProduct();
                break;
            case 'deleteProduct':
                $this->handleDeleteProduct();
                break;
            default:
                $this->sendErrorResponse("Endpoint not found", 404);
                break;
        }
    }

    private function sendSuccessResponse($data) {
        echo json_encode([
            "status" => "success",
            "timestamp" => time(),
            "data" => $data
        ]);
        exit;
    }

    private function sendErrorResponse($message, $httpCode = 400) {
        http_response_code($httpCode);
        echo json_encode([
            "status" => "error",
            "timestamp" => time(),
            "message" => $message
        ]);
        exit;
    }

    // =========== LOGIN ==========
    private function handleLogin() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendErrorResponse("Method not allowed", 405);
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['email']) || !isset($data['password'])) {
            $this->sendErrorResponse("Email and password are required", 400);
        }
        
        $email = $this->connection->real_escape_string($data['email']);
        $query = "SELECT UserID, UserName, Email, Password, Role FROM User WHERE Email = '$email'";
        $result = $this->connection->query($query);
        
        if ($result->num_rows === 0) {
            $this->sendErrorResponse("User not found", 401);
        }
        
        $user = $result->fetch_assoc();
        
        if (!password_verify($data['password'], $user['Password'])) {
            $this->sendErrorResponse("Invalid credentials", 401);
        }
        
        unset($user['Password']);
        
        $this->sendSuccessResponse([
            'message' => 'Login successful',
            'user' => $user
        ]);
    }

    // =========== USER OPERATIONS ==========
    private function handleDeleteUser() {
        $userID = $_GET['id'] ?? null;
        
        if (!$userID) {
            $data = json_decode(file_get_contents('php://input'), true);
            $userID = $data['id'] ?? null;
        }
        
        if (!$userID) {
            $this->sendErrorResponse("User ID is required", 400);
        }
        
        $this->connection->begin_transaction();
        
        try {
            $userID = $this->connection->real_escape_string($userID);
            $queryCheckRole = "SELECT Role FROM User WHERE UserID = '$userID'";
            $roleResult = $this->connection->query($queryCheckRole);
            
            if ($roleResult->num_rows === 0) {
                $this->connection->rollback();
                $this->sendErrorResponse("User not found", 404);
            }
            
            $user = $roleResult->fetch_assoc();
            
            if ($user['Role'] === 'customer') {
                $queryDeleteCustomer = "DELETE FROM Customer WHERE UserID = '$userID'";
                if (!$this->connection->query($queryDeleteCustomer)) 
                    throw new Exception('Failed to delete customer record');
            } else if ($user['Role'] === 'admin') {
                $queryDeleteAdmin = "DELETE FROM Admin WHERE UserID = '$userID'";
                if (!$this->connection->query($queryDeleteAdmin)) 
                    throw new Exception('Failed to delete admin record');
            }
            
            $queryDeleteUser = "DELETE FROM User WHERE UserID = '$userID'";
            if (!$this->connection->query($queryDeleteUser)) 
                throw new Exception('Failed to delete user record');
            
            $this->connection->commit();
            
            $this->sendSuccessResponse(['message' => 'User deleted successfully']);
            
        } catch (Exception $e) {
            $this->connection->rollback();
            $this->sendErrorResponse('Failed to delete user: ' . $e->getMessage(), 500);
        }
    }

    // =========== REVIEW OPERATIONS ==========
    private function handleInsertReview() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['productID']) || !isset($data['rating'])) {
            $this->sendErrorResponse("Product ID and rating are required", 400);
        }
        
        $userID = $data['userID'] ?? null;
        $comment = $data['comment'] ?? null;
        $currentDate = date('Y-m-d');
        
        if ($data['rating'] < 1 || $data['rating'] > 5) {
            $this->sendErrorResponse("Rating must be between 1 and 5", 400);
        }
        
        try {
            $productID = $this->connection->real_escape_string($data['productID']);
            $rating = $this->connection->real_escape_string($data['rating']);
            $userID = $userID !== null ? $this->connection->real_escape_string($userID) : 'NULL';
            $comment = $comment !== null ? "'" . $this->connection->real_escape_string($comment) . "'" : 'NULL';
            
            if ($userID !== 'NULL') 
                $userID = "'$userID'";
            
            $query = "INSERT INTO Review (ProductID, UserID, Date, Rating, Comment) VALUES ('$productID', $userID, '$currentDate', '$rating', $comment)";
            
            if (!$this->connection->query($query)) 
                throw new Exception($this->connection->error);
            
            $reviewID = $this->connection->insert_id;
            
            $this->sendSuccessResponse([
                'message' => 'Review created successfully',
                'reviewID' => $reviewID
            ]);
            
        } catch (Exception $e) {
            $this->sendErrorResponse('Failed to create review: ' . $e->getMessage(), 500);
        }
    }

    // =========== PRODUCT OPERATIONS ==========
    private function handleAddProduct() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['productName'])) {
            $this->sendErrorResponse("Product name is required", 400);
        }
        
        $brand = $data['brand'] ?? null;
        $description = $data['description'] ?? null;
        $imageURL = $data['imageURL'] ?? null;
        $categories = $data['categories'] ?? [];
        
        $this->connection->begin_transaction();
        
        try {
            $productName = $this->connection->real_escape_string($data['productName']);
            $brand = $brand !== null ? "'" . $this->connection->real_escape_string($brand) . "'" : 'NULL';
            $description = $description !== null ? "'" . $this->connection->real_escape_string($description) . "'" : 'NULL';
            $imageURL = $imageURL !== null ? "'" . $this->connection->real_escape_string($imageURL) . "'" : 'NULL';
            
            $queryProduct = "INSERT INTO Product (ProductName, Brand, Description, ImageURL) VALUES ('$productName', $brand, $description, $imageURL)";
            
            if (!$this->connection->query($queryProduct)) 
                throw new Exception($this->connection->error);
            
            $productID = $this->connection->insert_id;
            
            if (!empty($categories)) 
                foreach ($categories as $categoryID) {
                    $categoryID = $this->connection->real_escape_string($categoryID);
                    $queryCategory = "INSERT INTO ProductCategory (ProductID, CategoryID) VALUES ('$productID', '$categoryID')";
                    if (!$this->connection->query($queryCategory)) 
                        throw new Exception($this->connection->error);
                }
            
            $this->connection->commit();
            
            $this->sendSuccessResponse([
                'message' => 'Product created successfully',
                'productID' => $productID
            ]);
            
        } catch (Exception $e) {
            $this->connection->rollback();
            $this->sendErrorResponse('Failed to create product: ' . $e->getMessage(), 500);
        }
    }

    // Edit an existing product
    private function handleEditProduct() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['productID'])) {
            $this->sendErrorResponse("Product ID is required", 400);
        }
        
        $this->connection->begin_transaction();
        
        try {
            $productID = $this->connection->real_escape_string($data['productID']);
            
            $queryCheck = "SELECT ProductID FROM Product WHERE ProductID = '$productID'";
            $result = $this->connection->query($queryCheck);
            
            if ($result->num_rows === 0) {
                $this->connection->rollback();
                $this->sendErrorResponse("Product not found", 404);
            }
            
            $updateFields = [];
            
            if (isset($data['productName'])) {
                $productName = $this->connection->real_escape_string($data['productName']);
                $updateFields[] = "ProductName = '$productName'";
            }
            
            if (isset($data['brand'])) {
                $brand = $this->connection->real_escape_string($data['brand']);
                $updateFields[] = "Brand = '$brand'";
            }
            
            if (isset($data['description'])) {
                $description = $this->connection->real_escape_string($data['description']);
                $updateFields[] = "Description = '$description'";
            }
            
            if (isset($data['imageURL'])) {
                $imageURL = $this->connection->real_escape_string($data['imageURL']);
                $updateFields[] = "ImageURL = '$imageURL'";
            }
            
            if (!empty($updateFields)) {
                $queryUpdate = "UPDATE Product SET " . implode(', ', $updateFields) . " WHERE ProductID = '$productID'";
                
                if (!$this->connection->query($queryUpdate)) 
                    throw new Exception($this->connection->error);
            }
            
            if (isset($data['categories'])) {
                $queryDeleteCategories = "DELETE FROM ProductCategory WHERE ProductID = '$productID'";
                
                if (!$this->connection->query($queryDeleteCategories)) 
                    throw new Exception($this->connection->error);
                
                foreach ($data['categories'] as $categoryID) {
                    $categoryID = $this->connection->real_escape_string($categoryID);
                    $queryInsertCategory = "INSERT INTO ProductCategory (ProductID, CategoryID) VALUES ('$productID', '$categoryID')";
                    if (!$this->connection->query($queryInsertCategory)) 
                        throw new Exception($this->connection->error);
                }
            }
            
            $this->connection->commit();
            
            $this->sendSuccessResponse(['message' => 'Product updated successfully']);
            
        } catch (Exception $e) {
            $this->connection->rollback();
            $this->sendErrorResponse('Failed to update product: ' . $e->getMessage(), 500);
        }
    }

    private function handleDeleteProduct() {
        $productID = $_GET['id'] ?? null;
        
        if (!$productID) {
            $data = json_decode(file_get_contents('php://input'), true);
            $productID = $data['id'] ?? null;
        }
        
        if (!$productID) {
            $this->sendErrorResponse("Product ID is required", 400);
        }
        
        try {
            $productID = $this->connection->real_escape_string($productID);
            
            $queryCheck = "SELECT ProductID FROM Product WHERE ProductID = '$productID'";
            $result = $this->connection->query($queryCheck);
            
            if ($result->num_rows === 0) {
                $this->sendErrorResponse("Product not found", 404);
            }
            
            $queryDelete = "DELETE FROM Product WHERE ProductID = '$productID'";
            
            if (!$this->connection->query($queryDelete)) 
                throw new Exception($this->connection->error);
            
            $this->sendSuccessResponse(['message' => 'Product deleted successfully']);
            
        } catch (Exception $e) {
            $this->sendErrorResponse('Failed to delete product: ' . $e->getMessage(), 500);
        }
    }
}

$api = API::getInstance();
$api->handleRequest();
?>