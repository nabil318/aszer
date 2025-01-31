<?php
// Démarrer la session pour stocker les réponses de l'utilisateur
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
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données : " . htmlspecialchars($e->getMessage()));
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

// Mettre à jour le rôle de l'utilisateur
$new_role = 'admin'; // Remplacez par le rôle que vous souhaitez attribuer
$update_sql = "UPDATE users SET role = ? WHERE utilisateur_id = ?";
$update_stmt = $db->prepare($update_sql);
$update_stmt->execute([$new_role, $user['utilisateur_id']]);

// Récupérer les derniers QCM complétés par l'utilisateur
$sql_qcm = "SELECT q.id_QI, q.enonce, r.reponse_utilisateur, r.note, r.date_reponse
            FROM reponses r
            JOIN QI q ON r.id_question = q.id_QI
            WHERE r.utilisateur_id = ?
            ORDER BY r.date_reponse DESC
            LIMIT 5";
$stmt_qcm = $db->prepare($sql_qcm);
$stmt_qcm->execute([$user['utilisateur_id']]);
$qcm_completes = $stmt_qcm->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Utilisateur</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f9;
            margin: 0;
            padding: 0;
        }

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

        .content {
            padding: 20px;
        }

        .profile-info {
            margin-bottom: 20px;
        }

        .profile-info h2 {
            margin-bottom: 10px;
        }

        .profile-info p {
            margin: 5px 0;
        }

        .qcm-list {
            margin-top: 20px;
        }

        .qcm-list h3 {
            margin-bottom: 10px;
        }

        .qcm-list ul {
            list-style-type: none;
            padding: 0;
        }

        .qcm-list li {
            margin-bottom: 10px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: #fff;
        }

        .qcm-list li h4 {
            margin-top: 0;
        }

        .qcm-list li p {
            margin: 5px 0;
        }
    </style>
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

    <div class="content">
        <div class="profile-info">
            <h2>Informations du Profil</h2>
            <p><strong>Nom:</strong> <?php echo htmlspecialchars($user['nom'] ?? 'N/A'); ?></p>
            <p><strong>Prénom:</strong> <?php echo htmlspecialchars($user['prenom'] ?? 'N/A'); ?></p>
            <p><strong>Sexe:</strong> <?php echo htmlspecialchars($user['sexe'] ?? 'N/A'); ?></p>
            <p><strong>Mail Universitaire:</strong> <?php echo htmlspecialchars($user['mail_universitaire'] ?? 'N/A'); ?></p>
            <p><strong>Pseudo:</strong> <?php echo htmlspecialchars($user['pseudo'] ?? 'N/A'); ?></p>
            <p><strong>Faculté:</strong> <?php echo htmlspecialchars($user['faculte'] ?? 'N/A'); ?></p>
            <p><strong>Année de Médecine:</strong> <?php echo htmlspecialchars($user['annee_medecine'] ?? 'N/A'); ?></p>
            <p><strong>Rôle:</strong> <?php echo htmlspecialchars($user['role'] ?? 'N/A'); ?></p>
        </div>

        <div class="qcm-list">
            <h3>Derniers QCM Complétés</h3>
            <ul>
                <?php if (empty($qcm_completes)): ?>
                    <li>Aucun QCM complété.</li>
                <?php else: ?>
                    <?php foreach ($qcm_completes as $qcm): ?>
                        <li>
                            <h4><?php echo htmlspecialchars($qcm['enonce']); ?></h4>
                            <p><strong>Réponse:</strong> <?php echo htmlspecialchars($qcm['reponse_utilisateur']); ?></p>
                            <p><strong>Note:</strong> <?php echo htmlspecialchars($qcm['note']); ?></p>
                            <p><strong>Date:</strong> <?php echo htmlspecialchars($qcm['date_reponse']); ?></p>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</body>

</html>