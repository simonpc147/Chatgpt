<?php
if (!is_user_logged_in()) {
    wp_redirect(wp_login_url());
    exit;
}

$user_id = get_current_user_id();
$dashboard = new AI_Chat_Dashboard();
$available_models = $dashboard->get_available_models($user_id);
$projects_manager = new AI_Chat_Projects_Manager();
$projects = $projects_manager->get_user_projects($user_id);
?>

<div id="ai-chat-container">
    <div class="chat-sidebar">
        <div class="sidebar-header">
            <h3>AI Chat</h3>
            <button id="new-chat-btn" class="btn-new-chat">+ Nueva Conversación</button>
        </div>

        <div class="conversations-list" id="conversations-list">
            <p class="loading">Cargando conversaciones...</p>
        </div>
    </div>

    <div class="chat-main">
        <div class="chat-header">
            <div class="chat-info">
                <h2 id="chat-title">Selecciona una conversación</h2>
                <select id="model-selector" class="model-selector">
                    <?php foreach ($available_models as $model_id => $model_info): ?>
                        <option value="<?php echo esc_attr($model_id); ?>">
                            <?php echo esc_html($model_info['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="chat-actions">
                <select id="project-selector">
                    <option value="">Sin proyecto</option>
                    <?php foreach ($projects as $project): ?>
                        <option value="<?php echo $project['id']; ?>">
                            <?php echo esc_html($project['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="chat-messages" id="chat-messages">
            <div class="welcome-screen">
                <h2>¡Bienvenido al Chat AI!</h2>
                <p>Selecciona un modelo y comienza a chatear</p>
                <div class="quick-actions">
                    <button class="quick-action" data-prompt="Explícame como si tuviera 5 años: ">
                        📚 Explica simple
                    </button>
                    <button class="quick-action" data-prompt="Ayúdame a escribir código para: ">
                        💻 Ayuda con código
                    </button>
                    <button class="quick-action" data-prompt="Dame ideas creativas sobre: ">
                        💡 Ideas creativas
                    </button>
                </div>
            </div>
        </div>

        <div class="chat-input-container">
            <textarea
                id="chat-input"
                placeholder="Escribe tu mensaje aquí... (Shift+Enter para nueva línea)"
                rows="3"></textarea>
            <button id="send-btn" class="btn-send">
                <span class="send-icon">➤</span>
                Enviar
            </button>
        </div>
    </div>
</div>