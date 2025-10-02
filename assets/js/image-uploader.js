(function ($) {
  "use strict";

  let selectedFiles = [];
  let currentConversationId = null;
  let currentProjectId = null;

  function initImageUploader() {
    const attachBtn = $("#attach-image-btn");
    const imageInput = $("#image-input");
    const previewContainer = $("#image-previews");

    attachBtn.on("click", function (e) {
      e.preventDefault();
      imageInput.click();
    });

    imageInput.on("change", function (e) {
      const files = Array.from(e.target.files);
      handleFileSelection(files);
      imageInput.val("");
    });

    $(document).on("click", ".remove-preview", function () {
      const index = $(this).data("index");
      removeFile(index);
    });
  }

  function handleFileSelection(files) {
    files.forEach((file) => {
      if (!validateFile(file)) {
        return;
      }

      selectedFiles.push(file);
      addPreview(file, selectedFiles.length - 1);
    });
  }

  function validateFile(file) {
    const allowedTypes = ["image/jpeg", "image/png", "image/webp", "image/gif"];
    const maxSize = 10 * 1024 * 1024;

    if (!allowedTypes.includes(file.type)) {
      showError("Tipo de archivo no permitido: " + file.type);
      return false;
    }

    if (file.size > maxSize) {
      showError("Archivo muy grande. Máximo 10MB.");
      return false;
    }

    return true;
  }

  function addPreview(file, index) {
    const reader = new FileReader();

    reader.onload = function (e) {
      const preview = `
                <div class="image-preview" data-index="${index}">
                    <img src="${e.target.result}" alt="Preview">
                    <button class="remove-preview" data-index="${index}">×</button>
                    <span class="file-name">${file.name}</span>
                </div>
            `;

      $("#image-previews").append(preview);
    };

    reader.readAsDataURL(file);
  }

  function removeFile(index) {
    selectedFiles.splice(index, 1);
    $(`.image-preview[data-index="${index}"]`).remove();

    $(".image-preview").each(function (i) {
      $(this).attr("data-index", i);
      $(this).find(".remove-preview").attr("data-index", i);
    });
  }

  async function uploadImages() {
    if (selectedFiles.length === 0) {
      return null;
    }

    const formData = new FormData();

    selectedFiles.forEach((file, index) => {
      formData.append("images[" + index + "]", file);
    });

    if (currentConversationId) {
      formData.append("conversation_id", currentConversationId);
    }

    if (currentProjectId) {
      formData.append("project_id", currentProjectId);
    }

    try {
      const response = await $.ajax({
        url: aiChatSettings.apiUrl + "/upload-images",
        method: "POST",
        data: formData,
        processData: false,
        contentType: false,
        timeout: 60000,
      });

      if (response.success) {
        return response.files;
      } else {
        throw new Error("Error al subir imágenes");
      }
    } catch (error) {
      console.error("Upload error:", error);
      showError("Error al subir imágenes: " + error.message);
      return null;
    }
  }

  function clearPreviews() {
    selectedFiles = [];
    $("#image-previews").empty();
  }

  function showError(message) {
    const errorDiv = $('<div class="upload-error">' + message + "</div>");
    $("#image-previews").prepend(errorDiv);

    setTimeout(function () {
      errorDiv.fadeOut(function () {
        $(this).remove();
      });
    }, 5000);
  }

  function setCurrentContext(conversationId, projectId) {
    currentConversationId = conversationId;
    currentProjectId = projectId;
  }

  function hasSelectedFiles() {
    return selectedFiles.length > 0;
  }

  window.AIChatImageUploader = {
    init: initImageUploader,
    upload: uploadImages,
    clear: clearPreviews,
    setContext: setCurrentContext,
    hasFiles: hasSelectedFiles,
  };
})(jQuery);
