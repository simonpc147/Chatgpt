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

  async function handleFileSelection(files) {
    const validFiles = [];

    for (const file of files) {
      if (validateFile(file)) {
        validFiles.push(file);
      }
    }

    if (validFiles.length === 0) return;

    showLoading("Comprimiendo imágenes...");

    try {
      const compressedResults =
        await window.AIChatImageCompressor.compressMultiple(validFiles);

      for (let i = 0; i < compressedResults.length; i++) {
        const result = compressedResults[i];

        selectedFiles.push({
          file: result.compressed,
          thumbnail: result.thumbnail,
          fileName: result.fileName,
          originalSize: result.originalSize,
          compressedSize: result.compressedSize,
        });

        await addPreview(
          result.thumbnail,
          selectedFiles.length - 1,
          result.fileName
        );
      }

      hideLoading();
      showSuccess(`${validFiles.length} imagen(es) optimizada(s)`);
    } catch (error) {
      hideLoading();
      showError("Error al comprimir imágenes: " + error.message);
      console.error(error);
    }
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

  async function addPreview(thumbnailBlob, index, fileName) {
    const thumbnailUrl =
      window.AIChatImageCompressor.createPreviewURL(thumbnailBlob);

    const preview = `
      <div class="image-preview" data-index="${index}">
        <img src="${thumbnailUrl}" alt="Preview" loading="lazy">
        <button class="remove-preview" data-index="${index}">×</button>
        <span class="file-name">${fileName}</span>
      </div>
    `;

    $("#image-previews").append(preview);
  }

  function removeFile(index) {
    const fileData = selectedFiles[index];
    if (fileData && fileData.thumbnail) {
      const imgElement = $(`.image-preview[data-index="${index}"] img`);
      if (imgElement.length > 0) {
        window.AIChatImageCompressor.revokePreviewURL(imgElement.attr("src"));
      }
    }

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

    selectedFiles.forEach((fileData, index) => {
      formData.append(
        "images[" + index + "]",
        fileData.file,
        fileData.fileName
      );
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
    selectedFiles.forEach((fileData) => {
      if (fileData.thumbnail) {
        const imgElements = $(".image-preview img");
        imgElements.each(function () {
          window.AIChatImageCompressor.revokePreviewURL($(this).attr("src"));
        });
      }
    });

    selectedFiles = [];
    $("#image-previews").empty();
  }

  function showLoading(message) {
    const loadingDiv = $('<div class="upload-loading">' + message + "</div>");
    $("#image-previews").prepend(loadingDiv);
  }

  function hideLoading() {
    $(".upload-loading").remove();
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

  function showSuccess(message) {
    const successDiv = $('<div class="upload-success">' + message + "</div>");
    $("#image-previews").prepend(successDiv);

    setTimeout(function () {
      successDiv.fadeOut(function () {
        $(this).remove();
      });
    }, 3000);
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
