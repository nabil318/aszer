<?php
// Connexion à la base de données SQLite
try {
    $db = new PDO('sqlite:QCM.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Récupérer l'ID de la question depuis l'URL
$id = isset($_GET['id']) ? $_GET['id'] : null;

if ($id) {
    // Récupérer les détails de la question à partir de l'ID
    $sql = "SELECT * FROM QI WHERE id_QI = :id";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    // Récupérer la question
    $QI = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($QI) {
        // Récupérer les informations (avec gestion des valeurs nulles)
        $enonce = isset($QI['enonce']) ? htmlspecialchars($QI['enonce']) : '';
        $reponse_correcte = isset($QI['reponse_correcte']) ? $QI['reponse_correcte'] : ''; // Vérification null pour la réponse correcte
        $correction = isset($QI['correction']) ? htmlspecialchars($QI['correction']) : '';
        $imageUrl = isset($QI['image_url']) ? $QI['image_url'] : ''; // Récupérer l'URL de l'image
    } else {
        echo "<p>Question non trouvée.</p>";
        exit();
    }
} else {
    echo "<p>Identifiant de question manquant.</p>";
    exit();
}

$message_resultat = ''; // Message à afficher après la soumission du formulaire
$note = null; // Note de l'utilisateur
$reponse_utilisateur = null; // Réponse de l'utilisateur
$responses_correctes = explode(',', $reponse_correcte); // Découper les bonnes réponses (ex : "1,3")

// Supposons que l'utilisateur a un ID (peut être récupéré par une session ou une valeur fictive)
$utilisateur_id = 1; // Utilisateur fictif pour cet exemple

// Initialisation de la variable $erreur
$erreur = 0;

// Vérification si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Récupérer la réponse soumise par l'utilisateur
    $reponse_utilisateur = isset($_POST['reponse']) ? trim($_POST['reponse']) : null;

    // Comparer la réponse soumise avec la réponse correcte
    if (!in_array($reponse_utilisateur, $responses_correctes)) {
        $erreur = 1; // 1 erreur si la réponse est incorrecte
    }

    // Calculer la note en fonction des erreurs
    if ($erreur == 0) {
        $note = 20;
    } else {
        $note = 0;
    }

    // Enregistrer la réponse de l'utilisateur dans la table 'user_results' (si la réponse n'est pas vide)
    if ($reponse_utilisateur !== null) {
        // Ajouter la date de la réponse dans la requête
        $sql_insert = "INSERT INTO user_results (user_id, qcm_id, date, note)
                       VALUES (:user_id, :qcm_id, :date, :note)";
        $stmt_insert = $db->prepare($sql_insert);
        $stmt_insert->bindParam(':user_id', $utilisateur_id, PDO::PARAM_INT);
        $stmt_insert->bindParam(':qcm_id', $id, PDO::PARAM_INT);
        $stmt_insert->bindValue(':date', date('Y-m-d H:i:s')); // Enregistrer la date et l'heure actuelles
        $stmt_insert->bindParam(':note', $note, PDO::PARAM_INT); // Enregistrer la note
        $stmt_insert->execute();
    }

    // Message à afficher après la soumission
    if ($erreur == 0) {
        $message_resultat = "<div class='feedback correct'>Bonne réponse !</div>";
    } else {
        $message_resultat = "<div class='feedback incorrect'></div>"; // Pas de texte ici
    }
}

// Si l'utilisateur a validé la bonne réponse avec le bouton "Ma réponse était juste"
if (isset($_POST['valider_bonne_reponse'])) {
    // Considérer que la réponse est correcte sans validation manuelle
    $reponse_utilisateur = $responses_correctes[0]; // On suppose que la première réponse est correcte
    $note = 20;

    // Enregistrer la réponse de l'utilisateur dans la table 'user_results' (en utilisant la bonne réponse)
    if ($reponse_utilisateur !== null) {
        // Ajouter la date de la réponse dans la requête
        $sql_insert = "INSERT INTO user_results (user_id, qcm_id, date, note)
                       VALUES (:user_id, :qcm_id, :date, :note)";
        $stmt_insert = $db->prepare($sql_insert);
        $stmt_insert->bindParam(':user_id', $utilisateur_id, PDO::PARAM_INT);
        $stmt_insert->bindParam(':qcm_id', $id, PDO::PARAM_INT);
        $stmt_insert->bindValue(':date', date('Y-m-d H:i:s')); // Enregistrer la date et l'heure actuelles
        $stmt_insert->bindParam(':note', $note, PDO::PARAM_INT); // Enregistrer la note
        $stmt_insert->execute();
    }

    // Message à afficher après la validation
    $message_resultat = "<div class='feedback correct'>Réponse validée comme correcte !</div>";
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QCM - Question QI</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Style de base */
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: rgb(48, 8, 86);
            color: white;
            padding: 10px 20px;
        }

        header a {
            color: white;
            text-decoration: none;
            padding: 10px;
            border: 1px solid white;
            border-radius: 5px;
            margin-left: 10px;
            transition: background-color 0.3s;
        }

        header a:hover {
            background-color: #555;
        }

        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f9;
            margin: 0;
            padding: 0;
        }

        #question-container {
            padding: 20px;
            margin: 20px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        h1,
        h2 {
            font-size: 1.5rem;
        }

        .feedback {
            margin-top: 20px;
            padding: 10px;
            border-radius: 5px;
            font-weight: bold;
        }

        .feedback.correct {
            background-color: green;
            color: white;
        }

        .feedback.incorrect {
            background-color: red;
            color: white;
        }

        input[type="submit"] {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
            margin-top: 20px;
        }

        input[type="submit"]:hover {
            background-color: #45a049;
        }

        .note {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 20px;
            margin-top: 20px;
            position: absolute;
            top: 40px;
            right: 20px;
            border: 2px solid black;
        }

        .note.correct {
            background-color: green;
        }

        .note.incorrect {
            background-color: red;
        }

        .reponses-correctes {
            margin-top: 20px;
            font-weight: bold;
        }

        .valider-bonne-reponse {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
            margin-top: 20px;
        }

        .valider-bonne-reponse:hover {
            background-color: #45a049;
        }

        img {
            max-width: 600px;
            /* Réduire la taille de l'image */
            height: auto;
            display: block;
            margin: 20px auto;
        }

        #div1 {
            background-color: <?php echo isset($note) ? ($note == 20 ? '#4CAF50' : ($note == 0 ? '#F44336' : '#FF9800')) : 'rgb(86, 8, 69)'; ?>;
            color: white;
            padding: 20px;
            text-align: center;
        }

        #score {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 20px;
            margin-top: 20px;
            position: absolute;
            top: 40px;
            right: 20px;
            border: 2px solid black;
        }
    </style>
