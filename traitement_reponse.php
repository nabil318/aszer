<?php
// Connexion à la base de données SQLite
try {
    $db = new PDO('sqlite:QCM.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Vérification des paramètres POST
if (isset($_POST['specialite']) && isset($_POST['item']) && isset($_POST['reponse'])) {
    $specialite = $_POST['specialite'];
    $item = $_POST['item'];
    $reponses_utilisateur = $_POST['reponse']; // Les réponses de l'utilisateur

    // Déterminer la table à interroger en fonction du type_qcm
    // On interroge 'QI' si le type_qcm est 'QI', sinon on interroge DP_x
    $sql_type_qcm = "SELECT type_qcm FROM QI WHERE specialite = :specialite AND item = :item";
    $stmt_type_qcm = $db->prepare($sql_type_qcm);
    $stmt_type_qcm->bindParam(':specialite', $specialite);
    $stmt_type_qcm->bindParam(':item', $item);
    $stmt_type_qcm->execute();

    $question_data = $stmt_type_qcm->fetch(PDO::FETCH_ASSOC);
    if (!$question_data) {
        echo "<p>Aucune question trouvée pour cette spécialité et cet item.</p>";
        exit();
    }

    $type_qcm = $question_data['type_qcm'];

    // Déterminer la table à interroger
    if ($type_qcm == 'QI') {
        // Si type_qcm est 'QI', on interroge la table 'QI'
        $table = 'QI';
    } else {
        // Sinon on interroge la table 'DP_x', où x est l'item
        $table = "DP_$item";
    }

    // Requête SQL pour récupérer la réponse correcte depuis la table choisie
    $sql = "SELECT reponse_correcte FROM $table WHERE specialite = :specialite AND item = :item";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':specialite', $specialite);
    $stmt->bindParam(':item', $item);
    $stmt->execute();

    $question = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$question) {
        echo "<p>Aucune question trouvée pour cette spécialité et cet item dans la table $table.</p>";
        exit();
    }

    // Récupération de la réponse correcte (qui peut être une liste pour QRM)
    $reponse_correcte = explode(',', $question['reponse_correcte']);  // Divise les réponses correctes si c'est une liste

    // Vérification de la réponse de l'utilisateur
    $bonne_reponse = false;

    if (is_array($reponses_utilisateur)) {
        // Pour les réponses multiples (QRM)
        $bonne_reponse = !array_diff($reponses_utilisateur, $reponse_correcte) && !array_diff($reponse_correcte, $reponses_utilisateur);
    } else {
        // Pour les réponses uniques (QRU, QM)
        $bonne_reponse = in_array($reponses_utilisateur, $reponse_correcte);
    }

    // Affichage du résultat
    if ($bonne_reponse) {
        echo "<p>Bravo ! Vous avez choisi la bonne réponse.</p>";
        // Ajout de la note de 1 si c'est la bonne réponse
        $note = 1;
    } else {
        echo "<p>Désolé, la réponse est incorrecte.</p>";
        // Note de 0 si la réponse est incorrecte
        $note = 0;
    }

    // Enregistrer la réponse dans la table 'reponses'
    $sql_insert_reponse = "INSERT INTO reponses (id_question, utilisateur_id, reponse_utilisateur) 
                           VALUES (:id_question, :utilisateur_id, :reponse_utilisateur)";
    $stmt_insert = $db->prepare($sql_insert_reponse);
    $stmt_insert->bindParam(':id_question', $item); // L'ID de la question ou de l'item
    $stmt_insert->bindParam(':utilisateur_id', $_SESSION['utilisateur_id']); // ID utilisateur (si vous gérez les sessions utilisateurs)
    $stmt_insert->bindParam(':reponse_utilisateur', $reponses_utilisateur); // La réponse donnée par l'utilisateur
    $stmt_insert->execute();

    // Vous pouvez aussi enregistrer la note dans une autre table si nécessaire.
} else {
    echo "<p>Il manque des informations dans le formulaire.</p>";
}
// ehh