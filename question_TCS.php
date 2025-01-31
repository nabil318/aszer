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
        // Récupérer les informations de la question
        $enonce = htmlspecialchars($QI['enonce_accueil'] ?? $QI['enonce']); // Utiliser enonce_accueil si disponible
        if (empty($enonce)) {
            echo "<p>Énoncé de la question manquant.</p>";
            exit();
        }

        $propositions = [
            'prop1' => htmlspecialchars($QI['prop1']),
            'prop2' => htmlspecialchars($QI['prop2']),
            'prop3' => htmlspecialchars($QI['prop3']),
            'prop4' => htmlspecialchars($QI['prop4']),
            'prop5' => htmlspecialchars($QI['prop5']),
            'prop6' => htmlspecialchars($QI['prop6']),
            'prop7' => htmlspecialchars($QI['prop7']),
            'prop8' => htmlspecialchars($QI['prop8']),
            'prop9' => htmlspecialchars($QI['prop9']),
            'prop10' => htmlspecialchars($QI['prop10']),
        ];
        $reponse_correcte = $QI['reponse_correcte'];  // La réponse correcte (ex : "1,3")
        $correction = htmlspecialchars($QI['correction']);
        $commentaires = [
            'commentaire_prop1' => htmlspecialchars($QI['commentaire_prop1']),
            'commentaire_prop2' => htmlspecialchars($QI['commentaire_prop2']),
            'commentaire_prop3' => htmlspecialchars($QI['commentaire_prop3']),
            'commentaire_prop4' => htmlspecialchars($QI['commentaire_prop4']),
            'commentaire_prop5' => htmlspecialchars($QI['commentaire_prop5']),
            'commentaire_prop6' => htmlspecialchars($QI['commentaire_prop6']),
            'commentaire_prop7' => htmlspecialchars($QI['commentaire_prop7']),
            'commentaire_prop8' => htmlspecialchars($QI['commentaire_prop8']),
            'commentaire_prop9' => htmlspecialchars($QI['commentaire_prop9']),
            'commentaire_prop10' => htmlspecialchars($QI['commentaire_prop10']),
        ];
        $imageUrl = $QI['image_url']; // Récupérer l'URL de l'image
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
$erreurs = []; // Tableau pour gérer les erreurs et encadrer les propositions
$reponse_utilisateur = null; // Réponse de l'utilisateur

// Supposons que l'utilisateur a un ID (peut être récupéré par une session ou une valeur fictive)
$utilisateur_id = 1; // Utilisateur fictif pour cet exemple

