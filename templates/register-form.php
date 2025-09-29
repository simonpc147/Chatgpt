<div class="ai-chat-register-form">
    <h2>Crear Cuenta</h2>

    <form id="ai-chat-register-form">
        <div class="form-group">
            <label for="reg-username">Nombre de Usuario:</label>
            <input type="text" id="reg-username" name="username" required>
        </div>

        <div class="form-group">
            <label for="reg-email">Email:</label>
            <input type="email" id="reg-email" name="email" required>
        </div>

        <div class="form-group">
            <label for="reg-password">Contraseña:</label>
            <input type="password" id="reg-password" name="password" required>
        </div>

        <div class="form-group">
            <label for="plan">Selecciona tu Plan:</label>
            <select id="plan" name="plan" required>
                <option value="free">Free - 100 mensajes/mes</option>
                <option value="premium">Premium - 1000 mensajes/mes</option>
                <option value="enterprise">Enterprise - Ilimitado</option>
            </select>
        </div>

        <div class="form-group">
            <button type="submit" class="btn-primary">Crear Cuenta</button>
        </div>

        <div class="form-links">
            <p>¿Ya tienes cuenta? <a href="#login">Inicia sesión aquí</a></p>
        </div>
    </form>

    <div id="register-message" class="form-message"></div>
</div>