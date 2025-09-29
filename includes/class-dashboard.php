<?php
if (!defined('ABSPATH')) {
    exit;
}

class AI_Chat_Dashboard
{

    private $user_manager;
    private $projects_manager;
    private $conversation_manager;

    public function __construct()
    {
        $this->user_manager = new AI_Chat_User_Manager();
        $this->projects_manager = new AI_Chat_Projects_Manager();
        $this->conversation_manager = new AI_Chat_Conversation_Manager();

        add_action('init', array($this, 'create_dashboard_page'));
        // add_action('template_redirect', array($this, 'protect_dashboard'));
        add_action('init', array($this, 'create_chat_page'));
    }

    public function create_dashboard_page()
    {
        $page_slug = 'ai-chat-dashboard';
        $page = get_page_by_path($page_slug);

        if (!$page) {
            wp_insert_post(array(
                'post_title' => 'AI Chat Dashboard',
                'post_name' => $page_slug,
                'post_content' => '[ai_chat_dashboard]',
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_author' => 1
            ));
        }
    }

    // public function protect_dashboard()
    // {
    //     // Temporalmente deshabilitado para debugging
    //     return;

    //     if (is_page('ai-chat-dashboard')) {
    //         if (!is_user_logged_in()) {
    //             wp_redirect(wp_login_url(get_permalink()));
    //             exit;
    //         }

    //         if (!current_user_can('administrator') && !current_user_can('ai_chat_access')) {
    //             wp_die('No tienes permisos para acceder a esta página.');
    //         }
    //     }
    // }

    public function render_dashboard()
    {
        if (!is_user_logged_in()) {
            return '<p>Debes iniciar sesión para acceder al dashboard.</p>';
        }

        $user_id = get_current_user_id();
        $user = wp_get_current_user();
        $plan = $this->user_manager->get_user_plan($user_id);
        $limits = $this->user_manager->get_plan_limits($plan);

        $stats = $this->get_user_stats($user_id);

        ob_start();
        include AI_CHAT_PLUGIN_PATH . 'templates/dashboard.php';
        return ob_get_clean();
    }

    public function get_user_stats($user_id)
    {
        $projects = $this->projects_manager->get_user_projects($user_id, 10);
        $conversations = $this->conversation_manager->get_user_conversations($user_id, null, 10);

        global $wpdb;

        $message_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->posts} c ON p.post_parent = c.ID
            WHERE p.post_type = 'ai_message'
            AND c.post_author = %d
        ", $user_id));

        $monthly_messages = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->posts} c ON p.post_parent = c.ID
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'ai_message'
            AND c.post_author = %d
            AND pm.meta_key = 'role'
            AND pm.meta_value = 'user'
            AND p.post_date >= %s
        ", $user_id, date('Y-m-01')));

        return array(
            'projects_count' => count($projects),
            'conversations_count' => count($conversations),
            'total_messages' => intval($message_count),
            'monthly_messages' => intval($monthly_messages),
            'recent_projects' => array_slice($projects, 0, 5),
            'recent_conversations' => array_slice($conversations, 0, 5)
        );
    }

    public function get_plan_usage($user_id)
    {
        $plan = $this->user_manager->get_user_plan($user_id);
        $limits = $this->user_manager->get_plan_limits($plan);
        $stats = $this->get_user_stats($user_id);

        $usage = array(
            'plan' => $plan,
            'limits' => $limits,
            'usage' => array()
        );

        if ($limits['monthly_messages'] !== -1) {
            $usage['usage']['messages'] = array(
                'used' => $stats['monthly_messages'],
                'limit' => $limits['monthly_messages'],
                'percentage' => ($stats['monthly_messages'] / $limits['monthly_messages']) * 100
            );
        }

        if ($limits['projects'] !== -1) {
            $usage['usage']['projects'] = array(
                'used' => $stats['projects_count'],
                'limit' => $limits['projects'],
                'percentage' => ($stats['projects_count'] / $limits['projects']) * 100
            );
        }

        return $usage;
    }

    public function render_usage_widget($user_id)
    {
        $usage = $this->get_plan_usage($user_id);

        echo '<div class="ai-chat-usage-widget">';
        echo '<h3>Plan: ' . ucfirst($usage['plan']) . '</h3>';

        if (isset($usage['usage']['messages'])) {
            $msg = $usage['usage']['messages'];
            echo '<div class="usage-item">';
            echo '<span>Mensajes este mes: ' . $msg['used'] . '/' . $msg['limit'] . '</span>';
            echo '<div class="progress-bar">';
            echo '<div class="progress-fill" style="width: ' . min($msg['percentage'], 100) . '%"></div>';
            echo '</div>';
            echo '</div>';
        }

        if (isset($usage['usage']['projects'])) {
            $proj = $usage['usage']['projects'];
            echo '<div class="usage-item">';
            echo '<span>Proyectos: ' . $proj['used'] . '/' . $proj['limit'] . '</span>';
            echo '<div class="progress-bar">';
            echo '<div class="progress-fill" style="width: ' . min($proj['percentage'], 100) . '%"></div>';
            echo '</div>';
            echo '</div>';
        }

        echo '</div>';
    }

    public function get_available_models($user_id)
    {
        $plan = $this->user_manager->get_user_plan($user_id);
        $limits = $this->user_manager->get_plan_limits($plan);

        $openrouter_api = new AI_Chat_OpenRouter_API();
        $all_models = $openrouter_api->get_available_models();

        $available_models = array();

        foreach ($all_models as $model_id => $model_info) {
            if (in_array($model_id, $limits['models'])) {
                $available_models[$model_id] = $model_info;
            }
        }

        return $available_models;
    }

    public function create_chat_page()
    {
        $page_slug = 'ai-chat';
        $page = get_page_by_path($page_slug);

        if (!$page) {
            wp_insert_post(array(
                'post_title' => 'AI Chat',
                'post_name' => $page_slug,
                'post_content' => '[ai_chat_interface]',
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_author' => 1
            ));
        }
    }
}
