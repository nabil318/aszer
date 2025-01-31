<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['pseudo'])) {
    header('Location: login.php');
    exit();
}

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
            FOREIGN KEY (qcm_id) REFERENCES DP_0(id_DP)
        );
    ";
    $db->exec($createTableSQL_DP);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Récupérer les informations de l'utilisateur connecté
$pseudo = $_SESSION['pseudo'];
$sql = "SELECT * FROM users WHERE pseudo = ?";
$stmt = $db->prepare($sql);
$stmt->execute([$pseudo]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Vérifier si l'utilisateur a été trouvé
if (!$user) {
    die("Utilisateur non trouvé. Veuillez vérifier votre pseudo ou contacter l'administrateur.");
}

// Vérifier si l'utilisateur est un administrateur
$is_admin = ($user['role'] === 'admin');

// Initialiser la variable pour stocker les résultats
$resultats = '';

// Vérification si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Récupérer les valeurs du formulaire
    $specialite = $_POST['specialite'] ?? null;
    $item = $_POST['item'] ?? null;
    $ville = $_POST['ville'] ?? null;
    $type_qcm = $_POST['type_qcm'] ?? null;
    $date = $_POST['date'] ?? null;
    $rang = $_POST['rang'] ?? null;
    $origine = $_POST['origine'] ?? null;
    $session = $_POST['session'] ?? null; // Ajout du filtre pour la session

    // Construction de la requête SQL pour les QI
    $sql = "SELECT q.*, 'QI' AS table_source,
                   CASE WHEN q.type_qcm = 'KFP' THEN 'KFP' ELSE q.type_qcm END AS type_qcm_display,
                   ur.date AS derniere_date, ur.note AS derniere_note, q.session, q.ville,
                   CASE
                       WHEN q.is_etu = 1 THEN 'etu'
                       WHEN q.is_EDN = 1 THEN 'EDN'
                       WHEN q.is_FAQ = 1 THEN 'FAQ'
                       ELSE 'inconnu'
                   END AS origine
            FROM QI q
            LEFT JOIN (
                SELECT qcm_id, MAX(date) AS max_date
                FROM user_results
                GROUP BY qcm_id
            ) ur_max ON q.id_QI = ur_max.qcm_id
            LEFT JOIN user_results ur ON q.id_QI = ur.qcm_id AND ur.date = ur_max.max_date
            WHERE 1=1";

    if ($specialite) {
        $sql .= " AND q.specialite = :specialite";
    }
    if ($item) {
        $sql .= " AND INSTR(',' || q.item || ',', :item) > 0";
    }
    if ($type_qcm && $type_qcm !== 'KFP') {
        $sql .= " AND q.type_qcm = :type_qcm";
    }
    if ($ville) {
        $sql .= " AND q.ville = :ville";
    }
    if ($origine) {
        $sql .= " AND CASE
                       WHEN q.is_etu = 1 THEN 'etu'
                       WHEN q.is_EDN = 1 THEN 'EDN'
                       WHEN q.is_FAQ = 1 THEN 'FAQ'
                       ELSE 'inconnu'
                   END = :origine";
    }
    if ($session) {
        $sql .= " AND q.session = :session";
    }
    if ($date) {
        $sql .= " AND q.date = :date";
    }
    if ($rang) {
        if ($rang == 'A') {
            $sql .= " AND (q.rang = 'A' OR q.rang = 'A,B')";
        } elseif ($rang == 'B') {
            $sql .= " AND (q.rang = 'B' OR q.rang = 'A,B')";
        }
    }

    // Construction de la requête SQL pour les DP
    $sql_DP = "SELECT q.*, 'DP' AS table_source,
                      CASE WHEN q.is_KFP = 1 THEN 'KFP' ELSE q.type_qcm END AS type_qcm_display,
                      ur.date AS derniere_date, ur.note AS derniere_note, q.session, q.ville,
                      CASE
                          WHEN q.is_etu = 1 THEN 'etu'
                          WHEN q.is_EDN = 1 THEN 'EDN'
                          WHEN q.is_FAQ = 1 THEN 'FAQ'
                          ELSE 'inconnu'
                      END AS origine
               FROM DP_0 q
               LEFT JOIN (
                   SELECT qcm_id, MAX(date) AS max_date
                   FROM user_results_DP
                   GROUP BY qcm_id
               ) ur_max ON q.id_DP = ur_max.qcm_id
               LEFT JOIN user_results_DP ur ON q.id_DP = ur.qcm_id AND ur.date = ur_max.max_date
               WHERE 1=1";

    if ($specialite) {
        $sql_DP .= " AND q.specialite = :specialite";
    }
    if ($item) {
        $sql_DP .= " AND INSTR(',' || q.item || ',', :item) > 0";
    }
    if ($type_qcm && $type_qcm === 'KFP') {
        $sql_DP .= " AND q.is_KFP = 1";
    } else if ($type_qcm) {
        $sql_DP .= " AND q.type_qcm = :type_qcm";
    }
    if ($ville) {
        $sql_DP .= " AND q.ville = :ville";
    }
    if ($origine) {
        $sql_DP .= " AND CASE
                          WHEN q.is_etu = 1 THEN 'etu'
                          WHEN q.is_EDN = 1 THEN 'EDN'
                          WHEN q.is_FAQ = 1 THEN 'FAQ'
                          ELSE 'inconnu'
                      END = :origine";
    }
    if ($session) {
        $sql_DP .= " AND q.session = :session";
    }
    if ($date) {
        $sql_DP .= " AND q.date = :date";
    }
    if ($rang) {
        if ($rang == 'A') {
            $sql_DP .= " AND (q.rang = 'A' OR q.rang = 'A,B')";
        } elseif ($rang == 'B') {
            $sql_DP .= " AND (q.rang = 'B' OR q.rang = 'A,B')";
        }
    }

    // Préparer les requêtes
    $stmt = $db->prepare($sql);
    $stmt_DP = $db->prepare($sql_DP);

    // Lier les paramètres
    if ($specialite) {
        $stmt->bindParam(':specialite', $specialite);
        $stmt_DP->bindParam(':specialite', $specialite);
    }
    if ($item) {
        $stmt->bindValue(':item', $item);
        $stmt_DP->bindValue(':item', $item);
    }
    if ($type_qcm && $type_qcm !== 'KFP') {
        $stmt->bindParam(':type_qcm', $type_qcm);
        $stmt_DP->bindParam(':type_qcm', $type_qcm);
    }
    if ($ville) {
        $stmt->bindParam(':ville', $ville);
        $stmt_DP->bindParam(':ville', $ville);
    }
    if ($origine) {
        $stmt->bindParam(':origine', $origine);
        $stmt_DP->bindParam(':origine', $origine);
    }
    if ($session) {
        $stmt->bindParam(':session', $session);
        $stmt_DP->bindParam(':session', $session);
    }
    if ($date) {
        $stmt->bindParam(':date', $date);
        $stmt_DP->bindParam(':date', $date);
    }

    // Exécuter les requêtes
    $stmt->execute();
    $stmt_DP->execute();

    // Fusionner les résultats
    $resultats = array_merge($stmt->fetchAll(PDO::FETCH_ASSOC), $stmt_DP->fetchAll(PDO::FETCH_ASSOC));
}