</head>

<body>
    <header>
        <div>
            <a id="home-button" href="accueil.php">Accueil</a>
            <a href='data.php'>Ajouter un QCM</a>
            <a href='ancrage.php'>Ancrage</a>
            <a href="exam_univ.php">Examens universitaires</a>
            <a href="annales_edn.php">Annales EDN</a>
            <a href="qcm_etudiant.php">QCM Étudiant</a>
        </div>
        <div>
            <a href="profil.php" style="float: right;">Profil</a>
        </div>
    </header>
    <div id="div1">
        <h1>QCM - QROC</h1>
    </div>
    <div id="question-container">
        <h1>Question : <?php echo $enonce; ?></h1>

        <?php
        // Utilise __DIR__ pour obtenir le chemin absolu du répertoire actuel
        $imagePath = __DIR__ . '/' . $imageUrl;

        // Vérifier si l'image existe à cet emplacement
        if (!empty($imageUrl) && file_exists($imagePath)) {
            echo '<h3>Image :</h3>';
            echo '<img src="' . htmlspecialchars($imageUrl) . '" alt="QCM Image">';
        }
        ?>

        <form method="POST" action="">
            <label for="reponse">Votre réponse :</label>
            <input type="text" id="reponse" name="reponse" value="<?php echo htmlspecialchars($reponse_utilisateur ?? ''); ?>" required>
            <input type="submit" value="Valider ma réponse">
        </form>

        <?php if ($message_resultat): ?>
            <div class="feedback <?php echo ($erreur == 0) ? 'correct' : 'incorrect'; ?>">
                <?php echo $message_resultat; ?>
                <div class="reponses-correctes">
                    Réponses correctes :<br>
                    <?php foreach ($responses_correctes as $reponse): ?>
                        - <?php echo htmlspecialchars($reponse); ?><br>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($note)): ?>
            <div class="note <?php echo ($note == 20) ? 'correct' : 'incorrect'; ?>">
                <?php echo $note; ?>/20
            </div>
        <?php endif; ?>

        <?php if ($erreur == 1 && $note == 0): ?>
            <form method="POST" action="">
                <input type="submit" name="valider_bonne_reponse" value="Ma réponse était juste" class="valider-bonne-reponse">
            </form>
        <?php endif; ?>
    </div>
</body>

</html>