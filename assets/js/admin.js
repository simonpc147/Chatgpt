function editKey(userId, apiKey, plan) {
  document.getElementById("edit-user-id").value = userId;
  document.getElementById("edit-api-key").value = apiKey || "";
  document.getElementById("edit-plan").value = plan || "free";
  document.getElementById("edit-key-modal").style.display = "block";
}

function closeModal() {
  document.getElementById("edit-key-modal").style.display = "none";
}

function validateApiKey(key) {
  return key.length >= 20;
}

document.addEventListener("DOMContentLoaded", function () {
  const modal = document.getElementById("edit-key-modal");
  const form = modal ? modal.querySelector("form") : null;

  if (modal) {
    window.onclick = function (event) {
      if (event.target === modal) {
        closeModal();
      }
    };
  }

  if (form) {
    form.addEventListener("submit", function (e) {
      const apiKey = document.getElementById("edit-api-key").value;

      if (!validateApiKey(apiKey)) {
        e.preventDefault();
        alert("La API Key debe tener al menos 20 caracteres");
        return false;
      }
    });
  }
});
