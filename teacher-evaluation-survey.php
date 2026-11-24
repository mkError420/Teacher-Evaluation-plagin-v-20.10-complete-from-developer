<?php
/**
 * Plugin Name: Teacher Evaluation Survey
 * Description: Create teacher-wise multiple choice surveys with admin dashboard and student submission.
 * Version: 20.10
 * Author: MK.RABBANI(Website manager)
 */

if (!defined('ABSPATH')) exit;

define('TES_PATH', plugin_dir_path(__FILE__));
define('TES_URL', plugin_dir_url(__FILE__));


// Include all necessary files
require_once TES_PATH . 'includes/db.php';
require_once TES_PATH . 'includes/admin-menu.php';
require_once TES_PATH . 'includes/admin-teachers.php';
require_once TES_PATH . 'includes/admin-supervisors.php';
require_once TES_PATH . 'includes/admin-surveys.php';
require_once TES_PATH . 'includes/admin-results.php';
require_once TES_PATH . 'includes/shortcode-survey.php';
require_once TES_PATH . 'includes/admin-questions.php';
require_once TES_PATH . 'includes/admin-students.php';
require_once TES_PATH . 'includes/admin-settings.php';
require_once TES_PATH . 'includes/shortcode-teacher-dashboard.php';
require_once TES_PATH . 'includes/shortcode-advisor-dashboard.php';





// Activation hook to create tables
register_activation_hook(__FILE__, 'tes_create_tables');

// Enqueue styles
add_action('admin_enqueue_scripts', function () {
    wp_enqueue_style('tes-admin', TES_URL . 'assets/css/admin.css');
    wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], null, true);
});
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('tes-front', TES_URL . 'assets/css/frontend.css');
    wp_enqueue_script('tes-front-js', TES_URL . 'assets/js/frontend.js', ['jquery'], filemtime(TES_PATH . 'assets/js/frontend.js'), true);
    wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], null, true);

    wp_localize_script('tes-front-js', 'tes_ajax', [
        'ajax_url' => admin_url('admin-ajax.php')
    ]);
});



// AJAX: Load survey questions
add_action('wp_ajax_tes_load_questions', 'tes_load_questions');
add_action('wp_ajax_nopriv_tes_load_questions', 'tes_load_questions');

// AJAX: Load surveys by class
add_action('wp_ajax_tes_load_surveys_by_class', 'tes_load_surveys_by_class');
add_action('wp_ajax_nopriv_tes_load_surveys_by_class', 'tes_load_surveys_by_class');

// AJAX: Submit survey
add_action('wp_ajax_tes_submit_survey', 'tes_submit_survey');
add_action('wp_ajax_nopriv_tes_submit_survey', 'tes_submit_survey');

// AJAX: Add question
add_action('wp_ajax_tes_add_question', 'tes_add_question');

// AJAX: Import Students
add_action('wp_ajax_tes_import_students', 'tes_import_students');

// AJAX: Import Teachers
add_action('wp_ajax_tes_import_teachers', 'tes_import_teachers');

// AJAX: Student Search Autocomplete
add_action('wp_ajax_tes_student_search_autocomplete', 'tes_student_search_autocomplete');

// AJAX: Survey Search Autocomplete
add_action('wp_ajax_tes_survey_search_autocomplete', 'tes_survey_search_autocomplete');
add_action('wp_ajax_nopriv_tes_survey_search_autocomplete', 'tes_survey_search_autocomplete');

// AJAX: Teacher Search Autocomplete
add_action('wp_ajax_tes_teacher_search_autocomplete', 'tes_teacher_search_autocomplete');
add_action('wp_ajax_nopriv_tes_teacher_search_autocomplete', 'tes_teacher_search_autocomplete');

// AJAX: Question Search Autocomplete
add_action('wp_ajax_tes_question_search_autocomplete', 'tes_question_search_autocomplete');

