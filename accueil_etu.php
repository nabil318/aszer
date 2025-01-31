<?php
// Connexion à la base de données SQLite
try {
    $db = new PDO('sqlite:QCM.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Créer les tables user_results et user_results_DP si elles n'existent pas
    $createTableSQL = "
        CREATE TABLE IF NOT EXISTS user_results (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            qcm_id INTEGER NOT NULL,
            date TEXT NOT NULL,
            note INTEGER NOT NULL,
            FOREIGN KEY (qcm_id) REFERENCES QI(id_QI)
        );
    ";
    $db->exec($createTableSQL);

    $createTableSQL_DP = "
        CREATE TABLE IF NOT EXISTS user_results_DP (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            qcm_id INTEGER NOT NULL,
            date TEXT NOT NULL,
            note INTEGER NOT NULL,
            FOREIGN KEY (qcm_id) REFERENCES DP(id_DP)
        );
    ";
    $db->exec($createTableSQL_DP);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Déclarer une variable pour stocker les résultats
$resultats = '';

// Vérification si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Récupérer les valeurs du formulaire
    $specialite = $_POST['specialite'] ?? null;
    $item = $_POST['item'] ?? null;
    $ville = $_POST['ville'] ?? null;
    $type_qcm = $_POST['type_qcm'] ?? null;
    $date = $_POST['date'] ?? null;  // Récupérer la date du formulaire

    // Vérification obligatoire de spécialité et item
    if (!$specialite || !$item) {
        $resultats = "Veuillez sélectionner une spécialité et un item.";
    } else {
        // Construction de la requête SQL avec des conditions facultatives
        $sql = "SELECT q.*, CASE WHEN q.type_qcm = 'KFP' THEN 'KFP' ELSE q.type_qcm END AS type_qcm_display,
                       ur.date AS derniere_date, ur.note AS derniere_note, q.session
                FROM QI q
                LEFT JOIN (
                    SELECT qcm_id, MAX(date) AS max_date
                    FROM user_results
                    GROUP BY qcm_id
                ) ur_max ON q.id_QI = ur_max.qcm_id
                LEFT JOIN user_results ur ON q.id_QI = ur.qcm_id AND ur.date = ur_max.max_date
                WHERE q.specialite = :specialite AND q.item = :item AND q.is_etu = 1";  // Condition is_etu = 1

        // Si le type de QCM est KFP ou DP, on cherche dans la table DP_0
        if ($type_qcm == 'KFP' || strpos($type_qcm, 'DP') === 0) {
            $sql = "SELECT q.*, CASE WHEN q.is_KFP = 1 THEN 'KFP' ELSE q.type_qcm END AS type_qcm_display,
                           ur.date AS derniere_date, ur.note AS derniere_note, q.session
                    FROM DP_0 q
                    LEFT JOIN (
                        SELECT qcm_id, MAX(date) AS max_date
                        FROM user_results_DP
                        GROUP BY qcm_id
                    ) ur_max ON q.id_DP = ur_max.qcm_id
                    LEFT JOIN user_results_DP ur ON q.id_DP = ur.qcm_id AND ur.date = ur_max.max_date
                    WHERE q.specialite = :specialite AND q.item = :item AND q.is_etu = 1";  // Condition is_etu = 1
            if ($type_qcm == 'KFP') {
                $sql .= " AND q.is_KFP = 1";
            }
        }

        // Ajouter les conditions facultatives à la requête
        if ($ville) {
            $sql .= " AND q.ville = :ville";
        }
        if ($type_qcm && $type_qcm != 'KFP' && strpos($type_qcm, 'DP') !== 0) {
            $sql .= " AND q.type_qcm = :type_qcm";
        }
        if ($date) {
            $sql .= " AND q.date = :date";
        }

        // Préparer la requête
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':specialite', $specialite);
        $stmt->bindParam(':item', $item);

        // Lier les paramètres facultatifs
        if ($ville) {
            $stmt->bindParam(':ville', $ville);
        }
        if ($type_qcm && $type_qcm != 'KFP' && strpos($type_qcm, 'DP') !== 0) {
            $stmt->bindParam(':type_qcm', $type_qcm);
        }
        if ($date) {
            $stmt->bindParam(':date', $date);  // Lier la date si elle est spécifiée
        }

        // Exécuter la requête
        $stmt->execute();
        $resultats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} else {
    // Si le formulaire n'a pas été soumis, afficher toutes les QI avec is_etu = 1
    $sql = "SELECT q.*, CASE WHEN q.type_qcm = 'KFP' THEN 'KFP' ELSE q.type_qcm END AS type_qcm_display,
                   ur.date AS derniere_date, ur.note AS derniere_note, q.session
            FROM QI q
            LEFT JOIN (
                SELECT qcm_id, MAX(date) AS max_date
                FROM user_results
                GROUP BY qcm_id
            ) ur_max ON q.id_QI = ur_max.qcm_id
            LEFT JOIN user_results ur ON q.id_QI = ur.qcm_id AND ur.date = ur_max.max_date
            WHERE q.is_etu = 1";  // Condition is_etu = 1
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $resultats = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QCM d'Étudiant- Sélectionner Spécialité et Item</title>
    <style>
        /* Style du header */
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

        /* Style du formulaire */
        form {
            margin-top: 20px;
            padding: 20px;
            background-color: #f4f4f9;
            border-radius: 8px;
        }

        select,
        input[type="submit"] {
            padding: 10px;
            margin: 5px;
            border-radius: 5px;
        }

        #resultat ul {
            margin-top: 20px;
        }

        #resultat ul li {
            margin-bottom: 10px;
        }

        /* Button Styling */
        .btn {
            padding: 10px 15px;
            background-color: #333;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .btn:hover {
            background-color: #555;
        }
    </style>
