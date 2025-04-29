<?php
header('Content-Type: application/json');

// Enable detailed error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'pawsfromabove');

// Create connection log
file_put_contents('volunteer_debug.log', "\n\n" . date('Y-m-d H:i:s') . " - New submission attempt\n", FILE_APPEND);

try {
    // Validate required fields
    $required = ['fullName', 'email', 'phone', 'interest', 'why'];
    $errors = [];
    
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            $errors[] = "The $field field is required.";
        }
    }
    
    // Check at least one availability
    if (!isset($_POST['weekday_mornings']) && 
        !isset($_POST['weekday_afternoons']) && 
        !isset($_POST['weekends'])) {
        $errors[] = "Please select at least one availability option.";
    }
    
    if (!empty($errors)) {
        throw new Exception(implode(' ', $errors));
    }

    // Validate email
    if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Invalid email format.");
    }

    // Process availability
    $availability = [
        'weekday_mornings' => isset($_POST['weekday_mornings']) ? 1 : 0,
        'weekday_afternoons' => isset($_POST['weekday_afternoons']) ? 1 : 0,
        'weekends' => isset($_POST['weekends']) ? 1 : 0
    ];

    // Connect to database
    $pdo = new PDO(
        "mysql:host=".DB_HOST.";dbname=".DB_NAME,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    // Check if volunteers table exists
    $tableExists = $pdo->query("SHOW TABLES LIKE 'volunteers'")->rowCount() > 0;
    
    if (!$tableExists) {
        // Create volunteers table if it doesn't exist
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS volunteers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                full_name VARCHAR(100) NOT NULL,
                email VARCHAR(100) NOT NULL,
                phone VARCHAR(20) NOT NULL,
                interest_area VARCHAR(50) NOT NULL,
                availability TEXT NOT NULL,
                experience TEXT,
                motivation TEXT NOT NULL,
                application_date DATETIME NOT NULL,
                status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending'
            )
        ");
    }

    // Insert application
    $stmt = $pdo->prepare("
        INSERT INTO volunteers 
        (full_name, email, phone, interest_area, availability, experience, motivation, application_date)
        VALUES (:full_name, :email, :phone, :interest, :availability, :experience, :motivation, NOW())
    ");
    
    $stmt->execute([
        ':full_name' => htmlspecialchars($_POST['fullName']),
        ':email' => filter_var($_POST['email'], FILTER_SANITIZE_EMAIL),
        ':phone' => htmlspecialchars($_POST['phone']),
        ':interest' => $_POST['interest'],
        ':availability' => json_encode($availability),
        ':experience' => $_POST['experience'] ?? '',
        ':motivation' => htmlspecialchars($_POST['why'])
    ]);

    $newId = $pdo->lastInsertId();
    file_put_contents('volunteer_debug.log', "Successfully inserted record ID: $newId\n", FILE_APPEND);
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Thank you for your application! We will contact you soon.',
        'id' => $newId
    ]);

} catch (PDOException $e) {
    $error = "Database Error: " . $e->getMessage();
    file_put_contents('volunteer_error.log', date('Y-m-d H:i:s') . " - " . $error . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error occurred. Please try again later.',
        'debug' => $error
    ]);
} catch (Exception $e) {
    $error = "Application Error: " . $e->getMessage();
    file_put_contents('volunteer_error.log', date('Y-m-d H:i:s') . " - " . $error . "\n", FILE_APPEND);
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}