// Database update: Ensure student_id column exists
add_action('init', 'tes_update_db_schema', 1);
function tes_update_db_schema() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'tes_submissions';
    
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'")) {
        $column = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'student_id'");
        if (empty($column)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN student_id bigint(20) NULL AFTER survey_id");
        }
    }

    $students_table = $wpdb->prefix . 'tes_students';
    if ($wpdb->get_var("SHOW TABLES LIKE '$students_table'")) {
        $new_cols = ['session', 'batch_name', 'phase', 'roll', 'class_name', 'group'];
        foreach ($new_cols as $col) {
            if (empty($wpdb->get_results("SHOW COLUMNS FROM $students_table LIKE '$col'"))) {
                $wpdb->query("ALTER TABLE $students_table ADD COLUMN `$col` VARCHAR(100) NULL");
            }
        }
        if (empty($wpdb->get_results("SHOW COLUMNS FROM $students_table LIKE 'email'"))) {
            $wpdb->query("ALTER TABLE $students_table ADD COLUMN email VARCHAR(254) NULL");
        }
    }

    $teachers_table = $wpdb->prefix . 'tes_teachers';
    if ($wpdb->get_var("SHOW TABLES LIKE '$teachers_table'")) {
        $teacher_cols = ['teacher_id_number', 'phase', 'class_name', 'designation', 'group'];
        foreach ($teacher_cols as $col) {
            if (empty($wpdb->get_results("SHOW COLUMNS FROM $teachers_table LIKE '$col'"))) {
                $wpdb->query("ALTER TABLE $teachers_table ADD COLUMN `$col` VARCHAR(100) NULL");
            }
        }
        // Add username and password for teachers
        if (empty($wpdb->get_results("SHOW COLUMNS FROM $teachers_table LIKE 'username'"))) {
            $wpdb->query("ALTER TABLE $teachers_table ADD COLUMN username VARCHAR(50) NULL UNIQUE");
        }
        if (empty($wpdb->get_results("SHOW COLUMNS FROM $teachers_table LIKE 'password'"))) {
            $wpdb->query("ALTER TABLE $teachers_table ADD COLUMN password VARCHAR(255) NULL");
        }
        if (empty($wpdb->get_results("SHOW COLUMNS FROM $teachers_table LIKE 'role'"))) {
            $wpdb->query("ALTER TABLE $teachers_table ADD COLUMN role VARCHAR(20) DEFAULT 'teacher'");
        }
        if (empty($wpdb->get_results("SHOW COLUMNS FROM $teachers_table LIKE 'created_at'"))) {
            $wpdb->query("ALTER TABLE $teachers_table ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP");
            $wpdb->query("UPDATE $teachers_table SET created_at = NOW() WHERE created_at IS NULL OR created_at = '0000-00-00 00:00:00'");
        }

        // Auto-assign username & password for any existing teachers who don't have login credentials
        $teachers_without_user = $wpdb->get_results("SELECT id, name, teacher_id_number FROM $teachers_table WHERE (username IS NULL OR username = '') AND (role != 'advisor' OR role IS NULL)");
        if (!empty($teachers_without_user)) {
            foreach ($teachers_without_user as $twu) {
                $candidate_user = !empty($twu->teacher_id_number)
                    ? sanitize_user(strtolower($twu->teacher_id_number), true)
                    : sanitize_user(strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $twu->name)), true);
                if (empty($candidate_user)) {
                    $candidate_user = 'teacher' . $twu->id;
                }
                $final_user = $candidate_user;
                $suffix = 1;
                while ($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $teachers_table WHERE username = %s AND id != %d", $final_user, $twu->id)) > 0) {
                    $final_user = $candidate_user . $suffix;
                    $suffix++;
                }
                $default_pass = !empty($twu->teacher_id_number) ? $twu->teacher_id_number : '123456';
                $wpdb->update($teachers_table, [
                    'username' => $final_user,
                    'password' => $default_pass,
                    'role'     => 'teacher'
                ], ['id' => $twu->id]);
            }
        }
    }

    $surveys_table = $wpdb->prefix . 'tes_surveys';
    if ($wpdb->get_var("SHOW TABLES LIKE '$surveys_table'")) {
        if (empty($wpdb->get_results("SHOW COLUMNS FROM $surveys_table LIKE 'group'"))) {
            $wpdb->query("ALTER TABLE $surveys_table ADD COLUMN `group` VARCHAR(100) NULL");
        }
        if (empty($wpdb->get_results("SHOW COLUMNS FROM $surveys_table LIKE 'batch_name'"))) {
            $wpdb->query("ALTER TABLE $surveys_table ADD COLUMN batch_name VARCHAR(100) NULL");
        }
        if (empty($wpdb->get_results("SHOW COLUMNS FROM $surveys_table LIKE 'status'"))) {
            $wpdb->query("ALTER TABLE $surveys_table ADD COLUMN status VARCHAR(20) DEFAULT 'active'");
            $wpdb->query("UPDATE $surveys_table SET status = 'active' WHERE status IS NULL OR status = ''");
        }
        if (empty($wpdb->get_results("SHOW COLUMNS FROM $surveys_table LIKE 'last_updated'"))) {
            $wpdb->query("ALTER TABLE $surveys_table ADD COLUMN last_updated DATETIME NULL");
        }
        if (empty($wpdb->get_results("SHOW COLUMNS FROM $surveys_table LIKE 'created_at'"))) {
            $wpdb->query("ALTER TABLE $surveys_table ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP");
            $wpdb->query("UPDATE $surveys_table SET created_at = NOW() WHERE created_at IS NULL OR created_at = '0000-00-00 00:00:00'");
        }
    }

    $submissions_table = $wpdb->prefix . 'tes_submissions';
    if ($wpdb->get_var("SHOW TABLES LIKE '$submissions_table'")) {
        if (empty($wpdb->get_results("SHOW COLUMNS FROM $submissions_table LIKE 'submission_date'"))) {
            $wpdb->query("ALTER TABLE $submissions_table ADD COLUMN submission_date DATETIME DEFAULT CURRENT_TIMESTAMP");
            // Set a default date for existing records so they aren't treated as infinitely old
            $wpdb->query("UPDATE $submissions_table SET submission_date = NOW() WHERE submission_date IS NULL OR submission_date = '0000-00-00 00:00:00'");
        }
        if (empty($wpdb->get_results("SHOW COLUMNS FROM $submissions_table LIKE 'comment'"))) {
            $wpdb->query("ALTER TABLE $submissions_table ADD COLUMN comment TEXT NULL");
        }
    }

    $questions_table = $wpdb->prefix . 'tes_questions';
    if ($wpdb->get_var("SHOW TABLES LIKE '$questions_table'")) {
        if (empty($wpdb->get_results("SHOW COLUMNS FROM $questions_table LIKE 'sub_question_title'"))) {
            $wpdb->query("ALTER TABLE $questions_table ADD COLUMN sub_question_title TEXT NULL AFTER question_text");
        }
        if (empty($wpdb->get_results("SHOW COLUMNS FROM $questions_table LIKE 'question_type'"))) {
            $wpdb->query("ALTER TABLE $questions_table ADD COLUMN question_type VARCHAR(100) DEFAULT 'Explicit Issues' AFTER survey_id");
        }
        if (empty($wpdb->get_results("SHOW COLUMNS FROM $questions_table LIKE 'created_at'"))) {
            $wpdb->query("ALTER TABLE $questions_table ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP");
            $wpdb->query("UPDATE $questions_table SET created_at = NOW() WHERE created_at IS NULL OR created_at = '0000-00-00 00:00:00'");
        }
    }
}