// Vérification si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Récupérer la réponse soumise par l'utilisateur
    $reponse_utilisateur = isset($_POST['reponse']) ? $_POST['reponse'] : null;

    // Comparer les réponses sélectionnées avec la réponse correcte
    $erreur = 0;
    $reponses_correctes = explode(',', $reponse_correcte); // Si plusieurs bonnes réponses (ex : "1,3")
    if (!in_array($reponse_utilisateur, $reponses_correctes)) {
        $erreur = 1; // 1 erreur si la réponse est incorrecte
    }

    // Calculer la note en fonction des erreurs
    if ($erreur == 0) {
        $note = 20;
    } else {
        $note = 0;
    }

    // Enregistrer la réponse de l'utilisateur dans la table 'user_results'
    $sql_insert = "INSERT INTO user_results (user_id, qcm_id, date, note) VALUES (:user_id, :qcm_id, :date, :note)";
    $stmt_insert = $db->prepare($sql_insert);
    $stmt_insert->bindParam(':user_id', $utilisateur_id, PDO::PARAM_INT);
    $stmt_insert->bindParam(':qcm_id', $id, PDO::PARAM_INT);
    $stmt_insert->bindValue(':date', date('Y-m-d H:i:s')); // Enregistrer la date et l'heure actuelles
    $stmt_insert->bindParam(':note', $note, PDO::PARAM_INT); // Enregistrer la note
    $stmt_insert->execute();

    // Message à afficher après la soumission
    if ($erreur == 0) {
        $message_resultat = "<div class='bonne-reponse'>Bonne réponse !</div>";
    } else {
        $message_resultat = "<div class='mauvaise-reponse'>Mauvaise réponse. Essayez encore !</div>";
    }

    // Gérer les erreurs pour encadrer les propositions
    foreach ($propositions as $key => $prop) {
        $i = substr($key, -1); // Récupérer le numéro de la proposition (prop1 -> 1, prop2 -> 2, ...)
        if ($reponse_utilisateur == $i) {
            if (in_array($reponse_utilisateur, $reponses_correctes)) {
                $erreurs[$i] = 'correct'; // Réponse correcte
            } else {
                $erreurs[$i] = 'incorrect'; // Réponse incorrecte
            }
        } else {
            if (in_array($i, $reponses_correctes)) {
                $erreurs[$i] = 'correct'; // Afficher toutes les bonnes réponses en vert
            } else {
                $erreurs[$i] = 'incorrect'; // Afficher toutes les mauvaises réponses en rouge
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QCM - Question TCS</title>
    <link rel="stylesheet" href="./style.css">
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

        #div1 {
            background-color: <?php echo isset($note) && $note === 20 ? '#4CAF50' : (isset($note) ? '#f44336' : 'rgb(86, 8, 69)'); ?>;
            color: white;
            padding: 20px;
            text-align: center;
        }

        #question {
            padding: 20px;
            margin: 20px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        h1,
        h2,
        h3 {
            font-size: 1.5rem;
        }

        .resultat {
            margin-top: 20px;
            padding: 10px;
        }

        .bonne-reponse {
            background-color: #4CAF50;
            color: white;
            padding: 10px;
            border-radius: 5px;
        }

        .mauvaise-reponse {
            background-color: #f44336;
            color: white;
            padding: 10px;
            border-radius: 5px;
        }

        .score-circle {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background-color: #f1f1f1;
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

        .score-20 {
            background-color: #4CAF50;
        }

        .score-0 {
            background-color: #F44336;
        }

        .correct {
            background-color: #4CAF50;
            color: white;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 10px;
        }

        .incorrect {
            background-color: #f44336;
            color: white;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 10px;
        }

        .commentaire {
            font-style: italic;
            color: #555;
            margin-top: 5px;
        }

        label {
            display: block;
            margin-bottom: 15px;
            font-size: 1.1rem;
            cursor: pointer;
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

        img {
            max-width: 600px;
            /* Réduire la taille de l'image */
            height: auto;
            display: block;
            margin: 20px auto;
        }
    </style>
</head>

<body>
    <header>
        <div>
            <a id="home-button" href="accueil.php">Accueil</a>
            <a href='data.php'>Ajouter un QCM</a>
            <a href='ancrage.php'>Ancrage</a>
            <a href="exam_univ.php">Examens Universitaires</a>
            <a href="annales_edn.php">Annales EDN</a>
            <a href="qcm_etudiant.php">QCM Étudiant</a>
        </div>
        <div>
            <a href="profil.php" style="float: right;">Profil</a>
        </div>
    </header>

    <div id="div1">
        <h1>QCM - TCS</h1>
    </div>

    <div id="question">
        <h2>Énoncé :</h2>
        <p><?php echo $enonce; ?></p>

        <?php
        // Utilise __DIR__ pour obtenir le chemin absolu du répertoire actuel
        $imagePath = __DIR__ . '/' . $imageUrl;

        // Vérifier si l'image existe à cet emplacement
        if (!empty($imageUrl) && file_exists($imagePath)) {
            echo '<h3>Image :</h3>';
            echo '<img src="' . htmlspecialchars($imageUrl) . '" alt="QCM Image">';
        }
        ?>

        <h3>Propositions :</h3>
        <!-- Formulaire pour valider la réponse -->
        <form method="POST">
            <?php
            // Affichage dynamique des propositions avec des radio buttons
            foreach ($propositions as $key => $prop) {
                if (!empty($prop)) {
                    $i = substr($key, -1); // Récupérer le numéro de la proposition (prop1 -> 1, prop2 -> 2, ...)
                    $checked = (isset($reponse_utilisateur) && $reponse_utilisateur == $i) ? 'checked' : ''; // Garder la réponse sélectionnée
                    $class = isset($erreurs[$i]) ? $erreurs[$i] : ''; // Appliquer la classe selon l'état de la réponse

                    echo "<label class='$class'>
                            <input type='radio' name='reponse' value='$i' $checked> " . htmlspecialchars($prop) . "
                        </label>";

                    // Affichage du commentaire pour chaque proposition seulement si la réponse a été soumise
                    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                        $commentaire_key = 'commentaire_prop' . $i;
                        if (isset($commentaires[$commentaire_key])) {
                            echo "<div class='commentaire'>" . $commentaires[$commentaire_key] . "</div>";
                        }
                    }
                }
            }
            ?>

            <br>
            <input type="submit" value="Valider la réponse">
        </form>

        <!-- Affichage du résultat après soumission -->
        <?php if ($_SERVER['REQUEST_METHOD'] == 'POST'): ?>
            <div class="resultat">
                <h3>Correction :</h3>
                <p><?php echo $correction; ?></p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Affichage du cercle avec la note -->
    <?php if ($_SERVER['REQUEST_METHOD'] == 'POST'): ?>
        <div class="score-circle <?php echo $note === 20 ? 'score-20' : 'score-0'; ?>">
            <p><?php echo $note ? $note : '0'; ?></p>
        </div>
    <?php endif; ?>
</body>

</html>