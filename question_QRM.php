<?php
// Démarrer la session pour stocker les réponses de l'utilisateur
session_start();

// Connexion à la base de données SQLite
try {
    $db = new PDO('sqlite:QCM.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données : " . htmlspecialchars($e->getMessage()));
}

// Récupérer l'ID de la question depuis l'URL
$id = isset($_GET['id']) ? $_GET['id'] : null;
$pseudo = isset($_GET['pseudo']) ? $_GET['pseudo'] : null;

if ($id && $pseudo) {
    // Récupérer les détails de la question à partir de l'ID
    $sql = "SELECT * FROM QI WHERE id_QI = :id";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    // Récupérer la question
    $QI = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($QI) {
        // Récupérer les informations
        $enonce = ucfirst(nl2br(htmlspecialchars($QI['enonce'])));
        $propositions = [];
        $commentaires = [];
        for ($i = 1; $i <= 10; $i++) {
            if (!empty($QI["prop$i"])) {
                $propositions["prop$i"] = ucfirst(nl2br(htmlspecialchars($QI["prop$i"])));
                $commentaires["commentaire_prop$i"] = ucfirst(nl2br(htmlspecialchars($QI["commentaire_prop$i"])));
            }
        }
        $reponse_correcte = $QI['reponse_correcte'];  // La réponse correcte (ex : "1,3")
        $correction = ucfirst(nl2br(htmlspecialchars($QI['correction']))); // Correction à afficher après réponse
        $imageUrl = $QI['image_url']; // Récupérer l'URL de l'image
        $imageUrlCorrection = $QI['image_url_correction']; // Récupérer l'URL de l'image de correction
    } else {
        echo "<p>Question non trouvée.</p>";
        exit();
    }
} else {
    echo "<p>Identifiant de question ou pseudo manquant.</p>";
    exit();
}

// Récupérer l'ID de l'utilisateur à partir du pseudo
$sql_user = "SELECT utilisateur_id FROM users WHERE pseudo = :pseudo";
$stmt_user = $db->prepare($sql_user);
$stmt_user->bindParam(':pseudo', $pseudo, PDO::PARAM_STR);
$stmt_user->execute();
$user = $stmt_user->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "<p>Utilisateur non trouvé.</p>";
    exit();
}

$utilisateur_id = $user['utilisateur_id'];

$message_resultat = ''; // Message à afficher après la soumission du formulaire
$note = null; // Note de l'utilisateur
$erreurs = []; // Tableau pour gérer les erreurs et encadrer les propositions
$reponse_utilisateur = null; // Réponse de l'utilisateur

