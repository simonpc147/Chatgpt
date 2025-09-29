<div class="ai-chat-login-form">
    <h2>Iniciar Sesión</h2>

    <form id="ai-chat-login-form">
        <div class="form-group">
            <label for="username">Usuario o Email:</label>
            <input type="text" id="username" name="username" required>
        </div>

        <div class="form-group">
            <label for="password">Contraseña:</label>
            <input type="password" id="password" name="password" required>
        </div>

        <div class="form-group">
            <button type="submit" class="btn-primary">Iniciar Sesión</button>
        </div>

        <div class="form-links">
            <p>¿No tienes cuenta? <a href="#register">Regístrate aquí</a></p>
            <p><a href="<?php echo wp_lostpassword_url(); ?>">¿Olvidaste tu contraseña?</a></p>
        </div>
    </form>

    <div id="login-message" class="form-message"></div>
</div>