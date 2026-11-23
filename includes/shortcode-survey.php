<?php
if (!defined('ABSPATH')) exit;

function tes_email_verification_enabled() {
    return get_option('tes_student_email_verification_enabled', '0') === '1';
}

function tes_student_verification_transient_key($token) {
    return 'tes_student_verify_' . hash('sha256', $token);
}

function tes_set_student_login_cookie($student_id) {
    $value = absint($student_id) . '.' . hash_hmac('sha256', (string) absint($student_id), wp_salt('auth'));
    setcookie('tes_student_id', $value, time() + DAY_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true);
    $_COOKIE['tes_student_id'] = $value;
}

function tes_get_student_login_id() {
    if (empty($_COOKIE['tes_student_id']) || !is_string($_COOKIE['tes_student_id'])) {
        return 0;
    }

    $cookie_parts = explode('.', sanitize_text_field(wp_unslash($_COOKIE['tes_student_id'])), 2);
    if (count($cookie_parts) !== 2 || !ctype_digit($cookie_parts[0])) {
        return 0;
    }

    $student_id = absint($cookie_parts[0]);
    $expected_signature = hash_hmac('sha256', (string) $student_id, wp_salt('auth'));
    return hash_equals($expected_signature, $cookie_parts[1]) ? $student_id : 0;
}

function tes_clear_student_cookie($cookie_name) {
    setcookie($cookie_name, '', time() - HOUR_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true);
    unset($_COOKIE[$cookie_name]);
}

