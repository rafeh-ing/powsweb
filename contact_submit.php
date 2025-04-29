<?php
header('Content-Type: application/json');

// Enable all error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Database configuration - UPDATE THESE TO MATCH YOUR SERVER
define('DB_HOST', 'localhost');
define('DB_USER', 'root');      // Your database username
define('DB_PASS', '');          // Your database password
define('DB_NAME', 'pawsfromabove'); // Your database name

// Create logs directory if it doesn't exist
if (!is_dir('logs')) {
    mkdir('logs', 0755, true);
}

// Start logging
$logData = [
    'timestamp' => date('Y-m-d H:i:s'),
    'ip' => $_SERVER['REMOTE_ADDR'],
    'post_data' => $_POST,
    'server_data' => $_SERVER
];
file_put_contents('logs/contact_debug.log', print_r($logData, true) . "\n", FILE_APPEND);

try {
    // Validate required fields
    $required = ['name', 'email', 'subject', 'message'];
    $errors = [];
    
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            $errors[] = "The $field field is required.";
        }
    }
    
    if (!empty($errors)) {
        throw new Exception(implode(" ", $errors));
    }

    // Validate email format
    if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Please enter a valid email address.");
    }

    // Test database connection
    try {
        $pdo = new PDO(
            "mysql:host=".DB_HOST.";dbname=".DB_NAME,
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ]
        );
    } catch (PDOException $e) {
        throw new Exception("Database connection failed: " . $e->getMessage());
    }

    // Check if table exists, create if not
    $tableExists = $pdo->query("SHOW TABLES LIKE 'contact_messages'")->rowCount() > 0;
    
    if (!$tableExists) {
        $createSQL = "CREATE TABLE contact_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL,
            phone VARCHAR(20),
            subject VARCHAR(50) NOT NULL,
            message TEXT NOT NULL,
            submission_date DATETIME NOT NULL,
            status ENUM('unread','read','replied') DEFAULT 'unread'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        if ($pdo->exec($createSQL) === false) {
            throw new Exception("Failed to create table: " . implode(" ", $pdo->errorInfo()));
        }
    }

    // Insert message with prepared statement
    $stmt = $pdo->prepare("INSERT INTO contact_messages 
        (name, email, phone, subject, message, submission_date)
        VALUES (:name, :email, :phone, :subject, :message, NOW())");
    
    $params = [
        ':name' => htmlspecialchars($_POST['name'], ENT_QUOTES, 'UTF-8'),
        ':email' => filter_var($_POST['email'], FILTER_SANITIZE_EMAIL),
        ':phone' => !empty($_POST['phone']) ? htmlspecialchars($_POST['phone'], ENT_QUOTES, 'UTF-8') : null,
        ':subject' => htmlspecialchars($_POST['subject'], ENT_QUOTES, 'UTF-8'),
        ':message' => htmlspecialchars($_POST['message'], ENT_QUOTES, 'UTF-8')
    ];
    
    if (!$stmt->execute($params)) {
        throw new Exception("Failed to save message: " . implode(" ", $stmt->errorInfo()));
    }

    // Success response
    echo json_encode([
        'status' => 'success',
        'message' => 'Thank you! Your message has been sent successfully.',
        'id' => $pdo->lastInsertId()
    ]);

} catch (Exception $e) {
    $errorMsg = "Error: " . $e->getMessage();
    file_put_contents('logs/contact_error.log', date('Y-m-d H:i:s') . " - " . $errorMsg . "\n", FILE_APPEND);
    
    // User-friendly error message
    $userMessage = 'Submission error. Please try again later.';
    if (strpos($e->getMessage(), 'Database connection') !== false) {
        $userMessage = 'System error. Our team has been notified.';
    }
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $userMessage,
        'debug' => $errorMsg // Remove in production
    ]);
}