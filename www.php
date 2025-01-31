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

// Récupérer l'ID du DP (dossier progressif)
$id_DP = $_GET['id'] ?? null;

if (!$id_DP) {
    die("Erreur : ID du DP manquant.");
}

// Récupérer toutes les questions et leurs commentaires ainsi que l'énoncé d'accueil
$questions = [];
$enonce_accueil = null; // Variable pour l'énoncé d'accueil
$sql_accueil = "SELECT enonce_accueil FROM DP_0 WHERE id_DP = :id_DP";
$stmt_accueil = $db->prepare($sql_accueil);
$stmt_accueil->bindParam(':id_DP', $id_DP, PDO::PARAM_INT);
$stmt_accueil->execute();
$result_accueil = $stmt_accueil->fetch(PDO::FETCH_ASSOC);

if ($result_accueil) {
    $enonce_accueil = ucfirst(nl2br(htmlspecialchars($result_accueil['enonce_accueil'])));
} else {
    die("Erreur : énoncé d'accueil manquant.");
}

for ($i = 1; $i <= 8; $i++) { // Changer la boucle pour inclure DP_8
    $sql = "SELECT * FROM DP_" . $i . " WHERE id_DP = :id_DP";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':id_DP', $id_DP, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result && !empty($result['type'])) {
        $result['enonce'] = ucfirst(nl2br(htmlspecialchars($result['enonce'])));
        $result['commentaire'] = ucfirst(nl2br(htmlspecialchars($result['commentaire'])));
        for ($j = 1; $j <= 10; $j++) {
            $propKey = "prop$j";
            $commentaireKey = "commentaire_prop{$j}_DP";
            if (isset($result[$propKey])) {
                $result[$propKey] = ucfirst(nl2br(htmlspecialchars($result[$propKey])));
            }
            if (isset($result[$commentaireKey])) {
                $result[$commentaireKey] = ucfirst(nl2br(htmlspecialchars($result[$commentaireKey])));
            }
        }

        // Compter le nombre de réponses correctes attendues
        $correctAnswers = explode(',', $result['reponse_correcte']);
        $result['correct_count'] = count($correctAnswers);

        $questions[$i] = $result;
    } else {
        // Si une question est manquante ou n'a pas de type, on la saute (pas d'arrêt du script)
        continue;
    }
}

if (empty($questions)) {
    die("Aucune question disponible pour ce DP.");
}

