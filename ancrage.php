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
        if ($type_qcm == 'QRU' || $type_qcm == 'QRM' || $type_qcm == 'QM' || $type_qcm == 'TCS') {
            // Pour les QCM QRU, QRM, QM, TCS
            $sql = "SELECT q.*,
                           CASE WHEN q.type_qcm = 'QRU' THEN 'QRU'
                                WHEN q.type_qcm = 'QRM' THEN 'QRM'
                                WHEN q.type_qcm = 'QM' THEN 'QM'
                                WHEN q.type_qcm = 'TCS' THEN 'TCS'
                           END AS type_qcm_display,
                           ur.date AS derniere_date, ur.note AS derniere_note, q.session
                    FROM QCM q
                    LEFT JOIN (
                        SELECT qcm_id, MAX(date) AS max_date
                        FROM user_results
                        GROUP BY qcm_id
                    ) ur_max ON q.id_QCM = ur_max.qcm_id
                    LEFT JOIN user_results ur ON q.id_QCM = ur.qcm_id AND ur.date = ur_max.max_date
                    WHERE q.specialite = :specialite AND q.item = :item AND ur.note < 20";
        } else {
            // Autres types de QCM
            $sql = "SELECT q.*,
                           CASE WHEN q.type_qcm = 'KFP' THEN 'KFP' ELSE q.type_qcm END AS type_qcm_display,
                           ur.date AS derniere_date, ur.note AS derniere_note, q.session
                    FROM QI q
                    LEFT JOIN (
                        SELECT qcm_id, MAX(date) AS max_date
                        FROM user_results
                        GROUP BY qcm_id
                    ) ur_max ON q.id_QI = ur_max.qcm_id
                    LEFT JOIN user_results ur ON q.id_QI = ur.qcm_id AND ur.date = ur_max.max_date
                    WHERE q.specialite = :specialite AND q.item = :item AND ur.note < 20";
        }

        // Ajouter les conditions facultatives à la requête
        if ($ville) {
            $sql .= " AND q.ville = :ville";
        }
        if ($type_qcm && $type_qcm != 'KFP' && strpos($type_qcm, 'DP') === false) {
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
        if ($type_qcm && $type_qcm != 'KFP' && strpos($type_qcm, 'DP') === false) {
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
    // Si le formulaire n'a pas été soumis, afficher toutes les QI avec une note inférieure à 20
    $sql = "SELECT q.*,
                   CASE WHEN q.type_qcm = 'KFP' THEN 'KFP' ELSE q.type_qcm END AS type_qcm_display,
                   ur.date AS derniere_date, ur.note AS derniere_note, q.session
            FROM QI q
            LEFT JOIN (
                SELECT qcm_id, MAX(date) AS max_date
                FROM user_results
                GROUP BY qcm_id
            ) ur_max ON q.id_QI = ur_max.qcm_id
            LEFT JOIN user_results ur ON q.id_QI = ur.qcm_id AND ur.date = ur_max.max_date
            WHERE ur.note < 20";
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
    <title>QCM - Sélectionner Spécialité et Item</title>
    <style>
        /* Style du header */
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
            <a href='accueil.php'>Accueil</a>
            <a href='ancrage.php'>Ancrage</a>
            <a href="exam_univ.php">Examens Universitaires</a>
            <a href="exam_EDN.php">Annales EDN</a>
            <a href="2index.php">QCM Étudiant</a>
            <a href="compte_user.php">Compte User</a>
        </div>
        <div>
            <a href="profil.php" style="float: right;">Profil</a>
        </div>
    </header>

    <h1>QCM - Sélectionner Spécialité et Item</h1>

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
            <option value="item_1">Item 1</option>
            <option value="item_2">Item 2</option>
            <option value="item_3">Item 3</option>
            <option value="item_4">Item 4</option>
        </select>

        <label for="ville">Ville :</label>
        <select name="ville" id="ville">
            <option value="">Toutes les villes</option>
            <option value="Paris">Paris</option>
            <option value="Lyon">Lyon</option>
            <option value="Marseille">Marseille</option>
        </select>

        <label for="type_qcm">Type de QCM :</label>
        <select name="type_qcm" id="type_qcm">
            <option value="">Choisir un type de QCM</option>
            <option value="QRU">QRU</option>
            <option value="QRM">QRM</option>
            <option value="QM">QM</option>
            <option value="TCS">TCS</option>
            <option value="KFP">KFP</option>
            <option value="DP_0">DP</option>
        </select>

        <label for="date">Date :</label>
        <input type="date" id="date" name="date">

        <input type="submit" value="Rechercher" class="btn">
    </form>

    <!-- Affichage des résultats -->
    <div id="resultat">
        <?php if (!empty($resultats)) : ?>
            <ul>
                <?php foreach ($resultats as $row) : ?>
                    <li>
                        <strong><?= htmlspecialchars($row['type_qcm_display'] ?? 'Non renseigné') ?>:</strong>
                        <?= htmlspecialchars($row['specialite'] ?? 'Non renseigné') ?> -
                        <?= htmlspecialchars($row['item'] ?? 'Non renseigné') ?> -
                        <strong>Date:</strong> <?= htmlspecialchars($row['derniere_date'] ?? 'Non renseigné') ?> -
                        <strong>Note:</strong> <?= htmlspecialchars($row['derniere_note'] ?? 'Non renseigné') ?>

                        <!-- Formulaire de redirection -->
                        <form method="GET" action="<?php
                                                    // Rediriger selon le type de QCM
                                                    switch ($row['type_qcm_display']) {
                                                        case 'QRU':
                                                            echo 'question_QRU.php';
                                                            break;
                                                        case 'QRM':
                                                            echo 'question_QRM.php';
                                                            break;
                                                        case 'QM':
                                                            echo 'question_QM.php';
                                                            break;
                                                        case 'TCS':
                                                            echo 'question_TCS.php';
                                                            break;
                                                        default:
                                                            echo 'question.php';
                                                    }
                                                    ?>">
                            <input type="hidden" name="id" value="<?= htmlspecialchars($row['id_QI'] ?? $row['id_DP'] ?? '') ?>">
                            <input type="hidden" name="type_qcm" value="<?= htmlspecialchars($row['type_qcm_display'] ?? '') ?>">
                            <input type="submit" value="Retenter le QCM" class="btn">
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else : ?>
            <p>Aucun résultat trouvé.</p>
        <?php endif; ?>
    </div>

</body>

</html>