function tes_load_questions() {
    global $wpdb;

    $survey_id = intval($_POST['survey_id']);
    if (!$survey_id) {
        wp_send_json_error('Invalid survey.');
    }

    $student_id = tes_get_student_login_id();
    if (!$student_id) {
        wp_send_json_error('Please log in as a student to access this survey.');
    }

    $student = $wpdb->get_row($wpdb->prepare(
        "SELECT id, student_name, class_name, phase, batch_name, `group`
         FROM {$wpdb->prefix}tes_students WHERE id = %d",
        $student_id
    ));
    if (!$student) {
        wp_send_json_error('Student account not found. Please log in again.');
    }

    $survey_info = $wpdb->get_row($wpdb->prepare(
        "SELECT s.id, s.status, s.`group` as survey_group, s.batch_name,
                t.`group` as teacher_group, t.class_name, t.phase
         FROM {$wpdb->prefix}tes_surveys s 
         INNER JOIN {$wpdb->prefix}tes_teachers t ON s.teacher_id = t.id 
         WHERE s.id = %d", 
        $survey_id
    ));
    if (!$survey_info) {
        wp_send_json_error('Survey not found.');
    }
    if (!empty($survey_info->status) && $survey_info->status === 'paused') {
        wp_send_json_error('This survey is currently paused and not accepting responses.');
    }
    $selected_group = isset($_POST['group']) ? sanitize_text_field(wp_unslash($_POST['group'])) : '';
    if (!tes_student_can_access_survey($student, $survey_info, $selected_group)) {
        wp_send_json_error('Access restricted: this survey is not assigned to your class, phase, batch, or group.');
    }

    $questions = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}tes_questions WHERE survey_id = %d",
            $survey_id
        )
    );

    if (!$questions) {
        $questions = [];
    }

    // Add fixed question for Implicit Issues
    $fixed_question = new stdClass();
    $fixed_question->id = 'fixed_implicit_role_model';
    $fixed_question->survey_id = $survey_id;
    $fixed_question->question_type = 'Implicit Issues';
    $fixed_question->question_text = 'How well does the teacher model the core values through how he/she behaves with students and with other staff persons?';
    $fixed_question->sub_question_title = 'I follow the teacher as my role model ';
    $fixed_question->options = 'Too much extent,All Most,Yes  ';
    $questions[] = $fixed_question;

    wp_send_json_success($questions);
}

