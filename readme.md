ARCHIVOS Y SU FINALIDAD
ai-chat-manager.php

Archivo principal del plugin
Define constantes y configuración base
Maneja activación/desactivación
Include de otros archivos

includes/database.php

Crear tabla wp_ai_user_keys
Funciones CRUD para gestión de keys
Asignación automática de key en registro
Consultas optimizadas

includes/admin-panel.php

Menú de administración en WordPress
Lista de usuarios con sus keys y planes
Formularios para editar keys individuales
Página de configuración global

includes/functions.php

Función obtener key por usuario
Función actualizar key por usuario
Función validar key
Helpers varios

assets/css/admin.css

Estilos para panel de administración
Modal para edición de keys
Tablas responsive

assets/js/admin.js

Modal para editar keys
AJAX para actualizaciones rápidas
Validaciones de formulario

FINALIDAD DEL PLUGIN
Para el Administrador:

Ver lista completa de usuarios registrados
Gestionar API key individual de cada usuario
Cambiar plan de cada usuario (free, premium, enterprise)
Configurar key por defecto para nuevos registros
Tracking de qué key usa cada usuario

Para el Sistema:

Crear tabla dedicada para keys de usuarios
Asignar automáticamente key por defecto en registro
Proveer funciones para obtener key según usuario
Base para control de gastos por usuario en OpenRouter

Control de Gastos:

Cada usuario tiene su propia key en OpenRouter
Admin puede ver gastos individuales en OpenRouter
Facilita control de límites y facturación

ARCHIVOS Y SU FINALIDAD:
class-openrouter-api.php

Conexión con OpenRouter API
Envío de mensajes con key específica del usuario
Manejo de respuestas y errores
Control de modelos disponibles

class-chat-handler.php

Lógica principal del chat
Validación de permisos de usuario
Preparación de mensajes para API
Guardado de conversaciones

class-rest-api.php

Endpoints personalizados para el frontend
/wp-json/ai-chat/v1/send-message
/wp-json/ai-chat/v1/get-models
Autenticación de usuarios

class-models-manager.php

Lista de modelos disponibles
Configuración por plan de usuario
Límites y restricciones

ARCHIVOS Y SU FINALIDAD:
class-projects-manager.php

Crear y gestionar proyectos de usuario
Organizar conversaciones por proyecto
CRUD completo de proyectos

class-conversation-manager.php

Gestionar conversaciones individuales
Guardar historial de mensajes
Relación con proyectos

Actualizar database.php

Crear tablas adicionales necesarias
Optimizar queries para proyectos

Actualizar ai-chat-manager.php

Incluir nuevas clases
Actualizar Custom Post Types

Usar shortcodes [ai_chat_register] y [ai_chat_login] en páginas

📄 Archivo 1: image-compressor.js
📍 Ubicación: /assets/js/image-compressor.js
¿Qué hace?
Comprime imágenes en el navegador ANTES de subirlas al servidor:

✅ Redimensiona a máximo 1920x1920px
✅ Comprime a 85% calidad JPEG
✅ Genera miniaturas de 400x400px
✅ Reduce típicamente 80-90% el peso
✅ Muestra estadísticas en consola

Ejemplo: Imagen de 5MB → se convierte a 800KB antes de subir.
