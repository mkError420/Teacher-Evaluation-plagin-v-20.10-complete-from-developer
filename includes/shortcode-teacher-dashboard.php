<?php
if (!defined('ABSPATH')) exit;

/**
 * Cookie Helper Functions for Teacher Authentication
 */
function tes_get_teacher_login_id() {
    if (empty($_COOKIE['tes_teacher_id']) || !is_string($_COOKIE['tes_teacher_id'])) {
        return 0;
    }

    $raw = sanitize_text_field(wp_unslash($_COOKIE['tes_teacher_id']));
    if (strpos($raw, '.') !== false) {
        $parts = explode('.', $raw, 2);
        if (count($parts) === 2 && ctype_digit($parts[0])) {
            $id = absint($parts[0]);
            $expected = hash_hmac('sha256', (string) $id, wp_salt('auth'));
            if (hash_equals($expected, $parts[1])) {
                return $id;
            }
        }
        return 0;
    }

    return ctype_digit($raw) ? absint($raw) : 0;
}

function tes_set_teacher_login_cookie($teacher_id) {
    $id = absint($teacher_id);
    $signature = hash_hmac('sha256', (string) $id, wp_salt('auth'));
    $value = $id . '.' . $signature;
    setcookie('tes_teacher_id', $value, time() + 86400, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true);
    $_COOKIE['tes_teacher_id'] = $value;
}

function tes_clear_teacher_login_cookie() {
    setcookie('tes_teacher_id', '', time() - 3600, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true);
    unset($_COOKIE['tes_teacher_id']);
}

/**
 * Handle Teacher Authentication
 */
function tes_handle_teacher_auth() {
    if (is_admin()) return;

    // Login
    if (isset($_POST['tes_teacher_login'])) {
        if (!isset($_POST['tes_teacher_login_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tes_teacher_login_nonce'])), 'tes_teacher_login_action')) {
            wp_die('Security check failed. Please refresh the page and try again.');
        }

        if (empty($_POST['username']) || empty($_POST['password'])) {
            wp_safe_redirect(add_query_arg('tes_login_error', '1'));
            exit;
        }

        global $wpdb;
        $username = sanitize_text_field(wp_unslash($_POST['username']));
        $password = wp_unslash($_POST['password']);
        
        $teacher = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}tes_teachers WHERE username = %s", $username));

        if ($teacher && (wp_check_password($password, $teacher->password) || $password === $teacher->password)) {
            tes_set_teacher_login_cookie($teacher->id);
            wp_safe_redirect(remove_query_arg(['tes_login_error', 'tes_teacher_action']));
            exit;
        } else {
            wp_safe_redirect(add_query_arg('tes_login_error', '1'));
            exit;
        }
    }

    // Logout
    if (isset($_REQUEST['tes_teacher_action']) && $_REQUEST['tes_teacher_action'] === 'logout') {
        tes_clear_teacher_login_cookie();
        wp_safe_redirect(remove_query_arg(['tes_teacher_action', '_wpnonce', 'survey_id', 's']));
        exit;
    }
}
add_action('init', 'tes_handle_teacher_auth');

/**
 * Teacher Dashboard Shortcode [teacher_dashboard] and [teacher_login]
 */
