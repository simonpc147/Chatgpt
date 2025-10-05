<?php

/**
 * Plugin Name: AI Chat Manager
 * Description: Sistema de chat con múltiples AIs y gestión de API keys
 * Version: 1.0
 * Author: Tu Nombre
 */

if (!defined('ABSPATH')) {
    exit;
}

define('AI_CHAT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AI_CHAT_PLUGIN_PATH', plugin_dir_path(__FILE__));

class AI_Chat_Manager
{

    public function __construct()
    {
        $this->load_dependencies();
        $this->init_hooks();
        $this->init_managers();
    }

    private function load_dependencies()
    {
        require_once AI_CHAT_PLUGIN_PATH . 'includes/database.php';
        require_once AI_CHAT_PLUGIN_PATH . 'includes/functions.php';
        require_once AI_CHAT_PLUGIN_PATH . 'includes/admin-panel.php';
        require_once AI_CHAT_PLUGIN_PATH . 'includes/class-openrouter-api.php';
        require_once AI_CHAT_PLUGIN_PATH . 'includes/class-image-handler.php';
        require_once AI_CHAT_PLUGIN_PATH . 'includes/class-image-uploader.php';
        require_once AI_CHAT_PLUGIN_PATH . 'includes/class-imagerouter-vision-api.php';
        require_once AI_CHAT_PLUGIN_PATH . 'includes/class-models-manager.php';
        require_once AI_CHAT_PLUGIN_PATH . 'includes/class-projects-manager.php';
        require_once AI_CHAT_PLUGIN_PATH . 'includes/class-conversation-manager.php';
        require_once AI_CHAT_PLUGIN_PATH . 'includes/class-user-manager.php';
        require_once AI_CHAT_PLUGIN_PATH . 'includes/class-dashboard.php';
        require_once AI_CHAT_PLUGIN_PATH . 'includes/class-shortcodes.php';
        require_once AI_CHAT_PLUGIN_PATH . 'includes/class-chat-handler.php';
        require_once AI_CHAT_PLUGIN_PATH . 'includes/class-rest-api.php';
    }

    private function init_hooks()
    {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        add_action('user_register', array($this, 'assign_default_key'));
        add_action('admin_enqueue_scripts', array($this, 'load_admin_assets'));
        add_action('wp_enqueue_scripts', array($this, 'load_frontend_assets'));
    }

    private function init_managers()
    {
        new AI_Chat_Projects_Manager();
        new AI_Chat_Conversation_Manager();
        new AI_Chat_User_Manager();
        new AI_Chat_Dashboard();
        new AI_Chat_Shortcodes();
    }

    public function activate()
    {
        ai_chat_create_user_keys_table();
        ai_chat_create_usage_table();
        ai_chat_update_database_for_imagerouter();
        $this->create_user_roles();
        flush_rewrite_rules();
    }

    public function deactivate()
    {
        delete_option('ai_chat_default_key');
        flush_rewrite_rules();
    }

    private function create_user_roles()
    {
        add_role('ai_chat_free', 'AI Chat Free', array(
            'read' => true,
            'ai_chat_access' => true
        ));

        add_role('ai_chat_premium', 'AI Chat Premium', array(
            'read' => true,
            'ai_chat_access' => true,
            'ai_chat_premium' => true
        ));

        add_role('ai_chat_enterprise', 'AI Chat Enterprise', array(
            'read' => true,
            'ai_chat_access' => true,
            'ai_chat_premium' => true,
            'ai_chat_enterprise' => true
        ));
    }

    public function assign_default_key($user_id)
    {
        ai_chat_set_user_default_key($user_id);
    }

    public function load_admin_assets($hook)
    {
        if (strpos($hook, 'ai-chat') !== false) {
            wp_enqueue_style('ai-chat-admin-css', AI_CHAT_PLUGIN_URL . 'assets/css/admin.css', array(), '1.0');
            wp_enqueue_script('ai-chat-admin-js', AI_CHAT_PLUGIN_URL . 'assets/js/admin.js', array(), '1.0', true);
        }
    }

    public function load_frontend_assets()
    {
        wp_enqueue_style(
            'ai-chat-frontend-css',
            AI_CHAT_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            '1.0'
        );

        wp_enqueue_script(
            'ai-chat-frontend',
            AI_CHAT_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            '1.0',
            true
        );

        wp_localize_script('ai-chat-frontend', 'aiChatData', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ai_chat_nonce'),
            'rest_url' => rest_url('ai-chat/v1/'),
            'rest_nonce' => wp_create_nonce('wp_rest')
        ));

        wp_enqueue_script(
            'ai-chat-image-compressor',
            AI_CHAT_PLUGIN_URL . 'assets/js/image-compressor.js',
            array('jquery'),
            '1.0',
            true
        );

        wp_enqueue_script(
            'ai-chat-image-uploader',
            AI_CHAT_PLUGIN_URL . 'assets/js/image-uploader.js',
            array('jquery', 'ai-chat-image-compressor'),
            '1.0',
            true
        );

        wp_localize_script('ai-chat-image-uploader', 'aiChatSettings', array(
            'apiUrl' => rest_url('ai-chat/v1'),
            'nonce' => wp_create_nonce('wp_rest')
        ));

        wp_enqueue_script(
            'ai-chat-lazy-loader',
            AI_CHAT_PLUGIN_URL . 'assets/js/chat-lazy-loader.js',
            array('jquery'),
            '1.0',
            true
        );

        wp_enqueue_script(
            'dompurify',
            'https://cdn.jsdelivr.net/npm/dompurify@3.0.6/dist/purify.min.js',
            array(),
            '3.0.6',
            true
        );

        wp_enqueue_script(
            'marked-js',
            'https://cdn.jsdelivr.net/npm/marked/marked.min.js',
            array('dompurify'),
            '4.0.0',
            true
        );
    }
}

new AI_Chat_Manager();
