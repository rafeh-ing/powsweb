<?php
// Active l'affichage des erreurs
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Enregistre les données reçues pour debug
file_put_contents('debug.log', date('Y-m-d H:i:s')."\n".print_r($_POST, true)."\n\n", FILE_APPEND);

// Vérifie les données obligatoires
$required = ['name', 'image', 'fullName', 'email', 'phone', 'address'];
foreach ($required as $field) {
    if (empty($_POST[$field])) {
        die("Erreur: Le champ $field est requis.");
    }
}

// Connexion à la base de données
try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=pawsfromabove", 
        "root", 
        "", 
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    file_put_contents('error.log', $e->getMessage()."\n", FILE_APPEND);
    die("Erreur de connexion à la base de données. Veuillez réessayer plus tard.");
}

// Préparation et exécution de la requête
try {
    $stmt = $pdo->prepare("
        INSERT INTO adoptions 
        (pet_name, pet_image, full_name, email, phone, address) 
        VALUES (:name, :image, :full_name, :email, :phone, :address)
    ");
    
    $stmt->execute([
        ':name' => $_POST['name'],
        ':image' => $_POST['image'],
        ':full_name' => $_POST['fullName'],
        ':email' => $_POST['email'],
        ':phone' => $_POST['phone'],
        ':address' => $_POST['address']
    ]);
    
    // Redirection en cas de succès
    header("Location: thanks.html");
    exit;

} catch (PDOException $e) {
    file_put_contents('error.log', $e->getMessage()."\n", FILE_APPEND);
    die("Erreur lors de l'enregistrement. Veuillez réessayer.");
}
?>