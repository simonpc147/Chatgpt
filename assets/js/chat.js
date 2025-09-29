(function ($) {
  let currentConversationId = null;
  let isLoading = false;

  $(document).ready(function () {
    initializeChat();
    loadConversations();
    attachEventListeners();
  });

  function initializeChat() {
    console.log("Chat initialized");
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

    $(".conversation-item").removeClass("active");
    $(`.conversation-item[data-id="${conversationId}"]`).addClass("active");

    $.ajax({
      url:
        aiChatAjax.resturl +
        "get-conversation-history?conversation_id=" +
        conversationId,
      method: "GET",
      success: function (response) {
        if (response.success) {
          $("#chat-title").text(response.conversation.title);
          renderMessages(response.messages);
        }
      },
      error: function () {
        alert("Error al cargar conversación");
      },
    });
  }

  function renderMessages(messages) {
    const $container = $("#chat-messages");
    $container.empty();

    if (messages.length === 0) {
      $container.html(
        '<div class="welcome-screen"><p>Comienza a chatear escribiendo un mensaje</p></div>'
      );
      return;
    }

    messages.forEach(function (msg) {
      appendMessage(msg.role, msg.content, false);
    });

    scrollToBottom();
  }

  function sendMessage() {
    if (isLoading) return;

    const message = $("#chat-input").val().trim();
    const model = $("#model-selector").val();

    if (!message) {
      alert("Escribe un mensaje");
      return;
    }

    if (!currentConversationId) {
      alert("Primero crea una conversación");
      return;
    }

    isLoading = true;
    $("#send-btn").prop("disabled", true);
    $("#chat-input").val("");

    appendMessage("user", message, true);
    showTypingIndicator();

    $.ajax({
      url: aiChatAjax.resturl + "send-message",
      method: "POST",
      contentType: "application/json",
      data: JSON.stringify({
        message: message,
        model: model,
        conversation_id: currentConversationId,
      }),
      success: function (response) {
        hideTypingIndicator();

        if (response.success) {
          appendMessage("assistant", response.message, true);
          loadConversations();
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

  function appendMessage(role, content, animate) {
    const $messages = $("#chat-messages");

    if ($messages.find(".welcome-screen").length) {
      $messages.empty();
    }

    const $message = $("<div>").addClass("message").addClass(role).html(`
                <div class="message-content">
                    ${escapeHtml(content)}
                    <div class="message-meta">${new Date().toLocaleTimeString()}</div>
                </div>
            `);

    if (animate) {
      $message.hide().appendTo($messages).fadeIn(300);
    } else {
      $message.appendTo($messages);
    }

    scrollToBottom();
  }

  function showTypingIndicator() {
    const $indicator = $("<div>").addClass("message assistant typing-indicator")
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
    $(".typing-indicator").parent().parent().remove();
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
})(jQuery);
