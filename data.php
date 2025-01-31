<!-- Data.php pas encore dispo: j'ai juste changer la ref du bouton -->

<?php
// Connexion à la base de données SQLite
try {
    $db = new PDO('sqlite:QCM.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id']) && $_POST['action'] === 'supprimer') {
    $id = (int) $_POST['id'];

    try {
        // Vérifier si l'ID existe dans la table
        $stmtCheck = $db->prepare("SELECT COUNT(*) FROM QI WHERE id_QI = :id");
        $stmtCheck->bindParam(':id', $id, PDO::PARAM_INT);
        $stmtCheck->execute();
        $exists = $stmtCheck->fetchColumn();

        if ($exists) {
            $stmt = $db->prepare("DELETE FROM QI WHERE id_QI = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                echo "Question supprimée avec succès.";
            } else {
                echo "Aucune question trouvée pour cet ID.";
            }
        } else {
            echo "La question avec cet ID n'existe pas.";
        }
    } catch (PDOException $e) {
        echo "Erreur de suppression : " . $e->getMessage();
    }
}

// Ajout d'une nouvelle question
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    $requiredFields = ['specialite', 'item', 'rang', 'enonce', 'type_qcm', 'reponse_correcte', 'date', 'session'];
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            die("Veuillez remplir tous les champs obligatoires.");
        }
    }

    $props = [];
    $comments = [];
    for ($i = 1; $i <= 10; $i++) {
        $props[] = $_POST["prop$i"] ?? '';
        $comments[] = $_POST["commentaire_prop$i"] ?? '';
    }

    $imagePath = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $imageTmpName = $_FILES['image']['tmp_name'];
        $imageName = basename($_FILES['image']['name']);
        $imagePath = 'uploads/' . $imageName;
        move_uploaded_file($imageTmpName, $imagePath);
    }

    $is_EDN = 0; // Définir is_EDN à 0
    $is_etu = 1; // Définir is_etu à 1
    $is_FAQ = 0; // Définir is_FAQ à 0
    $date = $_POST['date'];
    $session = $_POST['session'];

    try {
        $stmt = $db->prepare("
    INSERT INTO QI (specialite, item, rang, enonce, prop1, prop2, prop3, prop4, prop5, prop6, prop7, prop8, prop9, prop10, ville, type_qcm, reponse_correcte, correction, image_url, zone_correcte, commentaire_prop1, commentaire_prop2, commentaire_prop3, commentaire_prop4, commentaire_prop5, commentaire_prop6, commentaire_prop7, commentaire_prop8, commentaire_prop9, commentaire_prop10, date, session, is_EDN, is_etu, is_FAQ)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");
        $stmt->execute([
            $_POST['specialite'],
            $_POST['item'],
            $_POST['rang'],
            $_POST['enonce'],
            ...$props,
            $_POST['ville'],
            $_POST['type_qcm'],
            $_POST['reponse_correcte'],
            $_POST['correction'] ?? '',
            $imagePath,
            $_POST['coordonnees'] ?? '',
            ...$comments,
            $date,
            $session,
            $is_EDN,
            $is_etu,
            $is_FAQ
        ]);

        echo "Question ajoutée avec succès.";
    } catch (PDOException $e) {
        echo "Erreur d'ajout de la question : " . $e->getMessage();
    }
}

