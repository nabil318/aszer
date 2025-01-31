<?php
// Connexion à la base de données SQLite
try {
    $db = new PDO('sqlite:QCM.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Récupérer les DP de la base de données
$dpData = [];
try {
    $stmt = $db->query("SELECT * FROM DP_0");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $dpData[] = $row;
    }
} catch (PDOException $e) {
    die("Erreur de récupération des DP : " . $e->getMessage());
}

// Variables pour stocker les messages d'erreur
$errors = [];

// Ajout d'un nouveau DP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    $requiredFields = ['specialite', 'item', 'rang', 'enonce_accueil', 'ville', 'date', 'session'];
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            $errors[$field] = "Le champ {$field} est obligatoire.";
        }
    }

    if (empty($errors)) {
        $imagePath = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $imageTmpName = $_FILES['image']['tmp_name'];
            $imageName = basename($_FILES['image']['name']);
            $imagePath = 'uploads/' . $imageName;
            move_uploaded_file($imageTmpName, $imagePath);
        }

        $is_kfp = isset($_POST['is_kfp']) ? 1 : 0;
        $is_EDN = isset($_POST['is_EDN']) ? 1 : 0;
        $is_etu = isset($_POST['is_etu']) ? 1 : 0;
        $is_FAQ = isset($_POST['is_FAQ']) ? 1 : 0;
        $session = $_POST['session'];

        try {
            $stmt = $db->prepare("
                INSERT INTO DP_0 (specialite, item, rang, enonce_accueil, ville, image_url, date, session, origine, type_qcm, is_kfp, is_EDN, is_etu, is_FAQ)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                implode(', ', $_POST['specialite']),
                implode(', ', $_POST['item']),
                implode(', ', $_POST['rang']),
                $_POST['enonce_accueil'],
                $_POST['ville'],
                $imagePath,
                $_POST['date'],
                $session,
                $_POST['origine'] ?? '',
                $_POST['type_qcm'] ?? '',
                $is_kfp,
                $is_EDN,
                $is_etu,
                $is_FAQ
            ]);

            $id_DP = $db->lastInsertId();

            for ($i = 1; $i <= 7; $i++) {
                $type = $_POST["type_{$i}"] ?? '';
                $enonce = $_POST["enonce_{$i}"] ?? '';
                $props = [];
                $comments = [];
                for ($j = 1; $j <= 10; $j++) { // Assurez-vous d'aller jusqu'à 10
                    $props[] = $_POST["prop{$i}_{$j}"] ?? '';
                    $comments[] = $_POST["commentaire_prop{$i}_{$j}"] ?? '';
                }
                $reponse_correcte = $_POST["reponse_correcte_{$i}"] ?? '';
                $image_url = $_POST["image_url_{$i}"] ?? '';
                $zone_correcte = $_POST["zone_correcte_{$i}"] ?? '';
                $commentaire = $_POST["commentaire_{$i}"] ?? '';

                $stmt = $db->prepare("
                    INSERT INTO DP_{$i} (id_DP, type, enonce, prop1, prop2, prop3, prop4, prop5, prop6, prop7, prop8, prop9, prop10, reponse_correcte, image_url, zone_correcte, commentaire, commentaire_prop1_DP, commentaire_prop2_DP, commentaire_prop3_DP, commentaire_prop4_DP, commentaire_prop5_DP, commentaire_prop6_DP, commentaire_prop7_DP, commentaire_prop8_DP, commentaire_prop9_DP, commentaire_prop10_DP)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $id_DP,
                    $type,
                    $enonce,
                    ...$props,
                    $reponse_correcte,
                    $image_url,
                    $zone_correcte,
                    $commentaire,
                    ...$comments
                ]);
            }

            echo "DP ajouté avec succès.";
        } catch (PDOException $e) {
            echo "Erreur d'ajout du DP : " . $e->getMessage();
        }
    }
}

