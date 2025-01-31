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
    $sql = "SELECT * FROM QI WHERE id_QI = :id AND type_qcm = 'QP'";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    // Récupérer la question
    $QCM = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($QCM) {
        // Récupérer les informations (avec gestion des valeurs nulles)
        $enonce = isset($QCM['enonce']) ? nl2br(htmlspecialchars($QCM['enonce'])) : '';
        $zone_correcte = isset($QCM['zone_correcte']) ? $QCM['zone_correcte'] : '';
        $zone_correcte_2 = isset($QCM['zone_correcte_2']) ? $QCM['zone_correcte_2'] : '';
        $zone_correcte_3 = isset($QCM['zone_correcte_3']) ? $QCM['zone_correcte_3'] : '';
        $zone_correcte_4 = isset($QCM['zone_correcte_4']) ? $QCM['zone_correcte_4'] : '';
        $correction = isset($QCM['correction']) ? nl2br(htmlspecialchars($QCM['correction'])) : '';
        $imageUrl = isset($QCM['image_url']) ? $QCM['image_url'] : ''; // Récupérer l'URL de l'image
        $imageUrlCorrection = isset($QCM['image_url_correction']) ? $QCM['image_url_correction'] : ''; // Récupérer l'URL de l'image de correction
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
$zone_correcte_coords = !empty($zone_correcte) ? explode(',', $zone_correcte) : []; // Découper les coordonnées correctes (ex : "100,200,20")
$zone_correcte_2_coords = !empty($zone_correcte_2) ? explode(',', $zone_correcte_2) : [];
$zone_correcte_3_coords = !empty($zone_correcte_3) ? explode(',', $zone_correcte_3) : [];
$zone_correcte_4_coords = !empty($zone_correcte_4) ? explode(',', $zone_correcte_4) : [];

// Supposons que l'utilisateur a un ID (peut être récupéré par une session ou une valeur fictive)
$utilisateur_id = 1; // Utilisateur fictif pour cet exemple

// Initialisation de la variable $erreur
$erreur = 0;

// Vérification si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Récupérer les coordonnées soumises par l'utilisateur
    $reponse_utilisateur = isset($_POST['reponse']) ? trim($_POST['reponse']) : null;
    $user_coords = explode(',', $reponse_utilisateur);

    // Vérifier si les coordonnées soumises tombent dans une des zones de sécurité
    $valid = false;
    if (count($user_coords) == 2) {
        $x_user = $user_coords[0];
        $y_user = $user_coords[1];

        if (
            (count($zone_correcte_coords) == 3 &&
                $x_user >= $zone_correcte_coords[0] - $zone_correcte_coords[2] && $x_user <= $zone_correcte_coords[0] + $zone_correcte_coords[2] &&
                $y_user >= $zone_correcte_coords[1] - $zone_correcte_coords[2] && $y_user <= $zone_correcte_coords[1] + $zone_correcte_coords[2]) ||
            (count($zone_correcte_2_coords) == 3 &&
                $x_user >= $zone_correcte_2_coords[0] - $zone_correcte_2_coords[2] && $x_user <= $zone_correcte_2_coords[0] + $zone_correcte_2_coords[2] &&
                $y_user >= $zone_correcte_2_coords[1] - $zone_correcte_2_coords[2] && $y_user <= $zone_correcte_2_coords[1] + $zone_correcte_2_coords[2]) ||
            (count($zone_correcte_3_coords) == 3 &&
                $x_user >= $zone_correcte_3_coords[0] - $zone_correcte_3_coords[2] && $x_user <= $zone_correcte_3_coords[0] + $zone_correcte_3_coords[2] &&
                $y_user >= $zone_correcte_3_coords[1] - $zone_correcte_3_coords[2] && $y_user <= $zone_correcte_3_coords[1] + $zone_correcte_3_coords[2]) ||
            (count($zone_correcte_4_coords) == 3 &&
                $x_user >= $zone_correcte_4_coords[0] - $zone_correcte_4_coords[2] && $x_user <= $zone_correcte_4_coords[0] + $zone_correcte_4_coords[2] &&
                $y_user >= $zone_correcte_4_coords[1] - $zone_correcte_4_coords[2] && $y_user <= $zone_correcte_4_coords[1] + $zone_correcte_4_coords[2])
        ) {
            $valid = true;
        }
    }

    if (!$valid) {
        $erreur = 1; // 1 erreur si les coordonnées sont incorrectes
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
    $message_resultat = "<div class='feedback'>";
    $message_resultat .= "<h3>Correction:</h3>";
    if (!empty($imageUrlCorrection)) {
        $message_resultat .= "<img src='" . htmlspecialchars($imageUrlCorrection) . "' alt='Correction Image' style='max-width: 100%; height: auto;'>";
    }
    $message_resultat .= "<p>$correction</p></div>";
}

// Si l'utilisateur a validé la bonne réponse avec le bouton "Ma réponse était juste"
if (isset($_POST['valider_bonne_reponse'])) {
    // Considérer que la réponse est correcte sans validation manuelle
    $reponse_utilisateur = $zone_correcte; // On suppose que la première réponse est correcte
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
    $message_resultat = "<div class='feedback'>";
    $message_resultat .= "<h3>Correction:</h3>";
    if (!empty($imageUrlCorrection)) {
        $message_resultat .= "<img src='" . htmlspecialchars($imageUrlCorrection) . "' alt='Correction Image' style='max-width: 100%; height: auto;'>";
    }
    $message_resultat .= "<p>$correction</p></div>";
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QCM - Question QP</title>
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
            border: 2px solid #ccc;
        }

        .feedback h3 {
            margin-top: 0;
            font-size: 1.2rem;
            color: #333;
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
            width: 70px;
            height: 70px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            position: fixed;
            /* Pour positionner de manière fixe sur l'écran */
            top: 30px;
            /* Décalage depuis le haut */
            right: 30px;
            /* Décalage depuis la droite */
            font-weight: bold;
            color: white;
            z-index: 1000;
            /* Assurer que la note soit au-dessus des autres éléments */
            margin-top: 70px;
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

        .image-container {
            position: relative;
            display: inline-block;
        }

        .pointer {
            position: absolute;
            width: 15px;
            height: 15px;
            background-color: red;
            border-radius: 50%;
            cursor: pointer;
        }

        .correct-zone {
            position: absolute;
            border: 4px dashed green;
            border-radius: 50%;
            opacity: 0.8;
        }

        /* Cacher l'input de la réponse de l'utilisateur et son label */
        #reponse-container {
            display: none;
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

        #div1 {
            background-color: <?php echo isset($note) ? ($note == 20 ? '#4CAF50' : ($note == 0 ? '#F44336' : '#FF9800')) : 'rgb(86, 8, 69)'; ?>;
            color: white;
            padding: 20px;
            text-align: center;
        }

        /* #gps {
            display: none;
        } */

        /* #reponse {
            display: none;
        } */
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
        <h1>QCM - QP</h1>
    </div>

    <div id="question-container">
        <h1>Question : <?php echo $enonce; ?></h1>

        <?php
        // Utiliser __DIR__ pour obtenir le chemin absolu du répertoire actuel
        $imagePath = __DIR__ . '/' . $imageUrl;

        // Vérifier si l'image existe à cet emplacement
        if (!empty($imageUrl) && file_exists($imagePath)) {
            echo '<h3>Image :</h3>';
            echo '<div class="image-container" id="image-container">';
            echo '<img src="' . htmlspecialchars($imageUrl) . '" alt="QCM Image" id="qcm-image">';
            echo '<div class="pointer" id="pointer" style="display: none;"></div>';
            echo '<div class="correct-zone" id="correct-zone-1" style="display: none;"></div>';
            echo '<div class="correct-zone" id="correct-zone-2" style="display: none;"></div>';
            echo '<div class="correct-zone" id="correct-zone-3" style="display: none;"></div>';
            echo '<div class="correct-zone" id="correct-zone-4" style="display: none;"></div>';
            echo '</div>';
        }
        ?>

        <form method="POST" action="">
            <label reponse for="reponse" id='gps'>Votre réponse (coordonnées x,y) :</label>
            <input type="text" id="reponse" name="reponse" value="<?php echo htmlspecialchars($reponse_utilisateur ?? ''); ?>" required>
            <input type="submit" value="Valider ma réponse">
        </form>

        <?php if ($message_resultat): ?>
            <div class="feedback">
                <?php echo $message_resultat; ?>
            </div>
            <!-- Ajout du bouton "Ma réponse était juste" après la soumission -->
            <?php if ($erreur == 1): ?>
                <form method="POST" action="" id="justify-form">
                    <input type="submit" name="valider_bonne_reponse" value="Ma réponse était juste" class="valider-bonne-reponse">
                </form>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($_SERVER['REQUEST_METHOD'] == 'POST'): ?>
            <div id='score' class="note <?php echo ($note == 20) ? 'correct' : 'incorrect'; ?>">
                <?php echo $note; ?>/20
            </div>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', (event) => {
            const imageContainer = document.getElementById('image-container');
            const qcmImage = document.getElementById('qcm-image');
            const pointer = document.getElementById('pointer');
            const correctZone1 = document.getElementById('correct-zone-1');
            const correctZone2 = document.getElementById('correct-zone-2');
            const correctZone3 = document.getElementById('correct-zone-3');
            const correctZone4 = document.getElementById('correct-zone-4');
            let isDragging = false;

            if (imageContainer && qcmImage && pointer && correctZone1 && correctZone2 && correctZone3 && correctZone4) {
                pointer.style.display = 'block';

                pointer.addEventListener('mousedown', (event) => {
                    isDragging = true;
                });

                document.addEventListener('mouseup', (event) => {
                    isDragging = false;
                });

                document.addEventListener('mousemove', (event) => {
                    if (isDragging) {
                        const rect = qcmImage.getBoundingClientRect();
                        const x = event.clientX - rect.left;
                        const y = event.clientY - rect.top;

                        pointer.style.left = `${x}px`;
                        pointer.style.top = `${y}px`;

                        document.getElementById('reponse').value = `${x},${y}`;
                    }
                });

                imageContainer.addEventListener('click', (event) => {
                    const rect = qcmImage.getBoundingClientRect();
                    const x = event.clientX - rect.left;
                    const y = event.clientY - rect.top;

                    pointer.style.left = `${x}px`;
                    pointer.style.top = `${y}px`;

                    document.getElementById('reponse').value = `${x},${y}`;
                });

                // Afficher les zones correctes après la soumission du formulaire
                <?php if ($_SERVER['REQUEST_METHOD'] == 'POST'): ?>
                    const zones = [
                        <?php if (count($zone_correcte_coords) == 3): ?> {
                                x: <?php echo $zone_correcte_coords[0]; ?>,
                                y: <?php echo $zone_correcte_coords[1]; ?>,
                                margin: <?php echo $zone_correcte_coords[2]; ?>
                            },
                        <?php endif; ?>
                        <?php if (count($zone_correcte_2_coords) == 3): ?> {
                                x: <?php echo $zone_correcte_2_coords[0]; ?>,
                                y: <?php echo $zone_correcte_2_coords[1]; ?>,
                                margin: <?php echo $zone_correcte_2_coords[2]; ?>
                            },
                        <?php endif; ?>
                        <?php if (count($zone_correcte_3_coords) == 3): ?> {
                                x: <?php echo $zone_correcte_3_coords[0]; ?>,
                                y: <?php echo $zone_correcte_3_coords[1]; ?>,
                                margin: <?php echo $zone_correcte_3_coords[2]; ?>
                            },
                        <?php endif; ?>
                        <?php if (count($zone_correcte_4_coords) == 3): ?> {
                                x: <?php echo $zone_correcte_4_coords[0]; ?>,
                                y: <?php echo $zone_correcte_4_coords[1]; ?>,
                                margin: <?php echo $zone_correcte_4_coords[2]; ?>
                            }
                        <?php endif; ?>
                    ];

                    zones.forEach((zone, index) => {
                        if (zone) {
                            const radius = Math.sqrt(zone.margin * zone.margin + zone.margin * zone.margin);
                            const correctZone = document.getElementById(`correct-zone-${index + 1}`);
                            correctZone.style.display = 'block';
                            correctZone.style.width = `${2 * radius}px`;
                            correctZone.style.height = `${2 * radius}px`;
                            correctZone.style.left = `${zone.x - radius}px`;
                            correctZone.style.top = `${zone.y - radius}px`;
                        }
                    });
                <?php endif; ?>
            }
        });
    </script>
</body>

</html>