function tes_teacher_dashboard_shortcode() {
    global $wpdb;

    $teacher_id = tes_get_teacher_login_id();
    $current_teacher = null;
    
    if ($teacher_id) {
        $current_teacher = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}tes_teachers WHERE id = %d", $teacher_id));
        if (!$current_teacher) {
            $teacher_id = 0;
            tes_clear_teacher_login_cookie();
        }
    }

    ob_start();

    // --- Login Form ---
    if (!$teacher_id) {
        $error = isset($_GET['tes_login_error']);
        ?>
        <div class="tes-login-wrapper" style="max-width: 420px; margin: 40px auto; padding: 35px; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
            <div style="text-align: center; margin-bottom: 25px;">
                <div style="width: 50px; height: 50px; background: #0284c7; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; color: #fff; font-size: 24px; margin-bottom: 10px;">&#128104;&#8205;&#127979;</div>
                <h2 style="margin: 0; color: #0f172a; font-size: 22px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Teacher Login</h2>
                <p style="margin: 5px 0 0; color: #64748b; font-size: 14px;">Sign in to view your evaluation results</p>
            </div>
            
            <?php if ($error): ?>
                <div style="background: #fff5f5; color: #c53030; padding: 12px; border-radius: 6px; margin-bottom: 20px; text-align: center; border: 1px solid #feb2b2; font-size: 14px;">
                    Invalid username or password. Please try again.
                </div>
            <?php endif; ?>

            <form method="post">
                <?php wp_nonce_field('tes_teacher_login_action', 'tes_teacher_login_nonce'); ?>
                <div style="margin-bottom: 20px;">
                    <label for="tes_teacher_username" style="display: block; margin-bottom: 8px; font-weight: 600; color: #334155; font-size: 14px;">Username</label>
                    <input type="text" name="username" id="tes_teacher_username" required placeholder="Enter your username" style="width: 100%; padding: 11px 12px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; font-size: 14px; transition: border-color 0.2s;" autocomplete="username">
                </div>
                <div style="margin-bottom: 25px;">
                    <label for="tes_teacher_password" style="display: block; margin-bottom: 8px; font-weight: 600; color: #334155; font-size: 14px;">Password</label>
                    <div style="position: relative;">
                        <input type="password" name="password" id="tes_teacher_password" required placeholder="Enter your password" style="width: 100%; padding: 11px 42px 11px 12px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; font-size: 14px; transition: border-color 0.2s;" autocomplete="current-password">
                        <span id="tes-toggle-teacher-pass" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #64748b; user-select: none; font-size: 18px;" title="Show/Hide Password">&#128065;</span>
                    </div>
                </div>
                <button type="submit" name="tes_teacher_login" value="1" style="width: 100%; padding: 12px; background: #0284c7; color: white; border: none; border-radius: 6px; font-size: 15px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; cursor: pointer; transition: background 0.15s;">Login</button>
            </form>
            <div style="text-align: center; margin-top: 20px; color: #94a3b8; font-size: 12px;">Developed by IT Department (Rangpur Group)-2026</div>
        </div>
        <script>
        (function() {
            var togglePassword = document.getElementById('tes-toggle-teacher-pass');
            var password = document.getElementById('tes_teacher_password');
            if (togglePassword && password) {
                togglePassword.addEventListener('click', function () {
                    var type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                    password.setAttribute('type', type);
                    this.innerHTML = type === 'password' ? '&#128065;' : '&#128584;';
                });
            }
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    // --- Role redirection if an advisor logs in here ---
    if (isset($current_teacher->role) && $current_teacher->role === 'advisor') {
        if (function_exists('tes_render_advisor_dashboard_content')) {
            $logout_url = add_query_arg('tes_teacher_action', 'logout');
            tes_render_advisor_dashboard_content($current_teacher, $logout_url);
            return ob_get_clean();
        }
    }

    $surveys_table     = $wpdb->prefix . 'tes_surveys';
    $questions_table   = $wpdb->prefix . 'tes_questions';
    $submissions_table = $wpdb->prefix . 'tes_submissions';

    // Handle Search
    $search_term = isset($_REQUEST['s']) ? sanitize_text_field(wp_unslash($_REQUEST['s'])) : '';

    // Get surveys for this teacher only
    $query = "SELECT * FROM $surveys_table WHERE teacher_id = %d";
    $query_args = [$teacher_id];

    if (!empty($search_term)) {
        $query .= " AND title LIKE %s";
        $query_args[] = '%' . $wpdb->esc_like($search_term) . '%';
    }
    $query .= " ORDER BY id DESC";
    $surveys = $wpdb->get_results($wpdb->prepare($query, ...$query_args));

    $selected_survey = isset($_REQUEST['survey_id']) ? intval($_REQUEST['survey_id']) : 0;
    
    // Verify survey belongs to this teacher
    if ($selected_survey) {
        $check_ownership = $wpdb->get_var($wpdb->prepare("SELECT id FROM $surveys_table WHERE id = %d AND teacher_id = %d", $selected_survey, $teacher_id));
        if (!$check_ownership) {
            $selected_survey = 0;
        }
    }
    ?>

    <div class="tes-dashboard-container" style="max-width: 1100px; margin: 25px auto; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
        
        <!-- Header Bar -->
        <div style="display: flex; justify-content: space-between; align-items: center; background: #ffffff; padding: 20px 24px; border-radius: 12px; margin-bottom: 25px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
            <div>
                <h2 style="margin: 0; font-size: 22px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 24px;">&#128104;&#8205;&#127979;</span> Teacher Portal: <?php echo esc_html($current_teacher->name); ?>
                </h2>
                <div style="color: #64748b; font-size: 13.5px; margin-top: 5px; display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
                    <?php if (!empty($current_teacher->department)): ?>
                        <span><strong>Dept:</strong> <?php echo esc_html($current_teacher->department); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($current_teacher->class_name)): ?>
                        <span style="background: #f1f5f9; padding: 2px 8px; border-radius: 4px; border: 1px solid #e2e8f0;"><strong>Class:</strong> <?php echo esc_html($current_teacher->class_name); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($current_teacher->phase)): ?>
                        <span style="background: #f1f5f9; padding: 2px 8px; border-radius: 4px; border: 1px solid #e2e8f0;"><strong>Phase:</strong> <?php echo esc_html($current_teacher->phase); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($current_teacher->group)): ?>
                        <span style="background: #f1f5f9; padding: 2px 8px; border-radius: 4px; border: 1px solid #e2e8f0;"><strong>Group:</strong> <?php echo esc_html($current_teacher->group); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($current_teacher->teacher_id_number)): ?>
                        <span style="color: #94a3b8;">ID: <?php echo esc_html($current_teacher->teacher_id_number); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <a href="<?php echo esc_url(add_query_arg('tes_teacher_action', 'logout')); ?>" style="background: #ef4444; color: #fff; text-decoration: none; font-weight: 600; padding: 8px 16px; border-radius: 6px; font-size: 13.5px; transition: background 0.15s;" onmouseover="this.style.background='#dc2626'" onmouseout="this.style.background='#ef4444'">Logout</a>
        </div>

        <!-- Filter & Search Toolbar (Unified, Simple, Modern - No Change Option) -->
        <div class="tes-filter-card" style="background: #ffffff; padding: 18px 22px; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); margin-bottom: 25px;">
            <form method="get" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
                
                <!-- Search Input with Autocomplete -->
                <div style="position: relative; flex: 1; min-width: 240px; max-width: 360px;">
                    <input type="text" name="s" id="tes_teacher_search_input" value="<?php echo esc_attr($search_term); ?>" placeholder="Search survey title..." style="width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13.5px; box-sizing: border-box;" autocomplete="off">
                    <div id="tes_teacher_search_suggestions" style="display:none; position: absolute; top: calc(100% + 4px); left: 0; right: 0; background: #fff; border: 1px solid #cbd5e1; border-radius: 6px; z-index: 1000; max-height: 280px; overflow-y: auto; box-shadow: 0 4px 15px rgba(0,0,0,0.1);"></div>
                </div>

                <!-- Select Survey Dropdown (No Change Option) -->
                <div style="min-width: 260px; flex: 1;">
                    <select name="survey_id" id="tes_teacher_survey_select" required style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13.5px; box-sizing: border-box; background: #fff;">
                        <option value="">Select Survey</option>
                        <?php foreach ($surveys as $s): ?>
                            <option value="<?php echo esc_attr($s->id); ?>" <?php selected($selected_survey, $s->id); ?>>
                                <?php echo esc_html($s->title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Action Buttons -->
                <div style="display: flex; gap: 8px;">
                    <button type="submit" class="button button-primary" style="padding: 8px 18px; font-size: 13.5px; font-weight: 600; border-radius: 6px; background: #0284c7; border: none; cursor: pointer; color: #fff; display: inline-flex; align-items: center; gap: 6px; transition: background 0.15s;">
                        View Results
                    </button>
                    <?php if (!empty($search_term) || !empty($selected_survey)): ?>
                        <a href="<?php echo esc_url(remove_query_arg(['s', 'survey_id'])); ?>" class="button" style="padding: 8px 14px; font-size: 13.5px; border-radius: 6px; background: #f1f5f9; border: 1px solid #cbd5e1; color: #475569; text-decoration: none; display: inline-flex; align-items: center;">
                            Reset
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <?php if (!$selected_survey): ?>
            
            <!-- All Surveys List Table for this Teacher -->
            <div class="tes-card" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); overflow: hidden;">
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 18px 22px; border-bottom: 1px solid #f1f5f9; background: #f8fafc;">
                    <div>
                        <h3 style="margin: 0; font-size: 17px; font-weight: 700; color: #0f172a;">My Assigned Evaluation Surveys</h3>
                        <p style="margin: 3px 0 0; font-size: 13px; color: #64748b;">Overview of surveys assigned to your profile</p>
                    </div>
                    <span style="background: #e2e8f0; color: #334155; font-size: 12px; font-weight: 600; padding: 4px 10px; border-radius: 999px;">
                        <?php echo count($surveys); ?> Surveys
                    </span>
                </div>

                <div style="overflow-x: auto;">
                    <table class="tes-table" style="width: 100%; border-collapse: collapse; font-size: 13.5px; text-align: left;">
                        <thead>
                            <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                                <th style="padding: 12px 16px; color: #475569; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Survey Title</th>
                                <th style="padding: 12px 16px; color: #475569; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Department</th>
                                <th style="padding: 12px 16px; color: #475569; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Class</th>
                                <th style="padding: 12px 16px; color: #475569; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Phase</th>
                                <th style="padding: 12px 16px; color: #475569; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Group</th>
                                <th style="padding: 12px 16px; color: #475569; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; text-align: center;">Status</th>
                                <th style="padding: 12px 16px; color: #475569; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; text-align: center;">Submissions</th>
                                <th style="padding: 12px 16px; color: #475569; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; text-align: center;">Average Score</th>
                                <th style="padding: 12px 16px; color: #475569; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($surveys): foreach ($surveys as $survey): 
                                // Calculate Score for this survey
                                $survey_questions = $wpdb->get_results($wpdb->prepare("SELECT * FROM $questions_table WHERE survey_id = %d", $survey->id));
                                
                                $fixed_q = new stdClass();
                                $fixed_q->id = 'fixed_implicit_role_model';
                                $fixed_q->survey_id = $survey->id;
                                $fixed_q->question_type = 'Implicit Issues';
                                $fixed_q->question_text = 'How well does the teacher model the core values through how he/she behaves with students and with other staff persons?';
                                $fixed_q->sub_question_title = 'I follow the teacher as my role model ';
                                $fixed_q->options = 'Too much extent,All Most,Yes  ';
                                if (!$survey_questions) $survey_questions = [];
                                $survey_questions[] = $fixed_q;

                                $survey_submissions = $wpdb->get_results($wpdb->prepare("SELECT answers FROM $submissions_table WHERE survey_id = %d", $survey->id));
                                $sub_count = count($survey_submissions);

                                $total_score_sum = 0;
                                $total_score_count = 0;

                                $score_map = [
                                    'Never'             => 1,
                                    'Once in a while'   => 2,
                                    'Sometimes'         => 3,
                                    'Most of the times' => 4,
                                    'Almost always'     => 5
                                ];
                                
                                if ($survey_questions && $survey_submissions) {
                                    $questions_map = [];
                                    foreach ($survey_questions as $q) $questions_map[$q->id] = $q;

                                    foreach ($survey_submissions as $sub) {
                                        $answers = maybe_unserialize($sub->answers);
                                        if (!is_array($answers)) continue;

                                        foreach ($answers as $q_id => $answer) {
                                            if (!isset($questions_map[$q_id])) continue;
                                            if ((string) $q_id === 'fixed_implicit_role_model') continue;
                                            
                                            $value = 0;
                                            if (isset($score_map[$answer])) {
                                                $value = $score_map[$answer];
                                            } else {
                                                $q_options = array_map('trim', explode(',', $questions_map[$q_id]->options));
                                                $num_options = count($q_options);
                                                $index = array_search($answer, $q_options);
                                                if ($index !== false && $num_options > 0) {
                                                    $value = (($num_options - $index) / $num_options) * 5;
                                                }
                                            }
                                            
                                            if ($value > 0) {
                                                $total_score_sum += $value;
                                                $total_score_count++;
                                            }
                                        }
                                    }
                                }
                                
                                $avg_score = $total_score_count > 0 ? $total_score_sum / $total_score_count : 0;
                            ?>
                                <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                                    <td style="padding: 14px 16px;">
                                        <div style="font-weight: 600; color: #0f172a; font-size: 14px;">
                                            <?php echo esc_html($survey->title); ?>
                                        </div>
                                        <?php if (!empty($survey->created_at)): ?>
                                            <div style="font-size: 12px; color: #94a3b8; margin-top: 2px;">
                                                <?php echo esc_html(date('F j, Y', strtotime($survey->created_at))); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 14px 16px; color: #475569;">
                                        <?php echo esc_html($current_teacher->department ?: 'N/A'); ?>
                                    </td>
                                    <td style="padding: 14px 16px;">
                                        <span style="background: #f1f5f9; color: #475569; font-size: 12px; font-weight: 500; padding: 3px 8px; border-radius: 4px; border: 1px solid #e2e8f0;">
                                            <?php echo esc_html($current_teacher->class_name ?: 'N/A'); ?>
                                        </span>
                                    </td>
                                    <td style="padding: 14px 16px;">
                                        <span style="background: #f1f5f9; color: #475569; font-size: 12px; font-weight: 500; padding: 3px 8px; border-radius: 4px; border: 1px solid #e2e8f0;">
                                            <?php echo esc_html($current_teacher->phase ?: 'N/A'); ?>
                                        </span>
                                    </td>
                                    <td style="padding: 14px 16px;">
                                        <?php 
                                        $s_grp = !empty($survey->group) ? $survey->group : (!empty($current_teacher->group) ? $current_teacher->group : '');
                                        if (!empty($s_grp) && strcasecmp($s_grp, 'all') !== 0): ?>
                                            <span style="background: #e0f2fe; color: #0369a1; font-size: 12px; font-weight: 600; padding: 3px 8px; border-radius: 4px; border: 1px solid #bae6fd;">
                                                Group <?php echo esc_html($s_grp); ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color: #94a3b8; font-size: 12px;">All</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 14px 16px; text-align: center;">
                                        <?php if (!empty($survey->status) && $survey->status === 'paused'): ?>
                                            <span style="display:inline-flex; align-items:center; gap:4px; background:#fef3c7; color:#92400e; font-size:11px; font-weight:600; padding:2px 7px; border-radius:4px; border:1px solid #fde68a;">
                                                <span style="display:inline-block; width:6px; height:6px; border-radius:50%; background:#f59e0b;"></span> Paused
                                            </span>
                                        <?php else: ?>
                                            <span style="display:inline-flex; align-items:center; gap:4px; background:#dcfce7; color:#15803d; font-size:11px; font-weight:600; padding:2px 7px; border-radius:4px; border:1px solid #86efac;">
                                                <span style="display:inline-block; width:6px; height:6px; border-radius:50%; background:#22c55e;"></span> Active
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 14px 16px; text-align: center; font-weight: 700; color: #0f172a;">
                                        <?php echo intval($sub_count); ?>
                                    </td>
                                    <td style="padding: 14px 16px; text-align: center;">
                                        <?php if ($total_score_count > 0): 
                                            $badge_bg = $avg_score >= 3.5 ? '#ecfdf5' : ($avg_score >= 2.5 ? '#fef3c7' : '#fee2e2');
                                            $badge_color = $avg_score >= 3.5 ? '#047857' : ($avg_score >= 2.5 ? '#b45309' : '#dc2626');
                                        ?>
                                            <span style="background: <?php echo $badge_bg; ?>; color: <?php echo $badge_color; ?>; font-weight: 700; font-size: 13px; padding: 4px 10px; border-radius: 999px; display: inline-flex; align-items: center; gap: 4px;">
                                                ★ <?php echo number_format($avg_score, 2); ?> <span style="font-weight: normal; font-size: 11px; opacity: 0.85;">/ 5</span>
                                            </span>
                                        <?php else: ?>
                                            <span style="background: #f1f5f9; color: #94a3b8; font-size: 12px; padding: 4px 10px; border-radius: 999px; display: inline-block;">
                                                No Data
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 14px 16px; text-align: right;">
                                        <a href="<?php echo esc_url(add_query_arg('survey_id', $survey->id)); ?>" class="button" style="text-decoration: none; background: #0284c7; color: #fff; padding: 6px 14px; border-radius: 6px; font-weight: 600; font-size: 13px; border: none; display: inline-flex; align-items: center; gap: 4px; transition: background 0.15s;" onmouseover="this.style.background='#0369a1'" onmouseout="this.style.background='#0284c7'">
                                            View Results &rarr;
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr>
                                    <td colspan="9" style="padding: 30px; text-align: center; color: #64748b;">
                                        <div style="font-size: 16px; font-weight: 600; color: #1e293b; margin-bottom: 4px;">No surveys found</div>
                                        <div>No evaluation surveys match your search or are assigned to your profile.</div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php else: 
            // --- Survey Results View ---
            $survey_details = $wpdb->get_row($wpdb->prepare(
                "SELECT s.title, s.`group` as survey_group, t.name, t.department, t.class_name, t.phase, t.teacher_id_number, t.`group` as teacher_group 
                 FROM $surveys_table s 
                 LEFT JOIN {$wpdb->prefix}tes_teachers t ON s.teacher_id = t.id 
                 WHERE s.id = %d", 
                $selected_survey
            ));
        ?>
            <!-- Results View Actions Bar -->
            <div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <a href="<?php echo esc_url(remove_query_arg('survey_id')); ?>" class="button" style="text-decoration: none; background: #f8fafc; color: #334155; padding: 8px 16px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13.5px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px;">
                    &larr; Back to My Surveys
                </a>
                <button id="tes-teacher-download-pdf" class="button" style="background: #0284c7; color: #fff; border: none; padding: 8px 18px; border-radius: 6px; cursor: pointer; font-size: 13.5px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px;">
                    &#128196; Download PDF Report
                </button>
            </div>

            <!-- PDF Capture Wrapper Container -->
            <div id="tes-teacher-pdf-content" style="padding: 5px; background: transparent; box-sizing: border-box;">

                <?php
                // Fetch questions & submissions
                $all_questions = $wpdb->get_results($wpdb->prepare("SELECT * FROM $questions_table WHERE survey_id = %d", $selected_survey));

                // Add fixed role model question
                $fixed_question = new stdClass();
                $fixed_question->id = 'fixed_implicit_role_model';
                $fixed_question->survey_id = $selected_survey;
                $fixed_question->question_type = 'Implicit Issues';
                $fixed_question->question_text = 'How well does the teacher model the core values through how he/she behaves with students and with other staff persons?';
                $fixed_question->sub_question_title = 'I follow the teacher as my role model ';
                $fixed_question->options = 'Too much extent,All Most,Yes  ';
                if (!$all_questions) $all_questions = [];
                $all_questions[] = $fixed_question;

                $submissions = $wpdb->get_results($wpdb->prepare(
                    "SELECT s.answers, s.student_name, s.comment, s.submission_date 
                     FROM $submissions_table s 
                     WHERE s.survey_id = %d",
                    $selected_survey
                ));

                if (!$all_questions || !$submissions): ?>
                    <div style="padding: 30px; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; text-align: center; color: #64748b;">
                        <div style="font-size: 18px; font-weight: 600; color: #1e293b; margin-bottom: 6px;">No Submissions Recorded</div>
                        <p style="margin: 0;">No student evaluation submissions have been recorded for this survey yet.</p>
                    </div>
                <?php else:
                    // Process submissions
                    $questions_by_id = [];
                    foreach ($all_questions as $q) $questions_by_id[$q->id] = $q;

                    $answer_counts = [];
                    $averages = [];
                    $student_averages = [];
                    $total_sum = 0;
                    $total_count = 0;

                    $score_map = [
                        'Never'             => 1,
                        'Once in a while'   => 2,
                        'Sometimes'         => 3,
                        'Most of the times' => 4,
                        'Almost always'     => 5
                    ];

                    foreach ($submissions as $sub) {
                        $answers = maybe_unserialize($sub->answers);
                        if (!is_array($answers)) continue;

                        $student_sum = 0;
                        $student_count = 0;

                        foreach ($answers as $q_id => $answer) {
                            if (!isset($questions_by_id[$q_id])) continue;
                            if (!isset($answer_counts[$q_id])) $answer_counts[$q_id] = [];
                            if (!isset($answer_counts[$q_id][$answer])) $answer_counts[$q_id][$answer] = 0;
                            $answer_counts[$q_id][$answer]++;

                            if ((string) $q_id === 'fixed_implicit_role_model') continue;

                            $value = 0;
                            if (isset($score_map[$answer])) {
                                $value = $score_map[$answer];
                            } else {
                                $current_question = $questions_by_id[$q_id];
                                $q_options = array_map('trim', explode(',', $current_question->options));
                                $num_options = count($q_options);
                                $index = array_search($answer, $q_options);
                                if ($index !== false && $num_options > 0) {
                                    $value = (($num_options - $index) / $num_options) * 5;
                                }
                            }
                            if ($value > 0) {
                                if (!isset($averages[$q_id])) $averages[$q_id] = ['sum' => 0, 'count' => 0];
                                $averages[$q_id]['sum'] += $value;
                                $averages[$q_id]['count']++;
                                $student_sum += $value;
                                $student_count++;
                                $total_sum += $value;
                                $total_count++;
                            }
                        }
                        if ($student_count > 0 && !empty($sub->student_name)) {
                            $student_averages[$sub->student_name] = [
                                'avg' => $student_sum / $student_count,
                                'date' => $sub->submission_date
                            ];
                        }
                    }

                    $overall_avg = $total_count > 0 ? $total_sum / $total_count : null;

                    // Calculate eligible students
                    $total_eligible = 0;
                    $eff_group = !empty($survey_details->survey_group) ? trim($survey_details->survey_group) : (!empty($survey_details->teacher_group) ? trim($survey_details->teacher_group) : '');
                    if ($survey_details && !empty($survey_details->class_name)) {
                        $eligible_query = "SELECT COUNT(*) FROM {$wpdb->prefix}tes_students WHERE class_name = %s";
                        $e_params = [$survey_details->class_name];
                        if (!empty($survey_details->phase)) {
                            $eligible_query .= " AND phase = %s";
                            $e_params[] = $survey_details->phase;
                        }
                        if (!empty($eff_group) && strcasecmp($eff_group, 'all') !== 0) {
                            $eligible_query .= " AND `group` = %s";
                            $e_params[] = $eff_group;
                        }
                        $total_eligible = $wpdb->get_var($wpdb->prepare($eligible_query, ...$e_params));
                    }
                    $submission_count = count($submissions);
                    $palette_colors = ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#06b6d4', '#64748b', '#6366f1'];
                ?>

                    <!-- Executive Summary / KPI Cards (.tes-teacher-pdf-top-section) -->
                    <div class="tes-teacher-pdf-top-section" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; margin-bottom: 25px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); page-break-after: always; width: 100%; box-sizing: border-box;">
                        
                        <!-- College Branding Header -->
                        <div style="text-align: center; margin-bottom: 22px; border-bottom: 1px solid #f1f5f9; padding-bottom: 18px;">
                            <div style="display: inline-block; background: #0c273d; color: #ffffff; padding: 8px 18px; border-radius: 6px; font-size: 16px; font-weight: 700; letter-spacing: 0.3px; margin-bottom: 6px;">
                                Rangpur Community Medical College Hospital (RCMCH)
                            </div>
                            <div style="font-size: 12px; color: #64748b; font-weight: 500; margin-bottom: 10px;">
                                Category of The Medical College: Non govt.
                            </div>
                            <h2 style="margin: 8px 0 4px; font-size: 21px; font-weight: 700; color: #0f172a;">
                                <?php echo esc_html($survey_details->title); ?> - Evaluation Report
                            </h2>
                            <span style="font-size: 13px; color: #64748b;">
                                <strong>Date:</strong> <?php echo date('F j, Y'); ?>
                            </span>
                        </div>

                        <!-- 3 KPI Scorecard Grid -->
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px;">
                            
                            <!-- KPI 1: Overall Average Rating -->
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px; text-align: center;">
                                <div style="font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; font-weight: 600; margin-bottom: 6px;">Overall Average Rating</div>
                                <div style="font-size: 30px; font-weight: 800; color: #0284c7; line-height: 1.2;">
                                    <?php echo number_format($overall_avg, 2); ?> <span style="font-size: 16px; color: #64748b; font-weight: normal;">/ 5.0</span>
                                </div>
                                <div style="margin-top: 8px;">
                                    <?php
                                    $rating_label = $overall_avg >= 4.5 ? 'Excellent' : ($overall_avg >= 3.5 ? 'Good' : ($overall_avg >= 2.5 ? 'Average' : 'Needs Improvement'));
                                    $badge_bg = $overall_avg >= 3.5 ? '#ecfdf5' : ($overall_avg >= 2.5 ? '#fef3c7' : '#fee2e2');
                                    $badge_color = $overall_avg >= 3.5 ? '#047857' : ($overall_avg >= 2.5 ? '#b45309' : '#dc2626');
                                    ?>
                                    <span style="background: <?php echo $badge_bg; ?>; color: <?php echo $badge_color; ?>; font-size: 12px; font-weight: 600; padding: 3px 10px; border-radius: 999px; display: inline-block;">
                                        ★ <?php echo $rating_label; ?>
                                    </span>
                                </div>
                            </div>

                            <!-- KPI 2: Teacher & Academic Information -->
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px; text-align: left;">
                                <div style="font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; font-weight: 600; margin-bottom: 6px;">Teacher Details</div>
                                <div style="font-size: 17px; font-weight: 700; color: #0f172a; margin-bottom: 6px;">
                                    <?php echo esc_html($survey_details->name); ?>
                                </div>
                                <div style="font-size: 13px; color: #475569; line-height: 1.6;">
                                    <div><strong>Dept:</strong> <?php echo esc_html($survey_details->department); ?></div>
                                    <div><strong>Class:</strong> <?php echo esc_html($survey_details->class_name); ?> | <strong>Phase:</strong> <?php echo esc_html($survey_details->phase); ?><?php if (!empty($eff_group) && strcasecmp($eff_group, 'all') !== 0) echo ' | <strong>Group:</strong> ' . esc_html($eff_group); ?></div>
                                </div>
                            </div>

                            <!-- KPI 3: Participation & Submissions -->
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px; text-align: center;">
                                <div style="font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; font-weight: 600; margin-bottom: 6px;">Student Responses</div>
                                <div style="font-size: 22px; font-weight: 700; color: #0f172a; margin-bottom: 4px;">
                                    <?php echo intval($submission_count); ?> <span style="font-size: 14px; font-weight: normal; color: #64748b;">/ <?php echo intval($total_eligible); ?> Students</span>
                                </div>
                                <?php if ($total_eligible > 0): 
                                    $resp_rate = round(($submission_count / $total_eligible) * 100, 1);
                                ?>
                                    <div style="margin-top: 10px;">
                                        <div style="display: flex; justify-content: space-between; font-size: 11.5px; color: #64748b; margin-bottom: 3px;">
                                            <span>Response Rate</span>
                                            <span style="font-weight: 700; color: #0f172a;"><?php echo $resp_rate; ?>%</span>
                                        </div>
                                        <div style="background: #e2e8f0; height: 6px; border-radius: 3px; overflow: hidden;">
                                            <div style="width: <?php echo min(100, $resp_rate); ?>%; background: #0284c7; height: 100%; border-radius: 3px;"></div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div style="font-size: 13px; color: #64748b; margin-top: 6px;">Total eligible students not configured</div>
                                <?php endif; ?>
                            </div>

                        </div>
                    </div>

                    <!-- Questions Grouped by Type -->
                    <?php
                    $grouped_questions = [];
                    foreach ($all_questions as $q) {
                        $type = isset($q->question_type) ? $q->question_type : 'General';
                        $grouped_questions[$type][] = $q;
                    }
                    $type_order = ['Explicit Issues', 'Implicit Issues'];
                    foreach (array_keys($grouped_questions) as $type) {
                        if (!in_array($type, $type_order)) $type_order[] = $type;
                    }

                    foreach ($type_order as $type):
                        if (empty($grouped_questions[$type])) continue;
                        ?>
                        
                        <!-- Section Header (.tes-teacher-pdf-section-header) -->
                        <div class="tes-teacher-pdf-section-header" style="margin: 30px 0 16px; display: flex; align-items: center; gap: 10px; border-bottom: 2px solid #0284c7; padding-bottom: 8px;">
                            <h2 style="margin: 0; font-size: 18px; font-weight: 700; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px;">
                                <?php echo esc_html($type); ?>
                            </h2>
                            <span style="background: #e0f2fe; color: #0284c7; font-size: 12px; font-weight: 700; padding: 2px 8px; border-radius: 999px;">
                                <?php echo count($grouped_questions[$type]); ?> Questions
                            </span>
                        </div>

                        <?php
                        foreach ($grouped_questions[$type] as $q):
                            $options = array_map('trim', explode(',', $q->options));
                            $avg = isset($averages[$q->id]) && $averages[$q->id]['count'] > 0 ? $averages[$q->id]['sum'] / $averages[$q->id]['count'] : null;
                            $chart_data = [];
                            foreach ($options as $opt) {
                                $chart_data[] = isset($answer_counts[$q->id][$opt]) ? intval($answer_counts[$q->id][$opt]) : 0;
                            }
                            $q_total_resp = array_sum($chart_data);
                        ?>
                            <div class="tes-teacher-question-container" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); width: 100%; box-sizing: border-box; page-break-inside: avoid; page-break-after: always;">
                                
                                <!-- Question Title & Rating Header -->
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px; margin-bottom: 16px; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px;">
                                    <div style="flex: 1; min-width: 260px;">
                                        <div style="font-size: 15.5px; font-weight: 700; color: #0f172a; line-height: 1.4;">
                                            <?php echo esc_html($q->sub_question_title ? $q->sub_question_title : $q->question_text); ?>
                                        </div>
                                        <?php if ($q->sub_question_title && $q->question_text && $q->sub_question_title !== $q->question_text): ?>
                                            <div style="font-size: 13px; color: #64748b; margin-top: 4px; font-style: italic;">
                                                <?php echo esc_html($q->question_text); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($avg !== null): 
                                        $q_badge_bg = $avg >= 3.5 ? '#ecfdf5' : ($avg >= 2.5 ? '#fef3c7' : '#fee2e2');
                                        $q_badge_color = $avg >= 3.5 ? '#047857' : ($avg >= 2.5 ? '#b45309' : '#dc2626');
                                    ?>
                                        <span style="background: <?php echo $q_badge_bg; ?>; color: <?php echo $q_badge_color; ?>; font-size: 13px; font-weight: 700; padding: 5px 12px; border-radius: 999px; white-space: nowrap; display: inline-flex; align-items: center; gap: 4px;">
                                            Average Rating: <?php echo number_format($avg, 2); ?> / 5
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <!-- 2-Column Responsive Body: Table & Pie Chart -->
                                <div style="display: flex; gap: 24px; flex-wrap: wrap; align-items: center;">
                                    
                                    <!-- Left Column: Options Table (.tes-teacher-table-wrapper - No Change Option) -->
                                    <div class="tes-teacher-table-wrapper" style="flex: 1; min-width: 280px;">
                                        <table style="width: 100%; border-collapse: collapse; border: 1px solid #e2e8f0; font-size: 13px; border-radius: 8px; overflow: hidden;">
                                            <thead>
                                                <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                                                    <th style="padding: 10px 12px; text-align: left; color: #475569; font-weight: 600;">Option</th>
                                                    <th style="padding: 10px 12px; text-align: center; color: #475569; font-weight: 600; width: 85px;">Responses</th>
                                                    <th style="padding: 10px 12px; text-align: left; color: #475569; font-weight: 600; width: 130px;">Distribution</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($options as $opt_idx => $opt): 
                                                    $opt_count = isset($answer_counts[$q->id][$opt]) ? intval($answer_counts[$q->id][$opt]) : 0;
                                                    $opt_pct = $q_total_resp > 0 ? round(($opt_count / $q_total_resp) * 100, 1) : 0;
                                                    $color = $palette_colors[$opt_idx % count($palette_colors)];
                                                ?>
                                                    <tr style="border-bottom: 1px solid #f1f5f9;">
                                                        <td style="padding: 8px 12px; color: #334155;">
                                                            <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background-color: <?php echo esc_attr($color); ?>; margin-right: 6px;"></span>
                                                            <?php echo esc_html($opt); ?>
                                                        </td>
                                                        <td style="padding: 8px 12px; text-align: center; font-weight: 700; color: #0f172a;">
                                                            <?php echo $opt_count; ?>
                                                        </td>
                                                        <td style="padding: 8px 12px;">
                                                            <div style="display: flex; align-items: center; gap: 8px;">
                                                                <div style="flex: 1; background: #f1f5f9; height: 6px; border-radius: 3px; overflow: hidden;">
                                                                    <div style="width: <?php echo $opt_pct; ?>%; background: <?php echo esc_attr($color); ?>; height: 100%; border-radius: 3px;"></div>
                                                                </div>
                                                                <span style="font-size: 11px; color: #64748b; font-weight: 600; min-width: 32px;"><?php echo $opt_pct; ?>%</span>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>

                                    <!-- Right Column: Chart.js Pie Chart (.tes-teacher-chart-wrapper) -->
                                    <div class="tes-teacher-chart-wrapper" style="width: 250px; max-width: 100%; margin: 0 auto; text-align: center; height: 230px; position: relative;">
                                        <canvas id="teacher-chart-<?php echo esc_attr($q->id); ?>" width="240" height="230"></canvas>
                                    </div>
                                </div>

                                <script>
                                jQuery(document).ready(function($) {
                                    var canvasEl = document.getElementById('teacher-chart-<?php echo esc_js($q->id); ?>');
                                    if (!canvasEl) return;
                                    var ctx = canvasEl.getContext('2d');
                                    var colors = <?php echo json_encode($palette_colors); ?>;
                                    new Chart(ctx, {
                                        type: 'pie',
                                        data: {
                                            labels: <?php echo json_encode($options); ?>,
                                            datasets: [{
                                                data: <?php echo json_encode($chart_data); ?>,
                                                backgroundColor: colors.slice(0, <?php echo count($options); ?>),
                                                borderColor: '#ffffff',
                                                borderWidth: 2
                                            }]
                                        },
                                        options: {
                                            responsive: true,
                                            maintainAspectRatio: false,
                                            plugins: {
                                                legend: {
                                                    position: 'bottom',
                                                    labels: {
                                                        boxWidth: 10,
                                                        padding: 6,
                                                        font: { size: 10.5 }
                                                    }
                                                }
                                            }
                                        }
                                    });
                                });
                                </script>
                            </div>
                        <?php endforeach; 
                    endforeach; ?>

                <?php endif; ?>
            </div><!-- End #tes-teacher-pdf-content -->

            <!-- PDF Download Script using html2pdf -->
            <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
            <script>
            document.getElementById('tes-teacher-download-pdf').addEventListener('click', function() {
                var element = document.getElementById('tes-teacher-pdf-content');
                var downloadBtn = this;
                var originalBtnText = downloadBtn.innerHTML;
                var originalWidth = element.style.width;

                downloadBtn.innerHTML = 'Preparing PDF...';
                downloadBtn.disabled = true;

                element.style.width = '700px';

                var canvases = element.querySelectorAll('canvas');
                var tempImages = [];
                var promises = [];

                canvases.forEach(function(canvas) {
                    var promise = new Promise(function(resolve) {
                        var img = new Image();
                        img.onload = resolve;
                        img.src = canvas.toDataURL('image/png');
                        img.style.width = canvas.offsetWidth + 'px';
                        img.style.height = canvas.offsetHeight + 'px';
                        
                        canvas.style.display = 'none';
                        canvas.parentNode.insertBefore(img, canvas);
                        tempImages.push({ img: img, canvas: canvas });
                    });
                    promises.push(promise);
                });

                Promise.all(promises).then(function() {
                    var opt = {
                        margin: 0.5,
                        filename: '<?php echo esc_js(sanitize_file_name($survey_details->title)); ?>_evaluation.pdf',
                        image: { type: 'jpeg', quality: 0.98 },
                        html2canvas: { scale: 2, useCORS: true, logging: false },
                        jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' },
                        pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
                    };

                    html2pdf().set(opt).from(element).save().then(function() {
                        tempImages.forEach(function(item) {
                            item.img.remove();
                            item.canvas.style.display = '';
                        });
                        element.style.width = originalWidth;
                        downloadBtn.innerHTML = originalBtnText;
                        downloadBtn.disabled = false;
                    });
                });
            });
            </script>
        <?php endif; ?>
    </div>

    <!-- Scoped Styles for Teacher Dashboard -->
    <style>
        .tes-teacher-suggestion-item {
            padding: 10px 14px;
            cursor: pointer;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13.5px;
            color: #1e293b;
            transition: background 0.15s;
        }
        .tes-teacher-suggestion-item:hover {
            background-color: #f0f9ff;
            color: #0284c7;
        }
        .tes-teacher-suggestion-item:last-child {
            border-bottom: none;
        }
    </style>

    <script type="text/javascript">
    jQuery(document).ready(function($) {
        var searchInput = $('#tes_teacher_search_input');
        var suggestionsBox = $('#tes_teacher_search_suggestions');
        var timer;

        searchInput.on('input', function() {
            var term = $(this).val();
            clearTimeout(timer);
            
            if (term.length < 1) {
                suggestionsBox.hide().empty();
                return;
            }

            timer = setTimeout(function() {
                var ajaxUrl = typeof tes_ajax !== 'undefined' ? tes_ajax.ajax_url : '<?php echo admin_url('admin-ajax.php'); ?>';
                $.ajax({
                    url: ajaxUrl,
                    data: {
                        action: 'tes_survey_search_autocomplete',
                        term: term
                    },
                    success: function(response) {
                        suggestionsBox.empty();
                        if (response.success && response.data.length > 0) {
                            var html = '';
                            $.each(response.data, function(i, item) {
                                var escapedTitle = $('<div>').text(item.title).html();
                                html += '<div class="tes-teacher-suggestion-item" data-id="' + item.id + '">' + escapedTitle + '</div>';
                            });
                            suggestionsBox.html(html).show();
                        } else {
                            suggestionsBox.html('<div class="tes-teacher-suggestion-item" style="cursor: default; color: #94a3b8;">No surveys found</div>').show();
                        }
                    }
                });
            }, 300);
        });

        $(document).on('click', '.tes-teacher-suggestion-item', function() {
            var surveyId = $(this).data('id');
            if (!surveyId) return;
            window.location.href = '?survey_id=' + surveyId;
        });

        $(document).on('click', function(e) {
            if (!$(e.target).closest('#tes_teacher_search_input').length && !$(e.target).closest('#tes_teacher_search_suggestions').length) {
                suggestionsBox.hide();
            }
        });
    });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('teacher_dashboard', 'tes_teacher_dashboard_shortcode');
add_shortcode('teacher_login', 'tes_teacher_dashboard_shortcode');
