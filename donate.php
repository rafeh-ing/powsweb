<?php
header('Content-Type: application/json');

// Debug
file_put_contents('donation_debug.log', date('Y-m-d H:i:s')."\n".print_r($_POST, true)."\n", FILE_APPEND);

// Configuration BDD
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'pawsfromabove');

// Validation
$required = [
    'donor_name' => 'string',
    'donor_email' => 'email',
    'amount' => 'float',
    'donation_type' => ['one-time', 'monthly', 'sponsor'],
    'payment_method' => ['credit_card', 'paypal', 'bank_transfer']
];

$errors = [];
foreach ($required as $field => $validation) {
    if (empty($_POST[$field])) {
        $errors[] = "Le champ $field est requis.";
        continue;
    }

    if (is_array($validation) && !in_array($_POST[$field], $validation)) {
        $errors[] = "Valeur invalide pour $field.";
    }
}

if (!empty($errors)) {
    die(json_encode(['status' => 'error', 'message' => implode(' ', $errors)]));
}

// Connexion BDD
try {
    $pdo = new PDO(
        "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die(json_encode([
        'status' => 'error',
        'message' => 'Database connection failed'
    ]));
}

// Insertion
try {
    $stmt = $pdo->prepare("
        INSERT INTO donations 
        (donor_name, donor_email, donation_type, amount, payment_method, message, is_recurring, animal_id) 
        VALUES (:name, :email, :type, :amount, :method, :message, :recurring, :animal_id)
    ");
    
    $stmt->execute([
        ':name' => htmlspecialchars($_POST['donor_name']),
        ':email' => filter_var($_POST['donor_email'], FILTER_SANITIZE_EMAIL),
        ':type' => $_POST['donation_type'],
        ':amount' => (float)$_POST['amount'],
        ':method' => $_POST['payment_method'],
        ':message' => htmlspecialchars($_POST['message'] ?? ''),
        ':recurring' => ($_POST['donation_type'] === 'monthly') ? 1 : 0,
        ':animal_id' => ($_POST['donation_type'] === 'sponsor') ? (int)($_POST['animal_id'] ?? null) : null
    ]);
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Thank you for your donation!',
        'donation_id' => $pdo->lastInsertId()
    ]);
    
} catch (PDOException $e) {
    file_put_contents('donation_error.log', $e->getMessage()."\n", FILE_APPEND);
    die(json_encode([
        'status' => 'error',
        'message' => 'Database error: '.$e->getMessage()
    ]));
}