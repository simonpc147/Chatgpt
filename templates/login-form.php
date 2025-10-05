<?php
if (is_user_logged_in()) {
    wp_redirect(home_url('/ai-chat/'));
    exit;
}
?>

<div class="ai-chat-login-wrapper">
    <div class="login-container">
        <div class="login-header">
            <h1>Welcome to AI Chat!</h1>
            <p>Login to your account</p>
        </div>

        <form id="ai-chat-login-form">
            <div class="form-group">
                <label for="username">Email</label>
                <input type="text" id="username" name="username" placeholder="username@gmail.com" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" placeholder="Password" required>
                    <button type="button" class="toggle-password">👁️</button>
                </div>
            </div>

            <div class="forgot-password">
                <a href="<?php echo wp_lostpassword_url(); ?>">Forgot Password?</a>
            </div>

            <div class="form-group">
                <button type="submit" class="btn-login">Sign in</button>
            </div>

            <div class="form-links">
                <p>Don't have an account? <a href="#register">Sign up here</a></p>
            </div>
        </form>

        <div id="login-message" class="form-message"></div>
    </div>
</div>