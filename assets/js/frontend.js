(function ($) {
  $(document).ready(function () {
    $("#ai-chat-register-form").on("submit", function (e) {
      e.preventDefault();

      var formData = {
        action: "ai_chat_register",
        username: $("#reg-username").val(),
        email: $("#reg-email").val(),
        password: $("#reg-password").val(),
        plan: $("#plan").val(),
        nonce: wp.create_nonce("ai_chat_register"),
      };

      $.post(aiChatAjax.ajaxurl, formData, function (response) {
        if (response.success) {
          $("#register-message").html(
            '<p style="color: green;">' + response.data.message + "</p>"
          );
          setTimeout(function () {
            window.location.reload();
          }, 2000);
        } else {
          $("#register-message").html(
            '<p style="color: red;">' + response.data + "</p>"
          );
        }
      });
    });

    $("#ai-chat-login-form").on("submit", function (e) {
      e.preventDefault();

      var formData = {
        action: "ai_chat_login",
        username: $("#username").val(),
        password: $("#password").val(),
        nonce: wp.create_nonce("ai_chat_login"),
      };

      $.post(aiChatAjax.ajaxurl, formData, function (response) {
        if (response.success) {
          $("#login-message").html(
            '<p style="color: green;">' + response.data.message + "</p>"
          );
          window.location.href = response.data.redirect_url;
        } else {
          $("#login-message").html(
            '<p style="color: red;">' + response.data + "</p>"
          );
        }
      });
    });

    $("#new-project, #create-first-project").on("click", function (e) {
      e.preventDefault();
      var projectName = prompt("Nombre del proyecto:");

      if (projectName) {
        $.ajax({
          url: aiChatAjax.resturl + "projects",
          method: "POST",
          data: JSON.stringify({
            title: projectName,
            description: "",
          }),
          contentType: "application/json",
          success: function (response) {
            alert("Proyecto creado exitosamente");
            location.reload();
          },
          error: function () {
            alert("Error al crear proyecto");
          },
        });
      }
    });

    $("#new-conversation, #start-first-chat").on("click", function (e) {
      e.preventDefault();

      $.ajax({
        url: aiChatAjax.resturl + "conversations",
        method: "POST",
        data: JSON.stringify({
          title: "Nueva Conversación",
        }),
        contentType: "application/json",
        success: function (response) {
          alert("Conversación creada. ID: " + response.conversation_id);
          jQuery("#open-chat").click();
        },
        error: function () {
          alert("Error al crear conversación");
        },
      });
    });

    $("#open-chat").on("click", function (e) {
      e.preventDefault();
      window.location.href = "/ai-chat/";
    });

    $("#send-message").on("click", function () {
      var message = $("#message-input").val();
      var model = $("#model-selector").val();

      if (!message) {
        alert("Escribe un mensaje");
        return;
      }

      $("#chat-messages").append(
        '<div class="message user-message"><strong>Tú:</strong> ' +
          message +
          "</div>"
      );

      $.ajax({
        url: aiChatAjax.resturl + "send-message",
        method: "POST",
        data: JSON.stringify({
          message: message,
          model: model,
        }),
        contentType: "application/json",
        success: function (response) {
          $("#chat-messages").append(
            '<div class="message ai-message"><strong>AI:</strong> ' +
              response.message +
              "</div>"
          );
          $("#message-input").val("");
          $("#chat-messages").scrollTop($("#chat-messages")[0].scrollHeight);
        },
        error: function () {
          alert("Error al enviar mensaje");
        },
      });
    });

    $("#message-input").on("keypress", function (e) {
      if (e.which === 13 && !e.shiftKey) {
        e.preventDefault();
        $("#send-message").click();
      }
    });
  });
})(jQuery);
