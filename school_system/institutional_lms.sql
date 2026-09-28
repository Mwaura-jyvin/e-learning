-- ============================================================
-- INSTITUTIONAL LEARNING MANAGEMENT SYSTEM (LMS)
-- Database: institutional_lms
-- Backend: PHP + PDO
-- Database Engine: MySQL 8+
-- Character Set: utf8mb4
--
-- IMPORTANT:
-- PDO is used by PHP to connect to and query this database.
-- PDO itself is NOT part of SQL, so there is no "PDO syntax" inside
-- this SQL file. This schema is designed for safe PDO-based PHP code.
-- ============================================================

CREATE DATABASE IF NOT EXISTS institutional_lms
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE institutional_lms;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- 1. SYSTEM / ORGANIZATION STRUCTURE
-- ============================================================

CREATE TABLE IF NOT EXISTS departments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    code VARCHAR(50) NOT NULL UNIQUE,
    description TEXT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS programs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    department_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(200) NOT NULL,
    code VARCHAR(50) NOT NULL UNIQUE,
    description TEXT NULL,
    duration_value DECIMAL(5,2) NULL,
    duration_unit ENUM('months','years','semesters','terms') NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_program_department
        FOREIGN KEY (department_id) REFERENCES departments(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS academic_years (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('planned','active','completed','archived') NOT NULL DEFAULT 'planned',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS semesters (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    academic_year_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    number TINYINT UNSIGNED NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('planned','active','completed','archived') NOT NULL DEFAULT 'planned',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_semester_year
        FOREIGN KEY (academic_year_id) REFERENCES academic_years(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================================
-- 2. ROLES AND USERS
-- ============================================================

CREATE TABLE IF NOT EXISTS roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS permissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    display_name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS role_permissions (
    role_id BIGINT UNSIGNED NOT NULL,
    permission_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_role_permission_role
        FOREIGN KEY (role_id) REFERENCES roles(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_role_permission_permission
        FOREIGN KEY (permission_id) REFERENCES permissions(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id BIGINT UNSIGNED NOT NULL,
    department_id BIGINT UNSIGNED NULL,
    program_id BIGINT UNSIGNED NULL,
    admission_no VARCHAR(100) NULL UNIQUE,
    employee_no VARCHAR(100) NULL UNIQUE,
    first_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100) NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    phone VARCHAR(30) NULL,
    password_hash VARCHAR(255) NOT NULL,
    gender ENUM('male','female','other','prefer_not_to_say') NULL,
    date_of_birth DATE NULL,
    profile_photo VARCHAR(500) NULL,
    address TEXT NULL,
    city VARCHAR(100) NULL,
    country VARCHAR(100) NULL DEFAULT 'Kenya',
    account_status ENUM('active','inactive','suspended','pending') NOT NULL DEFAULT 'active',
    email_verified_at DATETIME NULL,
    last_login_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_role
        FOREIGN KEY (role_id) REFERENCES roles(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_user_department
        FOREIGN KEY (department_id) REFERENCES departments(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_user_program
        FOREIGN KEY (program_id) REFERENCES programs(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_users_role (role_id),
    INDEX idx_users_department (department_id),
    INDEX idx_users_program (program_id),
    INDEX idx_users_status (account_status)
) ENGINE=InnoDB;

-- ============================================================
-- 3. COURSE CATALOG
-- ============================================================

CREATE TABLE IF NOT EXISTS course_categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL UNIQUE,
    description TEXT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS courses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    department_id BIGINT UNSIGNED NOT NULL,
    category_id BIGINT UNSIGNED NULL,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(200) NOT NULL,
    short_description VARCHAR(500) NULL,
    description LONGTEXT NULL,
    level ENUM('foundation','certificate','diploma','beginner','intermediate','advanced','undergraduate','postgraduate','other')
        NOT NULL DEFAULT 'other',
    credits DECIMAL(6,2) NULL,
    course_image VARCHAR(500) NULL,
    status ENUM('draft','pending_review','published','archived') NOT NULL DEFAULT 'draft',
    created_by BIGINT UNSIGNED NOT NULL,
    approved_by BIGINT UNSIGNED NULL,
    approved_at DATETIME NULL,
    published_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_course_department
        FOREIGN KEY (department_id) REFERENCES departments(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_course_category
        FOREIGN KEY (category_id) REFERENCES course_categories(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_course_creator
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_course_approver
        FOREIGN KEY (approved_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_courses_department (department_id),
    INDEX idx_courses_status (status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS course_programs (
    course_id BIGINT UNSIGNED NOT NULL,
    program_id BIGINT UNSIGNED NOT NULL,
    is_core BOOLEAN NOT NULL DEFAULT TRUE,
    PRIMARY KEY (course_id, program_id),
    CONSTRAINT fk_course_program_course
        FOREIGN KEY (course_id) REFERENCES courses(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_course_program_program
        FOREIGN KEY (program_id) REFERENCES programs(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS course_prerequisites (
    course_id BIGINT UNSIGNED NOT NULL,
    prerequisite_course_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (course_id, prerequisite_course_id),
    CONSTRAINT fk_prereq_course
        FOREIGN KEY (course_id) REFERENCES courses(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_prereq_required
        FOREIGN KEY (prerequisite_course_id) REFERENCES courses(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 4. COURSE OFFERINGS / TEACHING ASSIGNMENTS
-- A course can be offered in different semesters and taught by
-- different lecturers.
-- ============================================================

CREATE TABLE IF NOT EXISTS course_offerings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id BIGINT UNSIGNED NOT NULL,
    semester_id BIGINT UNSIGNED NOT NULL,
    section_name VARCHAR(100) NULL,
    start_date DATE NULL,
    end_date DATE NULL,
    capacity INT UNSIGNED NULL,
    status ENUM('planned','open','ongoing','completed','cancelled') NOT NULL DEFAULT 'planned',
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_offering_course
        FOREIGN KEY (course_id) REFERENCES courses(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_offering_semester
        FOREIGN KEY (semester_id) REFERENCES semesters(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_offering_creator
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_offering_course (course_id),
    INDEX idx_offering_semester (semester_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS lecturer_course_assignments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_offering_id BIGINT UNSIGNED NOT NULL,
    lecturer_id BIGINT UNSIGNED NOT NULL,
    assignment_role ENUM('lecturer','co_lecturer','assistant','assessor') NOT NULL DEFAULT 'lecturer',
    assigned_by BIGINT UNSIGNED NOT NULL,
    assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    UNIQUE KEY uq_lecturer_offering_role (course_offering_id, lecturer_id, assignment_role),
    CONSTRAINT fk_lecturer_assignment_offering
        FOREIGN KEY (course_offering_id) REFERENCES course_offerings(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_assignment_lecturer
        FOREIGN KEY (lecturer_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_assignment_assigner
        FOREIGN KEY (assigned_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_assignment_lecturer (lecturer_id)
) ENGINE=InnoDB;

-- ============================================================
-- 5. STUDENT ENROLLMENT
-- ============================================================

CREATE TABLE IF NOT EXISTS enrollments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_offering_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    enrolled_by BIGINT UNSIGNED NULL,
    enrollment_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending','active','completed','dropped','suspended','cancelled') NOT NULL DEFAULT 'active',
    completion_date DATETIME NULL,
    final_score DECIMAL(5,2) NULL,
    final_grade VARCHAR(10) NULL,
    UNIQUE KEY uq_student_offering (course_offering_id, student_id),
    CONSTRAINT fk_enrollment_offering
        FOREIGN KEY (course_offering_id) REFERENCES course_offerings(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_enrollment_student
        FOREIGN KEY (student_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_enrollment_by
        FOREIGN KEY (enrolled_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_enrollment_student (student_id),
    INDEX idx_enrollment_status (status)
) ENGINE=InnoDB;

-- ============================================================
-- 6. COURSE MODULES / LESSONS
-- ============================================================

CREATE TABLE IF NOT EXISTS course_modules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    module_order INT UNSIGNED NOT NULL DEFAULT 1,
    status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_course_module_order (course_id, module_order),
    CONSTRAINT fk_module_course
        FOREIGN KEY (course_id) REFERENCES courses(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_module_creator
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_modules_course (course_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS lessons (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    module_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NULL,
    description TEXT NULL,
    content LONGTEXT NULL,
    lesson_type ENUM('text','video','document','audio','presentation','external_link','mixed')
        NOT NULL DEFAULT 'text',
    video_url VARCHAR(1000) NULL,
    external_url VARCHAR(1000) NULL,
    duration_minutes INT UNSIGNED NULL,
    lesson_order INT UNSIGNED NOT NULL DEFAULT 1,
    is_preview BOOLEAN NOT NULL DEFAULT FALSE,
    is_mandatory BOOLEAN NOT NULL DEFAULT TRUE,
    status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
    created_by BIGINT UNSIGNED NOT NULL,
    published_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_lesson_module_order (module_id, lesson_order),
    CONSTRAINT fk_lesson_module
        FOREIGN KEY (module_id) REFERENCES course_modules(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_lesson_creator
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_lessons_module (module_id),
    INDEX idx_lessons_status (status)
) ENGINE=InnoDB;

-- ============================================================
-- 7. LEARNING RESOURCES / FILES
-- ============================================================

CREATE TABLE IF NOT EXISTS learning_resources (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id BIGINT UNSIGNED NOT NULL,
    module_id BIGINT UNSIGNED NULL,
    lesson_id BIGINT UNSIGNED NULL,
    uploaded_by BIGINT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    resource_type ENUM('pdf','document','spreadsheet','presentation','image','audio','video','zip','other')
        NOT NULL DEFAULT 'other',
    file_path VARCHAR(1000) NOT NULL,
    original_filename VARCHAR(500) NULL,
    mime_type VARCHAR(150) NULL,
    file_size BIGINT UNSIGNED NULL,
    download_allowed BOOLEAN NOT NULL DEFAULT TRUE,
    status ENUM('active','hidden','archived') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_resource_course
        FOREIGN KEY (course_id) REFERENCES courses(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_resource_module
        FOREIGN KEY (module_id) REFERENCES course_modules(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_resource_lesson
        FOREIGN KEY (lesson_id) REFERENCES lessons(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_resource_uploader
        FOREIGN KEY (uploaded_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_resources_course (course_id),
    INDEX idx_resources_lesson (lesson_id)
) ENGINE=InnoDB;

-- ============================================================
-- 8. STUDENT LEARNING PROGRESS
-- ============================================================

CREATE TABLE IF NOT EXISTS lesson_progress (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lesson_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    status ENUM('not_started','in_progress','completed') NOT NULL DEFAULT 'not_started',
    progress_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    first_opened_at DATETIME NULL,
    last_accessed_at DATETIME NULL,
    completed_at DATETIME NULL,
    UNIQUE KEY uq_student_lesson (lesson_id, student_id),
    CONSTRAINT fk_lesson_progress_lesson
        FOREIGN KEY (lesson_id) REFERENCES lessons(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_lesson_progress_student
        FOREIGN KEY (student_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX idx_lesson_progress_student (student_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS course_progress (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_offering_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    progress_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    lessons_completed INT UNSIGNED NOT NULL DEFAULT 0,
    lessons_total INT UNSIGNED NOT NULL DEFAULT 0,
    last_accessed_at DATETIME NULL,
    completed_at DATETIME NULL,
    UNIQUE KEY uq_course_progress (course_offering_id, student_id),
    CONSTRAINT fk_course_progress_offering
        FOREIGN KEY (course_offering_id) REFERENCES course_offerings(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_course_progress_student
        FOREIGN KEY (student_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 9. ASSIGNMENTS
-- ============================================================

CREATE TABLE IF NOT EXISTS assignments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_offering_id BIGINT UNSIGNED NOT NULL,
    module_id BIGINT UNSIGNED NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    instructions LONGTEXT NOT NULL,
    attachment_path VARCHAR(1000) NULL,
    max_score DECIMAL(7,2) NOT NULL DEFAULT 100.00,
    passing_score DECIMAL(7,2) NULL,
    available_from DATETIME NULL,
    due_date DATETIME NULL,
    late_submission_allowed BOOLEAN NOT NULL DEFAULT FALSE,
    late_penalty_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    max_attempts INT UNSIGNED NOT NULL DEFAULT 1,
    status ENUM('draft','published','closed','archived') NOT NULL DEFAULT 'draft',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_assignments_offering
        FOREIGN KEY (course_offering_id) REFERENCES course_offerings(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_assignment_module
        FOREIGN KEY (module_id) REFERENCES course_modules(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_assignment_creator
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_assignments_offering (course_offering_id),
    INDEX idx_assignments_due (due_date)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS assignment_submissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    assignment_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    attempt_number INT UNSIGNED NOT NULL DEFAULT 1,
    submission_text LONGTEXT NULL,
    file_path VARCHAR(1000) NULL,
    submitted_at DATETIME NULL,
    status ENUM('draft','submitted','late','graded','returned','resubmission_required')
        NOT NULL DEFAULT 'draft',
    score DECIMAL(7,2) NULL,
    feedback LONGTEXT NULL,
    graded_by BIGINT UNSIGNED NULL,
    graded_at DATETIME NULL,
    UNIQUE KEY uq_assignment_student_attempt (assignment_id, student_id, attempt_number),
    CONSTRAINT fk_submission_assignment
        FOREIGN KEY (assignment_id) REFERENCES assignments(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_submission_student
        FOREIGN KEY (student_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_submission_grader
        FOREIGN KEY (graded_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_submissions_student (student_id),
    INDEX idx_submissions_status (status)
) ENGINE=InnoDB;

-- ============================================================
-- 10. QUIZZES / EXAMS
-- ============================================================

CREATE TABLE IF NOT EXISTS assessments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_offering_id BIGINT UNSIGNED NOT NULL,
    module_id BIGINT UNSIGNED NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    description LONGTEXT NULL,
    assessment_type ENUM('quiz','midterm','final_exam','practice','survey','other')
        NOT NULL DEFAULT 'quiz',
    instructions LONGTEXT NULL,
    total_marks DECIMAL(7,2) NOT NULL DEFAULT 0.00,
    pass_mark DECIMAL(7,2) NULL,
    duration_minutes INT UNSIGNED NULL,
    available_from DATETIME NULL,
    available_until DATETIME NULL,
    attempts_allowed INT UNSIGNED NOT NULL DEFAULT 1,
    randomize_questions BOOLEAN NOT NULL DEFAULT FALSE,
    show_results_immediately BOOLEAN NOT NULL DEFAULT FALSE,
    status ENUM('draft','published','closed','archived') NOT NULL DEFAULT 'draft',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_assessment_offering
        FOREIGN KEY (course_offering_id) REFERENCES course_offerings(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_assessment_module
        FOREIGN KEY (module_id) REFERENCES course_modules(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_assessment_creator
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_assessment_offering (course_offering_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS assessment_questions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    assessment_id BIGINT UNSIGNED NOT NULL,
    question_text LONGTEXT NOT NULL,
    question_type ENUM(
        'multiple_choice',
        'multiple_select',
        'true_false',
        'short_answer',
        'long_answer',
        'fill_blank'
    ) NOT NULL DEFAULT 'multiple_choice',
    marks DECIMAL(7,2) NOT NULL DEFAULT 1.00,
    question_order INT UNSIGNED NOT NULL DEFAULT 1,
    explanation LONGTEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_question_assessment
        FOREIGN KEY (assessment_id) REFERENCES assessments(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX idx_questions_assessment (assessment_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS assessment_options (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    question_id BIGINT UNSIGNED NOT NULL,
    option_text LONGTEXT NOT NULL,
    is_correct BOOLEAN NOT NULL DEFAULT FALSE,
    option_order INT UNSIGNED NOT NULL DEFAULT 1,
    CONSTRAINT fk_option_question
        FOREIGN KEY (question_id) REFERENCES assessment_questions(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX idx_options_question (question_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS assessment_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    assessment_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    attempt_number INT UNSIGNED NOT NULL DEFAULT 1,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    submitted_at DATETIME NULL,
    status ENUM('in_progress','submitted','graded','abandoned') NOT NULL DEFAULT 'in_progress',
    score DECIMAL(7,2) NULL,
    percentage DECIMAL(5,2) NULL,
    passed BOOLEAN NULL,
    UNIQUE KEY uq_assessment_attempt (assessment_id, student_id, attempt_number),
    CONSTRAINT fk_attempt_assessment
        FOREIGN KEY (assessment_id) REFERENCES assessments(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_attempt_student
        FOREIGN KEY (student_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX idx_attempt_student (student_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS assessment_answers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    attempt_id BIGINT UNSIGNED NOT NULL,
    question_id BIGINT UNSIGNED NOT NULL,
    selected_option_id BIGINT UNSIGNED NULL,
    answer_text LONGTEXT NULL,
    marks_awarded DECIMAL(7,2) NULL,
    is_correct BOOLEAN NULL,
    feedback TEXT NULL,
    answered_at DATETIME NULL,
    UNIQUE KEY uq_attempt_question (attempt_id, question_id),
    CONSTRAINT fk_answer_attempt
        FOREIGN KEY (attempt_id) REFERENCES assessment_attempts(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_answer_question
        FOREIGN KEY (question_id) REFERENCES assessment_questions(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_answer_option
        FOREIGN KEY (selected_option_id) REFERENCES assessment_options(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_answers_attempt (attempt_id)
) ENGINE=InnoDB;

-- ============================================================
-- 11. GRADES / RESULTS
-- ============================================================

CREATE TABLE IF NOT EXISTS grade_scales (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS grade_scale_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    grade_scale_id BIGINT UNSIGNED NOT NULL,
    grade VARCHAR(10) NOT NULL,
    min_percentage DECIMAL(5,2) NOT NULL,
    max_percentage DECIMAL(5,2) NOT NULL,
    grade_point DECIMAL(5,2) NULL,
    description VARCHAR(255) NULL,
    CONSTRAINT fk_grade_scale_item
        FOREIGN KEY (grade_scale_id) REFERENCES grade_scales(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS course_results (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    enrollment_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    course_offering_id BIGINT UNSIGNED NOT NULL,
    coursework_score DECIMAL(7,2) NULL,
    exam_score DECIMAL(7,2) NULL,
    total_score DECIMAL(7,2) NULL,
    grade VARCHAR(10) NULL,
    grade_point DECIMAL(5,2) NULL,
    passed BOOLEAN NULL,
    remarks TEXT NULL,
    entered_by BIGINT UNSIGNED NULL,
    entered_at DATETIME NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_course_result_enrollment (enrollment_id),
    CONSTRAINT fk_result_enrollment
        FOREIGN KEY (enrollment_id) REFERENCES enrollments(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_result_student
        FOREIGN KEY (student_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_result_offering
        FOREIGN KEY (course_offering_id) REFERENCES course_offerings(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_result_entered_by
        FOREIGN KEY (entered_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_results_student (student_id)
) ENGINE=InnoDB;

-- ============================================================
-- 12. CERTIFICATES
-- ============================================================

CREATE TABLE IF NOT EXISTS certificate_templates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL UNIQUE,
    description TEXT NULL,
    template_path VARCHAR(1000) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS certificates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    certificate_template_id BIGINT UNSIGNED NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    course_id BIGINT UNSIGNED NULL,
    course_offering_id BIGINT UNSIGNED NULL,
    certificate_number VARCHAR(100) NOT NULL UNIQUE,
    verification_code VARCHAR(150) NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    issued_date DATE NOT NULL,
    completion_date DATE NULL,
    certificate_file VARCHAR(1000) NULL,
    status ENUM('valid','revoked','expired') NOT NULL DEFAULT 'valid',
    issued_by BIGINT UNSIGNED NULL,
    revoked_by BIGINT UNSIGNED NULL,
    revoked_at DATETIME NULL,
    revocation_reason TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_certificate_template
        FOREIGN KEY (certificate_template_id) REFERENCES certificate_templates(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_certificate_student
        FOREIGN KEY (student_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_certificate_course
        FOREIGN KEY (course_id) REFERENCES courses(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_certificate_offering
        FOREIGN KEY (course_offering_id) REFERENCES course_offerings(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_certificate_issuer
        FOREIGN KEY (issued_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_certificate_revoker
        FOREIGN KEY (revoked_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_cert_student (student_id),
    INDEX idx_cert_verification (verification_code)
) ENGINE=InnoDB;

-- ============================================================
-- 13. ANNOUNCEMENTS
-- ============================================================

CREATE TABLE IF NOT EXISTS announcements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    created_by BIGINT UNSIGNED NOT NULL,
    course_offering_id BIGINT UNSIGNED NULL,
    title VARCHAR(255) NOT NULL,
    message LONGTEXT NOT NULL,
    audience_type ENUM('all','students','lecturers','deans','course') NOT NULL DEFAULT 'all',
    priority ENUM('normal','important','urgent') NOT NULL DEFAULT 'normal',
    publish_at DATETIME NULL,
    expires_at DATETIME NULL,
    status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_announcement_creator
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_announcement_offering
        FOREIGN KEY (course_offering_id) REFERENCES course_offerings(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX idx_announcements_status (status),
    INDEX idx_announcements_offering (course_offering_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS announcement_reads (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    announcement_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    read_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_announcement_user (announcement_id, user_id),
    CONSTRAINT fk_announcement_read
        FOREIGN KEY (announcement_id) REFERENCES announcements(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_announcement_reader
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 14. DISCUSSION / COMMUNICATION
-- ============================================================

CREATE TABLE IF NOT EXISTS course_discussions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_offering_id BIGINT UNSIGNED NOT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    message LONGTEXT NOT NULL,
    status ENUM('open','closed','archived') NOT NULL DEFAULT 'open',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_discussion_offering
        FOREIGN KEY (course_offering_id) REFERENCES course_offerings(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_discussion_creator
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS discussion_replies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    discussion_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    message LONGTEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_reply_discussion
        FOREIGN KEY (discussion_id) REFERENCES course_discussions(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_reply_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 15. NOTIFICATIONS
-- ============================================================

CREATE TABLE IF NOT EXISTS notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    type VARCHAR(100) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    related_table VARCHAR(100) NULL,
    related_id BIGINT UNSIGNED NULL,
    is_read BOOLEAN NOT NULL DEFAULT FALSE,
    read_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notification_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX idx_notifications_user_read (user_id, is_read),
    INDEX idx_notifications_created (created_at)
) ENGINE=InnoDB;

-- ============================================================
-- 16. AUDIT LOG
-- ============================================================

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100) NULL,
    entity_id BIGINT UNSIGNED NULL,
    description TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_audit_user (user_id),
    INDEX idx_audit_entity (entity_type, entity_id),
    INDEX idx_audit_created (created_at)
) ENGINE=InnoDB;

-- ============================================================
-- 17. LOGIN / SECURITY SUPPORT
-- ============================================================

CREATE TABLE IF NOT EXISTS login_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    email VARCHAR(190) NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    was_successful BOOLEAN NOT NULL DEFAULT FALSE,
    attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_login_attempt_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_login_email_time (email, attempted_at),
    INDEX idx_login_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    token_hash VARCHAR(255) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reset_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 18. SYSTEM SETTINGS
-- ============================================================

CREATE TABLE IF NOT EXISTS system_settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(150) NOT NULL UNIQUE,
    setting_value LONGTEXT NULL,
    setting_type ENUM('string','integer','boolean','json','text') NOT NULL DEFAULT 'string',
    description TEXT NULL,
    updated_by BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_setting_updater
        FOREIGN KEY (updated_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- 19. DEFAULT ROLES
-- ============================================================

INSERT IGNORE INTO roles (name, display_name, description) VALUES
('super_admin', 'Super Administrator', 'Full control of the entire LMS'),
('dean', 'Dean / Academic Administrator', 'Oversees academic activities, students, lecturers and reports'),
('lecturer', 'Lecturer', 'Manages assigned courses and learning content'),
('student', 'Student', 'Accesses enrolled courses and completes learning activities'),
('assessor', 'Assessor', 'Assesses and grades designated academic work');

-- ============================================================
-- 20. DEFAULT PERMISSIONS
-- ============================================================

INSERT IGNORE INTO permissions (name, display_name, description) VALUES
('dashboard.view', 'View Dashboard', 'Access the appropriate dashboard'),
('users.view', 'View Users', 'View user accounts'),
('users.create', 'Create Users', 'Create user accounts'),
('users.update', 'Update Users', 'Update user accounts'),
('users.delete', 'Delete Users', 'Delete user accounts'),
('roles.manage', 'Manage Roles', 'Manage roles and permissions'),
('departments.manage', 'Manage Departments', 'Manage departments'),
('programs.manage', 'Manage Programs', 'Manage academic programs'),
('academic_years.manage', 'Manage Academic Years', 'Manage academic years'),
('semesters.manage', 'Manage Semesters', 'Manage semesters'),
('courses.view', 'View Courses', 'View courses'),
('courses.create', 'Create Courses', 'Create courses'),
('courses.update', 'Update Courses', 'Update courses'),
('courses.delete', 'Delete Courses', 'Delete courses'),
('courses.publish', 'Publish Courses', 'Publish course content'),
('courses.assign_lecturers', 'Assign Lecturers', 'Assign lecturers to courses'),
('enrollments.view', 'View Enrollments', 'View course enrollments'),
('enrollments.manage', 'Manage Enrollments', 'Enroll or remove students'),
('content.create', 'Create Learning Content', 'Create modules, lessons and resources'),
('content.update', 'Update Learning Content', 'Edit learning content'),
('content.delete', 'Delete Learning Content', 'Delete learning content'),
('assignments.manage', 'Manage Assignments', 'Create and manage assignments'),
('assignments.grade', 'Grade Assignments', 'Grade student submissions'),
('assessments.manage', 'Manage Assessments', 'Create quizzes and exams'),
('assessments.take', 'Take Assessments', 'Take quizzes and exams'),
('results.view_all', 'View All Results', 'View institutional results'),
('results.view_course', 'View Course Results', 'View results for assigned courses'),
('results.view_own', 'View Own Results', 'View personal results'),
('certificates.manage', 'Manage Certificates', 'Issue and manage certificates'),
('certificates.verify', 'Verify Certificates', 'Verify certificate authenticity'),
('reports.view', 'View Reports', 'View academic reports'),
('announcements.manage', 'Manage Announcements', 'Create and publish announcements'),
('discussions.manage', 'Manage Discussions', 'Moderate course discussions'),
('audit.view', 'View Audit Logs', 'View system audit logs'),
('settings.manage', 'Manage System Settings', 'Manage system settings');

-- ============================================================
-- 21. DEFAULT ROLE PERMISSIONS
-- ============================================================

-- Super Admin gets every permission.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.name = 'super_admin';

-- Dean permissions.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.name IN (
    'dashboard.view',
    'users.view',
    'users.create',
    'users.update',
    'departments.manage',
    'programs.manage',
    'academic_years.manage',
    'semesters.manage',
    'courses.view',
    'courses.create',
    'courses.update',
    'courses.publish',
    'courses.assign_lecturers',
    'enrollments.view',
    'enrollments.manage',
    'results.view_all',
    'certificates.manage',
    'certificates.verify',
    'reports.view',
    'announcements.manage',
    'discussions.manage'
)
WHERE r.name = 'dean';

-- Lecturer permissions.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.name IN (
    'dashboard.view',
    'courses.view',
    'content.create',
    'content.update',
    'content.delete',
    'assignments.manage',
    'assignments.grade',
    'assessments.manage',
    'results.view_course',
    'announcements.manage',
    'discussions.manage'
)
WHERE r.name = 'lecturer';

-- Assessor permissions.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.name IN (
    'dashboard.view',
    'courses.view',
    'assignments.grade',
    'results.view_course',
    'results.view_all'
)
WHERE r.name = 'assessor';

-- Student permissions.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.name IN (
    'dashboard.view',
    'courses.view',
    'enrollments.view',
    'assessments.take',
    'results.view_own',
    'certificates.verify',
    'discussions.manage'
)
WHERE r.name = 'student';

-- ============================================================
-- 22. DEFAULT GRADE SCALE
-- ============================================================

INSERT IGNORE INTO grade_scales (name, description)
VALUES ('Default Academic Scale', 'Default percentage-to-grade scale');

INSERT IGNORE INTO grade_scale_items
    (grade_scale_id, grade, min_percentage, max_percentage, grade_point, description)
SELECT id, 'A', 80.00, 100.00, 4.00, 'Excellent'
FROM grade_scales
WHERE name = 'Default Academic Scale';

INSERT IGNORE INTO grade_scale_items
    (grade_scale_id, grade, min_percentage, max_percentage, grade_point, description)
SELECT id, 'B', 70.00, 79.99, 3.00, 'Very Good'
FROM grade_scales
WHERE name = 'Default Academic Scale';

INSERT IGNORE INTO grade_scale_items
    (grade_scale_id, grade, min_percentage, max_percentage, grade_point, description)
SELECT id, 'C', 60.00, 69.99, 2.00, 'Good'
FROM grade_scales
WHERE name = 'Default Academic Scale';

INSERT IGNORE INTO grade_scale_items
    (grade_scale_id, grade, min_percentage, max_percentage, grade_point, description)
SELECT id, 'D', 50.00, 59.99, 1.00, 'Pass'
FROM grade_scales
WHERE name = 'Default Academic Scale';

INSERT IGNORE INTO grade_scale_items
    (grade_scale_id, grade, min_percentage, max_percentage, grade_point, description)
SELECT id, 'E', 0.00, 49.99, 0.00, 'Fail'
FROM grade_scales
WHERE name = 'Default Academic Scale';

-- ============================================================
-- 23. DEFAULT SYSTEM SETTINGS
-- ============================================================

INSERT IGNORE INTO system_settings
    (setting_key, setting_value, setting_type, description)
VALUES
('site_name', 'Institutional Learning Management System', 'string', 'Platform name'),
('site_short_name', 'LMS', 'string', 'Short platform name'),
('default_country', 'Kenya', 'string', 'Default country'),
('allow_student_registration', '1', 'boolean', 'Allow students to self-register'),
('require_course_approval', '1', 'boolean', 'Require approval before publishing courses'),
('certificate_prefix', 'CERT', 'string', 'Certificate number prefix'),
('max_login_attempts', '5', 'integer', 'Maximum login attempts before temporary lockout');

-- ============================================================
-- 24. VIEWS FOR COMMON DASHBOARD DATA
-- ============================================================

CREATE OR REPLACE VIEW v_student_course_progress AS
SELECT
    e.id AS enrollment_id,
    e.student_id,
    e.course_offering_id,
    c.id AS course_id,
    c.code AS course_code,
    c.name AS course_name,
    co.section_name,
    s.name AS semester_name,
    cp.progress_percent,
    cp.lessons_completed,
    cp.lessons_total,
    e.status AS enrollment_status,
    e.final_score,
    e.final_grade,
    e.completion_date
FROM enrollments e
JOIN course_offerings co ON co.id = e.course_offering_id
JOIN courses c ON c.id = co.course_id
JOIN semesters s ON s.id = co.semester_id
LEFT JOIN course_progress cp
    ON cp.course_offering_id = e.course_offering_id
    AND cp.student_id = e.student_id;

CREATE OR REPLACE VIEW v_lecturer_courses AS
SELECT
    lca.id AS assignment_id,
    lca.lecturer_id,
    lca.assignment_role,
    lca.status AS assignment_status,
    co.id AS course_offering_id,
    c.id AS course_id,
    c.code AS course_code,
    c.name AS course_name,
    co.section_name,
    s.name AS semester_name,
    s.start_date,
    s.end_date,
    co.status AS offering_status
FROM lecturer_course_assignments lca
JOIN course_offerings co ON co.id = lca.course_offering_id
JOIN courses c ON c.id = co.course_id
JOIN semesters s ON s.id = co.semester_id;

CREATE OR REPLACE VIEW v_course_enrollment_summary AS
SELECT
    co.id AS course_offering_id,
    c.code AS course_code,
    c.name AS course_name,
    co.section_name,
    COUNT(e.id) AS total_students,
    SUM(CASE WHEN e.status = 'active' THEN 1 ELSE 0 END) AS active_students,
    SUM(CASE WHEN e.status = 'completed' THEN 1 ELSE 0 END) AS completed_students,
    SUM(CASE WHEN e.status = 'dropped' THEN 1 ELSE 0 END) AS dropped_students
FROM course_offerings co
JOIN courses c ON c.id = co.course_id
LEFT JOIN enrollments e ON e.course_offering_id = co.id
GROUP BY
    co.id,
    c.code,
    c.name,
    co.section_name;

-- ============================================================
-- 25. OPTIONAL SAMPLE ADMIN ACCOUNT PLACEHOLDER
-- ============================================================
-- DO NOT store a plain-text password in this SQL file.
-- Generate the password hash in PHP using:
-- password_hash($password, PASSWORD_DEFAULT)
--
-- Then insert:
--
-- INSERT INTO users (
--     role_id, first_name, last_name, email, password_hash,
--     account_status
-- )
-- SELECT
--     id, 'System', 'Administrator', 'admin@example.com',
--     'REPLACE_WITH_PHP_PASSWORD_HASH',
--     'active'
-- FROM roles
-- WHERE name = 'super_admin';

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- END OF DATABASE SCHEMA
-- ============================================================
