<?php
// Connexion à la base de données SQLite
try {
    $db = new PDO('sqlite:QCM.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Récupérer les données envoyées par le formulaire
$id_DP = $_POST['id_DP'] ?? null;
$current_question = $_POST['question_id'] ?? null;  // Correction ici
$reponse_utilisateur = $_POST['reponse'] ?? null;

if (!$id_DP || !$current_question || !$reponse_utilisateur) {
    die("Erreur : données manquantes");
}

// Vérifier si la réponse existe déjà dans la base pour éviter les doublons
try {
    $sql_check = "SELECT * FROM reponses WHERE id_DP = :id_DP AND id_question = :id_question";
    $stmt_check = $db->prepare($sql_check);
    $stmt_check->bindParam(':id_DP', $id_DP);
    $stmt_check->bindParam(':id_question', $current_question);
    $stmt_check->execute();
    $existing_answer = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$existing_answer) {
        // Insérer la réponse de l'utilisateur dans la table 'reponses'
        $sql = "INSERT INTO reponses (id_DP, id_question, reponse_utilisateur) VALUES (:id_DP, :id_question, :reponse_utilisateur)";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':id_DP', $id_DP);
        $stmt->bindParam(':id_question', $current_question);
        $stmt->bindParam(':reponse_utilisateur', $reponse_utilisateur);
        $stmt->execute();
    }
} catch (PDOException $e) {
    die("Erreur de requête : " . $e->getMessage());
}

// Rediriger vers la question suivante
if ($current_question < 7) {
    // Rediriger vers la question suivante avec un "exit" après le header pour éviter des sorties non désirées
    header("Location: question_DP.php?id=$id_DP&question=" . ($current_question + 1));
    exit();
} else {
    echo "Vous avez terminé le dossier progressif.";
}
