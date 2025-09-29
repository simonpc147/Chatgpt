<div class="ai-chat-dashboard">
    <div class="dashboard-header">
        <h1>Bienvenido, <?php echo esc_html($user->display_name); ?></h1>
        <p>Plan: <strong><?php echo ucfirst($plan); ?></strong></p>
    </div>

    <div class="dashboard-stats">
        <div class="stat-card">
            <h3><?php echo $stats['projects_count']; ?></h3>
            <p>Proyectos</p>
        </div>
        <div class="stat-card">
            <h3><?php echo $stats['conversations_count']; ?></h3>
            <p>Conversaciones</p>
        </div>
        <div class="stat-card">
            <h3><?php echo $stats['total_messages']; ?></h3>
            <p>Mensajes Totales</p>
        </div>
        <div class="stat-card">
            <h3><?php echo $stats['monthly_messages']; ?></h3>
            <p>Este Mes</p>
        </div>
    </div>

    <div class="dashboard-content">
        <div class="recent-projects">
            <h2>Proyectos Recientes</h2>
            <?php if (!empty($stats['recent_projects'])): ?>
                <div class="projects-list">
                    <?php foreach ($stats['recent_projects'] as $project): ?>
                        <div class="project-item">
                            <h4><?php echo esc_html($project['title']); ?></h4>
                            <p><?php echo esc_html($project['description']); ?></p>
                            <span class="project-meta">
                                <?php echo $project['conversation_count']; ?> conversaciones
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>No tienes proyectos aún. <button id="create-first-project">Crear tu primer proyecto</button></p>
            <?php endif; ?>
        </div>

        <div class="recent-conversations">
            <h2>Conversaciones Recientes</h2>
            <?php if (!empty($stats['recent_conversations'])): ?>
                <div class="conversations-list">
                    <?php foreach ($stats['recent_conversations'] as $conversation): ?>
                        <div class="conversation-item">
                            <h4><?php echo esc_html($conversation['title']); ?></h4>
                            <p><?php echo esc_html($conversation['last_message_preview']); ?></p>
                            <span class="conversation-meta">
                                <?php echo $conversation['message_count']; ?> mensajes
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>No tienes conversaciones aún. <button id="start-first-chat">Iniciar tu primer chat</button></p>
            <?php endif; ?>
        </div>
    </div>

    <div class="dashboard-actions">
        <button id="new-project" class="btn-primary">Nuevo Proyecto</button>
        <button id="new-conversation" class="btn-secondary">Nueva Conversación</button>
        <button id="open-chat" class="btn-success">Abrir Chat</button>
    </div>

    <div class="usage-section">
        <?php $this->render_usage_widget($user_id); ?>
    </div>
</div>