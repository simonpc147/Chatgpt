<?php
if (!defined('ABSPATH')) {
    exit;
}

class AI_Chat_REST_API
{

    private $chat_handler;

    public function __construct()
    {
        $this->chat_handler = new AI_Chat_Handler();
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    public function register_routes()
    {
        register_rest_route('ai-chat/v1', '/get-models', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_models'),
            'permission_callback' => '__return_true'
        ));

        register_rest_route('ai-chat/v1', '/get-conversations', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_conversations'),
            'permission_callback' => '__return_true'
        ));

        register_rest_route('ai-chat/v1', '/send-message', array(
            'methods' => 'POST',
            'callback' => array($this, 'send_message'),
            'permission_callback' => '__return_true',
            'args' => array(
                'message' => array(
                    'required' => true,
                    'type' => 'string'
                ),
                'model' => array(
                    'required' => true,
                    'type' => 'string'
                )
            )
        ));

        register_rest_route('ai-chat/v1', '/projects', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_projects'),
            'permission_callback' => '__return_true'
        ));

        register_rest_route('ai-chat/v1', '/projects', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_project'),
            'permission_callback' => '__return_true',
            'args' => array(
                'title' => array('required' => true, 'type' => 'string'),
                'description' => array('required' => false, 'type' => 'string')
            )
        ));

        register_rest_route('ai-chat/v1', '/conversations', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_conversation'),
            'permission_callback' => '__return_true',
            'args' => array(
                'title' => array('required' => false, 'type' => 'string'),
                'project_id' => array('required' => false, 'type' => 'integer')
            )
        ));

        register_rest_route('ai-chat/v1', '/get-conversation-history', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_conversation_history'),
            'permission_callback' => '__return_true'
        ));
    }

    public function check_user_permissions()
    {
        return is_user_logged_in();
    }

    public function get_models($request)
    {
        $openrouter_api = new AI_Chat_OpenRouter_API();
        $models = $openrouter_api->get_available_models();

        return rest_ensure_response(array(
            'success' => true,
            'models' => $models
        ));
    }

    public function get_conversations($request)
    {
        $conversation_manager = new AI_Chat_Conversation_Manager();
        $conversations = $conversation_manager->get_user_conversations(1, null, 50);

        return rest_ensure_response(array(
            'success' => true,
            'conversations' => $conversations
        ));
    }

    public function send_message($request)
    {
        $user_id = 1; // TEMPORAL
        $message = $request->get_param('message');
        $model = $request->get_param('model');
        $conversation_id = $request->get_param('conversation_id');

        $response = $this->chat_handler->process_message($user_id, $message, $model, $conversation_id);

        if (isset($response['error'])) {
            return new WP_Error('chat_error', $response['error'], array('status' => 400));
        }

        return rest_ensure_response($response);
    }

    public function get_projects($request)
    {
        $projects_manager = new AI_Chat_Projects_Manager();
        $projects = $projects_manager->get_user_projects(1);

        return rest_ensure_response(array(
            'success' => true,
            'projects' => $projects
        ));
    }

    public function create_project($request)
    {
        $projects_manager = new AI_Chat_Projects_Manager();
        $title = $request->get_param('title');
        $description = $request->get_param('description');

        $project_id = $projects_manager->create_project(1, $title, $description);

        if ($project_id) {
            return rest_ensure_response(array(
                'success' => true,
                'project_id' => $project_id,
                'message' => 'Proyecto creado exitosamente'
            ));
        }

        return rest_ensure_response(array(
            'success' => false,
            'message' => 'Error al crear proyecto'
        ));
    }

    public function create_conversation($request)
    {
        $conversation_manager = new AI_Chat_Conversation_Manager();
        $title = $request->get_param('title');
        $project_id = $request->get_param('project_id');

        $conversation_id = $conversation_manager->create_conversation(1, $title, $project_id);

        if ($conversation_id) {
            return rest_ensure_response(array(
                'success' => true,
                'conversation_id' => $conversation_id,
                'message' => 'Conversación creada exitosamente'
            ));
        }

        return rest_ensure_response(array(
            'success' => false,
            'message' => 'Error al crear conversación'
        ));
    }

    public function get_conversation_history($request)
    {
        $conversation_id = $request->get_param('conversation_id');

        if (!$conversation_id) {
            return new WP_Error('missing_param', 'conversation_id es requerido', array('status' => 400));
        }

        $user_id = get_current_user_id(); // O usar 1 si sigues con user temporal

        $conversation_manager = new AI_Chat_Conversation_Manager();

        // Obtener conversación
        $conversation = get_post($conversation_id);
        if (!$conversation) {
            return new WP_Error('not_found', 'Conversación no encontrada', array('status' => 404));
        }

        // Obtener mensajes
        $messages = $conversation_manager->get_conversation_messages($conversation_id, $user_id, 100);

        return rest_ensure_response(array(
            'success' => true,
            'conversation' => array(
                'id' => $conversation->ID,
                'title' => $conversation->post_title
            ),
            'messages' => $messages
        ));
    }
}

new AI_Chat_REST_API();
