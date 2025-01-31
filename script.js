document.addEventListener("DOMContentLoaded", function () {
  let isDragging = false;
  let isResizing = false;
  let circleElement = document.getElementById("circle");
  let imageContainer = document.getElementById("image-container");
  let circleDataInput = document.getElementById("circle_data");
  let circleData = JSON.parse(circleDataInput.value);

  // Déplacer le cercle
  circleElement.addEventListener("mousedown", function (e) {
    isDragging = true;
    const offsetX = e.clientX - circleElement.offsetLeft;
    const offsetY = e.clientY - circleElement.offsetTop;

    document.addEventListener("mousemove", onMouseMove);
    document.addEventListener("mouseup", onMouseUp);

    function onMouseMove(e) {
      if (isDragging) {
        const newX = e.clientX - offsetX - imageContainer.offsetLeft;
        const newY = e.clientY - offsetY - imageContainer.offsetTop;

        // Limiter la position du cercle à l'intérieur de l'image
        circleElement.style.left = `${Math.max(
          0,
          Math.min(newX, imageContainer.offsetWidth - circleElement.offsetWidth)
        )}px`;
        circleElement.style.top = `${Math.max(
          0,
          Math.min(
            newY,
            imageContainer.offsetHeight - circleElement.offsetHeight
          )
        )}px`;

        // Mettre à jour les données du cercle
        circleData.x = newX;
        circleData.y = newY;
        updateCircleData();
      }
    }

    function onMouseUp() {
      isDragging = false;
      document.removeEventListener("mousemove", onMouseMove);
      document.removeEventListener("mouseup", onMouseUp);
    }
  });

  // Redimensionner le cercle à partir des handles
  let resizeHandles = document.querySelectorAll(".resize-handle");
  resizeHandles.forEach((handle) => {
    handle.addEventListener("mousedown", function (e) {
      isResizing = true;
      const initialSize = circleData.size;
      const initialMouseX = e.clientX;
      const initialMouseY = e.clientY;
      const handleClass = e.target.classList[1]; // Classe pour savoir quel coin est cliqué

      document.addEventListener("mousemove", onResize);
      document.addEventListener("mouseup", onResizeEnd);

      function onResize(e) {
        if (isResizing) {
          let newSize = initialSize;

          // Calcul de la taille en fonction du coin cliqué
          if (
            handleClass.includes("top-left") ||
            handleClass.includes("bottom-right")
          ) {
            newSize += Math.min(
              e.clientX - initialMouseX,
              e.clientY - initialMouseY
            );
          } else if (
            handleClass.includes("top-right") ||
            handleClass.includes("bottom-left")
          ) {
            newSize += Math.min(
              initialMouseX - e.clientX,
              initialMouseY - e.clientY
            );
          }

          // Limiter la taille minimale du cercle
          newSize = Math.max(30, newSize);
          circleElement.style.width = `${newSize}px`;
          circleElement.style.height = `${newSize}px`;

          // Mettre à jour la taille du cercle
          circleData.size = newSize;
          updateCircleData();
        }
      }

      function onResizeEnd() {
        isResizing = false;
        document.removeEventListener("mousemove", onResize);
        document.removeEventListener("mouseup", onResizeEnd);
      }
    });
  });

  // Mettre à jour les données du cercle dans le champ caché
  function updateCircleData() {
    circleDataInput.value = JSON.stringify(circleData);
  }

  // Prévisualisation de l'image téléchargée
  document.getElementById("image").addEventListener("change", function (event) {
    var imagePreview = document.getElementById("image-preview");
    var file = event.target.files[0];
    var reader = new FileReader();

    reader.onload = function (e) {
      imagePreview.src = e.target.result; // Afficher l'image sélectionnée
    };

    if (file) {
      reader.readAsDataURL(file); // Lire l'image
    }
  });

  // Gestionnaire d'événement pour la validation des questions
  document.querySelectorAll(".validate-btn").forEach((button) => {
    button.addEventListener("click", function () {
      const current = button.dataset.current;
      const next = button.dataset.next;
      const questionDiv = button.closest(".question-div");

      // Vérifier la réponse sélectionnée
      const selectedAnswer = questionDiv.querySelector(
        "input[name='answer']:checked"
      );
      const correctAnswer = button.getAttribute("data-correct");
      const commentaire = button.getAttribute("data-comment");
      const correctionDiv = questionDiv.querySelector(".correction");

      if (selectedAnswer) {
        if (selectedAnswer.value === correctAnswer) {
          correctionDiv.innerHTML = `<p style='color: green;'>Bonne réponse !</p><p>${commentaire}</p>`;
        } else {
          correctionDiv.innerHTML = `<p style='color: red;'>Mauvaise réponse. La bonne réponse est : ${correctAnswer}</p><p>${commentaire}</p>`;
        }
      } else {
        correctionDiv.innerHTML = `<p style='color: orange;'>Veuillez sélectionner une réponse.</p>`;
      }

      correctionDiv.style.display = "block"; // Afficher la correction

      // Désactiver le bouton "Valider"
      button.disabled = true;

      // Afficher la question suivante
      if (document.getElementById("question-" + next)) {
        document.getElementById("question-" + next).classList.remove("hidden");
      } else {
        document.getElementById("final-submit").style.display = "block";
      }
    });
  });

  // Gérer l'affichage de la première question au démarrage
  const questions = document.querySelectorAll(".question-div");
  questions.forEach((question, index) => {
    if (index > 0) {
      question.classList.add("hidden");
    }
  });
});
