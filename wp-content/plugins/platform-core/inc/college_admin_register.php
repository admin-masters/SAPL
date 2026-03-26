<?php
add_shortcode('college_admin_register', 'college_admin_registration_form');

function college_admin_registration_form() {
    // 1. Handle Form Submission
    if (isset($_POST['college_admin_register_submit'])) {
        
        $institution_name = sanitize_text_field($_POST['institution_name']);
        $email            = sanitize_email($_POST['user_email']);
        $contact_number   = sanitize_text_field($_POST['contact_number']);
        $contact_name     = sanitize_text_field($_POST['contact_name']);
        $username         = sanitize_user($_POST['user_login']);
        $password         = $_POST['user_pass'];
        $confirm_pass     = $_POST['user_pass_confirm'];

        if (username_exists($username) || email_exists($email)) {
   	 echo '<script>window.location.href = "' . home_url('/login/') . '";</script>';
  	  return;
	}
        $errors = array();

        if ($password !== $confirm_pass) {
            $errors[] = "Passwords do not match.";
        }

        if (empty($errors)) {
            $user_id = wp_create_user($username, $password, $email);

            if (!is_wp_error($user_id)) {
                update_user_meta($user_id, 'institution_name', $institution_name);
                update_user_meta($user_id, 'contact_number', $contact_number);
                update_user_meta($user_id, 'contact_name', $contact_name);
                
                echo '<div class="ca-alert ca-success">Registration Successful! Redirecting...</div>';
                echo '<script>window.location = "'.home_url('/login/').'";</script>';
            } else {
                $errors[] = $user_id->get_error_message();
            }
        }

        if (!empty($errors)) {
            foreach ($errors as $error) {
                echo '<div class="ca-alert ca-error">' . $error . '</div>';
            }
        }
    }

    // 2. Output HTML
    ob_start();
    ?>
    
    <div class="ca-wrapper">
        <div class="ca-container">
            <div class="ca-header">
                <h2>Welcome to Our Platform</h2>
                <p>Please create a new account</p>
            </div>

            <div class="ca-card">
                <div class="ca-card-title">Register</div>

                <form method="post" class="ca-form">
                    <div class="ca-group">
                        <label>Medical Institution Name</label>
                        <input type="text" name="institution_name" required>
                    </div>
                    <div class="ca-group">
                        <label>Email Address</label>
                        <input type="email" name="user_email" required>
                    </div>
                    <div class="ca-group">
                        <label>Contact Person Number</label>
                        <input type="text" name="contact_number" required>
                    </div>
                    <div class="ca-group">
                        <label>Contact Person Name</label>
                        <input type="text" name="contact_name" required>
                    </div>
                    <div class="ca-group">
                        <label>Username</label>
                        <input type="text" name="user_login" required>
                    </div>
                    <div class="ca-group">
                        <label>Password</label>
                        <input type="password" name="user_pass" required>
                    </div>
                    <div class="ca-group">
                        <label>Confirm Password</label>
                        <input type="password" name="user_pass_confirm" required>
                    </div>

                    <button type="submit" name="college_admin_register_submit" class="ca-btn">Create Account</button>
                    
                    <div class="ca-footer-link">
                        Already have an account? <a href="<?php echo home_url('/login/'); ?>">Login</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}