function tes_handle_student_auth() {
    if (is_admin()) return;

    if (isset($_POST['tes_student_login'])) {
        global $wpdb;
        if (
            !isset($_POST['tes_login_nonce']) || !is_string($_POST['tes_login_nonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tes_login_nonce'])), 'tes_student_login')
        ) {
            wp_die('Security check failed. Please reload the page and try again.');
        }

        if (
            !isset($_POST['username'], $_POST['password'])
            || !is_string($_POST['username']) || !is_string($_POST['password'])
        ) {
            wp_safe_redirect(add_query_arg('tes_login_error', '1'));
            exit;
        }

        $username = sanitize_text_field(wp_unslash($_POST['username']));
        $password = wp_unslash($_POST['password']);
        $student = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}tes_students WHERE username = %s", $username));

        if ($student && (wp_check_password($password, $student->password) || $password === $student->password)) {
            if (tes_email_verification_enabled()) {
                if (empty($student->email) || !is_email($student->email)) {
                    wp_safe_redirect(add_query_arg('tes_login_notice', 'email_missing', remove_query_arg(['tes_login_error', 'tes_login_notice', 'tes_verify', 'tes_verify_error'])));
                    exit;
                }

                $cooldown_key = 'tes_student_mail_cooldown_' . absint($student->id);
                $existing_token = isset($_COOKIE['tes_student_verification']) && is_string($_COOKIE['tes_student_verification'])
                    ? sanitize_text_field(wp_unslash($_COOKIE['tes_student_verification']))
                    : '';
                $existing_challenge = $existing_token ? get_transient(tes_student_verification_transient_key($existing_token)) : false;
                if (get_transient($cooldown_key) && $existing_challenge && (int) $existing_challenge['student_id'] === (int) $student->id) {
                    wp_safe_redirect(add_query_arg('tes_verify', '1', remove_query_arg(['tes_login_error', 'tes_login_notice', 'tes_verify_error'])));
                    exit;
                }
                if (get_transient($cooldown_key)) {
                    wp_safe_redirect(add_query_arg('tes_login_notice', 'send_wait', remove_query_arg(['tes_login_error', 'tes_login_notice', 'tes_verify', 'tes_verify_error'])));
                    exit;
                }

                $verification_code = sprintf('%06d', wp_rand(0, 999999));
                $verification_token = wp_generate_password(48, false, false);
                $challenge = [
                    'student_id' => (int) $student->id,
                    'code_hash' => wp_hash_password($verification_code),
                    'attempts' => 0,
                    'expires_at' => time() + 10 * MINUTE_IN_SECONDS,
                ];
                $challenge_key = tes_student_verification_transient_key($verification_token);
                set_transient($challenge_key, $challenge, 10 * MINUTE_IN_SECONDS);
                setcookie('tes_student_verification', $verification_token, time() + 10 * MINUTE_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true);
                $_COOKIE['tes_student_verification'] = $verification_token;

                $subject = sprintf('[%s] Student login verification code', wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES));
                $message = "Your student login verification code is: {$verification_code}\n\nThis code expires in 10 minutes. If you did not request it, you can ignore this email.";
                if (!wp_mail($student->email, $subject, $message)) {
                    delete_transient($challenge_key);
                    tes_clear_student_cookie('tes_student_verification');
                    wp_safe_redirect(add_query_arg('tes_login_notice', 'email_failed', remove_query_arg(['tes_login_error', 'tes_login_notice', 'tes_verify', 'tes_verify_error'])));
                    exit;
                }

                set_transient($cooldown_key, 1, MINUTE_IN_SECONDS);
                wp_safe_redirect(add_query_arg('tes_verify', '1', remove_query_arg(['tes_login_error', 'tes_login_notice', 'tes_verify_error'])));
                exit;
            }

            tes_set_student_login_cookie($student->id);
            wp_safe_redirect(remove_query_arg(['tes_login_error', 'tes_login_notice', 'tes_verify', 'tes_verify_error']));
            exit;
        } else {
            wp_safe_redirect(add_query_arg('tes_login_error', '1', remove_query_arg(['tes_login_notice', 'tes_verify', 'tes_verify_error'])));
            exit;
        }
    }

    if (isset($_POST['tes_student_verify'])) {
        if (!tes_email_verification_enabled()) {
            wp_safe_redirect(remove_query_arg(['tes_verify', 'tes_verify_error']));
            exit;
        }

        $token = isset($_COOKIE['tes_student_verification']) && is_string($_COOKIE['tes_student_verification'])
            ? sanitize_text_field(wp_unslash($_COOKIE['tes_student_verification']))
            : '';
        $challenge = $token ? get_transient(tes_student_verification_transient_key($token)) : false;
        if (
            !$challenge || !isset($_POST['tes_verify_nonce']) || !is_string($_POST['tes_verify_nonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tes_verify_nonce'])), 'tes_student_verify_' . $token)
        ) {
            wp_safe_redirect(add_query_arg(['tes_verify' => '1', 'tes_verify_error' => 'expired']));
            exit;
        }

        if ((int) $challenge['expires_at'] < time() || (int) $challenge['attempts'] >= 5) {
            delete_transient(tes_student_verification_transient_key($token));
            tes_clear_student_cookie('tes_student_verification');
            wp_safe_redirect(add_query_arg(['tes_verify' => '1', 'tes_verify_error' => 'expired']));
            exit;
        }

        $code = isset($_POST['verification_code']) && is_string($_POST['verification_code'])
            ? preg_replace('/\D/', '', wp_unslash($_POST['verification_code']))
            : '';
        if (!preg_match('/^\d{6}$/', $code) || !wp_check_password($code, $challenge['code_hash'])) {
            $challenge['attempts']++;
            set_transient(tes_student_verification_transient_key($token), $challenge, max(1, (int) $challenge['expires_at'] - time()));
            wp_safe_redirect(add_query_arg(['tes_verify' => '1', 'tes_verify_error' => 'invalid']));
            exit;
        }

        delete_transient(tes_student_verification_transient_key($token));
        tes_clear_student_cookie('tes_student_verification');
        tes_set_student_login_cookie($challenge['student_id']);
        wp_safe_redirect(remove_query_arg(['tes_login_error', 'tes_login_notice', 'tes_verify', 'tes_verify_error']));
        exit;
    }

    if (isset($_GET['tes_action']) && $_GET['tes_action'] === 'logout') {
        if (
            !isset($_GET['_wpnonce']) || !is_string($_GET['_wpnonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'tes_student_logout')
        ) {
            wp_die('Security check failed. Please reload the page and try again.');
        }
        tes_clear_student_cookie('tes_student_id');
        tes_clear_student_cookie('tes_student_verification');
        wp_safe_redirect(remove_query_arg(['tes_action', '_wpnonce']));
        exit;
    }
}
add_action('init', 'tes_handle_student_auth');

function tes_student_survey_shortcode() {
    global $wpdb;

    $student_id = tes_get_student_login_id();
    $current_student = null;
    if ($student_id) {
        $current_student = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}tes_students WHERE id = %d", $student_id));
        if (!$current_student) $student_id = 0;
    }

    // Fetch unique options for student selection controls
    $classes = $wpdb->get_col("
        SELECT DISTINCT class_name FROM (
            SELECT class_name FROM {$wpdb->prefix}tes_teachers WHERE class_name != ''
            UNION
            SELECT class_name FROM {$wpdb->prefix}tes_students WHERE class_name != ''
        ) AS c ORDER BY class_name ASC
    ");
    if ($current_student && !empty($current_student->class_name) && !in_array($current_student->class_name, $classes)) {
        array_unshift($classes, $current_student->class_name);
    }

    $sessions = $wpdb->get_col("SELECT DISTINCT session FROM {$wpdb->prefix}tes_students WHERE session != '' ORDER BY session DESC");
    if ($current_student && !empty($current_student->session) && !in_array($current_student->session, $sessions)) {
        array_unshift($sessions, $current_student->session);
    }

    $batches = $wpdb->get_col("SELECT DISTINCT batch_name FROM {$wpdb->prefix}tes_students WHERE batch_name != '' ORDER BY batch_name ASC");
    if ($current_student && !empty($current_student->batch_name) && !in_array($current_student->batch_name, $batches)) {
        array_unshift($batches, $current_student->batch_name);
    }

    $phases = $wpdb->get_col("
        SELECT DISTINCT phase FROM (
            SELECT phase FROM {$wpdb->prefix}tes_teachers WHERE phase != ''
            UNION
            SELECT phase FROM {$wpdb->prefix}tes_students WHERE phase != ''
        ) AS p ORDER BY phase ASC
    ");
    if ($current_student && !empty($current_student->phase) && !in_array($current_student->phase, $phases)) {
        array_unshift($phases, $current_student->phase);
    }

    $groups = $wpdb->get_col("
        SELECT DISTINCT `group` FROM (
            SELECT `group` FROM {$wpdb->prefix}tes_students WHERE `group` IS NOT NULL AND `group` != ''
            UNION
            SELECT `group` FROM {$wpdb->prefix}tes_teachers WHERE `group` IS NOT NULL AND `group` != ''
            UNION
            SELECT `group` FROM {$wpdb->prefix}tes_surveys WHERE `group` IS NOT NULL AND `group` != '' AND `group` != 'all'
        ) AS g ORDER BY `group` ASC
    ");
    if ($current_student && !empty($current_student->group) && !in_array($current_student->group, $groups)) {
        array_unshift($groups, $current_student->group);
    }

    ob_start();

    if (!$student_id) {
        $error = isset($_GET['tes_login_error']);
        $verification_pending = tes_email_verification_enabled() && isset($_GET['tes_verify']);
        $login_notice = isset($_GET['tes_login_notice']) ? sanitize_key(wp_unslash($_GET['tes_login_notice'])) : '';
        $verification_error = isset($_GET['tes_verify_error']) ? sanitize_key(wp_unslash($_GET['tes_verify_error'])) : '';
        ?>
        <style>
            body {
                background: #000 !important;
            }
            .tes-login-wrapper input::placeholder {
                color: #eee;
                opacity: 0.7;
            }
            .tes-login-wrapper input:-ms-input-placeholder {
                color: #eee;
            }
            .tes-login-wrapper input::-ms-input-placeholder {
                color: #eee;
            }
            .tes-login-btn:hover {
                background-color: #555 !important;
            }
        </style>
        <div class="tes-login-wrapper" style="max-width: 400px; margin: 40px auto; padding: 30px; background: #424242; /* Gray Box */ border-radius: 10px; box-shadow: 0 0 20px rgba(255,255,255,0.1); font-family: sans-serif;">
            <h2 style="text-align: center; margin-bottom: 20px; color: #fff;"><?php echo $verification_pending ? 'Verify Your Email' : 'Student Login'; ?></h2>
            <?php if ($error): ?>
                <div style="background: #ffebee; color: #c62828; padding: 12px; border-radius: 4px; margin-bottom: 20px; text-align: center; border: 1px solid #ef9a9a;">Invalid username or password.</div>
            <?php endif; ?>
            <?php
            $login_notices = [
                'email_missing' => 'Your student account does not have a valid email address. Please contact the administrator.',
                'email_failed' => 'The verification email could not be sent. Please try again later or contact the administrator.',
                'send_wait' => 'A verification code was recently sent. Please wait one minute before requesting another.',
            ];
            if (isset($login_notices[$login_notice])) :
            ?>
                <div style="background: #ffebee; color: #c62828; padding: 12px; border-radius: 4px; margin-bottom: 20px; text-align: center; border: 1px solid #ef9a9a;"><?php echo esc_html($login_notices[$login_notice]); ?></div>
            <?php endif; ?>
            <?php if ($verification_pending): ?>
                <?php if ($verification_error === 'invalid'): ?>
                    <div style="background: #ffebee; color: #c62828; padding: 12px; border-radius: 4px; margin-bottom: 20px; text-align: center; border: 1px solid #ef9a9a;">That code is incorrect. You have a maximum of five attempts.</div>
                <?php elseif ($verification_error === 'expired'): ?>
                    <div style="background: #ffebee; color: #c62828; padding: 12px; border-radius: 4px; margin-bottom: 20px; text-align: center; border: 1px solid #ef9a9a;">This verification request expired. Please sign in again to request a new code.</div>
                <?php endif; ?>
                <p style="color: #fff; text-align: center;">Enter the six-digit code sent to your student email address.</p>
                <form method="post">
                    <input type="hidden" name="tes_verify_nonce" value="<?php
                        $verification_token = isset($_COOKIE['tes_student_verification']) && is_string($_COOKIE['tes_student_verification'])
                            ? sanitize_text_field(wp_unslash($_COOKIE['tes_student_verification']))
                            : '';
                        echo esc_attr(wp_create_nonce('tes_student_verify_' . $verification_token));
                    ?>">
                    <div style="margin-bottom: 20px;">
                        <label for="tes_verification_code" style="display: block; margin-bottom: 8px; font-weight: 600; color: #fff;">Verification code</label>
                        <input type="text" name="verification_code" id="tes_verification_code" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" style="width: 100%; padding: 12px; border: 1px solid #757575; border-radius: 4px; box-sizing: border-box; font-size: 16px; background: #616161; color: #fff;">
                    </div>
                    <button type="submit" name="tes_student_verify" value="1" class="tes-login-btn" style="width: 100%; padding: 12px; background: #333; color: white; border: 1px solid #555; border-radius: 6px; font-size: 16px; font-weight: bold; text-transform: uppercase; cursor: pointer;">Verify and Login</button>
                </form>
                <p style="text-align: center; margin-top: 15px;"><a href="<?php echo esc_url(remove_query_arg(['tes_verify', 'tes_verify_error'])); ?>" style="color: #fff;">Return to Student Login</a></p>
            <?php else: ?>
            <form method="post">
                <?php wp_nonce_field('tes_student_login', 'tes_login_nonce'); ?>
                <div style="margin-bottom: 20px;">
                    <label for="tes_username" style="display: block; margin-bottom: 8px; font-weight: 600; color: #fff;">Username</label>
                    <input type="text" name="username" id="tes_username" required placeholder="Username" style="width: 100%; padding: 12px; border: 1px solid #757575; border-radius: 4px; box-sizing: border-box; font-size: 16px; background: #616161; color: #fff;">
                </div>
                <div style="margin-bottom: 25px;">
                    <label for="tes_password" style="display: block; margin-bottom: 8px; font-weight: 600; color: #fff;">Password</label>
                    <div style="position: relative;">
                        <input type="password" name="password" id="tes_password" required placeholder="Password" style="width: 100%; padding: 12px 40px 12px 12px; border: 1px solid #757575; border-radius: 4px; box-sizing: border-box; font-size: 16px; background: #616161; color: #fff;">
                        <span id="tes-toggle-password" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #BDBDBD; user-select: none; font-size: 18px;">&#128065;</span>
                    </div>
                </div>
                <button type="submit" name="tes_student_login" value="1" class="tes-login-btn" style="width: 100%; padding: 12px; background: #333; color: white; border: 1px solid #555; border-radius: 6px; font-size: 16px; font-weight: bold; text-transform: uppercase; cursor: pointer; transition: all 0.3s;">Login</button>
            </form>
            <?php endif; ?>
            <div style="text-align: center; margin-top: 15px; color: #ccc; font-size: 12px;">Developed by IT Department (Rangpur Group)-2026</div>
        </div>
        <script>
        (function() {
            const togglePassword = document.getElementById('tes-toggle-password');
            const password = document.getElementById('tes_password');

            if (togglePassword && password) {
                togglePassword.addEventListener('click', function () {
                    const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                    password.setAttribute('type', type);
                    this.innerHTML = type === 'password' ? '&#128065;' : '&#128584;';
                });
            }
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    ?>

    <style>
        /* Responsive Styles for 320px, 375px, 425px, 768px, 1024px, 1440px */
        .tes-survey-select {
            width: 100% !important;
            max-width: 100% !important;
            box-sizing: border-box !important;
        }

        @media only screen and (max-width: 768px) {
            .tes-survey-form {
                padding: 20px !important;
                width: 92% !important;
                margin: 15px auto !important;
            }
            .tes-header-info {
                width: 92% !important;
                max-width: 92% !important;
            }
        }
        @media only screen and (max-width: 425px) {
            .tes-survey-form {
                padding: 15px !important;
                width: 96% !important;
            }
            .tes-header-info {
                width: 96% !important;
                max-width: 96% !important;
                flex-direction: column;
                align-items: flex-start !important;
                gap: 10px;
            }
            .tes-header-info a {
                align-self: flex-end;
            }
        }
        @media only screen and (max-width: 375px) {
            .tes-survey-form {
                padding: 10px !important;
                width: 98% !important;
            }
            .tes-survey-select {
                font-size: 14px !important;
                padding: 8px !important;
            }
            .tes-header-info {
                width: 98% !important;
                max-width: 98% !important;
            }
        }
    </style>
    <div class="tes-survey-container">
        <div class="tes-header-info" style="max-width: 800px; margin: 0 auto 10px; display: flex; justify-content: space-between; align-items: center; background: #f8f9fa; padding: 10px 15px; border-radius: 8px;">
            <div>
                <div>Logged in as: <strong><?php echo esc_html($current_student->student_name); ?></strong></div>
                <div style="font-size: 0.9em; color: #555; margin-top: 2px;">
                    <?php echo esc_html($current_student->class_name); ?>
                    <?php if (!empty($current_student->phase)) echo ' - ' . esc_html($current_student->phase); ?>
                    <?php if (!empty($current_student->group)) echo ' - Group: ' . esc_html($current_student->group); ?>
                </div>
            </div>
            <a href="<?php echo esc_url(wp_nonce_url(add_query_arg('tes_action', 'logout'), 'tes_student_logout')); ?>" style="color: #dc3545; text-decoration: none; font-weight: bold; font-size: 14px;">Logout</a>
        </div>

    <div class="tes-survey-form" style="background: #fff; padding: 30px; border: 1px solid #e5e5e5; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin: 20px auto; max-width: 800px;">

        <form class="tes-survey-form" style="" novalidate>

            <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('tes_submit_survey'); ?>">

            <input type="hidden" name="student_name" value="<?php echo esc_attr($current_student->student_name); ?>">
            <input type="hidden" name="student_id" value="<?php echo esc_attr($current_student->id); ?>">

            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 18px; margin-bottom: 20px;">
                <div style="font-size: 13.5px; font-weight: 700; color: #1e293b; margin-bottom: 12px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
                    <span>Evaluation Criteria Selection</span>
                    <?php if (!empty($current_student->group)): ?>
                        <span style="background: #e0f2fe; color: #0369a1; font-size: 12px; font-weight: 600; padding: 3px 10px; border-radius: 999px; border: 1px solid #bae6fd;">
                            Assigned Group: <?php echo esc_html($current_student->group); ?>
                        </span>
                    <?php endif; ?>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(135px, 1fr)); gap: 12px;">
                    <div>
                        <label for="tes_filter_class" style="font-weight: 600; font-size: 12px; color: #475569; margin-bottom: 4px; display: block;">Class <span style="font-size: 10px; color: #64748b; font-weight: 600;">(Locked)</span></label>
                        <select name="class_name_display" id="tes_filter_class" disabled style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13.5px; background: #f1f5f9; cursor: not-allowed; color: #334155; font-weight: 500;">
                            <option value="<?php echo esc_attr($current_student->class_name); ?>" selected><?php echo esc_html($current_student->class_name ?: 'No Class Assigned'); ?></option>
                        </select>
                        <input type="hidden" name="class_name" class="tes-class-auto tes-class-input" value="<?php echo esc_attr($current_student->class_name); ?>">
                    </div>

                    <div>
                        <label for="tes_filter_group" style="font-weight: 600; font-size: 12px; color: #0f172a; margin-bottom: 4px; display: block;">Group <span style="font-size: 10px; color: #2563eb; font-weight: 700;">(Select)</span></label>
                        <select name="group" id="tes_filter_group" class="tes-group-select tes-survey-filter" style="width: 100%; padding: 8px; border: 1.5px solid #2563eb; border-radius: 6px; font-size: 13.5px; background: #fff; cursor: pointer; color: #0f172a; font-weight: 600;">
                            <option value="">Select Group</option>
                            <option value="all" <?php selected($current_student->group, 'all'); ?>>All Groups</option>
                            <?php foreach ($groups as $g): ?>
                                <option value="<?php echo esc_attr($g); ?>" <?php selected($current_student->group, $g); ?>>Group <?php echo esc_html($g); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="tes_filter_session" style="font-weight: 600; font-size: 12px; color: #475569; margin-bottom: 4px; display: block;">Session <span style="font-size: 10px; color: #64748b; font-weight: 600;">(Locked)</span></label>
                        <select name="session_display" id="tes_filter_session" disabled style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13.5px; background: #f1f5f9; cursor: not-allowed; color: #334155; font-weight: 500;">
                            <option value="<?php echo esc_attr($current_student->session); ?>" selected><?php echo esc_html($current_student->session ?: 'N/A'); ?></option>
                        </select>
                        <input type="hidden" name="session" class="tes-session-input" value="<?php echo esc_attr($current_student->session); ?>">
                    </div>

                    <div>
                        <label for="tes_filter_batch" style="font-weight: 600; font-size: 12px; color: #475569; margin-bottom: 4px; display: block;">Batch <span style="font-size: 10px; color: #64748b; font-weight: 600;">(Locked)</span></label>
                        <select name="batch_name_display" id="tes_filter_batch" disabled style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13.5px; background: #f1f5f9; cursor: not-allowed; color: #334155; font-weight: 500;">
                            <option value="<?php echo esc_attr($current_student->batch_name); ?>" selected><?php echo esc_html($current_student->batch_name ?: 'N/A'); ?></option>
                        </select>
                        <input type="hidden" name="batch_name" class="tes-batch-input" value="<?php echo esc_attr($current_student->batch_name); ?>">
                    </div>

                    <div>
                        <label for="tes_filter_phase" style="font-weight: 600; font-size: 12px; color: #475569; margin-bottom: 4px; display: block;">Phase <span style="font-size: 10px; color: #64748b; font-weight: 600;">(Locked)</span></label>
                        <select name="phase_display" id="tes_filter_phase" disabled style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13.5px; background: #f1f5f9; cursor: not-allowed; color: #334155; font-weight: 500;">
                            <option value="<?php echo esc_attr($current_student->phase); ?>" selected><?php echo esc_html($current_student->phase ?: 'N/A'); ?></option>
                        </select>
                        <input type="hidden" name="phase" class="tes-phase-auto tes-phase-input" value="<?php echo esc_attr($current_student->phase); ?>">
                    </div>
                </div>
            </div>

            <div class="tes-no-survey-msg" style="display:none; color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; padding: 10px; margin-bottom: 15px; border-radius: 8px;">
                No survey available now
            </div>

            <select name="survey_id"
                    class="tes-survey-select"
                    required
                    style="width:100%; max-width: 100%; box-sizing: border-box; margin-bottom:15px; padding: 10px; border: 2px solid #ddd; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); font-size: 16px; transition: all 0.3s ease; background: linear-gradient(145deg, #ffffff, #e6e6e6); display: none;">
                <option value="">Select Teacher Survey</option>
            </select>

            <div class="tes-questions-area" style="margin-bottom: 15px;"></div>

            <div class="tes-comment-section" style="margin-bottom: 15px; display: none;">
                <label style="font-weight: bold; display: block; margin-bottom: 5px; color: #333;">If any other comments please write down below (Optional)</label>
                <textarea name="comment" rows="4" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-family: inherit;"></textarea>
            </div>

            <button type="submit"
                    class="button button-primary"
                    style="margin-top:15px; padding: 12px 24px; border: none; border-radius: 6px; background: gray; color: white; font-size: 16px; font-weight: bold; box-shadow: 0 6px 12px rgba(0,0,0,0.2); cursor: pointer; transition: all 0.3s ease; transform: translateY(0);">
                Submit Feedback
            </button>

        </form>

        <div class="tes-success-msg" style="display:none; color: #155724; background-color: #d4edda; border-color: #c3e6cb; padding: 15px; margin-top: 20px; border: 1px solid #c3e6cb; border-radius: 4px;">
            ✔ Feedback submitted successfully.
        </div>

    </div>
    </div>

    <?php
    return ob_get_clean();
}

add_shortcode('teacher_survey', 'tes_student_survey_shortcode');