function tes_load_surveys_by_class() {
    global $wpdb;

    $student_id = tes_get_student_login_id();
    if (!$student_id) {
        wp_send_json_error('Please log in as a student to access surveys.');
    }

    $student = $wpdb->get_row($wpdb->prepare(
        "SELECT id, student_name, class_name, phase, batch_name, `group`
         FROM {$wpdb->prefix}tes_students WHERE id = %d",
        $student_id
    ));
    if (!$student) {
        wp_send_json_error('Student account not found. Please log in again.');
    }
    if (empty($student->class_name) || empty($student->phase)) {
        wp_send_json_error('Your student profile must have a class and phase assigned before surveys can be accessed.');
    }

    $group = isset($_POST['group']) ? sanitize_text_field(wp_unslash($_POST['group'])) : '';
    if ($group === '') {
        $group = trim((string) $student->group);
    }

    $query = "
        SELECT DISTINCT s.id, s.title, t.name as teacher_name,
               s.`group` as survey_group, s.batch_name,
               t.`group` as teacher_group, t.class_name, t.phase
        FROM {$wpdb->prefix}tes_surveys s
        INNER JOIN {$wpdb->prefix}tes_teachers t ON s.teacher_id = t.id
        LEFT JOIN {$wpdb->prefix}tes_submissions sub
            ON s.id = sub.survey_id
            AND (sub.student_id = %d OR (sub.student_id IS NULL AND sub.student_name = %s))
        WHERE LOWER(REPLACE(REPLACE(REPLACE(TRIM(t.class_name), ' ', ''), '-', ''), '_', '')) = %s
          AND LOWER(REPLACE(REPLACE(REPLACE(TRIM(t.phase), ' ', ''), '-', ''), '_', '')) = %s
          AND (
              s.batch_name IS NULL OR TRIM(s.batch_name) = '' OR LOWER(TRIM(s.batch_name)) = 'all'
              OR LOWER(REPLACE(REPLACE(REPLACE(TRIM(s.batch_name), ' ', ''), '-', ''), '_', '')) = %s
          )
          AND (s.status = 'active' OR s.status IS NULL OR s.status = '')
          AND EXISTS (SELECT 1 FROM {$wpdb->prefix}tes_questions WHERE survey_id = s.id)
          AND sub.id IS NULL
    ";
    $query .= " ORDER BY t.name ASC, s.title ASC";
    $params = [
        $student_id,
        $student->student_name,
        tes_normalize_survey_scope_value($student->class_name),
        tes_normalize_survey_scope_value($student->phase),
        tes_normalize_survey_scope_value($student->batch_name)
    ];

    $cohort_surveys = $wpdb->get_results($wpdb->prepare($query, $params));
    if ($cohort_surveys === null) {
        wp_send_json_error('Unable to load surveys: ' . $wpdb->last_error);
    }

    $surveys = [];
    foreach ($cohort_surveys as $survey) {
        if (tes_student_can_access_survey($student, $survey, $group)) {
            $survey->target_group = !empty($survey->survey_group)
                ? $survey->survey_group
                : (string) $survey->teacher_group;
            $surveys[] = $survey;
        }
    }
    wp_send_json_success($surveys ? $surveys : []);
}

/**
 * Get all questions defined for Survey Question Builder
 * Dynamically aggregates standard template questions and any custom builder questions from database
 */
function tes_get_builder_questions() {
    global $wpdb;
    $questions_table = $wpdb->prefix . 'tes_questions';

    $explicit_text = 'How well does the teacher teach the core subject?';
    $implicit_text = 'How well does the teacher model the core values through how he/she behaves with students and with other staff persons?';
    $default_options = 'Never,Once in a while,Sometimes,Most of the times,Almost always';

    $explicit_subs = [
        "The teacher gains attention of students at the beginning of the session.",
        "At the beginning of the session teacher inform about the objectives of the session",
        "The teacher checks students previous knowledge related to the topic of the class",
        "Teacher manages the time well ",
        "Language & pronunciation of the teacher is clear",
        "Preparation of the audio visual/teaching aids by the teacher is helpful",
        "Teacher encourages students to speak up and be active in the class",
        "The teacher emphasizes important point & summarize at the end of the class ",
        "The teacher provides useful & relevant references /sources for further study",
        "Teacher assess students learning within the class ",
        "Teaching learning session of this teacher is cooperative"
    ];

    $implicit_subs = [
        "Teacher is willing to accept his/her own error",
        "Teacher respects the opinions of students",
        "The teacher is approachable as per need of students ",
        "Teachers emphasize good academic environment within the class."
    ];

    $questions = [];
    $seen = [];

    // 1. Standard Explicit questions from Builder
    foreach ($explicit_subs as $sub) {
        $key = 'Explicit Issues||' . trim($sub);
        if (!isset($seen[$key])) {
            $seen[$key] = true;
            $questions[] = [
                'question_type'      => 'Explicit Issues',
                'question_text'      => $explicit_text,
                'sub_question_title' => $sub,
                'options'            => $default_options
            ];
        }
    }

    // 2. Standard Implicit questions from Builder
    foreach ($implicit_subs as $sub) {
        $key = 'Implicit Issues||' . trim($sub);
        if (!isset($seen[$key])) {
            $seen[$key] = true;
            $questions[] = [
                'question_type'      => 'Implicit Issues',
                'question_text'      => $implicit_text,
                'sub_question_title' => $sub,
                'options'            => $default_options
            ];
        }
    }

    // 3. Dynamically append any custom distinct questions created in Question Builder database
    if ($wpdb->get_var("SHOW TABLES LIKE '$questions_table'")) {
        $db_questions = $wpdb->get_results("SELECT DISTINCT question_type, question_text, sub_question_title, options FROM $questions_table WHERE sub_question_title IS NOT NULL AND sub_question_title != '' ORDER BY id ASC");
        if (!empty($db_questions)) {
            foreach ($db_questions as $dq) {
                $type = !empty($dq->question_type) ? $dq->question_type : 'Explicit Issues';
                $key = trim($type) . '||' . trim($dq->sub_question_title);
                if (!isset($seen[$key])) {
                    $seen[$key] = true;
                    $questions[] = [
                        'question_type'      => $type,
                        'question_text'      => !empty($dq->question_text) ? $dq->question_text : ($type === 'Implicit Issues' ? $implicit_text : $explicit_text),
                        'sub_question_title' => $dq->sub_question_title,
                        'options'            => !empty($dq->options) ? $dq->options : $default_options
                    ];
                }
            }
        }
    }

    return $questions;
}