</head>

<body>

    <!-- Header avec les boutons -->
    <header>
        <div>
            <a href='data.php'>Ajouter un QCM</a>
            <a href='ancrage.php'>Ancrage</a>
            <a href="exam_univ.php">Examens Universitaires</a>
            <a href="exam_EDN.php">Annales EDN</a>
            <a href="accueil_etu.php">QCM Étudiant</a>
            <a href="compte_user.php">Compte User</a>
        </div>
        <div>
            <a href="profil.php" style="float: right;">Profil</a>
        </div>
    </header>

    <h1>QCM d'Étudiant - Sélectionner Spécialité et Item</h1>

    <form method="POST" action="">
        <label for="specialite">Choisissez une spécialité :</label>
        <select id="specialite" name="specialite" required>
            <option value="">Choisissez une spécialité</option>
            <option value="cardiologie">Cardiologie</option>
            <option value="neurologie">Neurologie</option>
            <option value="nephrologie">Néphrologie</option>
            <option value="gynecologie">Gynécologie</option>
            <option value="hematologie">Hématologie</option>
            <option value="pneumologie">Pneumologie</option>
        </select>

        <label for="item">Choisissez un item :</label>
        <select id="item" name="item" required>
            <option value="">Choisissez un item</option>
            <option value="1">1</option>
            <option value="2">2</option>
            <option value="3">3</option>
            <option value="14">14</option>
        </select>

        <label for="ville">Choisissez une ville :</label>
        <select id="ville" name="ville">
            <option value="">--Sélectionnez une ville (facultatif)--</option>
            <option value="Paris">Paris</option>
            <option value="Marseille">Marseille</option>
            <option value="Lyon">Lyon</option>
        </select>

        <label for="type_qcm">Choisissez un type de QCM :</label>
        <select id="type_qcm" name="type_qcm">
            <option value="">--Sélectionnez un type (facultatif)--</option>
            <option value="QRM">QRM</option>
            <option value="DP">DP</option>
            <option value="KFP">KFP</option>
            <option value="QM">QM</option>
            <option value="QRU">QRU</option>
            <option value="QP">QP</option>
            <option value="TCS">TCS</option>
        </select>

        <label for="date">Choisissez une date :</label>
        <select id="date" name="date">
            <option value="">--Sélectionnez une date (facultatif)--</option>
            <option value="2025">2025</option>
            <option value="2024">2024</option>
            <option value="2023">2023</option>
        </select>

        <input type="submit" value="Valider">
    </form>

    <div id="resultat">
        <?php
        // Vérifier si des résultats ont été trouvés
        if ($resultats && is_array($resultats)) {
            echo "<h2>Résultats :</h2>";
            echo "<ul>";

            // Numéroter les résultats
            $count = 1;

            // Afficher les questions disponibles et un bouton pour chaque question
            foreach ($resultats as $row) {
                echo "<li><strong>Numéro: </strong>" . $count . "</li>";
                echo "<li><strong>Rang: </strong>" . htmlspecialchars($row['rang'] ?? '') . "</li>";
                echo "<li><strong>Énoncé: </strong>" . htmlspecialchars($row['enonce_accueil'] ?? $row['enonce'] ?? '') . "</li>";

                // Utiliser type_qcm_display pour afficher le type de QCM
                $type_qcm_display = htmlspecialchars($row['type_qcm_display'] ?? $row['type_qcm'] ?? '');
                echo "<li><strong>Type de QCM: </strong>" . $type_qcm_display . "</li>";

                // Afficher la date de création
                echo "<li><strong>Date: </strong>" . htmlspecialchars($row['date_creation'] ?? $row['date'] ?? '') . "</li>";

                // Afficher la dernière date et la dernière note
                echo "<li><strong>Dernière date: </strong>" . htmlspecialchars($row['derniere_date'] ?? '') . "</li>";
                echo "<li><strong>Dernière note: </strong>" . htmlspecialchars($row['derniere_note'] ?? '') . "</li>";

                // Afficher la session
                echo "<li><strong>Session: </strong>" . htmlspecialchars($row['session'] ?? '') . "</li>";

                // Formulaire pour rediriger vers la page appropriée en fonction du type de QCM
                $id = htmlspecialchars($row['id_QI'] ?? $row['id_DP'] ?? '');
                $url = '';

                // Redirection basée sur le type de QCM
                switch ($type_qcm_display) {
                    case 'QRU':
                        $url = 'question_QRU.php';
                        break;
                    case 'QRM':
                        $url = 'question_QRM.php';
                        break;
                    case 'QM':
                        $url = 'question_QM.php';
                        break;
                    case 'QP':
                        $url = 'question_QP.php';
                        break;
                    case 'KFP':
                        $url = 'question_KFP.php';
                        break;
                    case (strpos($type_qcm_display, 'DP') === 0):
                        $url = 'question_DP.php';
                        break;
                    case 'TCS':  // Nouveau cas pour le type TCS
                        $url = 'question_TCS.php';
                        break;
                    default:
                        $url = 'index.php';
                        break;
                }

                echo "<form method='GET' action='$url'>";
                echo "<input type='hidden' name='id' value='$id'>";
                echo "<input type='submit' value='Voir la question'>";
                echo "</form>";

                $count++;
            }

            echo "</ul>";
        } elseif ($resultats && is_string($resultats)) {
            // Afficher le message d'erreur si applicable
            echo "<p>$resultats</p>";
        } elseif ($_SERVER['REQUEST_METHOD'] == 'POST') {
            // Afficher un message si aucune question n'est trouvée
            echo "<p>Aucun résultat trouvé pour les critères sélectionnés.</p>";
        }
        ?>
    </div>

</body>

</html>