// Tableau pour mapper les numéros d'items à leurs noms
$item_titles = [
    '152' => 'Endocardite infectieuse',
    '153' => 'Surveillance des porteurs de valves et prothèses vasculaires',
    '198' => 'Arthropathie microcristalline',
    '203' => 'Dyspnée aiguë et chronique',
    '221' => 'Athérome: Épidémiologie et physiopathologie',
    '222' => 'Facteurs de risque cardiovasculaire et prévention',
    '223' => 'Dyslipidémies',
    '224' => 'Hypertension artérielle de l\'adulte et de l\'enfant',
    '225' => 'Artériopathie de l\'aorte, des artères viscérales et des membres inférieurs; Anévrismes',
    '226' => 'Thrombose veineuse profonde et embolie pulmonaire',
    '230' => 'Douleur thoracique aiguë',
    '231' => 'Électrocardiogramme: Indications et interprétations',
    '232' => 'Fibrillation atriale',
    '233' => 'Valvulopathies',
    '234' => 'Insuffisance cardiaque de l\'adulte',
    '235' => 'Péricardite aiguë',
    '236' => 'Trouble de la conduction intracardiaque',
    '237' => 'Palpitations',
    '339' => 'Syndromes coronariens aigus',
    '342' => 'Malaises, perte de connaissance, crise comitale chez l\'adulte',
    '128' => 'Ostéopathies fragilisantes',
    '129' => 'Arthrose',
    '156' => 'Infection ostéo-articulaire de l\'enfant et de l\'adulte',
    '196' => 'Polyarthrite rhumatoïde',
    '197' => 'Spondyloarthrite',
    '268' => 'Hypercalcémie',
    '307' => 'Tumeurs des os primitives et secondaires',
    '95' => 'Radiculalgie et syndrome canalaire',
    '349' => 'Infection aiguë des parties molles (abcès, panaris, phlegmon des gaines)',
    '361' => 'Lésions péri-articulaires et ligamentaires',
    '362' => 'Prothèses et ostéosynthèses',
    '363' => 'Fractures de l\'extrémité proximale du fémur et extrémité inférieure du radius',
    '365' => 'Surveillance d\'un malade sous plâtre/Résine',
    '195' => 'Artérite à cellules géantes',
    '256' => 'Aptitude au sport chez l\'adulte et l\'enfant',
    '330' => 'Prescription et surveillance des classes de médicaments les plus courants chez l\'adulte et chez l\'enfant',
    '1' => 'Test',
    '94' => 'Rachialgies',
];
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
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        form label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        form select,
        form input[type="submit"] {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 16px;
        }

        form input[type="submit"] {
            background-color: #333;
            color: white;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        form input[type="submit"]:hover {
            background-color: #555;
        }

        /* Style pour la mise en page des résultats */
        #resultat {
            margin-top: 20px;
        }

        #resultat h2 {
            margin-bottom: 10px;
        }

        .result-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .result-grid div {
            border: 1px solid #ccc;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            background-color: #fff;
            position: relative;
            /* Ajouté pour permettre le positionnement absolu */
        }

        .result-grid div strong {
            display: block;
            margin-bottom: 5px;
        }

        .result-grid div form {
            margin-top: 10px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .result-grid div form input[type="submit"] {
            background-color: #333;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .result-grid div form input[type="submit"]:hover {
            background-color: #555;
        }

        /* Style pour la spécialité */
        .specialite {
            margin-top: 10px;
            text-align: center;
            font-style: italic;
            font-weight: bold;
            text-decoration: underline;
            margin-bottom: 20px;
        }

        /* Style pour le type de QCM */
        .type-qcm {
            background-color: #f0f0f0;
            padding: 5px;
            border-radius: 5px;
            display: inline-block;
            margin-bottom: 10px;
            margin-left: 170px;
            top: 10px;
            right: 10px;

        }

        /* Style pour l'origine */
        .origine {
            background-color: #e0e0e0;
            padding: 5px;
            border-radius: 5px;
            display: inline-block;
            margin-bottom: 10px;
        }

        /* Style pour le rang */
        .rang-A {
            color: red;
            padding: 5px;
            border-radius: 5px;
            display: inline-block;
            margin-bottom: 10px;
            position: absolute;
            top: 10px;
            left: 10px;
        }

        .rang-B {
            color: blue;
            padding: 5px;
            border-radius: 5px;
            display: inline-block;
            margin-bottom: 10px;
            position: absolute;
            top: 10px;
            left: 10px;
        }

        .rang-AB {
            color: red;
            padding: 5px;
            border-radius: 5px;
            display: inline-block;
            margin-bottom: 10px;
            position: absolute;
            top: 10px;
            left: 10px;
        }

        /* Style pour la ville */
        .ville {
            background-color: #c0c0c0;
            padding: 5px;
            border-radius: 5px;
            display: inline-block;
            margin-bottom: 10px;
        }

        /* Style pour la date */
        .date {
            background-color: #b0b0b0;
            padding: 5px;
            border-radius: 5px;
            display: inline-block;
            margin-bottom: 10px;
        }

        /* Style pour la session */
        .session {
            background-color: #a0a0a0;
            padding: 5px;
            border-radius: 5px;
            display: inline-block;
            margin-bottom: 10px;
        }

        /* Style pour l'item */
        .item {
            text-align: center;
            font-style: italic;
            font-weight: bold;
            margin-bottom: 20px;
        }

        /* Style pour centrer les éléments */
        .centered-info {

            align-items: center;
            margin-bottom: 10px;
        }

        .centered-info span {
            margin: 0 5px;
        }

        /* Style pour le rectangle déroulant */
        #info-box {
            background-color: #f9f9f9;
            border: 1px solid #ccc;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.5s ease;
        }

        /* Style pour le bouton de fermeture */
        #toggle-info {
            background-color: #333;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
            margin-top: 10px;
            display: inline-block;
        }

        #toggle-info:hover {
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
            <a href="commentaire.php">Donnez-nous votre avis</a>
            <?php if ($is_admin): ?>
                <a href="compte_user.php">Compte User</a>
            <?php endif; ?>
        </div>
        <div>
            <a href="profil.php" style="float: right;">Profil</a>
            <a href="index.php" style="float: right;">Déconnexion</a>
        </div>
    </header>

    <!-- Rectangle déroulant avec les informations -->
    <div id="info-box">
        <h2>Informations sur l'utilisation du site</h2>
        <p>Bienvenue sur notre plateforme dédiée à la centralisation des annales universitaires et à l'amélioration de vos révisions grâce à des QCM. Notre objectif est de vous offrir un système de recherche optimisé pour que vous puissiez accéder plus facilement aux contenus dont vous avez besoin.</p>
        <p>Voici quelques informations pour vous aider à tirer le meilleur parti de notre site :</p>
        <ul>
            <li>Utilisez les filtres pour affiner vos recherches et trouver rapidement les QCM qui vous intéressent.</li>
            <li>En cliquant sur "Voir la question", vous pouvez consulter la question et y répondre directement.</li>
            <li>Vos réponses sont automatiquement enregistrées et vous pourrez consulter vos résultats dans la section "Dernière note" sur votre carte QCM.</li>
            <li>Le site propose une fonctionnalité d’ancrage : vous pouvez retenter des QI pour lesquels vous avez obtenu une note inférieure à 20/20.</li>
            <li>Vous avez également la possibilité de créer vos propres QI. Une fois créés, ils seront visibles pour tous les utilisateurs du site. Pour cela, il vous suffit de cliquer sur le bouton "Ajouter un QCM" ci-dessus. (Actuellement, cette fonctionnalité est disponible pour les QI, mais nous travaillons à l’étendre aux DPs et KFPs.)</li>
            <li>Les boutons "Annales EDN" et "Annales universitaires" sont temporairement indisponibles. Nous faisons tout notre possible pour que vous puissiez bientôt réaliser des sessions complètes d'examens.</li>
            <li>Nous avons également le plaisir de vous annoncer l'introduction d'une nouvelle fonctionnalité, actuellement en version d'essai, qui permet aux utilisateurs de commenter les QCM. Pour l'instant, cette option est exclusivement disponible pour les QRM. Si cette fonctionnalité rencontre un succès satisfaisant, nous prévoyons de l'étendre prochainement à d'autres types de QCM. Nous espérons qu'elle favorisera les échanges et l'entraide au sein de notre communauté</li>
        </ul>
        <p>Nous espérons que cette plateforme vous aidera à exceller dans vos études. N'hésitez pas à nous faire part de vos impressions en cliquant sur le bouton "Donnez-nous votre avis". Vos retours sont essentiels pour que nous puissions améliorer continuellement le service que nous vous offrons.</p>
        <p>Le site est actuellement gratuit, mais il pourrait devenir payant à l'avenir. Si cela se produit, le tarif restera accessible et servira principalement à financer le développement continu de la plateforme. Nous vous informerons à l’avance de tout changement.</p>
    </div>

    <!-- Bouton pour afficher/masquer les informations -->
    <button id="toggle-info">Afficher/Masquer les informations</button>

    <h1>Rechercher un QCM à l'aide des filtres ci-dessous</h1>

    <form method="POST" action="">
        <div>
            <label for="specialite">Choisissez une spécialité :</label>
            <select id="specialite" name="specialite">
                <option value="">Choisissez une spécialité</option>
                <option value="cardiologie">Cardiologie</option>
                <option value="rhumatologie">Rhumatologie</option>
                <option value="Orthopédie-traumatologie">Orthopédie-traumatologie</option>
                <option value="neurologie">Neurologie</option>
                <option value="nephrologie">Néphrologie</option>
                <option value="gynecologie">Gynécologie</option>
                <option value="hematologie">Hématologie</option>
                <option value="pneumologie">Pneumologie</option>
            </select>
        </div>

        <div>
            <label for="item">Choisissez un item :</label>
            <select id="item" name="item">
                <option value="">Choisissez un item</option>
                <option value="94">94: Rachialgies</option>
                <option value="95">95: Radiculalgie et syndrome canalaire</option>
                <option value="128">128: Ostéopathies fragilisantes</option>
                <option value="129">129: Arthrose</option>
                <option value="152">152: Endocardite infectieuse</option>
                <option value="153">153: Surveillance des porteurs de valves et prothèses vasculaires</option>
                <option value="156">156: Infection ostéo-articulaire de l'enfant et de l'adulte (IOA)</option>
                <option value="195">195: Artérite à cellules géantes</option>
                <option value="196">196: Polyarthrite rhumatoïde</option>
                <option value="197">197: Spondyloarthrite</option>
                <option value="198">198: Arthropathie microcristalline</option>
                <option value="203">203: Dyspnée aiguë et chronique</option>
                <option value="221">221: Athérome: Épidémiologie et physiopathologie</option>
                <option value="222">222: Facteurs de risque cardiovasculaire et prévention</option>
                <option value="223">223: Dyslipidémies</option>
                <option value="224">224: Hypertension artérielle de l'adulte et de l'enfant</option>
                <option value="225">225: Artériopathie de l'aorte, des artères viscérales et des membres inférieurs; Anévrismes</option>
                <option value="226">226: Thrombose veineuse profonde et embolie pulmonaire</option>
                <option value="230">230: Douleur thoracique aiguë</option>
                <option value="231">231: Électrocardiogramme: Indications et interprétations</option>
                <option value="232">232: Fibrillation atriale</option>
                <option value="233">233: Valvulopathies</option>
                <option value="234">234: Insuffisance cardiaque de l'adulte</option>
                <option value="235">235: Péricardite aiguë</option>
                <option value="236">236: Trouble de la conduction intracardiaque</option>
                <option value="237">237: Palpitations</option>
                <option value="256">256: Aptitude au sport chez l'adulte et l'enfant</option>
                <option value="268">268: Hypercalcémie</option>
                <option value="307">307: Tumeurs des os primitives et secondaires</option>
                <option value="330">330: Prescription et surveillance des classes de médicaments les plus courants chez l'adulte et chez l'enfant</option>
                <option value="339">339: Syndromes coronariens aigus</option>
                <option value="342">342: Malaises, perte de connaissance, crise comitale chez l'adulte</option>
                <option value="349">349: Infection aiguë des parties molles (abcès, panaris, phlegmon des gaines)</option>
                <option value="361">361: Lésions péri-articulaires et ligamentaires</option>
                <option value="362">362: Prothèses et ostéosynthèses</option>
                <option value="363">363: Fractures de l'extrémité proximale du fémur et extrémité inférieure du radius</option>
                <option value="365">365: Surveillance d'un malade sous plâtre/Résine</option>
            </select>
        </div>

        <div>
            <label for="ville">Choisissez une ville :</label>
            <select id="ville" name="ville">
                <option value="">--Sélectionnez une ville (facultatif)--</option>
                <option value="Lille">Lille</option>
                <option value="Clermont-Auvergne">Clermont-Auvergne</option>
                <option value="Paris">Paris</option>
                <option value="Marseille">Marseille</option>
                <option value="Lyon">Lyon</option>
            </select>
        </div>

        <div>
            <label for="type_qcm">Choisissez un type de QCM :</label>
            <select id="type_qcm" name="type_qcm">
                <option value="">--Sélectionnez un type (facultatif)--</option>
                <option value="QRM">QRM</option>
                <option value="QRU">QRU</option>
                <option value="QRP">QRP</option>
                <option value="DP">DP</option>
                <option value="KFP">KFP</option>
                <option value="QROC">QROC</option>
                <option value="QP">QP</option>
                <option value="TCS">TCS</option>
            </select>
        </div>

        <div>
            <label for="date">Choisissez une date :</label>
            <select id="date" name="date">
                <option value="">--Sélectionnez une date (facultatif)--</option>
                <option value="2025">2024-2025</option>
                <option value="2024">2023-2024</option>
                <option value="2023">2022-2023</option>
                <option value="2022">2021-2022</option>
                <option value="2021">2020-2021</option>
                <option value="2020">2019-2020</option>
            </select>
        </div>

        <div>
            <label for="rang">Choisissez un rang :</label>
            <select id="rang" name="rang">
                <option value="">--Sélectionnez un rang (facultatif)--</option>
                <option value="A">A</option>
                <option value="B">B</option>
            </select>
        </div>

        <div>
            <label for="origine">Choisissez l'origine :</label>
            <select id="origine" name="origine">
                <option value="">--Sélectionnez une origine (facultatif)--</option>
                <option value="etu">Étudiant</option>
                <option value="EDN">EDN</option>
                <option value="FAQ">Fac</option>
            </select>
        </div>

        <div>
            <label for="session">Choisissez une session :</label>
            <select id="session" name="session">
                <option value="">--Sélectionnez une session (facultatif)--</option>
                <option value="1">Session 1</option>
                <option value="2">Session 2</option>
            </select>
        </div>

        <div>
            <input type="submit" value="Valider">
        </div>
    </form>

    <div id="resultat">
        <?php
        // Vérifier si des résultats ont été trouvés
        if ($resultats && is_array($resultats)) {
            echo "<h2>Résultats :</h2>";
            echo "<div class='result-grid'>";

            // Numéroter les résultats
            $count = 1;

            // Afficher les questions disponibles et un bouton pour chaque question
            foreach ($resultats as $row) {
                // Déterminer la classe CSS en fonction du rang
                $rang = $row['rang'] ?? '';
                $rangClass = '';

                if ($rang == 'A,B') {
                    $rangClass = 'rang-AB';
                } elseif ($rang == 'A') {
                    $rangClass = 'rang-A';
                } elseif ($rang == 'B') {
                    $rangClass = 'rang-B';
                }

                echo "<div>";
                if ($rangClass) {
                    echo "<div class='$rangClass'>" . htmlspecialchars($rang) . "</div>";
                }
                // Utiliser type_qcm_display pour afficher le type de QCM en haut à droite
                $type_qcm_display = htmlspecialchars($row['type_qcm_display'] ?? $row['type_qcm'] ?? '');
                if ($type_qcm_display == 'QM') {
                    $type_qcm_display = 'QROC';
                }
                echo "<div class='type-qcm'>" . $type_qcm_display . "</div>";
                echo "<div class='specialite'>" . ucfirst(htmlspecialchars($row['specialite'] ?? '')) . "</div>";

                // Afficher le titre de l'item
                $item_numbers = explode(',', $row['item'] ?? '');
                foreach ($item_numbers as $item_number) {
                    $item_title = $item_titles[trim($item_number)] ?? 'Inconnu';
                    echo "<div class='item'>Item $item_number: $item_title</div>";
                }

                // Afficher la ville, la date, la session et l'origine en ligne sans encadré
                echo "<span class='ville'>" . ucfirst(htmlspecialchars($row['ville'] ?? '')) . "</span> | ";
                echo "<span class='date'>" . htmlspecialchars($row['date_creation'] ?? $row['date'] ?? '') . "</span> | ";
                echo "<span class='session'>Session: " . htmlspecialchars($row['session'] ?? '') . "</span> | ";

                // Afficher l'origine avec des libellés conviviaux
                $origine = htmlspecialchars($row['origine'] ?? '');
                switch ($origine) {
                    case 'etu':
                        $origine_label = 'Étudiant';
                        break;
                    case 'EDN':
                        $origine_label = 'EDN';
                        break;
                    case 'FAQ':
                        $origine_label = 'Fac';
                        break;
                    default:
                        $origine_label = 'Inconnu';
                        break;
                }
                echo "<span class='origine'>" . $origine_label . "</span><br>";

                echo "<strong>Numéro: </strong>" . $count . "<br>";
                echo "<strong>Énoncé: </strong>" . substr(htmlspecialchars($row['enonce_accueil'] ?? $row['enonce'] ?? ''), 0, 200) . (strlen($row['enonce_accueil'] ?? $row['enonce'] ?? '') > 200 ? '...' : '') . "<br>";

                // Afficher la dernière date et la dernière note
                echo "<strong>Dernière date: </strong>" . htmlspecialchars($row['derniere_date'] ?? '') . "<br>";
                echo "<strong>Dernière note: </strong>" . htmlspecialchars($row['derniere_note'] ?? '') . "<br>";

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
                    case 'QROC':
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
                    case 'QRP':  // Nouveau cas pour le type QRP
                        $url = 'question_QRP.php';
                        break;
                    default:
                        $url = 'index.php';
                        break;
                }

                echo "<form method='GET' action='$url' class='button-form'>";
                echo "<input type='hidden' name='id' value='$id'>";
                echo "<input type='hidden' name='pseudo' value='$pseudo'>"; // Transmettre le pseudo de l'utilisateur
                echo "<input type='submit' value='Voir la question'>";
                echo "</form>";

                echo "</div>";
                $count++;
            }

            echo "</div>";
        } elseif ($resultats && is_string($resultats)) {
            // Afficher le message d'erreur si applicable
            echo "<p>$resultats</p>";
        } elseif ($_SERVER['REQUEST_METHOD'] == 'POST') {
            // Afficher un message si aucune question n'est trouvée
            echo "<p>Aucun résultat trouvé pour les critères sélectionnés.</p>";
        }
        ?>
    </div>

    <script>
        // Fonction pour afficher/masquer le rectangle déroulant
        document.getElementById('toggle-info').addEventListener('click', function() {
            const infoBox = document.getElementById('info-box');
            if (infoBox.style.maxHeight) {
                infoBox.style.maxHeight = null;
            } else {
                infoBox.style.maxHeight = infoBox.scrollHeight + "px";
            }
        });
    </script>
</body>

</html>