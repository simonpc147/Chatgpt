<?php
if (!defined('ABSPATH')) {
    exit;
}

class AI_Chat_Shortcodes
{

    private $dashboard;
    private $user_manager;

    public function __construct()
    {
        $this->dashboard = new AI_Chat_Dashboard();
        $this->user_manager = new AI_Chat_User_Manager();

        add_action('init', array($this, 'register_shortcodes'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
    }

    public function register_shortcodes()
    {
        add_shortcode('ai_chat_dashboard', array($this, 'dashboard_shortcode'));
        add_shortcode('ai_chat_login', array($this, 'login_shortcode'));
        add_shortcode('ai_chat_register', array($this, 'register_shortcode'));
        add_shortcode('ai_chat_interface', array($this, 'chat_interface_shortcode'));
    }

    public function enqueue_frontend_assets()
    {
        wp_enqueue_script('jquery');

        wp_enqueue_script(
            'ai-chat-frontend',
            AI_CHAT_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            time(),
            true
        );

        wp_enqueue_style(
            'ai-chat-frontend',
            AI_CHAT_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            time()
        );

        // Cargar assets del chat si estamos en la página de chat
        if (is_page('ai-chat') || has_shortcode(get_post()->post_content ?? '', 'ai_chat_interface')) {
            wp_enqueue_script(
                'ai-chat-interface',
                AI_CHAT_PLUGIN_URL . 'assets/js/chat.js',
                array('jquery'),
                time(),
                true
            );

            wp_enqueue_style(
                'ai-chat-interface',
                AI_CHAT_PLUGIN_URL . 'assets/css/chat.css',
                array(),
                time()
            );
        }

        wp_localize_script('ai-chat-frontend', 'aiChatAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'resturl' => rest_url('ai-chat/v1/'),
            'nonce' => wp_create_nonce('ai_chat_nonce')
        ));
    }

    public function dashboard_shortcode($atts)
    {
        return $this->dashboard->render_dashboard();
    }

    public function login_shortcode($atts)
    {
        if (is_user_logged_in()) {
            return '<p>Ya has iniciado sesión. <a href="' . $this->user_manager->get_dashboard_url() . '">Ir al Dashboard</a></p>';
        }

        ob_start();
        include AI_CHAT_PLUGIN_PATH . 'templates/login-form.php';
        return ob_get_clean();
    }

    public function register_shortcode($atts)
    {
        if (is_user_logged_in()) {
            return '<p>Ya estás registrado. <a href="' . $this->user_manager->get_dashboard_url() . '">Ir al Dashboard</a></p>';
        }

        ob_start();
        include AI_CHAT_PLUGIN_PATH . 'templates/register-form.php';
        return ob_get_clean();
    }

    public function chat_interface_shortcode($atts)
    {
        if (!is_user_logged_in()) {
            return '<p>Debes <a href="' . wp_login_url() . '">iniciar sesión</a> para usar el chat.</p>';
        }

        ob_start();
        include AI_CHAT_PLUGIN_PATH . 'templates/chat-interface.php';
        return ob_get_clean();
    }
}