// Suppression d'un DP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id_DP = $_POST['id_DP'];

    try {
        // Supprimer les sections associées
        for ($i = 1; $i <= 7; $i++) {
            $stmt = $db->prepare("DELETE FROM DP_{$i} WHERE id_DP = ?");
            $stmt->execute([$id_DP]);
        }

        // Supprimer le DP principal
        $stmt = $db->prepare("DELETE FROM DP_0 WHERE id_DP = ?");
        $stmt->execute([$id_DP]);

        echo "DP supprimé avec succès.";
    } catch (PDOException $e) {
        echo "Erreur de suppression du DP : " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un DP</title>
    <style>
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

        .scrollable-div {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #ddd;
            padding: 10px;
            border-radius: 4px;
            background-color: #f9f9f9;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 10px;
        }

        .scrollable-div div {
            display: flex;
            align-items: center;
        }

        .scrollable-div label {
            margin-left: 5px;
        }

        .section {
            border: 1px solid #ddd;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
        }

        .section h3 {
            margin-top: 0;
        }

        .recap-table {
            margin-top: 20px;
        }

        .recap-table th,
        .recap-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        .recap-table th {
            background-color: #4CAF50;
            color: white;
        }

        .proposition-group {
            margin-bottom: 10px;
        }

        .error {
            color: red;
            font-size: 0.9em;
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

    <div id="formulaire">
        <h2>Ajouter un nouveau DP</h2>
        <form id="dpForm" method="POST" action="" enctype="multipart/form-data">
            <!-- Sélectionner la spécialité -->
            <label for="specialite">Spécialité :</label>
            <div class="scrollable-div">
                <div>
                    <input type="checkbox" id="specialite_cardiologie" name="specialite[]" value="cardiologie">
                    <label for="specialite_cardiologie">Cardiologie</label>
                </div>
                <div>
                    <input type="checkbox" id="specialite_nephrologie" name="specialite[]" value="nephrologie">
                    <label for="specialite_nephrologie">Néphrologie</label>
                </div>
                <div>
                    <input type="checkbox" id="specialite_pneumologie" name="specialite[]" value="pneumologie">
                    <label for="specialite_pneumologie">Pneumologie</label>
                </div>
                <div>
                    <input type="checkbox" id="specialite_gastroenterologie" name="specialite[]" value="gastroenterologie">
                    <label for="specialite_gastroenterologie">Gastroentérologie</label>
                </div>
                <!-- Ajoutez d'autres spécialités si nécessaire -->
            </div>
            <?php if (isset($errors['specialite'])): ?>
                <div class="error"><?php echo $errors['specialite']; ?></div>
            <?php endif; ?>

            <!-- Sélectionner l'item -->
            <label for="item">Item :</label>
            <div class="scrollable-div">
                <?php for ($i = 1; $i <= 340; $i++): ?>
                    <div>
                        <input type="checkbox" id="item_<?php echo $i; ?>" name="item[]" value="<?php echo $i; ?>">
                        <label for="item_<?php echo $i; ?>"><?php echo $i; ?></label>
                    </div>
                <?php endfor; ?>
            </div>
            <?php if (isset($errors['item'])): ?>
                <div class="error"><?php echo $errors['item']; ?></div>
            <?php endif; ?>

            <!-- Sélectionner le rang -->
            <label for="rang">Rang :</label>
            <div class="scrollable-div">
                <div>
                    <input type="checkbox" id="rang_A" name="rang[]" value="A">
                    <label for="rang_A">A</label>
                </div>
                <div>
                    <input type="checkbox" id="rang_B" name="rang[]" value="B">
                    <label for="rang_B">B</label>
                </div>
                <!-- Ajoutez d'autres rangs si nécessaire -->
            </div>
            <?php if (isset($errors['rang'])): ?>
                <div class="error"><?php echo $errors['rang']; ?></div>
            <?php endif; ?>

            <!-- Énoncé d'accueil -->
            <label for="enonce_accueil">Énoncé d'accueil :</label>
            <textarea id="enonce_accueil" name="enonce_accueil" required></textarea>
            <?php if (isset($errors['enonce_accueil'])): ?>
                <div class="error"><?php echo $errors['enonce_accueil']; ?></div>
            <?php endif; ?>

            <!-- Ville -->
            <label for="ville">Ville :</label>
            <select id="ville" name="ville" required>
                <option value="Paris">Paris</option>
                <option value="Marseille">Marseille</option>
                <option value="Lyon">Lyon</option>
                <option value="Toulouse">Toulouse</option>
                <option value="Nice">Nice</option>
            </select>
            <?php if (isset($errors['ville'])): ?>
                <div class="error"><?php echo $errors['ville']; ?></div>
            <?php endif; ?>

            <!-- Image -->
            <label for="image">Image (facultative) :</label>
            <input type="file" id="image" name="image">

            <!-- Date -->
            <label for="date">Date :</label>
            <select id="date" name="date" required>
                <option value="">Choisir une année</option>
                <option value="2023">2023</option>
                <option value="2024">2024</option>
                <option value="2025">2025</option>
                <!-- Ajoutez d'autres années si nécessaire -->
            </select>
            <?php if (isset($errors['date'])): ?>
                <div class="error"><?php echo $errors['date']; ?></div>
            <?php endif; ?>

            <!-- Session -->
            <label for="session">Session :</label>
            <select id="session" name="session" required>
                <option value="">Choisir une session</option>
                <option value="1">Session 1</option>
                <option value="2">Session 2</option>
            </select>
            <?php if (isset($errors['session'])): ?>
                <div class="error"><?php echo $errors['session']; ?></div>
            <?php endif; ?>

            <!-- is_kfp -->
            <label for="is_kfp">is_kfp :</label>
            <input type="checkbox" id="is_kfp" name="is_kfp" value="1">

            <!-- is_EDN -->
            <label for="is_EDN">is_EDN :</label>
            <input type="checkbox" id="is_EDN" name="is_EDN" value="1">

            <!-- is_etu -->
            <label for="is_etu">is_etu :</label>
            <input type="checkbox" id="is_etu" name="is_etu" value="1">

            <!-- is_FAQ -->
            <label for="is_FAQ">is_FAQ :</label>
            <input type="checkbox" id="is_FAQ" name="is_FAQ" value="1">

            <!-- Sections -->
            <div id="sections">
                <!-- Section 1 -->
                <div class="section">
                    <h3>Section 1</h3>
                    <label for="type_1">Type de QCM :</label>
                    <select id="type_1" name="type_1">
                        <option value="QRM">QRM</option>
                        <option value="QRU">QRU</option>
                        <option value="QM">QM</option>
                        <option value="KFP">KFP</option>
                    </select>

                    <label for="enonce_1">Énoncé :</label>
                    <textarea id="enonce_1" name="enonce_1"></textarea>

                    <div class="proposition-group">
                        <label for="prop1_1">Proposition 1 :</label>
                        <input type="text" id="prop1_1" name="prop1_1">

                        <label for="commentaire_prop1_1">Commentaire Proposition 1 :</label>
                        <input type="text" id="commentaire_prop1_1" name="commentaire_prop1_1">
                    </div>

                    <div class="proposition-group">
                        <label for="prop1_2">Proposition 2 :</label>
                        <input type="text" id="prop1_2" name="prop1_2">

                        <label for="commentaire_prop1_2">Commentaire Proposition 2 :</label>
                        <input type="text" id="commentaire_prop1_2" name="commentaire_prop1_2">
                    </div>

                    <button type="button" class="add-proposition-btn">Ajouter une proposition</button>

                    <label for="reponse_correcte_1">Réponse correcte :</label>
                    <input type="text" id="reponse_correcte_1" name="reponse_correcte_1">

                    <label for="image_url_1">Image URL :</label>
                    <input type="text" id="image_url_1" name="image_url_1">

                    <label for="zone_correcte_1">Zone correcte :</label>
                    <input type="text" id="zone_correcte_1" name="zone_correcte_1">

                    <label for="commentaire_1">Commentaire :</label>
                    <textarea id="commentaire_1" name="commentaire_1"></textarea>
                </div>

                <!-- Section 2 -->
                <div class="section">
                    <h3>Section 2</h3>
                    <label for="type_2">Type de QCM :</label>
                    <select id="type_2" name="type_2">
                        <option value="QRM">QRM</option>
                        <option value="QRU">QRU</option>
                        <option value="QM">QM</option>
                        <option value="KFP">KFP</option>
                    </select>

                    <label for="enonce_2">Énoncé :</label>
                    <textarea id="enonce_2" name="enonce_2"></textarea>

                    <div class="proposition-group">
                        <label for="prop2_1">Proposition 1 :</label>
                        <input type="text" id="prop2_1" name="prop2_1">

                        <label for="commentaire_prop2_1">Commentaire Proposition 1 :</label>
                        <input type="text" id="commentaire_prop2_1" name="commentaire_prop2_1">
                    </div>

                    <div class="proposition-group">
                        <label for="prop2_2">Proposition 2 :</label>
                        <input type="text" id="prop2_2" name="prop2_2">

                        <label for="commentaire_prop2_2">Commentaire Proposition 2 :</label>
                        <input type="text" id="commentaire_prop2_2" name="commentaire_prop2_2">
                    </div>

                    <button type="button" class="add-proposition-btn">Ajouter une proposition</button>

                    <label for="reponse_correcte_2">Réponse correcte :</label>
                    <input type="text" id="reponse_correcte_2" name="reponse_correcte_2">

                    <label for="image_url_2">Image URL :</label>
                    <input type="text" id="image_url_2" name="image_url_2">

                    <label for="zone_correcte_2">Zone correcte :</label>
                    <input type="text" id="zone_correcte_2" name="zone_correcte_2">

                    <label for="commentaire_2">Commentaire :</label>
                    <textarea id="commentaire_2" name="commentaire_2"></textarea>
                </div>
            </div>

            <!-- Bouton pour ajouter une nouvelle section -->
            <button type="button" id="addSectionBtn">Ajouter une section</button>

            <button type="submit">Soumettre le DP</button>
        </form>

        <!-- Tableau récapitulatif -->
        <table class="recap-table">
            <thead>
                <tr>
                    <th>ID DP</th>
                    <th>Énoncé d'accueil</th>
                    <th>is_KFP</th>
                    <th>is_FAC</th>
                    <th>is_EDN</th>
                    <th>is_etu</th>
                    <th>Session</th>
                    <?php for ($i = 1; $i <= 7; $i++): ?>
                        <th>Énoncé question <?php echo $i; ?></th>
                        <th>Propositions <?php echo $i; ?></th>
                        <th>Réponse correcte <?php echo $i; ?></th>
                        <th>Correction <?php echo $i; ?></th>
                    <?php endfor; ?>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="recapTableBody">
                <?php foreach ($dpData as $dp): ?>
                    <tr>
                        <td><?php echo $dp['id_DP']; ?></td>
                        <td><?php echo $dp['enonce_accueil']; ?></td>
                        <td><?php echo $dp['is_kfp'] ? '1' : '0'; ?></td>
                        <td><?php echo $dp['is_FAQ'] ? '1' : '0'; ?></td>
                        <td><?php echo $dp['is_EDN'] ? '1' : '0'; ?></td>
                        <td><?php echo $dp['is_etu'] ? '1' : '0'; ?></td>
                        <td><?php echo $dp['session']; ?></td>
                        <?php for ($i = 1; $i <= 7; $i++): ?>
                            <?php
                            $stmt = $db->prepare("SELECT * FROM DP_{$i} WHERE id_DP = ?");
                            $stmt->execute([$dp['id_DP']]);
                            $section = $stmt->fetch(PDO::FETCH_ASSOC);
                            if ($section) {
                                echo "<td>{$section['enonce']}</td>";
                                echo "<td>" . implode(', ', array_filter([$section['prop1'], $section['prop2'], $section['prop3'], $section['prop4'], $section['prop5'], $section['prop6'], $section['prop7'], $section['prop8'], $section['prop9'], $section['prop10']])) . "</td>";
                                echo "<td>{$section['reponse_correcte']}</td>";
                                echo "<td>{$section['commentaire']}</td>";
                            } else {
                                echo "<td colspan='4'>Aucune donnée</td>";
                            }
                            ?>
                        <?php endfor; ?>
                        <td>
                            <form method="POST" action="" style="display:inline;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id_DP" value="<?php echo $dp['id_DP']; ?>">
                                <button type="submit">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const addSectionBtn = document.getElementById('addSectionBtn');
            const sectionsContainer = document.getElementById('sections');
            let sectionCount = 2;

            addSectionBtn.addEventListener('click', function() {
                sectionCount++;
                const newSection = document.createElement('div');
                newSection.className = 'section';
                newSection.innerHTML = `
                    <h3>Section ${sectionCount}</h3>
                    <label for="type_${sectionCount}">Type de QCM :</label>
                    <select id="type_${sectionCount}" name="type_${sectionCount}">
                        <option value="QRM">QRM</option>
                        <option value="QRU">QRU</option>
                        <option value="QM">QM</option>
                        <option value="KFP">KFP</option>
                    </select>

                    <label for="enonce_${sectionCount}">Énoncé :</label>
                    <textarea id="enonce_${sectionCount}" name="enonce_${sectionCount}"></textarea>

                    <div class="proposition-group">
                        <label for="prop${sectionCount}_1">Proposition 1 :</label>
                        <input type="text" id="prop${sectionCount}_1" name="prop${sectionCount}_1">

                        <label for="commentaire_prop${sectionCount}_1">Commentaire Proposition 1 :</label>
                        <input type="text" id="commentaire_prop${sectionCount}_1" name="commentaire_prop${sectionCount}_1">
                    </div>

                    <div class="proposition-group">
                        <label for="prop${sectionCount}_2">Proposition 2 :</label>
                        <input type="text" id="prop${sectionCount}_2" name="prop${sectionCount}_2">

                        <label for="commentaire_prop${sectionCount}_2">Commentaire Proposition 2 :</label>
                        <input type="text" id="commentaire_prop${sectionCount}_2" name="commentaire_prop${sectionCount}_2">
                    </div>

                    <button type="button" class="add-proposition-btn">Ajouter une proposition</button>

                    <label for="reponse_correcte_${sectionCount}">Réponse correcte :</label>
                    <input type="text" id="reponse_correcte_${sectionCount}" name="reponse_correcte_${sectionCount}">

                    <label for="image_url_${sectionCount}">Image URL :</label>
                    <input type="text" id="image_url_${sectionCount}" name="image_url_${sectionCount}">

                    <label for="zone_correcte_${sectionCount}">Zone correcte :</label>
                    <input type="text" id="zone_correcte_${sectionCount}" name="zone_correcte_${sectionCount}">

                    <label for="commentaire_${sectionCount}">Commentaire :</label>
                    <textarea id="commentaire_${sectionCount}" name="commentaire_${sectionCount}"></textarea>
                `;
                sectionsContainer.appendChild(newSection);

                // Ajouter l'écouteur d'événement pour le bouton "Ajouter une proposition"
                newSection.querySelector('.add-proposition-btn').addEventListener('click', function() {
                    addProposition(newSection, sectionCount);
                });
            });

            // Fonction pour ajouter une proposition
            function addProposition(section, sectionCount) {
                const propositionCount = section.querySelectorAll('.proposition-group').length + 1;
                const newPropositionGroup = document.createElement('div');
                newPropositionGroup.className = 'proposition-group';
                newPropositionGroup.innerHTML = `
                    <label for="prop${sectionCount}_${propositionCount}">Proposition ${propositionCount} :</label>
                    <input type="text" id="prop${sectionCount}_${propositionCount}" name="prop${sectionCount}_${propositionCount}">

                    <label for="commentaire_prop${sectionCount}_${propositionCount}">Commentaire Proposition ${propositionCount} :</label>
                    <input type="text" id="commentaire_prop${sectionCount}_${propositionCount}" name="commentaire_prop${sectionCount}_${propositionCount}">
                `;
                section.insertBefore(newPropositionGroup, section.querySelector('.add-proposition-btn'));
            }

            // Ajouter l'écouteur d'événement pour les boutons "Ajouter une proposition" existants
            document.querySelectorAll('.add-proposition-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const section = button.closest('.section');
                    const sectionCount = parseInt(section.querySelector('h3').textContent.split(' ')[1]);
                    addProposition(section, sectionCount);
                });
            });
        });
    </script>
</body>

</html>