/**
 * Get sub-questions list by question type
 */
function tes_get_builder_subquestions($type = 'Explicit Issues') {
    $all = tes_get_builder_questions();
    $subs = [];
    foreach ($all as $q) {
        if ($q['question_type'] === $type && !empty($q['sub_question_title'])) {
            $subs[] = $q['sub_question_title'];
        }
    }
    return array_values(array_unique($subs));
}

/**
 * Assign all Survey Question Builder questions to a survey automatically
 */
function tes_assign_default_questions_to_survey($survey_id) {
    global $wpdb;
    $survey_id = intval($survey_id);
    if ($survey_id <= 0) {
        return 0;
    }

    $questions_table = $wpdb->prefix . 'tes_questions';
    $surveys_table   = $wpdb->prefix . 'tes_surveys';

    // Verify survey exists
    $survey_exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $surveys_table WHERE id = %d", $survey_id));
    if (!$survey_exists) {
        return 0;
    }

    // Fetch existing questions for this survey to avoid duplicate inserts
    $existing = $wpdb->get_results($wpdb->prepare(
        "SELECT question_type, sub_question_title FROM $questions_table WHERE survey_id = %d",
        $survey_id
    ));
    $existing_keys = [];
    if (!empty($existing)) {
        foreach ($existing as $ex) {
            $existing_keys[trim($ex->question_type) . '||' . trim($ex->sub_question_title)] = true;
        }
    }

    $builder_questions = tes_get_builder_questions();
    $inserted_count = 0;

    foreach ($builder_questions as $q) {
        $key = trim($q['question_type']) . '||' . trim($q['sub_question_title']);
        if (isset($existing_keys[$key])) {
            continue;
        }

        $res = $wpdb->insert($questions_table, [
            'survey_id'          => $survey_id,
            'question_type'      => $q['question_type'],
            'question_text'      => $q['question_text'],
            'sub_question_title' => $q['sub_question_title'],
            'options'            => $q['options'],
            'created_at'         => current_time('mysql')
        ]);

        if ($res !== false) {
            $inserted_count++;
            $existing_keys[$key] = true;
        }
    }

    if ($inserted_count > 0) {
        $wpdb->update($surveys_table, ['last_updated' => current_time('mysql')], ['id' => $survey_id]);
    }

    $total_now = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $questions_table WHERE survey_id = %d", $survey_id));
    return intval($total_now);
}

function tes_add_question() {
    global $wpdb;

    if (!wp_verify_nonce($_POST['nonce'], 'tes_add_question')) {
        wp_send_json_error('Security check failed.');
    }

    $survey_id = intval($_POST['survey_id']);
    $question_text = sanitize_text_field($_POST['question_text']);
    $question_type = isset($_POST['question_type']) ? sanitize_text_field($_POST['question_type']) : 'Explicit Issues';
    $sub_question_title = isset($_POST['sub_question_title']) ? sanitize_text_field($_POST['sub_question_title']) : '';
    $options = array_map('sanitize_text_field', $_POST['options']);
    $options_str = implode(',', array_filter($options));

    if (!$survey_id || empty($question_text) || empty($options_str)) {
        wp_send_json_error('Please fill all fields.');
    }

    $result = $wpdb->insert(
        $wpdb->prefix . 'tes_questions',
        [
            'survey_id' => $survey_id,
            'question_type' => $question_type,
            'question_text' => $question_text,
            'sub_question_title' => $sub_question_title,
            'options' => $options_str
        ]
    );

    if ($result) {
        // Update survey last_updated
        $wpdb->update(
            $wpdb->prefix . 'tes_surveys',
            ['last_updated' => current_time('mysql')],
            ['id' => $survey_id]
        );
        $new_id = $wpdb->insert_id;
        $survey_title = $wpdb->get_var($wpdb->prepare("SELECT title FROM {$wpdb->prefix}tes_surveys WHERE id = %d", $survey_id));
        wp_send_json_success([
            'id' => $new_id,
            'survey_id' => $survey_id,
            'question_text' => $question_text,
            'question_type' => $question_type,
            'sub_question_title' => $sub_question_title,
            'options' => $options_str,
            'survey_title' => $survey_title,
            'created_at' => current_time('mysql')
        ]);
    } else {
        wp_send_json_error('Failed to add question.');
    }
}

