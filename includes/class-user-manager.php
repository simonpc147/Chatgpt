<?php
if (!defined('ABSPATH')) {
    exit;
}

class AI_Chat_User_Manager
{

    public function __construct()
    {
        add_action('init', array($this, 'init'));
        add_action('wp_login', array($this, 'redirect_after_login'), 10, 2);
        add_action('user_register', array($this, 'setup_new_user'));
    }

    public function init()
    {
        add_action('wp_ajax_ai_chat_register', array($this, 'handle_registration'));
        add_action('wp_ajax_nopriv_ai_chat_register', array($this, 'handle_registration'));
        add_action('wp_ajax_ai_chat_login', array($this, 'handle_login'));
        add_action('wp_ajax_nopriv_ai_chat_login', array($this, 'handle_login'));
    }

    public function handle_registration()
    {
        check_ajax_referer('ai_chat_nonce', 'nonce');

        $username = sanitize_user($_POST['username']);
        $email = sanitize_email($_POST['email']);
        $password = $_POST['password'];
        $plan = sanitize_text_field($_POST['plan']);

        if (empty($username) || empty($email) || empty($password)) {
            wp_send_json_error('Todos los campos son obligatorios');
        }

        if (username_exists($username) || email_exists($email)) {
            wp_send_json_error('Usuario o email ya existe');
        }

        $user_id = wp_create_user($username, $password, $email);

        if (is_wp_error($user_id)) {
            wp_send_json_error($user_id->get_error_message());
        }

        $this->assign_user_plan($user_id, $plan);

        wp_send_json_success(array(
            'message' => 'Usuario registrado exitosamente',
            'user_id' => $user_id
        ));
    }

    public function handle_login()
    {
        check_ajax_referer('ai_chat_nonce', 'nonce');

        $username = sanitize_user($_POST['username']);
        $password = $_POST['password'];

        $user = wp_authenticate($username, $password);

        if (is_wp_error($user)) {
            wp_send_json_error('Credenciales incorrectas');
        }

        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID);

        wp_send_json_success(array(
            'message' => 'Login exitoso',
            'redirect_url' => home_url('/ai-chat/')
        ));
    }

    public function setup_new_user($user_id)
    {
        $default_plan = 'free';
        $this->assign_user_plan($user_id, $default_plan);

        $projects_manager = new AI_Chat_Projects_Manager();
        $projects_manager->create_project($user_id, 'Proyecto Principal', 'Tu primer proyecto en AI Chat');
    }

    public function assign_user_plan($user_id, $plan)
    {
        $valid_plans = array('free', 'premium', 'enterprise');

        if (!in_array($plan, $valid_plans)) {
            $plan = 'free';
        }

        $user = new WP_User($user_id);

        $user->remove_role('subscriber');

        switch ($plan) {
            case 'premium':
                $user->add_role('ai_chat_premium');
                break;
            case 'enterprise':
                $user->add_role('ai_chat_enterprise');
                break;
            default:
                $user->add_role('ai_chat_free');
                break;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_user_keys';

        $wpdb->update(
            $table_name,
            array('plan' => $plan),
            array('user_id' => $user_id),
            array('%s'),
            array('%d')
        );
    }

    public function redirect_after_login($user_login, $user)
    {
        if (!is_admin()) {
            wp_safe_redirect(home_url('/ai-chat/'));
            exit;
        }
    }

    public function get_dashboard_url()
    {
        $dashboard_page = get_page_by_path('ai-chat-dashboard');

        if ($dashboard_page) {
            return get_permalink($dashboard_page->ID);
        }

        return home_url('/ai-chat-dashboard/');
    }

    public function get_user_plan($user_id)
    {
        $user = new WP_User($user_id);

        if (in_array('ai_chat_enterprise', $user->roles)) {
            return 'enterprise';
        } elseif (in_array('ai_chat_premium', $user->roles)) {
            return 'premium';
        } else {
            return 'free';
        }
    }

    public function get_plan_limits($plan)
    {
        $limits = array(
            'free' => array(
                'monthly_messages' => 100,
                'projects' => 3,
                'conversations_per_project' => 10,
                'models' => array('openai/gpt-3.5-turbo', 'google/gemini-pro')
            ),
            'premium' => array(
                'monthly_messages' => 1000,
                'projects' => 15,
                'conversations_per_project' => 50,
                'models' => array('openai/gpt-3.5-turbo', 'openai/gpt-4', 'anthropic/claude-3-sonnet', 'google/gemini-pro')
            ),
            'enterprise' => array(
                'monthly_messages' => -1,
                'projects' => -1,
                'conversations_per_project' => -1,
                'models' => array('openai/gpt-3.5-turbo', 'openai/gpt-4', 'anthropic/claude-3-sonnet', 'google/gemini-pro')
            )
        );

        return isset($limits[$plan]) ? $limits[$plan] : $limits['free'];
    }

    public function check_user_limits($user_id, $action)
    {
        $plan = $this->get_user_plan($user_id);
        $limits = $this->get_plan_limits($plan);

        switch ($action) {
            case 'create_project':
                if ($limits['projects'] === -1) return true;

                $projects_manager = new AI_Chat_Projects_Manager();
                $user_projects = $projects_manager->get_user_projects($user_id, 999);

                return count($user_projects) < $limits['projects'];

            case 'send_message':
                if ($limits['monthly_messages'] === -1) return true;

                return $this->get_monthly_message_count($user_id) < $limits['monthly_messages'];

            default:
                return true;
        }
    }

    private function get_monthly_message_count($user_id)
    {
        global $wpdb;

        $start_date = date('Y-m-01');
        $end_date = date('Y-m-t');

        $count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->posts} c ON p.post_parent = c.ID
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'ai_message'
            AND c.post_author = %d
            AND pm.meta_key = 'role'
            AND pm.meta_value = 'user'
            AND p.post_date >= %s
            AND p.post_date <= %s
        ", $user_id, $start_date, $end_date));

        return intval($count);
    }

    public function create_user_roles()
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
}
