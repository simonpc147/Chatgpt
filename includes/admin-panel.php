<?php
if (!defined('ABSPATH')) {
    exit;
}

class AI_Chat_Admin
{

    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
    }

    public function add_admin_menu()
    {
        add_menu_page(
            'AI Chat Manager',
            'AI Chat Manager',
            'manage_options',
            'ai-chat-manager',
            array($this, 'admin_page'),
            'dashicons-format-chat',
            30
        );

        add_submenu_page(
            'ai-chat-manager',
            'Configuración',
            'Configuración',
            'manage_options',
            'ai-chat-config',
            array($this, 'config_page')
        );
    }

    public function admin_page()
    {
        $message = '';

        if (isset($_POST['update_key']) && wp_verify_nonce($_POST['ai_chat_nonce'], 'update_user_key')) {
            $user_id = intval($_POST['user_id']);
            $new_key = ai_chat_validate_and_sanitize_key($_POST['api_key']);
            $new_image_key = ai_chat_validate_and_sanitize_key($_POST['imagerouter_api_key']);
            $plan = ai_chat_validate_plan($_POST['plan']);

            if ($new_key && $new_image_key) {
                global $wpdb;
                $table_name = $wpdb->prefix . 'ai_user_keys';

                $wpdb->update(
                    $table_name,
                    array(
                        'api_key' => $new_key,
                        'imagerouter_api_key' => $new_image_key,
                        'plan' => $plan
                    ),
                    array('user_id' => $user_id),
                    array('%s', '%s', '%s'),
                    array('%d')
                );

                $message = '<div class="notice notice-success"><p>API Keys actualizadas correctamente</p></div>';
            } else {
                $message = '<div class="notice notice-error"><p>API Keys inválidas. Deben tener al menos 20 caracteres</p></div>';
            }
        }

        $users_data = ai_chat_get_all_users_with_keys();

        echo $message;
?>
        <div class="wrap">
            <h1>Gestión de Usuarios y API Keys</h1>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Usuario</th>
                        <th>Email</th>
                        <th>Plan</th>
                        <th>OpenRouter Key</th>
                        <th>ImageRouter Key</th>
                        <th>Registro</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users_data as $user): ?>
                        <tr>
                            <td><?php echo $user->ID; ?></td>
                            <td><?php echo esc_html($user->user_login); ?></td>
                            <td><?php echo esc_html($user->user_email); ?></td>
                            <td><?php echo esc_html($user->plan ?: 'Sin plan'); ?></td>
                            <td><?php echo $user->api_key ? esc_html(substr($user->api_key, 0, 20)) . '...' : 'Sin key'; ?></td>
                            <td><?php echo isset($user->imagerouter_api_key) && $user->imagerouter_api_key ? esc_html(substr($user->imagerouter_api_key, 0, 20)) . '...' : 'Sin key'; ?></td>
                            <td><?php echo esc_html(date('Y-m-d', strtotime($user->user_registered))); ?></td>
                            <td>
                                <button class="button" onclick="editKey(<?php echo $user->ID; ?>, '<?php echo esc_js($user->api_key); ?>', '<?php echo esc_js($user->imagerouter_api_key ?? ''); ?>', '<?php echo esc_js($user->plan); ?>')">
                                    Editar
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div id="edit-key-modal" style="display:none;">
                <div class="modal-content">
                    <h3>Editar API Keys</h3>
                    <form method="post">
                        <?php wp_nonce_field('update_user_key', 'ai_chat_nonce'); ?>
                        <input type="hidden" id="edit-user-id" name="user_id">
                        <p>
                            <label>OpenRouter API Key:</label><br>
                            <input type="text" id="edit-api-key" name="api_key" style="width:100%;" required>
                        </p>
                        <p>
                            <label>ImageRouter API Key:</label><br>
                            <input type="text" id="edit-imagerouter-key" name="imagerouter_api_key" style="width:100%;" required>
                        </p>
                        <p>
                            <label>Plan:</label><br>
                            <select id="edit-plan" name="plan">
                                <option value="free">Free</option>
                                <option value="premium">Premium</option>
                                <option value="enterprise">Enterprise</option>
                            </select>
                        </p>
                        <p>
                            <input type="submit" name="update_key" value="Actualizar" class="button-primary">
                            <button type="button" class="button" onclick="closeModal()">Cancelar</button>
                        </p>
                    </form>
                </div>
            </div>
        </div>
    <?php
    }

    public function config_page()
    {
        if (isset($_POST['save_config']) && wp_verify_nonce($_POST['ai_chat_config_nonce'], 'save_config')) {
            update_option('ai_chat_default_key', sanitize_text_field($_POST['default_key']));
            update_option('ai_chat_imagerouter_default_key', sanitize_text_field($_POST['default_imagerouter_key']));
            echo '<div class="notice notice-success"><p>Configuración guardada</p></div>';
        }

        $default_key = get_option('ai_chat_default_key', '');
        $default_imagerouter_key = get_option('ai_chat_imagerouter_default_key', '');

    ?>
        <div class="wrap">
            <h1>Configuración AI Chat</h1>

            <form method="post">
                <?php wp_nonce_field('save_config', 'ai_chat_config_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">OpenRouter API Key por Defecto</th>
                        <td>
                            <input type="text" name="default_key" value="<?php echo esc_attr($default_key); ?>" class="regular-text">
                            <p class="description">Esta key se asignará a nuevos usuarios para texto</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">ImageRouter API Key por Defecto</th>
                        <td>
                            <input type="text" name="default_imagerouter_key" value="<?php echo esc_attr($default_imagerouter_key); ?>" class="regular-text">
                            <p class="description">Esta key se asignará a nuevos usuarios para imágenes</p>
                        </td>
                    </tr>
                </table>

                <input type="submit" name="save_config" value="Guardar Configuración" class="button-primary">
            </form>
        </div>
<?php
    }
}

new AI_Chat_Admin();
