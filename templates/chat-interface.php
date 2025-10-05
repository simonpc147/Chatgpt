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

        <div class="chat-actions">
            <a href="<?php echo wp_logout_url(home_url('/login/')); ?>" class="btn-logout" title="Cerrar sesión">
                Cerrar sesión
            </a>
        </div>

    </div>

    <div class="chat-main">
        <div class="chat-header">
            <button id="toggle-sidebar" class="btn-toggle-sidebar">☰</button>
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

            <!-- <div class="chat-actions">
                <select id="project-selector">
                    <option value="">Sin proyecto</option>
                    <?php foreach ($projects as $project): ?>
                        <option value="<?php echo $project['id']; ?>">
                            <?php echo esc_html($project['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div> -->
        </div>

        <div class="chat-messages" id="chat-messages">
            <div class="welcome-screen">
                <h2>¡Bienvenido a AI Chat!</h2>
                <p>Tu asistente personal de IA</p>
                <div class="quick-actions">
                    <div class="quick-action" data-prompt="Explícame como si tuviera 5 años: ">
                        📚 Ayúdame a entender conceptos complejos
                    </div>
                    <div class="quick-action" data-prompt="Ayúdame a escribir código para: ">
                        💻 Script de Python para reportes diarios
                    </div>
                    <div class="quick-action" data-prompt="Dame ideas creativas sobre: ">
                        💡 Hazme un quiz sobre civilizaciones antiguas
                    </div>
                </div>
            </div>
        </div>

        <div class="chat-input-container">
            <div id="image-previews" class="image-previews"></div>

            <div class="input-wrapper">
                <input
                    type="file"
                    id="image-input"
                    multiple
                    accept="image/jpeg,image/png,image/webp,image/gif"
                    style="display:none">

                <button id="attach-image-btn" class="btn-attach" title="Adjuntar imagen">
                    📎
                </button>

                <textarea
                    id="chat-input"
                    placeholder="Escribe tu mensaje aquí..."
                    rows="1"></textarea>

                <button id="send-btn" class="btn-send">
                    <span class="send-icon">➤</span>
                </button>
            </div>
        </div>
    </div>
</div>