<?php
// Démarrer la session pour stocker les informations de l'utilisateur
session_start();

// Connexion à la base de données SQLite
try {
    $db = new PDO('sqlite:QCM.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données : " . htmlspecialchars($e->getMessage()));
}

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    // Rediriger vers la page de connexion si l'utilisateur n'est pas connecté
    header("Location: login.php");
    exit();
}

// Récupérer les données du formulaire
$commentaire = $_POST['commentaire'] ?? null;
$user_id = $_SESSION['user_id'];
$date = date('Y-m-d H:i:s');

// Vérifier si les champs sont remplis
if ($commentaire && $user_id && $date) {
    // Préparer la requête SQL pour insérer le commentaire
    $sql = "INSERT INTO commentaires (user_id, commentaire, date) VALUES (:user_id, :commentaire, :date)";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':commentaire', $commentaire);
    $stmt->bindParam(':date', $date);

    // Exécuter la requête
    $stmt->execute();

    // Rediriger vers la page des commentaires après l'insertion
    header("Location: commentaires.php");
    exit();
} else {
    echo "Veuillez remplir tous les champs.";
}