function tes_submit_survey() {
    global $wpdb;

    if (
        !isset($_POST['nonce'])
        || !is_string($_POST['nonce'])
        || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'tes_submit_survey')
    ) {
        wp_send_json_error('Security check failed.');
    }

    $student_id = tes_get_student_login_id();
    if (!$student_id) {
        wp_send_json_error('Please log in as a student before submitting a survey.');
    }
    $student = $wpdb->get_row($wpdb->prepare(
        "SELECT id, student_name, class_name, phase, batch_name, `group`
         FROM {$wpdb->prefix}tes_students WHERE id = %d",
        $student_id
    ));
    if (!$student) {
        wp_send_json_error('Student account not found. Please log in again.');
    }

    $survey_id = isset($_POST['survey_id']) ? absint($_POST['survey_id']) : 0;
    $student_name = $student->student_name;
    $answers = isset($_POST['answers']) ? $_POST['answers'] : [];
    $comment = isset($_POST['comment']) ? sanitize_textarea_field(wp_unslash($_POST['comment'])) : '';

    // Sanitize answers
    if (is_array($answers)) {
        foreach ($answers as $key => $value) {
            $answers[$key] = sanitize_textarea_field($value);
        }
    }

    if (!$survey_id || empty($student_name) || empty($answers)) {
        wp_send_json_error('Please fill all required fields.');
    }

    $survey_info = $wpdb->get_row($wpdb->prepare(
        "SELECT s.id, s.status, s.`group` as survey_group, s.batch_name,
                t.`group` as teacher_group, t.class_name, t.phase
         FROM {$wpdb->prefix}tes_surveys s 
         INNER JOIN {$wpdb->prefix}tes_teachers t ON s.teacher_id = t.id 
         WHERE s.id = %d", 
        $survey_id
    ));
    if (!$survey_info) {
        wp_send_json_error('Survey not found.');
    }
    if (!empty($survey_info->status) && $survey_info->status === 'paused') {
        wp_send_json_error('This survey is currently paused and cannot be submitted.');
    }
    $submitted_group = isset($_POST['group']) ? sanitize_text_field(wp_unslash($_POST['group'])) : '';
    if (!tes_student_can_access_survey($student, $survey_info, $submitted_group)) {
        wp_send_json_error('Access restricted: this survey is not assigned to your class, phase, batch, or group.');
    }

    $existing_submission = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}tes_submissions
         WHERE survey_id = %d
         AND (student_id = %d OR (student_id IS NULL AND student_name = %s))",
        $survey_id,
        $student_id,
        $student_name
    ));
    if ($existing_submission) {
        wp_send_json_error('You have already submitted this survey.');
    }

    $serialized_answers = serialize($answers);
    $result = $wpdb->insert(
        $wpdb->prefix . 'tes_submissions',
        [
            'survey_id' => $survey_id,
            'student_id' => $student_id > 0 ? $student_id : null,
            'student_name' => $student_name,
            'answers' => $serialized_answers,
            'comment' => $comment
        ],
        [
            '%d',
            $student_id > 0 ? '%d' : 'NULL',
            '%s',
            '%s',
            '%s'
        ]
    );

    if ($result) {
        wp_send_json_success('Feedback submitted successfully.');
    } else {
        wp_send_json_error('Failed to submit feedback. Error: ' . $wpdb->last_error);
    }
}

function tes_student_search_autocomplete() {
    global $wpdb;
    $term = isset($_GET['term']) ? sanitize_text_field($_GET['term']) : '';
    
    if (empty($term)) {
        wp_send_json_success([]);
    }

    $like = '%' . $wpdb->esc_like($term) . '%';
    
    // Search for students by name, username, or department
    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT student_name, username, class_name 
         FROM {$wpdb->prefix}tes_students 
         WHERE student_name LIKE %s OR username LIKE %s OR class_name LIKE %s
         LIMIT 10",
        $like, $like, $like
    ));

    $suggestions = [];
    foreach ($results as $row) {
        $suggestions[] = $row;
    }
    
    wp_send_json_success($suggestions);
}

