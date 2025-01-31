<?php
// Démarrer la session pour stocker les réponses de l'utilisateur
session_start();

// // Vérifier si l'utilisateur est connecté et s'il est un administrateur
// if (!isset($_SESSION['utilisateur_id'])) {
//     // L'utilisateur n'est pas connecté, redirection vers la page d'accueil
//     header("Location: accueil.php");
//     exit();
// }

// if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
//     // Si l'utilisateur n'est pas admin, redirection vers la page d'accueil
//     header("Location: accueil.php");
//     exit();
// }

// Connexion à la base de données SQLite
try {
    $db = new PDO('sqlite:QCM.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données : " . htmlspecialchars($e->getMessage()));
}

// Récupérer les informations des utilisateurs
$sql = "SELECT * FROM users";
$stmt = $db->prepare($sql);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Logique pour changer le rôle en admin
if (isset($_POST['change_role']) && isset($_POST['utilisateur_id'])) {
    $utilisateur_id = $_POST['utilisateur_id'];
    $sql = "UPDATE users SET role = 'admin' WHERE utilisateur_id = :utilisateur_id";
    $stmt = $db->prepare($sql);
    $stmt->execute(['utilisateur_id' => $utilisateur_id]);

    // Si l'utilisateur change son propre rôle en admin, mettez-le à jour dans la session
    if ($_SESSION['utilisateur_id'] == $utilisateur_id) {
        $_SESSION['role'] = 'admin';  // Met à jour le rôle dans la session pour l'utilisateur connecté
    }

    // Rediriger après la mise à jour pour éviter le double post
    header("Location: compte_user.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compte Utilisateur</title>
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

        .content {
            padding: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        table,
        th,
        td {
            border: 1px solid #ddd;
        }

        th,
        td {
            padding: 12px;
            text-align: left;
        }

        th {
            background-color: #333;
            color: white;
        }

        tr:nth-child(even) {
            background-color: #f2f2f2;
        }

        tr:hover {
            background-color: #ddd;
        }

        .btn {
            background-color: #4CAF50;
            color: white;
            padding: 5px 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .btn:hover {
            background-color: #45a049;
        }

        .btn-danger {
            background-color: #f44336;
        }

        .btn-danger:hover {
            background-color: #e53935;
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
        <h1>Informations des Utilisateurs</h1>
        <table>
            <tr>
                <th>ID</th>
                <th>Nom</th>
                <th>Prénom</th>
                <th>Sexe</th>
                <th>Mail Universitaire</th>
                <th>Pseudo</th>
                <th>Faculté</th>
                <th>Année de Médecine</th>
                <th>Rôle</th>
                <th>Actions</th>
            </tr>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?php echo htmlspecialchars($user['utilisateur_id']); ?></td>
                    <td><?php echo htmlspecialchars($user['nom']); ?></td>
                    <td><?php echo htmlspecialchars($user['prenom']); ?></td>
                    <td><?php echo htmlspecialchars($user['sexe']); ?></td>
                    <td><?php echo htmlspecialchars($user['mail_universitaire']); ?></td>
                    <td><?php echo htmlspecialchars($user['pseudo']); ?></td>
                    <td><?php echo htmlspecialchars($user['faculte']); ?></td>
                    <td><?php echo htmlspecialchars($user['annee_medecine']); ?></td>
                    <td><?php echo htmlspecialchars($user['role'] ?? 'Rôle non défini'); ?></td>
                    <td>
                        <?php if ($user['role'] !== 'admin'): ?>
                            <form action="compte_user.php" method="POST">
                                <input type="hidden" name="utilisateur_id" value="<?php echo $user['utilisateur_id']; ?>">
                                <button type="submit" name="change_role" class="btn">Ajouter en tant qu'admin</button>
                            </form>
                        <?php else: ?>
                            <button class="btn btn-danger" disabled>Déjà admin</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</body>

</html>