// Récupération des questions
try {
    $stmt = $db->query("SELECT * FROM QI ORDER BY id_QI DESC");
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Erreur lors de la récupération des questions : " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter une Question QCM</title>
    <style>
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
            background-color: #f7f7f7;
            color: #333;
            margin: 0;
            padding: 20px;
        }

        #formulaire {
            background-color: #fff;
            padding: 20px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
            border-radius: 8px;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }

        h2 {
            text-align: center;
            color: #4CAF50;
        }

        form {
            display: grid;
            gap: 15px;
        }

        label {
            font-weight: bold;
            margin-bottom: 5px;
        }

        select,
        input[type="text"],
        textarea,
        input[type="file"] {
            width: 100%;
            padding: 8px;
            margin-bottom: 10px;
            box-sizing: border-box;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        textarea {
            height: 120px;
            resize: vertical;
        }

        .button-add {
            text-align: center;
        }

        button {
            padding: 10px 20px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        button:hover {
            background-color: #45a049;
        }

        .proposition {
            margin-bottom: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 40px;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        th,
        td {
            padding: 12px;
            text-align: center;
            border: 1px solid #ddd;
        }

        th {
            background-color: #4CAF50;
            color: white;
        }

        tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .actions form {
            display: inline;
        }

        #btn_DP {
            display: block;
            margin: 20px auto;
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            transition: background-color 0.3s;
        }

        #btn_DP:hover {
            background-color: #45a049;
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
    <a href="data.php" id="btn_DP">Ajouter un DP ou KFP (indisponible pour le moment)</a>

    <div id="formulaire">
        <h2>Ajouter un nouveau QI</h2>
        <form method="POST" action="" enctype="multipart/form-data">
            <!-- Sélectionner la spécialité -->
            <label for="specialite">Spécialité :</label>
            <select id="specialite" name="specialite" required>
                <option value="">Choisir une spécialité</option>
                <option value="cardiologie">Cardiologie</option>
                <option value="nephrologie">Néphrologie</option>
            </select>

            <!-- Sélectionner l'item -->
            <label for="item">Item :</label>
            <select id="item" name="item" required>
                <option value="">Choisir un item</option>
                <option value="1">1</option>
                <option value="2">2</option>
                <option value="3">3</option>
            </select>

            <!-- Sélectionner le rang -->
            <label for="rang">Rang :</label>
            <select id="rang" name="rang" required>
                <option value="">Choisir un rang</option>
                <option value="A">A</option>
                <option value="B">B</option>
            </select>

            <!-- Type de QCM -->
            <label for="type_qcm">Type de QCM :</label>
            <select id="type_qcm" name="type_qcm" required>
                <option value="QRM">QRM</option>
                <option value="QRU">QRU</option>
                <option value="QM">QROC</option>
                <option value="QM">QRP</option>
            </select>

            <!-- Énoncé de la question -->
            <label for="enonce">Énoncé :</label>
            <textarea id="enonce" name="enonce" required></textarea>

            <!-- Ajouter des propositions de réponses -->
            <div class="button-add">
                <button type="button" id="addPropositionBtn">Ajouter une proposition</button>
            </div>

            <div id="propositions">
                <!-- 3 premières propositions visibles par défaut -->
                <div class="proposition">
                    <label for="prop1">Proposition 1 ('Intolerable','indispensable') :</label>
                    <input type="text" id="prop1" name="prop1" required>
                    <label for="commentaire_prop1">Commentaire Proposition 1 :</label>
                    <input type="text" id="commentaire_prop1" name="commentaire_prop1">

                    <label for="prop2">Proposition 2 :</label>
                    <input type="text" id="prop2" name="prop2" required>
                    <label for="commentaire_prop2">Commentaire Proposition 2 :</label>
                    <input type="text" id="commentaire_prop2" name="commentaire_prop2">

                    <label for="prop3">Proposition 3 :</label>
                    <input type="text" id="prop3" name="prop3">
                    <label for="commentaire_prop3">Commentaire Proposition 3 :</label>
                    <input type="text" id="commentaire_prop3" name="commentaire_prop3">
                </div>
            </div>

            <!-- Ville -->
            <label for="ville">Ville :</label>
            <select id="ville" name="ville" required>
                <option value="Paris">Paris</option>
                <option value="Lille">Lille</option>
                <option value="Marseille">Marseille</option>
                <option value="Lyon">Lyon</option>
                <option value="Toulouse">Toulouse</option>
                <option value="Nice">Nice</option>
            </select>

            <!-- Réponse correcte -->
            <label for="reponse_correcte">Réponse correcte (ex: 1,3 ou "reponse1, reponse2") :</label>
            <input type="text" id="reponse_correcte" name="reponse_correcte" required>

            <!-- Correction -->
            <label for="correction">Correction :</label>
            <textarea id="correction" name="correction"></textarea>

            <!-- Image -->
            <label for="image">Image (facultative) :</label>
            <input type="file" id="image" name="image">

            <!-- Coordonnées -->
            <label for="coordonnees">Coordonnées :</label>
            <input type="text" id="coordonnees" name="coordonnees">

            <!-- Date -->
            <label for="date">Date :</label>
            <select id="date" name="date" required>
                <option value="">Choisir une année</option>
                <option value="2025">2024-2025</option>




                <!-- Ajoutez d'autres années si nécessaire -->
            </select>

            <!-- Session -->
            <label for="session">Session :</label>
            <select id="session" name="session" required>
                <option value="">Choisir une session</option>
                <option value="1">Session 1</option>

            </select>

            <!-- Champs cachés pour is_EDN, is_etu, et is_FAQ -->
            <input type="hidden" name="is_EDN" value="0">
            <input type="hidden" name="is_etu" value="1">
            <input type="hidden" name="is_FAQ" value="0">

            <button type="submit">Soumettre la question</button>
        </form>
    </div>

    <h2>Liste des Questions en Base</h2>
    <table>
        <thead>
            <tr>
                <th>ID_QI</th>
                <th>Spécialité</th>
                <th>Item</th>
                <th>Rang</th>
                <th>Énoncé</th>
                <th>Type de QCM</th>
                <th>Ville</th>
                <th>Propositions</th>
                <th>Commentaires des Propositions</th>
                <th>Réponse correcte</th>
                <th>Correction</th>
                <th>Session</th> <!-- Ajouter cette colonne -->
                <th>is_EDN</th>
                <th>is_etu</th> <!-- Ajouter cette colonne -->
                <th>is_FAQ</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($questions)): ?>
                <?php foreach ($questions as $question): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($question['id_QI']); ?></td>
                        <td><?php echo htmlspecialchars($question['specialite']); ?></td>
                        <td><?php echo htmlspecialchars($question['item']); ?></td>
                        <td><?php echo htmlspecialchars($question['rang']); ?></td>
                        <td><?php echo htmlspecialchars($question['enonce']); ?></td>
                        <td><?php echo htmlspecialchars($question['type_qcm']); ?></td>
                        <td><?php echo htmlspecialchars($question['ville']); ?></td>
                        <td>
                            <?php
                            for ($i = 1; $i <= 10; $i++) {
                                $prop = $question["prop$i"];
                                if (!empty($prop)) {
                                    echo htmlspecialchars($prop) . "<br>";
                                }
                            }
                            ?>
                        </td>
                        <td>
                            <?php
                            for ($i = 1; $i <= 10; $i++) {
                                $comment = $question["commentaire_prop$i"];
                                if (!empty($comment)) {
                                    echo htmlspecialchars($comment) . "<br>";
                                }
                            }
                            ?>
                        </td>
                        <td><?php echo htmlspecialchars($question['reponse_correcte'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($question['correction'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($question['session'] ?? ''); ?></td> <!-- Ajouter cette ligne -->
                        <td><?php echo htmlspecialchars($question['is_EDN'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($question['is_etu'] ?? ''); ?></td> <!-- Ajouter cette ligne -->
                        <td><?php echo htmlspecialchars($question['is_FAQ'] ?? ''); ?></td>
                        <td class="actions">
                            <form method="POST" action="modifier.php" style="display:inline;">
                                <input type="hidden" name="id" value="<?php echo htmlspecialchars($question['id_QI'] ?? ''); ?>">
                                <button type="submit">Modifier</button>
                            </form>
                            <form method="POST" action="" style="display:inline;">
                                <input type="hidden" name="action" value="supprimer">
                                <input type="hidden" name="id" value="<?php echo htmlspecialchars($question['id_QI'] ?? ''); ?>">
                                <button type="submit" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette question ?')">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="16">Aucune question n'a été trouvée dans la base de données.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <script>
        // Ajouter une nouvelle proposition de réponse
        let propositionCount = 3; // Commence à 3, car les 3 premières sont déjà affichées
        document.getElementById('addPropositionBtn').addEventListener('click', function() {
            if (propositionCount < 10) {
                propositionCount++;
                const propositionsDiv = document.getElementById('propositions');

                const newPropositionDiv = document.createElement('div');
                newPropositionDiv.classList.add('proposition');
                newPropositionDiv.innerHTML = `
                    <label for="prop${propositionCount}">Proposition ${propositionCount} :</label>
                    <input type="text" id="prop${propositionCount}" name="prop${propositionCount}">
                    <label for="commentaire_prop${propositionCount}">Commentaire Proposition ${propositionCount} :</label>
                    <input type="text" id="commentaire_prop${propositionCount}" name="commentaire_prop${propositionCount}">
                `;
                propositionsDiv.appendChild(newPropositionDiv);
            } else {
                alert('Vous ne pouvez ajouter que 10 propositions.');
            }
        });
    </script>
</body>

</html>