// Vérification si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Récupérer la réponse soumise par l'utilisateur
    $reponse_utilisateur = isset($_POST['reponse']) ? $_POST['reponse'] : null;

    // Convertir la réponse correcte et la réponse utilisateur en tableaux
    $reponses_correctes = !empty($reponse_correcte) ? explode(',', $reponse_correcte) : []; // Convertir '1,2,3' en array ['1', '2', '3']
    $reponse_utilisateur_array = is_array($reponse_utilisateur) ? $reponse_utilisateur : (!empty($reponse_utilisateur) ? explode(',', $reponse_utilisateur) : []);

    // Initialiser les variables pour suivre les erreurs spécifiques
    $intolerableChecked = false;
    $indispensableUnchecked = false;

    // Vérification des commentaires pour les mots "intolérable" et "indispensable"
    foreach ($commentaires as $key => $commentaire) {
        $i = substr($key, -1); // Récupérer le numéro de la proposition (commentaire_prop1 -> 1, commentaire_prop2 -> 2, ...)
        if (mb_strpos(mb_strtolower($commentaire), 'intolérable') !== false && in_array($i, $reponse_utilisateur_array)) {
            $intolerableChecked = true; // Erreur si la proposition est "intolérable" et cochée
        }
        if (mb_strpos(mb_strtolower($commentaire), 'indispensable') !== false && !in_array($i, $reponse_utilisateur_array)) {
            $indispensableUnchecked = true; // Erreur si la proposition est "indispensable" et non cochée
        }
    }

    // Calculer les erreurs (mauvaises réponses ou réponses manquantes)
    $erreurs_count = 0;

    // Compter les erreurs : les réponses correctes non sélectionnées par l'utilisateur
    foreach ($reponses_correctes as $correct_answer) {
        if (!in_array($correct_answer, $reponse_utilisateur_array)) {
            $erreurs_count++;
        }
    }

    // Compter les erreurs supplémentaires : les réponses de l'utilisateur qui sont incorrectes
    foreach ($reponse_utilisateur_array as $user_answer) {
        if (!in_array($user_answer, $reponses_correctes)) {
            $erreurs_count++;
        }
    }

    // Vérifier les conditions spécifiques pour "intolérable" et "indispensable"
    if ($intolerableChecked || $indispensableUnchecked) {
        $note = 0; // 0/20 si l'une des conditions est remplie
    } else {
        // Calcul de la note en fonction du nombre d'erreurs
        if ($erreurs_count == 0) {
            $note = 20;
        } elseif ($erreurs_count == 1) {
            $note = 10;
        } elseif ($erreurs_count == 2) {
            $note = 4;
        } else {
            $note = 0;
        }
    }

    // Créer une variable temporaire pour la réponse utilisateur sous forme de chaîne
    $reponse_utilisateur_str = implode(',', (array)$reponse_utilisateur);

    // Enregistrer la réponse de l'utilisateur dans la table 'user_results'
    $sql_insert = "INSERT INTO user_results (user_id, qcm_id, date, note) VALUES (:user_id, :qcm_id, :date, :note)";
    $stmt_insert = $db->prepare($sql_insert);
    $stmt_insert->bindParam(':user_id', $utilisateur_id, PDO::PARAM_INT);
    $stmt_insert->bindParam(':qcm_id', $id, PDO::PARAM_INT);
    $stmt_insert->bindValue(':date', date('Y-m-d H:i:s')); // Enregistrer la date et l'heure actuelles
    $stmt_insert->bindParam(':note', $note, PDO::PARAM_INT); // Enregistrer la note
    $stmt_insert->execute();

    // Message à afficher après la soumission
    if ($erreurs_count == 0 && !$intolerableChecked && !$indispensableUnchecked) {
        $message_resultat = "<div class='bonne-reponse'>Bonne réponse !</div>";
    } else {
        $message_resultat = "<div class='mauvaise-reponse'>Mauvaise réponse. Essayez encore !</div>";
    }

    // Gérer les erreurs pour encadrer les propositions
    foreach ($propositions as $key => $prop) {
        $i = substr($key, -1); // Récupérer le numéro de la proposition (prop1 -> 1, prop2 -> 2, ...)
        if (in_array($i, $reponse_utilisateur_array)) {
            if (in_array($i, $reponses_correctes)) {
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

// Fonction pour souligner et mettre en gras les mots "intolérable" et "indispensable"
function soulignerMots($texte)
{
    $mots = ['intolérable', 'indispensable'];
    foreach ($mots as $mot) {
        $texte = str_ireplace($mot, "<strong><u>$mot</u></strong>", $texte);
    }
    return $texte;
}

// Vérification si un commentaire a été soumis
if (isset($_POST['commentaire'])) {
    $commentaire = $_POST['commentaire'];
    $date = date('Y-m-d H:i:s');
    $parent_id = isset($_POST['parent_id']) ? $_POST['parent_id'] : null;

    // Insérer le commentaire dans la base de données
    $sql_commentaire = "INSERT INTO commentaires_QI (commentaire, date, pseudo, qcm_id, parent_id) VALUES (:commentaire, :date, :pseudo, :qcm_id, :parent_id)";
    $stmt_commentaire = $db->prepare($sql_commentaire);
    $stmt_commentaire->bindParam(':commentaire', $commentaire, PDO::PARAM_STR);
    $stmt_commentaire->bindParam(':date', $date, PDO::PARAM_STR);
    $stmt_commentaire->bindParam(':pseudo', $pseudo, PDO::PARAM_STR);
    $stmt_commentaire->bindParam(':qcm_id', $id, PDO::PARAM_INT);
    $stmt_commentaire->bindParam(':parent_id', $parent_id, PDO::PARAM_INT);
    $stmt_commentaire->execute();

    $message_commentaire = "<div class='bonne-reponse'>Le commentaire a bien été publié.</div>";
}

// Récupérer les commentaires existants pour le QCM spécifique
$sql_commentaires = "SELECT * FROM commentaires_QI WHERE qcm_id = :qcm_id ORDER BY date DESC";
$stmt_commentaires = $db->prepare($sql_commentaires);
$stmt_commentaires->bindParam(':qcm_id', $id, PDO::PARAM_INT);
$stmt_commentaires->execute();
$commentaires_existants = $stmt_commentaires->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QCM - Question QRM</title>
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
            background-color: rgb(249, 244, 248);
            margin: 0;
            padding: 0;
        }

        #div1 {
            background-color: <?php echo isset($note) ? ($note == 20 ? '#4CAF50' : ($note == 0 ? '#F44336' : '#FF9800')) : ' rgb(86, 8, 69)'; ?>;
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
            background-color: #F44336;
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
            background-color: #f8f8f8;
            padding: 8px;
            margin: 5px 0;
            border: 2px solid #ccc;
            border-radius: 8px;
            transition: background-color 0.3s ease, transform 0.3s ease;
        }

        label:hover {
            background-color: #efefef;
            transform: scale(1.05);
        }

        input[type="checkbox"] {
            margin-right: 10px;
            transform: scale(1.2);
        }

        /* Styles pour les réponses */
        .bonne-reponse {
            background-color: #4CAF50;
            color: white;
            padding: 10px;
            border-radius: 5px;
        }

        .mauvaise-reponse {
            background-color: #F44336;
            color: white;
            padding: 10px;
            border-radius: 5px;
        }

        .correction-box {
            background-color: #333;
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }

        img {
            max-width: 600px;
            /* Réduire la taille de l'image */
            height: auto;
            display: block;
            margin: 20px auto;
        }

        .submit {
            background-color: #888888;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
            margin-top: 20px;
        }

        .submit:hover {
            background-color: #666666;
        }

        /* Styles pour les commentaires */
        .comment-section {
            margin-top: 20px;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            margin: 20px;
        }

        .comment-section h3 {
            margin-bottom: 10px;
        }

        .comment-section textarea {
            width: 100%;
            padding: 10px;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        .comment-section button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .comment-section button:hover {
            background-color: #45a049;
        }

        .comment-list {
            margin-top: 20px;
            margin: 30px;
        }

        .comment-item {
            background-color: #fff;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .comment-item p {
            margin: 0;
        }

        .comment-item .comment-meta {
            font-size: 0.9rem;
            color: #777;
        }

        .reply-form {
            display: none;
            margin-top: 10px;
        }

        .reply-button {
            background-color: #007BFF;
            color: white;
            padding: 5px 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 10px;
        }

        .reply-button:hover {
            background-color: #0056b3;
        }

        /* Styles pour les commentaires de réponse */
        .reply-comment {
            margin-left: 20px;
            /* Indenter les commentaires de réponse */
            background-color: #f0f0f0;
            /* Changer la couleur de fond */
            border-left: 4px solid #007BFF;
            /* Ajouter une bordure à gauche pour indiquer une réponse */
            padding: 10px;
            border-radius: 5px;
        }

        .show-replies-button {
            background-color: #FF9800;
            color: white;
            padding: 5px 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 10px;
        }

        .show-replies-button:hover {
            background-color: #e68900;
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
            <a href="exam_EDN.php">Annales EDN</a>
            <a href="qcm_etudiant.php">QCM Étudiant</a>
        </div>
        <div>
            <a href="profil.php" style="float: right;">Profil</a>
        </div>
    </header>

    <div id="div1">
        <h1>QCM - QRM</h1>
    </div>

    <div id="question">
        <h2>Énoncé :</h2>
        <p class="correction-box"><?php echo $enonce; ?></p>

        <?php
        // Utiliser __DIR__ pour obtenir le chemin absolu du répertoire actuel
        $imagePath = __DIR__ . '/' . $imageUrl;

        // Vérifier si l'image existe à cet emplacement
        if (!empty($imageUrl) && file_exists($imagePath)) {
            echo '<h3>Image :</h3>';
            echo '<img src="' . htmlspecialchars($imageUrl) . '" alt="QCM Image">';
        }
        ?>

        <h3>Propositions :</h3>
        <form method="POST">
            <?php
            // Affichage dynamique des propositions avec des checkboxes
            $letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];
            foreach ($propositions as $key => $prop) {
                $i = substr($key, -1); // Récupérer le numéro de la proposition (prop1 -> 1, prop2 -> 2, ...)
                $letter = $letters[$i - 1]; // Attribuer une lettre à chaque proposition
                $checked = (isset($reponse_utilisateur) && in_array($i, $reponse_utilisateur_array)) ? 'checked' : ''; // Garder la réponse sélectionnée
                // Définir la classe en fonction de la bonne réponse ou de la réponse de l'utilisateur
                $class = '';
                $commentaire = isset($commentaires["commentaire_prop$i"]) ? soulignerMots($commentaires["commentaire_prop$i"]) : '';

                if (isset($reponse_utilisateur)) {
                    if (in_array($i, $reponses_correctes)) {
                        $class = 'correct';
                    } elseif (!in_array($i, $reponses_correctes) && in_array($i, $reponses_correctes)) {
                        $class = 'correct';
                    } else {
                        $class = 'incorrect';
                    }
                }
                echo "<label class='$class'><input type='checkbox' name='reponse[]' value='$i' $checked> $letter. $prop</label>";
                if (isset($reponse_utilisateur) && $commentaire) {
                    echo "<div class='commentaire'>$commentaire</div>";
                }
                echo "<br>";
            }
            ?>

            <button type="submit" class="submit">Soumettre</button>

            <?php if (isset($reponse_utilisateur)): ?>
                <div class="correction-box">
                    <h3>Correction :</h3>
                    <p><?php echo $correction; ?></p>
                    <?php
                    // Utiliser __DIR__ pour obtenir le chemin absolu du répertoire actuel
                    $imagePathCorrection = __DIR__ . '/' . $imageUrlCorrection;

                    // Vérifier si l'image de correction existe à cet emplacement
                    if (!empty($imageUrlCorrection) && file_exists($imagePathCorrection)) {
                        echo '<h3>Image de correction :</h3>';
                        echo '<img src="' . htmlspecialchars($imageUrlCorrection) . '" alt="QCM Correction Image">';
                    }
                    ?>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($_SERVER['REQUEST_METHOD'] == 'POST'): ?>
        <div class="score-circle <?php echo $note == 20 ? 'score-20' : 'score-0'; ?>">
            <?php echo $note; ?>/20
        </div>
    <?php endif; ?>

    <!-- Section pour les commentaires -->
    <div class="comment-section">
        <h3>Laisser un commentaire :</h3>
        <form method="POST">
            <textarea name="commentaire" placeholder="Votre commentaire..." required></textarea>
            <button type="submit">Valider</button>
        </form>
        <?php if (isset($message_commentaire)) echo $message_commentaire; ?>
    </div>

    <!-- Affichage des commentaires existants pour le QCM spécifique -->
    <div class="comment-list">
        <?php
        $parent_comments = [];
        $replies = [];

        // Séparer les commentaires parents et les réponses
        foreach ($commentaires_existants as $commentaire) {
            if (empty($commentaire['parent_id'])) {
                $parent_comments[] = $commentaire;
            } else {
                $replies[$commentaire['parent_id']][] = $commentaire;
            }
        }

        // Afficher les commentaires parents
        foreach ($parent_comments as $commentaire): ?>
            <div class="comment-item">
                <p><?php echo htmlspecialchars($commentaire['commentaire']); ?></p>
                <div class="comment-meta">
                    <span>Par <?php echo htmlspecialchars($commentaire['pseudo']); ?></span>
                    <span>le <?php echo htmlspecialchars($commentaire['date']); ?></span>
                </div>
                <button class="reply-button" onclick="toggleReplyForm(<?php echo $commentaire['id']; ?>)">Répondre</button>
                <form class="reply-form" id="reply-form-<?php echo $commentaire['id']; ?>" method="POST">
                    <textarea name="commentaire" placeholder="Votre réponse..." required></textarea>
                    <input type="hidden" name="parent_id" value="<?php echo $commentaire['id']; ?>">
                    <button type="submit">Valider</button>
                </form>
                <?php if (isset($replies[$commentaire['id']])): ?>
                    <button class="show-replies-button" onclick="toggleReplies(<?php echo $commentaire['id']; ?>)">Afficher les réponses</button>
                <?php endif; ?>
            </div>
            <?php if (isset($replies[$commentaire['id']])): ?>
                <div class="reply-list" id="reply-list-<?php echo $commentaire['id']; ?>" style="display: none;">
                    <?php foreach ($replies[$commentaire['id']] as $reply): ?>
                        <div class="comment-item reply-comment">
                            <p><?php echo htmlspecialchars($reply['commentaire']); ?></p>
                            <div class="comment-meta">
                                <span>Par <?php echo htmlspecialchars($reply['pseudo']); ?></span>
                                <span>le <?php echo htmlspecialchars($reply['date']); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <script>
        function toggleReplyForm(id) {
            var form = document.getElementById('reply-form-' + id);
            if (form.style.display === 'none' || form.style.display === '') {
                form.style.display = 'block';
            } else {
                form.style.display = 'none';
            }
        }

        function toggleReplies(id) {
            var replies = document.getElementById('reply-list-' + id);
            if (replies.style.display === 'none' || replies.style.display === '') {
                replies.style.display = 'block';
            } else {
                replies.style.display = 'none';
            }
        }
    </script>
</body>

</html>