// Récupérer les réponses de l'utilisateur et les stocker dans la table user_results
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'] ?? null;
    $qcm_id = $_POST['qcm_id'] ?? null;
    $date = date('Y-m-d H:i:s');
    $note = $_POST['note'] ?? null;

    if ($user_id && $qcm_id && $date && $note) {
        $sql = "INSERT INTO user_results (user_id, qcm_id, date, note) VALUES (:user_id, :qcm_id, :date, :note)";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':qcm_id', $qcm_id, PDO::PARAM_INT);
        $stmt->bindParam(':date', $date);
        $stmt->bindParam(':note', $note);
        $stmt->execute();
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
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QCM - Dossier Progressif</title>
    <style>
        img {
            max-width: 600px;
            /* Réduire la taille de l'image */
            height: auto;
            display: block;
            margin: 20px auto;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: #333;
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
            /* Style général */
            font-family: Arial, sans-serif;
            background-color: #f4f4f9;
            margin: 0;
            padding: 0;
        }

        .score {
            font-weight: bold;
            margin-bottom: 20px;
        }

        .question-div {
            margin-bottom: 20px;
            padding: 20px;
            border: 1px solid #ccc;
            border-radius: 8px;
            background-color: rgb(255, 255, 255);
            color: black;
            padding: 20px;
            margin: 20px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .question-score {
            font-size: 14px;
            margin-bottom: 5px;
            display: none;
            padding: 10px;
            border: 2px solid #ccc;
            background-color: #f9f9f9;
            border-radius: 5px;
        }

        .comment {
            margin-top: 10px;
            font-style: italic;
            color: #555;
            display: none;
            padding: 10px;
            border: 2px solid #ccc;
            background-color: #f9f9f9;
            border-radius: 5px;
        }

        .button {
            margin-top: 10px;
        }

        .hidden {
            display: none;
        }

        .disabled {
            pointer-events: none;
            opacity: 0.5;
        }

        .correct1 {
            background-color: green;
            color: black;
            /* Texte en noir */
            border: 2px solid black;
        }

        .incorrect1 {
            background-color: red;
            color: black;
            /* Texte en noir */
            border: 2px solid black;
        }

        .answer {
            display: flex;
            align-items: center;
            padding: 8px;
            margin: 5px 0;
            border: 2px solid #ccc;
            border-radius: 8px;
            background-color: #f8f8f8;
            transition: background-color 0.3s ease;
        }

        .answer-correct {
            background-color: green;
            /* Fond vert pour une bonne réponse */
            color: black;
            /* Texte en noir */
            border: 2px solid black;
            transition: background-color 0.3s ease;
        }

        .answer-incorrect {
            background-color: red;
            /* Fond rouge pour une mauvaise réponse */
            color: black;
            /* Texte en noir */
            border: 2px solid black;
            transition: background-color 0.3s ease;
        }

        /* Style spécifique pour les checkboxes */
        .answer input[type="checkbox"] {
            margin-right: 10px;
            /* Décalage entre la checkbox et le texte */
        }

        /* Changement de fond au survol */
        .answer:hover {
            background-color: #efefef;
            transform: scale(1.05);
        }

        /* Style pour les inputs de type texte (QM) avant la validation */
        input[type="text"] {
            background-color: #f8f8f8;
            /* Fond blanc par défaut */
            color: black;
            /* Texte noir */
            border: 2px solid #ccc;
            /* Bordure grise */
            padding: 8px;
            border-radius: 5px;
            width: 100%;
        }

        /* Classe pour indiquer que l'input est incorrect */
        input[type="text"].incorrect1 {
            background-color: red;
            /* Fond rouge pour une mauvaise réponse */
            border-color: black;
            /* Bordure noire */
            color: black;
            /* Texte noir */
        }

        /* Classe pour indiquer que l'input est correct */
        input[type="text"].correct1 {
            background-color: green;
            /* Fond vert pour une bonne réponse */
            border-color: black;
            /* Bordure noire */
            color: black;
            /* Texte noir */
        }

        /* Classe par défaut pour les inputs de type texte */
        input[type="text"].default-input {
            background-color: #f8f8f8;
            color: black;
            border: 2px solid #ccc;
            padding: 8px;
            border-radius: 5px;
            width: 100%;
        }

        /* Style pour séparer visuellement l'énoncé d'accueil de la première question */
        .accueil-section {
            border-bottom: 2px solid #ccc;
            margin-bottom: 20px;
            padding-bottom: 20px;
        }

        /* Style pour le conteneur du score global */
        .score-container {
            display: none;
            position: fixed;
            top: 60px;
            left: 50%;
            transform: translateX(-50%);
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            text-align: center;
            z-index: 1000;
        }

        .score-text {
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }

        .close-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
        }

        /* Style pour la carte Leaflet */
        #map {
            height: 400px;
            width: 100%;
            margin: 20px auto;
        }
    </style>
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
</head>