function tes_import_students() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    if (empty($_FILES['import_file'])) {
        wp_send_json_error('No file uploaded');
    }

    $file = $_FILES['import_file'];
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    if (strtolower($ext) !== 'csv') {
        wp_send_json_error('Please upload a CSV file.');
    }

    $handle = fopen($file['tmp_name'], 'r');
    if ($handle === false) {
        wp_send_json_error('Could not open file.');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'tes_students';
    
    $headers = fgetcsv($handle);
    if (!$headers) {
        wp_send_json_error('File is empty or invalid.');
    }

    // Normalize headers: remove BOM, trim, lowercase, and replace spaces with underscores
    $headers = array_map(function($h) {
        $h = trim($h, "\xEF\xBB\xBF \t\n\r\0\x0B");
        return strtolower(str_replace(' ', '_', $h));
    }, $headers);

    $allowed_cols = ['first_name', 'last_name', 'student_name', 'username', 'email', 'class_name', 'session', 'batch_name', 'phase', 'roll', 'group'];
    $col_indexes = [];

    foreach ($headers as $index => $header) {
        if ($header === 'student_group' || $header === 'group_name') {
            $header = 'group';
        }
        if (in_array($header, $allowed_cols)) {
            $col_indexes[$header] = $index;
        }
    }

    if (empty($col_indexes)) {
        wp_send_json_error('No valid columns found. Please use headers like: ' . implode(', ', $allowed_cols));
    }

    $imported = 0;
    $skipped_missing_email = 0;
    while (($data = fgetcsv($handle)) !== false) {
        $insert_data = [];
        foreach ($col_indexes as $col => $index) {
            if (isset($data[$index])) {
                $insert_data[$col] = $col === 'email' ? sanitize_email($data[$index]) : sanitize_text_field($data[$index]);
            }
        }

        if (!empty($insert_data)) {
            if (
                tes_email_verification_enabled()
                && (empty($insert_data['email']) || !is_email($insert_data['email']))
            ) {
                $skipped_missing_email++;
                continue;
            }

            if (isset($insert_data['first_name']) || isset($insert_data['last_name'])) {
                $fname = isset($insert_data['first_name']) ? $insert_data['first_name'] : '';
                $lname = isset($insert_data['last_name']) ? $insert_data['last_name'] : '';
                $insert_data['student_name'] = trim($fname . ' ' . $lname);
                if (empty($insert_data['username'])) {
                    $insert_data['username'] = !empty($lname) ? strtolower($lname) : strtolower($fname);
                }
                unset($insert_data['first_name']);
                unset($insert_data['last_name']);
            }

            if (empty($insert_data['username']) && !empty($insert_data['student_name'])) {
                $insert_data['username'] = $insert_data['student_name'];
            }

            if (isset($insert_data['roll'])) {
                $insert_data['password'] = $insert_data['roll'];
            }

            if ($wpdb->insert($table_name, $insert_data)) {
                $imported++;
            }
        }
    }

    fclose($handle);
    $message = "Successfully imported $imported students.";
    if ($skipped_missing_email > 0) {
        $message .= " Skipped $skipped_missing_email rows because they did not include a valid email address.";
    }
    wp_send_json_success($message);
}

