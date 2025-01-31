const express = require("express");
const mysql = require("mysql");
const bcrypt = require("bcrypt");
const bodyParser = require("body-parser");

const app = express();
app.use(bodyParser.json());
app.use(bodyParser.urlencoded({ extended: true }));

// Connexion à la base de données
const db = mysql.createConnection({
  host: "localhost",
  user: "root",
  password: "",
  database: "afm_db",
});

db.connect((err) => {
  if (err) throw err;
  console.log("Connecté à la base de données.");
});

// Route pour l'inscription
app.post("/inscription", async (req, res) => {
  const {
    nom,
    prenom,
    sexe,
    mail_universitaire,
    pseudo,
    faculté,
    annee_medecine,
    mot_de_passe,
  } = req.body;
  const hash = await bcrypt.hash(mot_de_passe, 10);

  const query = `INSERT INTO users (nom, prenom, sexe, mail_universitaire, pseudo, faculté, annee_medecine, mot_de_passe)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)`;

  db.query(
    query,
    [
      nom,
      prenom,
      sexe,
      mail_universitaire,
      pseudo,
      faculté,
      annee_medecine,
      hash,
    ],
    (err) => {
      if (err) return res.status(500).send("Erreur lors de l'inscription.");
      res.status(200).send("Inscription réussie.");
    }
  );
});

// Route pour la connexion
app.post("/connexion", (req, res) => {
  const { pseudo, mot_de_passe } = req.body;

  const query = `SELECT * FROM users WHERE pseudo = ?`;
  db.query(query, [pseudo], async (err, results) => {
    if (err) return res.status(500).send("Erreur lors de la connexion.");
    if (results.length === 0)
      return res.status(404).send("Utilisateur non trouvé.");

    const isMatch = await bcrypt.compare(mot_de_passe, results[0].mot_de_passe);
    if (!isMatch) return res.status(401).send("Mot de passe incorrect.");

    res.status(200).send("Connexion réussie.");
  });
});

// Route pour afficher les utilisateurs
app.get("/users", (req, res) => {
  const query = `SELECT * FROM users`;

  db.query(query, (err, results) => {
    if (err)
      return res
        .status(500)
        .send("Erreur lors de la récupération des utilisateurs.");
    res.json(results);
  });
});

// Démarrage du serveur
app.listen(3000, () => {
  console.log("Serveur démarré sur le port 3000.");
});
