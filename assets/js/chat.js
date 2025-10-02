(function ($) {
  let currentConversationId = null;
  let isLoading = false;

  function initializeChat() {
    console.log("Chat initialized");
    if (typeof AIChatImageUploader !== "undefined") {
      AIChatImageUploader.init();
    }
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
                    <h4>${conv.title}</h4>
                    <p>${conv.last_message_preview || "Sin mensajes"}</p>
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
        project_id: projectId,
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
      alert("Primero crea una conversación");
      return;
    }

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
        messageHtml += `<img src="${attachment.file_url}" alt="${attachment.file_name}" class="message-image">`;
      });
      messageHtml += "</div>";
    }

    if (type === "image") {
      messageHtml += `<img src="${content}" alt="Generated image" class="generated-image">`;
    } else if (content) {
      messageHtml += escapeHtml(content);
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

  function escapeHtml(text) {
    const div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML.replace(/\n/g, "<br>");
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
    });

    $("#project-selector").on("change", function () {
      updateImageUploaderContext();
    });
  }

  $(document).ready(function () {
    initializeChat();
    loadModels();
    loadConversations();
    attachEventListeners();
  });
})(jQuery);