function tes_import_teachers() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    if (empty($_FILES['import_file'])) {
        wp_send_json_error('No file uploaded');
    }

    $file = $_FILES['import_file'];
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    if (strtolower($ext) !== 'csv') {
        wp_send_json_error('Please upload a CSV file.');
    }

    $handle = fopen($file['tmp_name'], 'r');
    if ($handle === false) {
        wp_send_json_error('Could not open file.');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'tes_teachers';
    
    $headers = fgetcsv($handle);
    if (!$headers) {
        wp_send_json_error('File is empty or invalid.');
    }

    // Normalize headers
    $headers = array_map(function($h) {
        $h = trim($h, "\xEF\xBB\xBF \t\n\r\0\x0B");
        return strtolower(str_replace(' ', '_', $h));
    }, $headers);

    $column_map = [
        'name' => 'name',
        'teacher_name' => 'name',
        'department' => 'department',
        'dept' => 'department',
        'id' => 'teacher_id_number',
        'teacher_id' => 'teacher_id_number',
        'teacher_id_number' => 'teacher_id_number',
        'phase' => 'phase',
        'class' => 'class_name',
        'class_name' => 'class_name',
        'group' => 'group',
        'teacher_group' => 'group',
        'group_name' => 'group',
        'username' => 'username',
        'user' => 'username',
        'user_name' => 'username',
        'password' => 'password',
        'pass' => 'password'
    ];

    $col_indexes = [];
    foreach ($headers as $index => $header) {
        if (isset($column_map[$header])) {
            $col_indexes[$column_map[$header]] = $index;
        }
    }

    if (empty($col_indexes)) {
        wp_send_json_error('No valid columns found. Allowed headers: Name, Department, Teacher ID, Phase, Class, Group, Username, Password.');
    }

    $import_department = isset($_POST['import_department']) ? sanitize_text_field($_POST['import_department']) : '';

    $imported = 0;
    while (($data = fgetcsv($handle)) !== false) {
        $insert_data = [];
        foreach ($col_indexes as $col => $index) {
            if (isset($data[$index])) {
                $insert_data[$col] = sanitize_text_field($data[$index]);
            }
        }

        if (!empty($insert_data) && !empty($insert_data['name'])) {
            if (!empty($import_department)) {
                $insert_data['department'] = $import_department;
            }

            // Auto-generate username if not provided
            if (empty($insert_data['username'])) {
                if (!empty($insert_data['teacher_id_number'])) {
                    $insert_data['username'] = sanitize_user(strtolower($insert_data['teacher_id_number']), true);
                } else {
                    $insert_data['username'] = sanitize_user(strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $insert_data['name'])), true);
                }
            }
            if (empty($insert_data['username'])) {
                $insert_data['username'] = 'teacher_' . wp_rand(100, 9999);
            }

            // Auto-generate password if not provided
            if (empty($insert_data['password'])) {
                if (!empty($insert_data['teacher_id_number'])) {
                    $insert_data['password'] = $insert_data['teacher_id_number'];
                } else {
                    $insert_data['password'] = '123456';
                }
            }

            // Ensure username uniqueness
            $base_user = $insert_data['username'];
            $suffix = 1;
            while ($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE username = %s", $insert_data['username'])) > 0) {
                $insert_data['username'] = $base_user . $suffix;
                $suffix++;
            }

            $insert_data['role'] = 'teacher';

            if ($wpdb->insert($table_name, $insert_data)) {
                $imported++;
            }
        }
    }

    fclose($handle);
    wp_send_json_success("Successfully imported $imported teachers.");
}

function tes_question_search_autocomplete() {
    global $wpdb;
    $term = isset($_GET['term']) ? sanitize_text_field($_GET['term']) : '';
    
    if (empty($term)) {
        wp_send_json_success([]);
    }

    $like = '%' . $wpdb->esc_like($term) . '%';
    
    $questions_table = $wpdb->prefix . 'tes_questions';
    $surveys_table = $wpdb->prefix . 'tes_surveys';

    // Search for surveys by title (distinct)
    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT DISTINCT s.title as survey_title
         FROM $questions_table q
         LEFT JOIN $surveys_table s ON q.survey_id = s.id
         WHERE s.title LIKE %s
         LIMIT 10",
        $like
    ));
    
    wp_send_json_success($results);
}

function tes_survey_search_autocomplete() {
    global $wpdb;
    $term = isset($_GET['term']) ? sanitize_text_field($_GET['term']) : '';
    
    if (empty($term)) {
        wp_send_json_success([]);
    }

    $like = '%' . $wpdb->esc_like($term) . '%';
    
    // Search for surveys by title
    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT s.id, s.title, s.`group` as survey_group, t.name as teacher_name, t.phase, t.class_name, t.`group` as teacher_group
         FROM {$wpdb->prefix}tes_surveys s
         LEFT JOIN {$wpdb->prefix}tes_teachers t ON s.teacher_id = t.id
         WHERE s.title LIKE %s OR t.name LIKE %s OR t.phase LIKE %s OR t.class_name LIKE %s OR s.`group` LIKE %s OR t.`group` LIKE %s
         LIMIT 10",
        $like, $like, $like, $like, $like, $like
    ));
    
    wp_send_json_success( $results );
}

function tes_teacher_search_autocomplete() {
    global $wpdb;
    $term = isset($_GET['term']) ? sanitize_text_field($_GET['term']) : '';
    
    if (empty($term)) {
        wp_send_json_success([]);
    }

    $like = '%' . $wpdb->esc_like($term) . '%';
    
    // Search for teachers by name or department
    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT name, department, teacher_id_number, phase, class_name, `group` 
         FROM {$wpdb->prefix}tes_teachers 
         WHERE (name LIKE %s OR department LIKE %s OR teacher_id_number LIKE %s OR phase LIKE %s OR class_name LIKE %s OR `group` LIKE %s) AND (role != 'advisor' OR role IS NULL)
         LIMIT 10",
        $like, $like, $like, $like, $like, $like
    ));

    $suggestions = [];
    foreach ($results as $row) {
        $suggestions[] = $row;
    }
    
    wp_send_json_success($suggestions);
}