<body>
    <header>
        <div>
            <a href="accueil.php">Accueil</a>
            <a href="data.php">Ajouter un QCM</a>
            <a href="ancrage.php">Ancrage</a>
            <a href="exam_univ.php">Examens universitaires</a>
            <a href="annales_edn.php">Annales EDN</a>
            <a href="qcm_etudiant.php">QCM Étudiant</a>
        </div>
        <div>
            <a href="profil.php" style="float: right;">Profil</a>
        </div>
    </header>

    <div class="score-container" id="score-container">
        <span class="close-btn">&times;</span>
        <div class="score-text" id="score-text"></div>
        <button id="toggle-score-btn">Masquer la note</button>
    </div>

    <div class="score">Score global : <span id="global-score">0</span></div>
    <button type="button" id="instant-correction-btn">Afficher la correction</button>
    <button type="button" id="show-score-btn">Afficher la note globale</button>

    <form id="qcm-form" method="POST" action="traitement_reponse_DP.php">
        <div id="questions-container">
            <?php foreach ($questions as $i => $question) { ?>
                <div class="question-div" id="question-<?php echo $i; ?>" data-correct="<?php echo htmlspecialchars($question['reponse_correcte']); ?>" data-zone="<?php echo htmlspecialchars($question['zone_correcte']); ?>" data-correct-count="<?php echo htmlspecialchars($question['correct_count']); ?>">
                    <?php if ($i == 1) { ?>
                        <div class="accueil-section">
                            <h1>Bienvenue au QCM</h1>
                            <p><?php echo $enonce_accueil; ?></p>
                            <?php
                            // Utiliser __DIR__ pour obtenir le chemin absolu du répertoire actuel
                            foreach (range(0, 1) as $tableIndex) {
                                $imageUrl = $questions[$tableIndex]['image_url'] ?? null; // Récupérer l'URL de l'image
                                $imagePath = __DIR__ . '/' . $imageUrl;

                                // Vérifier si l'image existe à cet emplacement
                                if (!empty($imageUrl) && file_exists($imagePath)) {
                                    echo '<h3>Image :</h3>';
                                    echo '<img src="' . htmlspecialchars($imageUrl) . '" alt="QCM Image">';
                                }
                            }
                            ?>
                        </div>
                    <?php } ?>
                    <div class="question-score" id="score-<?php echo $i; ?>">Score : </div>
                    <h2>Question <?php echo $i; ?> : <?php echo $question['enonce']; ?></h2>
                    <?php
                    // Afficher les images pour chaque section
                    if ($i > 1) {
                        $imageUrl = $question['image_url'] ?? null; // Récupérer l'URL de l'image
                        $imagePath = __DIR__ . '/' . $imageUrl;

                        // Vérifier si l'image existe à cet emplacement
                        if (!empty($imageUrl) && file_exists($imagePath)) {
                            echo '<h3>Image :</h3>';
                            echo '<img src="' . htmlspecialchars($imageUrl) . '" alt="QCM Image">';
                        }
                    }
                    ?>
                    <?php
                    $type = $question['type'] ?? null;
                    $correctAnswers = explode(',', $question['reponse_correcte']);

                    if ($type === 'QRU') {
                        for ($j = 1; $j <= 10; $j++) {
                            $prop = $question["prop$j"] ?? null;
                            $commentaire_prop = $question["commentaire_prop{$j}_DP"] ?? null;
                            if (!empty($prop)) {
                                echo "<div class='answer'>
                                        <input type='radio' name='reponse[$i]' value='$j'> " . $prop . "
                                      </div>";
                                echo "<div class='comment' style='display: none;'>" . soulignerMots($commentaire_prop) . "</div>";
                            }
                        }
                        // Ajouter les boutons "Valider", "Ma réponse était bonne", et "Ma réponse était fausse"
                        echo "<button type='button' class='validate-btn' data-current='$i' data-next='" . ($i + 1) . "'>Valider</button>";
                        echo "<button type='button' class='correct-btn hidden' data-question='$i'>Ma réponse était bonne</button>";
                        echo "<button type='button' class='incorrect-btn hidden' data-question='$i'>Ma réponse était fausse</button>";
                    } elseif ($type === 'QRM') {
                        for ($j = 1; $j <= 10; $j++) {
                            $prop = $question["prop$j"] ?? null;
                            $commentaire_prop = $question["commentaire_prop{$j}_DP"] ?? null;
                            if (!empty($prop)) {
                                echo "<div class='answer'>
                                        <input type='checkbox' name='reponse[$i][]' value='$j'> " . $prop . "
                                      </div>";
                                echo "<div class='comment' style='display: none;'>" . soulignerMots($commentaire_prop) . "</div>";
                            }
                        }
                        echo "<button type='button' class='validate-btn' data-current='$i' data-next='" . ($i + 1) . "'>Valider</button>";
                    } elseif ($type === 'QM') {
                        echo "<input type='text' name='reponse[$i]' class='default-input'>";
                        echo "<button type='button' class='validate-btn' data-current='$i' data-next='" . ($i + 1) . "'>Valider</button>";
                        echo "<button type='button' class='correct-btn hidden' data-question='$i'>Ma réponse était bonne</button>";
                        echo "<button type='button' class='incorrect-btn hidden' data-question='$i'>Ma réponse était fausse</button>";
                    } elseif ($type === 'QP') {
                        for ($j = 1; $j <= 10; $j++) {
                            $prop = $question["prop$j"] ?? null;
                            $commentaire_prop = $question["commentaire_prop{$j}_DP"] ?? null;
                            if (!empty($prop)) {
                                echo "<div class='answer'>
                                        <input type='checkbox' name='reponse[$i][]' value='$j'> " . $prop . "
                                      </div>";
                                echo "<div class='comment' style='display: none;'>" . soulignerMots($commentaire_prop) . "</div>";
                            }
                        }
                        // Ajouter les boutons "Valider", "Ma réponse était bonne", et "Ma réponse était fausse"
                        echo "<button type='button' class='validate-btn' data-current='$i' data-next='" . ($i + 1) . "'>Valider</button>";
                        echo "<button type='button' class='correct-btn hidden' data-question='$i'>Ma réponse était bonne</button>";
                        echo "<button type='button' class='incorrect-btn hidden' data-question='$i'>Ma réponse était fausse</button>";
                    } elseif ($type === 'QRP') {
                        for ($j = 1; $j <= 10; $j++) {
                            $prop = $question["prop$j"] ?? null;
                            $commentaire_prop = $question["commentaire_prop{$j}_DP"] ?? null;
                            if (!empty($prop)) {
                                echo "<div class='answer'>
                                        <input type='checkbox' name='reponse[$i][]' value='$j'> " . $prop . "
                                      </div>";
                                echo "<div class='comment' style='display: none;'>" . soulignerMots($commentaire_prop) . "</div>";
                            }
                        }
                        // Ajouter les boutons "Valider", "Ma réponse était bonne", et "Ma réponse était fausse"
                        echo "<button type='button' class='validate-btn' data-current='$i' data-next='" . ($i + 1) . "'>Valider</button>";
                        echo "<button type='button' class='correct-btn hidden' data-question='$i'>Ma réponse était bonne</button>";
                        echo "<button type='button' class='incorrect-btn hidden' data-question='$i'>Ma réponse était fausse</button>";
                        // Ajouter l'input pour le nombre de réponses correctes
                        echo "<div>Nombre de réponses correctes attendues : " . htmlspecialchars($question['correct_count']) . "</div>";
                        echo "<div id='correct-answers-$i'></div>"; // Ajouter un div pour afficher les réponses correctes
                        echo "<div id='user-answers-$i'></div>"; // Ajouter un div pour afficher les réponses de l'utilisateur
                        echo "<div id='score-display-$i'></div>";
                    } else {
                        echo "<p>Type de question inconnu.</p>";
                    }
                    ?>
                    <div class="comment" id="comment-<?php echo $i; ?>">
                        Commentaire : <?php echo $question['commentaire']; ?>
                        <?php if ($type === 'QM') { ?>
                            <br><br>
                            Réponses possibles :
                            <ul>
                                <?php foreach ($correctAnswers as $answer) { ?>
                                    <li><?php echo htmlspecialchars(trim($answer)); ?></li>
                                <?php } ?>
                            </ul>
                        <?php } ?>
                        <?php
                        // Afficher le texte "Image de correction" et l'image de correction si l'URL est présente
                        $imageUrlCorrection = $question['image_url_correction'] ?? null;
                        if (!empty($imageUrlCorrection)) {
                            echo '<h3>Image de correction :</h3>';
                            echo '<img src="' . htmlspecialchars($imageUrlCorrection) . '" alt="Correction Image">';
                        }
                        ?>
                    </div>

                </div>
            <?php } ?>
        </div>
        <button type="submit" id="final-submit" style="display: none;">Soumettre</button>
    </form>

    <script>
        let globalScore = 0;
        let correctionVisible = false;
        let questionScores = {}; // Objet pour stocker les scores de chaque question

        // Fonction de validation de la question
        function validateQuestion(questionId) {
            const questionDiv = document.getElementById('question-' + questionId);
            const correctAnswers = questionDiv.dataset.correct.split(','); // Réponses correctes
            const answersDivs = questionDiv.querySelectorAll('.answer');
            const inputs = questionDiv.querySelectorAll('input');
            let userAnswers = [];
            let partialScore = 0;
            let errors = 0;

            // Vérifier si l'utilisateur a interagi avec les inputs
            let hasInteracted = false;
            inputs.forEach(input => {
                if ((input.type === 'radio' || input.type === 'checkbox') && input.checked) {
                    hasInteracted = true;
                } else if (input.type === 'text' && input.value.trim() !== '') {
                    hasInteracted = true;
                }
            });

            if (!hasInteracted) {
                alert("Veuillez sélectionner une réponse ou écrire quelque chose dans l'input.");
                return;
            }

            // Récupérer les réponses de l'utilisateur
            answersDivs.forEach(div => {
                const input = div.querySelector('input');
                if (input && input.checked) {
                    userAnswers.push(input.value);
                }
            });

            // Afficher les réponses de l'utilisateur
            const userAnswersDiv = document.getElementById('user-answers-' + questionId);
            if (userAnswersDiv) {
                userAnswersDiv.innerHTML = 'Réponses de l\'utilisateur : ' + userAnswers.join(', ');
            }

            // Afficher les réponses correctes
            const correctAnswersDiv = document.getElementById('correct-answers-' + questionId);
            if (correctAnswersDiv) {
                correctAnswersDiv.innerHTML = 'Réponses correctes : ' + correctAnswers.join(', ');
            }

            // Type de question et calcul du score
            const type = questionDiv.querySelector('input').type;

            if (type === 'radio') {
                // QRU : Question à réponse unique (Radio)
                if (userAnswers[0] === correctAnswers[0]) {
                    partialScore = 1; // Réponse correcte
                } else {
                    partialScore = 0; // Réponse incorrecte
                }
            } else if (type === 'checkbox') {
                // QRM ou QRP : Question à réponses multiples (Checkbox)
                let correctCount = 0;
                let incorrectCount = 0;
                let intolerableChecked = false;
                let indispensableUnchecked = false;

                answersDivs.forEach(div => {
                    const input = div.querySelector('input');
                    const commentDiv = div.nextElementSibling; // Récupérer le commentaire suivant
                    const commentText = commentDiv ? commentDiv.innerText : '';

                    if (input && input.checked) {
                        // Si l'option est cochée et correcte
                        if (correctAnswers.includes(input.value)) {
                            correctCount++;
                        } else {
                            incorrectCount++;
                        }

                        // Vérifier si le commentaire contient "intolérable"
                        if (commentText.includes('intolérable')) {
                            intolerableChecked = true;
                        }
                    } else if (correctAnswers.includes(input.value)) {
                        // Si l'option correcte est non cochée
                        incorrectCount++;

                        // Vérifier si le commentaire contient "indispensable"
                        if (commentText.includes('indispensable')) {
                            indispensableUnchecked = true;
                        }
                    }
                });

                // Vérifier les conditions spécifiques pour "intolérable" et "indispensable"
                if (intolerableChecked || indispensableUnchecked) {
                    partialScore = 0; // 0/1 si l'une des conditions est remplie
                } else {
                    // Calcul du score en fonction des erreurs
                    if (incorrectCount === 0 && correctCount === correctAnswers.length) {
                        partialScore = 1; // Tout est correct
                    } else if (incorrectCount === 1) {
                        partialScore = 0.5; // Une erreur
                    } else if (incorrectCount === 2) {
                        partialScore = 0.2; // Deux erreurs
                    } else {
                        partialScore = 0; // Trop d'erreurs
                    }
                }
            } else if (type === 'text') {
                // QM : Question avec réponse textuelle
                const userAnswer = questionDiv.querySelector('input[type="text"]').value.trim().toLowerCase();
                const inputField = questionDiv.querySelector('input[type="text"]'); // Récupère l'input

                // Appliquer la classe correcte ou incorrecte sur l'input
                if (correctAnswers.some(answer => answer.trim().toLowerCase() === userAnswer)) {
                    partialScore = 1;
                    inputField.classList.add('correct1'); // Appliquer fond vert
                    inputField.classList.remove('incorrect1'); // Retirer fond rouge
                } else {
                    partialScore = 0;
                    inputField.classList.add('incorrect1'); // Appliquer fond rouge
                    inputField.classList.remove('correct1'); // Retirer fond vert
                }
            }

            // Mise à jour du score pour cette question
            const scoreDiv = document.getElementById('score-' + questionId);
            if (partialScore === 1) {
                scoreDiv.innerHTML = 'Score : <span class="correct">1/1</span>';
            } else if (partialScore === 0.5) {
                scoreDiv.innerHTML = 'Score : <span class="half-correct">0.5/1</span>';
            } else if (partialScore === 0.2) {
                scoreDiv.innerHTML = 'Score : <span class="partial-correct">0.2/1</span>';
            } else {
                scoreDiv.innerHTML = 'Score : <span class="incorrect">0/1</span>';
            }

            // Stocker le score de la question
            questionScores[questionId] = partialScore;

            // Mise à jour du score global
            globalScore = Object.values(questionScores).reduce((sum, score) => sum + score, 0);
            document.getElementById('global-score').innerText = globalScore;

            // Désactiver le bouton de validation
            const validateButton = document.querySelector('.validate-btn[data-current="' + questionId + '"]');
            validateButton.disabled = true;

            // Gestion de la question suivante
            const nextDiv = document.getElementById('question-' + (questionId + 1));
            if (nextDiv) {
                nextDiv.classList.remove('hidden');
            } else {
                document.getElementById('final-submit').style.display = 'block';
            }

            // Désactiver les champs de la question précédente
            disablePreviousQuestionInputs(questionId);
        }

        // Gestionnaires d'événements pour la validation des questions
        document.querySelectorAll('.validate-btn').forEach(button => {
            button.addEventListener('click', () => {
                const current = button.dataset.current;
                const next = button.dataset.next;

                validateQuestion(current);

                // Affichage de la question suivante
                const nextDiv = document.getElementById('question-' + next);
                if (nextDiv) {
                    nextDiv.classList.remove('hidden');
                } else {
                    document.getElementById('final-submit').style.display = 'block';
                }
            });
        });

        // Gestionnaires d'événements pour les boutons "Ma réponse était bonne" et "Ma réponse était fausse"
        document.querySelectorAll('.correct-btn').forEach(button => {
            button.addEventListener('click', () => {
                const questionId = button.dataset.question;
                const scoreDiv = document.getElementById('score-' + questionId);
                scoreDiv.innerHTML = 'Score : <span class="correct">1/1</span>';

                // Mettre à jour le score de la question
                questionScores[questionId] = 1;

                // Mettre à jour le score global
                globalScore = Object.values(questionScores).reduce((sum, score) => sum + score, 0);
                document.getElementById('global-score').innerText = globalScore;

                // Mettre à jour l'apparence de l'input pour les questions de type QM
                const inputField = document.querySelector('#question-' + questionId + ' input[type="text"]');
                if (inputField) {
                    inputField.classList.add('correct1'); // Appliquer fond vert
                    inputField.classList.remove('incorrect1'); // Retirer fond rouge
                }

                // Mettre à jour la note finale dans le conteneur
                updateFinalScore();
            });
        });

        document.querySelectorAll('.incorrect-btn').forEach(button => {
            button.addEventListener('click', () => {
                const questionId = button.dataset.question;
                const scoreDiv = document.getElementById('score-' + questionId);
                scoreDiv.innerHTML = 'Score : <span class="incorrect">0/1</span>';

                // Mettre à jour le score de la question
                questionScores[questionId] = 0;

                // Mettre à jour le score global
                globalScore = Object.values(questionScores).reduce((sum, score) => sum + score, 0);
                document.getElementById('global-score').innerText = globalScore;

                // Mettre à jour l'apparence de l'input pour les questions de type QM
                const inputField = document.querySelector('#question-' + questionId + ' input[type="text"]');
                if (inputField) {
                    inputField.classList.add('incorrect1'); // Appliquer fond rouge
                    inputField.classList.remove('correct1'); // Retirer fond vert
                }

                // Mettre à jour la note finale dans le conteneur
                updateFinalScore();
            });
        });

        // Ajouter un gestionnaire d'événements pour valider le nombre de réponses correctes pour les questions de type QRP
        document.querySelectorAll('.validate-correct-answers-btn').forEach(button => {
            button.addEventListener('click', () => {
                const questionId = button.dataset.question;
                const questionDiv = document.getElementById('question-' + questionId);
                const correctAnswersCount = parseInt(questionDiv.dataset.correctCount, 10);

                // Récupérer les réponses de l'utilisateur
                const answersDivs = questionDiv.querySelectorAll('.answer');
                let userAnswers = [];

                answersDivs.forEach(div => {
                    const input = div.querySelector('input');
                    if (input && input.checked) {
                        userAnswers.push(input.value);
                    }
                });

                // Calculer le score
                const correctCount = userAnswers.filter(answer => correctAnswers.includes(answer)).length;
                const partialScore = correctCount / correctAnswersCount;

                // Mise à jour du score pour cette question
                const scoreDiv = document.getElementById('score-' + questionId);
                scoreDiv.innerHTML = 'Score : <span class="correct">' + partialScore.toFixed(2) + '/1</span>';

                // Stocker le score de la question
                questionScores[questionId] = partialScore;

                // Mise à jour du score global
                globalScore = Object.values(questionScores).reduce((sum, score) => sum + score, 0);
                document.getElementById('global-score').innerText = globalScore;

                // Désactiver le bouton de validation
                const validateButton = document.querySelector('.validate-btn[data-current="' + questionId + '"]');
                validateButton.disabled = true;

                // Gestion de la question suivante
                const nextDiv = document.getElementById('question-' + (questionId + 1));
                if (nextDiv) {
                    nextDiv.classList.remove('hidden');
                } else {
                    document.getElementById('final-submit').style.display = 'block';
                }

                // Désactiver les champs de la question précédente
                disablePreviousQuestionInputs(questionId);

                // Afficher la note calculée
                const scoreDisplay = document.getElementById('score-display-' + questionId);
                scoreDisplay.innerText = 'Note : ' + (partialScore * 20).toFixed(2) + '/20';
            });
        });

        // Affichage de la correction instantanée
        document.getElementById('instant-correction-btn').addEventListener('click', () => {
            correctionVisible = !correctionVisible;

            // Toggle pour afficher ou masquer les commentaires et scores
            document.querySelectorAll('.comment').forEach(commentDiv => {
                commentDiv.style.display = correctionVisible ? 'block' : 'none';
            });

            document.querySelectorAll('.question-score').forEach(scoreDiv => {
                scoreDiv.style.display = correctionVisible ? 'block' : 'none';
            });

            // Changer le texte du bouton en fonction de l'état de la correction
            document.getElementById('instant-correction-btn').innerText = correctionVisible ? 'Masquer la correction' : 'Afficher la correction';

            // Afficher ou masquer les boutons "Ma réponse était bonne" et "Ma réponse était fausse"
            document.querySelectorAll('.correct-btn').forEach(button => {
                button.classList.toggle('hidden', !correctionVisible);
            });

            document.querySelectorAll('.incorrect-btn').forEach(button => {
                button.classList.toggle('hidden', !correctionVisible);
            });

            // Réactiver les classes de correction si la correction est visible
            if (correctionVisible) {
                document.querySelectorAll('.question-div').forEach(questionDiv => {
                    const correctAnswers = questionDiv.dataset.correct.split(','); // Récupérer les bonnes réponses
                    const answerDivs = questionDiv.querySelectorAll('.answer');

                    answerDivs.forEach(answerDiv => {
                        const input = answerDiv.querySelector('input');
                        const answerValue = input ? input.value : null;

                        // Réinitialiser les classes avant d'ajouter les nouvelles
                        answerDiv.classList.remove('answer-correct', 'answer-incorrect');

                        if (correctAnswers.includes(answerValue)) {
                            // Réponse correcte
                            answerDiv.classList.add('answer-correct');
                        } else {
                            // Réponse incorrecte
                            answerDiv.classList.add('answer-incorrect');
                        }
                    });
                });

                // Appliquer les classes de correction pour les inputs de type texte
                document.querySelectorAll('input[type="text"]').forEach(input => {
                    const questionDiv = input.closest('.question-div');
                    const correctAnswers = questionDiv.dataset.correct.split(',');
                    const userAnswer = input.value.trim().toLowerCase();

                    if (correctAnswers.some(answer => answer.trim().toLowerCase() === userAnswer)) {
                        input.classList.add('correct1');
                        input.classList.remove('incorrect1', 'default-input');
                    } else {
                        input.classList.add('incorrect1');
                        input.classList.remove('correct1', 'default-input');
                    }
                });
            } else {
                // Désactiver les classes de correction si la correction est masquée
                document.querySelectorAll('.answer').forEach(answerDiv => {
                    answerDiv.classList.remove('answer-correct', 'answer-incorrect');
                });
                document.querySelectorAll('input[type="text"]').forEach(input => {
                    input.classList.remove('correct1', 'incorrect1');
                    input.classList.add('default-input');
                });
            }
        });

        // Soumission du score final
        document.getElementById('final-submit').addEventListener('click', (event) => {
            event.preventDefault(); // Empêcher la soumission du formulaire

            // Afficher la correction de toutes les questions
            correctionVisible = true;
            document.querySelectorAll('.comment').forEach(commentDiv => {
                commentDiv.style.display = 'block';
            });

            document.querySelectorAll('.question-score').forEach(scoreDiv => {
                scoreDiv.style.display = 'block';
            });

            document.querySelectorAll('.question-div').forEach(questionDiv => {
                const correctAnswers = questionDiv.dataset.correct.split(','); // Récupérer les bonnes réponses
                const answerDivs = questionDiv.querySelectorAll('.answer');

                answerDivs.forEach(answerDiv => {
                    const input = answerDiv.querySelector('input');
                    const answerValue = input ? input.value : null;

                    // Réinitialiser les classes avant d'ajouter les nouvelles
                    answerDiv.classList.remove('answer-correct', 'answer-incorrect');

                    if (correctAnswers.includes(answerValue)) {
                        // Réponse correcte
                        answerDiv.classList.add('answer-correct');
                    } else {
                        // Réponse incorrecte
                        answerDiv.classList.add('answer-incorrect');
                    }
                });
            });

            // Appliquer les classes de correction pour les inputs de type texte
            document.querySelectorAll('input[type="text"]').forEach(input => {
                const questionDiv = input.closest('.question-div');
                const correctAnswers = questionDiv.dataset.correct.split(',');
                const userAnswer = input.value.trim().toLowerCase();

                if (correctAnswers.some(answer => answer.trim().toLowerCase() === userAnswer)) {
                    input.classList.add('correct1');
                    input.classList.remove('incorrect1', 'default-input');
                } else {
                    input.classList.add('incorrect1');
                    input.classList.remove('correct1', 'default-input');
                }
            });

            // Afficher la note sur 20 dans le conteneur
            const scoreContainer = document.getElementById('score-container');
            const scoreText = document.getElementById('score-text');
            updateFinalScore();
            scoreContainer.style.display = 'block';

            // Rendre les boutons "Ma réponse était bonne" et "Ma réponse était fausse" visibles
            document.querySelectorAll('.correct-btn').forEach(button => {
                button.classList.remove('hidden');
            });

            document.querySelectorAll('.incorrect-btn').forEach(button => {
                button.classList.remove('hidden');
            });
        });

        // Ajouter un gestionnaire d'événements sur chaque div.answer pour les cases à cocher et les radios
        document.querySelectorAll('.answer').forEach(answerDiv => {
            answerDiv.addEventListener('click', (event) => {
                const checkbox = answerDiv.querySelector('input[type="checkbox"]');
                const radio = answerDiv.querySelector('input[type="radio"]');

                // Gestion des cases à cocher (checkbox)
                if (checkbox) {
                    checkbox.checked = !checkbox.checked;
                }

                // Gestion des boutons radio
                if (radio) {
                    radio.checked = !radio.checked;
                }
            });
        });

        // Gestion de la question précédente : désactiver les champs de la question précédente
        function disablePreviousQuestionInputs(questionId) {
            const previousQuestionId = questionId - 1; // Identifier la question précédente
            if (previousQuestionId > 0) { // Vérifier que la question précédente existe
                const previousQuestionDiv = document.getElementById('question-' + previousQuestionId);
                const previousInputs = previousQuestionDiv.querySelectorAll('input');
                previousInputs.forEach(input => {
                    input.disabled = true; // Désactiver les inputs de la question précédente
                    input.classList.add('inclined'); // Appliquer une classe pour un style visuel de désactivation
                });
            }
        }

        // Affichage de la première question au démarrage
        document.querySelectorAll('.question-div').forEach((div, index) => {
            if (index !== 0) div.classList.add('hidden');
        });

        // Ajouter un gestionnaire d'événements pour fermer le conteneur de score global
        document.querySelector('.close-btn').addEventListener('click', () => {
            const scoreContainer = document.getElementById('score-container');
            scoreContainer.style.display = 'none';
        });

        // Ajouter un gestionnaire d'événements pour afficher/masquer le conteneur de score global
        document.getElementById('toggle-score-btn').addEventListener('click', () => {
            const scoreContainer = document.getElementById('score-container');
            if (scoreContainer.style.display === 'none') {
                scoreContainer.style.display = 'block';
                document.getElementById('toggle-score-btn').innerText = 'Masquer la note';
            } else {
                scoreContainer.style.display = 'none';
                document.getElementById('toggle-score-btn').innerText = 'Afficher la note';
            }
        });

        // Ajouter un gestionnaire d'événements pour afficher la note globale
        document.getElementById('show-score-btn').addEventListener('click', () => {
            const scoreContainer = document.getElementById('score-container');
            scoreContainer.style.display = 'block';
            document.getElementById('toggle-score-btn').innerText = 'Masquer la note';
        });

        // Fonction pour afficher la carte Leaflet avec les coordonnées GPS
        function displayMap(lat, lng, radius) {
            const map = L.map('map').setView([lat, lng], 13);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);

            L.circle([lat, lng], {
                color: 'green',
                fillColor: '#green',
                fillOpacity: 0.5,
                radius: radius
            }).addTo(map);
        }

        // Afficher la carte pour chaque question avec des coordonnées GPS
        document.querySelectorAll('.question-div').forEach(questionDiv => {
            const zoneCorrecte = questionDiv.dataset.zone;
            if (zoneCorrecte) {
                const [lat, lng, radius] = zoneCorrecte.split(',').map(Number);
                displayMap(lat, lng, radius);
            }
        });

        // Fonction pour mettre à jour la note finale dans le conteneur
        function updateFinalScore() {
            const scoreText = document.getElementById('score-text');
            const numberOfQuestions = Object.keys(questionScores).length; // Nombre de questions disponibles
            const finalScore = (globalScore / numberOfQuestions) * 20; // Calcul de la note sur 20
            scoreText.innerText = "Note : " + finalScore.toFixed(2) + "/20";
        }
    </script>

    <!-- Tableau récapitulatif du système de notation -->
    <div class="score-table">
        <h2>Système de Notation</h2>
        <table border="1">
            <tr>
                <th>Type de QCM</th>
                <th>Notation</th>
            </tr>
            <tr>
                <td>QRM (Question à Réponses Multiples)</td>
                <td>
                    <ul>
                        <li>0 discordance : 1 pt</li>
                        <li>1 discordance : 0,5 pt</li>
                        <li>2 discordances : 0,2 pt</li>
                        <li>3 discordances et plus : 0 pt</li>
                    </ul>
                </td>
            </tr>
            <tr>
                <td>QRU (Question à Réponse Unique)</td>
                <td>
                    <ul>
                        <li>1 réponse juste : 1 pt</li>
                        <li>1 réponse fausse : 0 pt</li>
                    </ul>
                </td>
            </tr>
            <tr>
                <td>QROC (Question Réponse Ouverte Courte)</td>
                <td>
                    <ul>
                        <li>Concordance exacte : 1 pt</li>
                        <li>Sinon : 0 pt</li>
                    </ul>
                </td>
            </tr>
            <tr>
                <td>QRP (Question Réponse Précisée)</td>
                <td>
                    <ul>
                        <li>x/N (x divisé par N)</li>
                        <li>x : Nombre de réponses justes de l'étudiant</li>
                        <li>N : Nombre de réponses justes attendues</li>
                    </ul>
                </td>
            </tr>
        </table>
    </div>
</body>

</html>