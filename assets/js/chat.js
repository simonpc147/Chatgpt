(function ($) {
  let currentConversationId = null;
  let isLoading = false;

  function initializeChat() {
    console.log("Chat initialized");
    if (typeof AIChatImageUploader !== "undefined") {
      AIChatImageUploader.init();
    }
  }

  function decodeHTMLEntities(text) {
    const textarea = document.createElement("textarea");
    textarea.innerHTML = text;
    return textarea.value;
  }

  function loadModels() {
    $.ajax({
      url: aiChatAjax.resturl + "get-models",
      method: "GET",
      success: function (response) {
        if (response.success && response.models) {
          renderModels(response.models);
        }
      },
      error: function () {
        console.error("Error al cargar modelos");
      },
    });
  }

  function renderModels(models) {
    const $selector = $("#model-selector");
    $selector.empty();

    Object.keys(models).forEach(function (modelId) {
      const model = models[modelId];
      $selector.append($("<option>").val(modelId).text(model.name));
    });

    if ($selector.children().length > 0) {
      $selector.prop("disabled", false);
    }
  }

  function loadConversations() {
    $.ajax({
      url: aiChatAjax.resturl + "get-conversations",
      method: "GET",
      success: function (response) {
        if (response.success && response.conversations) {
          renderConversations(response.conversations);
        }
      },
      error: function () {
        $("#conversations-list").html(
          '<p class="error">Error al cargar conversaciones</p>'
        );
      },
    });
  }

  function renderConversations(conversations) {
    const $list = $("#conversations-list");
    $list.empty();

    if (conversations.length === 0) {
      $list.html('<p class="no-conversations">No hay conversaciones aún</p>');
      return;
    }

    conversations.forEach(function (conv) {
      const $item = $("<div>")
        .addClass("conversation-item")
        .attr("data-id", conv.id).html(`
                    <div class="conversation-content">
                        <h4>${conv.title}</h4>
                        <p>${conv.last_message_preview || "Sin mensajes"}</p>
                    </div>
                    <button class="btn-delete-conversation" data-id="${
                      conv.id
                    }" title="Eliminar conversación">🗑️</button>
                `);
      $list.append($item);
    });
  }

  function createNewConversation() {
    const projectId = $("#project-selector").val() || null;

    $.ajax({
      url: aiChatAjax.resturl + "conversations",
      method: "POST",
      contentType: "application/json",
      data: JSON.stringify({
        title: "Nueva Conversación",
        ...(projectId ? { project_id: parseInt(projectId) } : {}),
      }),
      success: function (response) {
        if (response.success) {
          currentConversationId = response.conversation_id;
          updateImageUploaderContext();
          loadConversations();
          clearMessages();
          $("#chat-title").text("Nueva Conversación");
        }
      },
      error: function () {
        alert("Error al crear conversación");
      },
    });
  }

  function loadConversation(conversationId) {
    currentConversationId = conversationId;
    updateImageUploaderContext();

    $(".conversation-item").removeClass("active");
    $(`.conversation-item[data-id="${conversationId}"]`).addClass("active");

    $.ajax({
      url:
        aiChatAjax.resturl +
        "get-conversation-history?conversation_id=" +
        conversationId,
      method: "GET",
      success: function (response) {
        console.log("Response:", response);
        console.log("Messages:", response.messages);

        if (response.success) {
          $("#chat-title").text(response.conversation.title);
          renderMessages(response.messages || []);
        }
      },
      error: function (xhr) {
        console.error("Error loading conversation:", xhr);
        alert("Error al cargar conversación");
      },
    });
  }

  function updateImageUploaderContext() {
    if (typeof AIChatImageUploader !== "undefined") {
      const projectId = $("#project-selector").val() || null;
      AIChatImageUploader.setContext(currentConversationId, projectId);
    }
  }

  function renderMessages(messages) {
    const $container = $("#chat-messages");
    $container.empty();

    if (!messages || !Array.isArray(messages) || messages.length === 0) {
      $container.html(
        '<div class="welcome-screen"><p>Comienza a chatear escribiendo un mensaje</p></div>'
      );
      return;
    }

    messages.forEach(function (msg) {
      const isImage =
        msg.model === "image-generator" ||
        msg.model === "google/gemini-2.5-flash" ||
        (msg.content && msg.content.startsWith("http"));
      const type = isImage ? "image" : "text";
      appendMessage(msg.role, msg.content, false, type, msg.attachments);
    });

    scrollToBottom();
  }

  async function sendMessage() {
    if (isLoading) return;

    const message = $("#chat-input").val().trim();
    const model = $("#model-selector").val();
    const hasImages =
      typeof AIChatImageUploader !== "undefined" &&
      AIChatImageUploader.hasFiles();

    if (!message && !hasImages) {
      alert("Escribe un mensaje o adjunta una imagen");
      return;
    }

    if (!model) {
      alert("Selecciona un modelo");
      return;
    }

    if (!currentConversationId) {
      await createConversationAndSend(message, model, hasImages);
      return;
    }

    processSendMessage(message, model, hasImages);
  }

  async function createConversationAndSend(message, model, hasImages) {
    const projectId = $("#project-selector").val() || null;

    $.ajax({
      url: aiChatAjax.resturl + "conversations",
      method: "POST",
      contentType: "application/json",
      beforeSend: function (xhr) {
        xhr.setRequestHeader("X-WP-Nonce", aiChatAjax.rest_nonce);
      },
      data: JSON.stringify({
        title: "Nueva Conversación",
        ...(projectId ? { project_id: parseInt(projectId) } : {}),
      }),
      success: function (response) {
        if (response.success) {
          currentConversationId = response.conversation_id;
          updateImageUploaderContext();
          loadConversations();
          $("#chat-title").text("Nueva Conversación");
          processSendMessage(message, model, hasImages);
        }
      },
      error: function () {
        alert("Error al crear conversación");
      },
    });
  }

  async function processSendMessage(message, model, hasImages) {
    isLoading = true;
    $("#send-btn").prop("disabled", true);
    $("#chat-input").val("");

    let uploadedFiles = null;

    if (hasImages) {
      uploadedFiles = await AIChatImageUploader.upload();

      if (!uploadedFiles) {
        isLoading = false;
        $("#send-btn").prop("disabled", false);
        return;
      }
    }

    appendMessage("user", message, true, "text", uploadedFiles);
    showTypingIndicator();

    if (hasImages) {
      AIChatImageUploader.clear();
    }

    const requestData = {
      message: message,
      model: model,
      conversation_id: currentConversationId,
    };

    if (uploadedFiles) {
      requestData.attachments = uploadedFiles;
    }

    $.ajax({
      url: aiChatAjax.resturl + "send-message",
      method: "POST",
      contentType: "application/json",
      data: JSON.stringify(requestData),
      success: function (response) {
        hideTypingIndicator();

        if (response.success) {
          const messageType = response.type || "text";
          appendMessage("assistant", response.content, true, messageType);
        } else {
          alert("Error: " + (response.message || "Error desconocido"));
        }
      },
      error: function (xhr) {
        hideTypingIndicator();
        const error = xhr.responseJSON?.message || "Error al enviar mensaje";
        alert(error);
      },
      complete: function () {
        isLoading = false;
        $("#send-btn").prop("disabled", false);
        $("#chat-input").focus();
      },
    });
  }

  function appendMessage(role, content, animate, type, attachments) {
    const $messages = $("#chat-messages");

    if ($messages.find(".welcome-screen").length) {
      $messages.empty();
    }

    let messageHtml = '<div class="message-content">';

    if (attachments && attachments.length > 0) {
      messageHtml += '<div class="message-attachments">';
      attachments.forEach(function (attachment) {
        messageHtml += `
          <div class="image-wrapper">
            <img src="${attachment.file_url}" alt="${attachment.file_name}" class="message-image">
            <button class="btn-download-image" data-url="${attachment.file_url}" data-name="${attachment.file_name}">⬇</button>
          </div>
        `;
      });
      messageHtml += "</div>";
    }

    const isImageUrl =
      content && /\.(jpg|jpeg|png|gif|webp)(\?.*)?$/i.test(content);

    if (isImageUrl) {
      const imageName = content.split("/").pop().split("?")[0];
      messageHtml += `
        <div class="image-wrapper">
          <img src="${content}" alt="Generated image" class="generated-image">
          <button class="btn-download-image" data-url="${content}" data-name="${imageName}">⬇</button>
        </div>
      `;
    } else if (content) {
      const decodedContent = decodeHTMLEntities(content);

      if (typeof marked !== "undefined" && typeof DOMPurify !== "undefined") {
        const html = marked.parse(decodedContent);
        messageHtml += DOMPurify.sanitize(html);
      } else {
        messageHtml += decodedContent.replace(/\n/g, "<br>");
      }
    }

    messageHtml += `<div class="message-meta">${new Date().toLocaleTimeString()}</div>`;
    messageHtml += "</div>";

    const $message = $("<div>")
      .addClass("message")
      .addClass(role)
      .html(messageHtml);

    if (animate) {
      $message.hide().appendTo($messages).fadeIn(300);
    } else {
      $message.appendTo($messages);
    }

    scrollToBottom();
  }

  function showTypingIndicator() {
    const $indicator = $("<div>").addClass("message assistant typing-message")
      .html(`
            <div class="message-content">
                <div class="typing-indicator">
                    <span class="typing-dot"></span>
                    <span class="typing-dot"></span>
                    <span class="typing-dot"></span>
                </div>
            </div>
        `);

    $("#chat-messages").append($indicator);
    scrollToBottom();
  }

  function hideTypingIndicator() {
    $(".typing-message").remove();
  }

  function clearMessages() {
    $("#chat-messages").html(
      '<div class="welcome-screen"><p>Comienza a chatear escribiendo un mensaje</p></div>'
    );
  }

  function scrollToBottom() {
    const $messages = $("#chat-messages");
    if ($messages.length > 0 && $messages[0]) {
      $messages.scrollTop($messages[0].scrollHeight);
    }
  }

  function downloadImage(url, filename) {
    fetch(url)
      .then((response) => response.blob())
      .then((blob) => {
        const blobUrl = window.URL.createObjectURL(blob);
        const link = document.createElement("a");
        link.href = blobUrl;
        link.download = filename || "image.png";
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(blobUrl);
      })
      .catch((error) => {
        console.error("Error downloading image:", error);
        alert("No se pudo descargar la imagen");
      });
  }

  function deleteConversation(conversationId) {
    $.ajax({
      url: aiChatAjax.resturl + "conversations/" + conversationId,
      method: "DELETE",
      success: function (response) {
        if (response.success) {
          if (currentConversationId === conversationId) {
            currentConversationId = null;
            clearMessages();
            $("#chat-title").text("Selecciona una conversación");
          }
          loadConversations();
        } else {
          alert("Error al eliminar conversación");
        }
      },
      error: function () {
        alert("Error al eliminar conversación");
      },
    });
  }

  function attachEventListeners() {
    $("#new-chat-btn").on("click", createNewConversation);
    $("#send-btn").on("click", sendMessage);

    $("#chat-input").on("keydown", function (e) {
      if (e.key === "Enter" && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
      }
    });

    $(".quick-action").on("click", function () {
      const prompt = $(this).data("prompt");
      $("#chat-input").val(prompt).focus();
    });

    $(document).on("click", ".conversation-item", function () {
      const conversationId = $(this).data("id");
      loadConversation(conversationId);

      if (window.innerWidth <= 768) {
        $(".chat-sidebar").removeClass("active");
      }
    });

    $("#project-selector").on("change", function () {
      updateImageUploaderContext();
    });

    $(document).on("click", ".btn-download-image", function () {
      const url = $(this).data("url");
      const name = $(this).data("name");
      downloadImage(url, name);
    });

    $(document).on("click", ".btn-delete-conversation", function (e) {
      e.stopPropagation();
      const conversationId = $(this).data("id");

      if (confirm("¿Estás seguro de eliminar esta conversación?")) {
        deleteConversation(conversationId);
      }
    });

    $("#toggle-sidebar").on("click", function () {
      $(".chat-sidebar").toggleClass("active");
    });
  }

  $(document).ready(function () {
    initializeChat();
    loadModels();
    loadConversations();
    attachEventListeners();
  });
})(jQuery);
