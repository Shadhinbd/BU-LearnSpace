-- ========================================================
-- BU LearnSpace - Updated SQL (with new modules)
-- ========================================================

CREATE DATABASE IF NOT EXISTS bu_learnspace;
USE bu_learnspace;

-- ========================================================
-- 1) Users Table
-- ========================================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','teacher','student') NOT NULL,
    department_id INT NULL DEFAULT NULL
);

INSERT INTO users (name, email, password, role) VALUES
('Admin', 'admin@bu.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('Teacher One', 'teacher@bu.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher'),
('Student One', 'student@bu.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student');

-- NOTE: Default password for all accounts is: password
-- Change passwords after first login!

-- ========================================================
-- 2) Departments Table
-- ========================================================
CREATE TABLE departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL
);

INSERT INTO departments (name) VALUES ('CSE'), ('EEE'), ('BBA'), ('English');

-- ========================================================
-- 3) Materials Table
-- ========================================================
CREATE TABLE materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    filename VARCHAR(255) NOT NULL,
    cover_image VARCHAR(255) NOT NULL DEFAULT 'no-cover.png',
    course_name VARCHAR(100) NOT NULL,
    semester VARCHAR(50) NOT NULL,
    department_id INT NOT NULL,
    uploaded_by INT NOT NULL,
    view_count INT DEFAULT 0,
    download_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
);

-- ========================================================
-- 4) Material Ratings Table
-- ========================================================
CREATE TABLE material_ratings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    material_id INT NOT NULL,
    student_id INT NOT NULL,
    rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_rating (material_id, student_id),
    FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ========================================================
-- 5) Material Bookmarks Table
-- ========================================================
CREATE TABLE material_bookmarks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    material_id INT NOT NULL,
    student_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_bookmark (material_id, student_id),
    FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ========================================================
-- 6) Assignments Table
-- ========================================================
CREATE TABLE assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    course_name VARCHAR(100) NOT NULL,
    semester VARCHAR(50) NOT NULL,
    department_id INT NOT NULL,
    teacher_id INT NOT NULL,
    deadline DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (teacher_id) REFERENCES users(id)
);

-- ========================================================
-- 6) Assignment Submissions Table
-- ========================================================
CREATE TABLE submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT NOT NULL,
    student_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mark DECIMAL(5,2) DEFAULT NULL,
    feedback TEXT DEFAULT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_submission (assignment_id, student_id),
    FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ========================================================
-- 7) Assessments Table (Teacher defines assessments)
-- ========================================================
CREATE TABLE assessments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    course_name VARCHAR(100) NOT NULL,
    semester VARCHAR(50) NOT NULL,
    department_id INT NOT NULL,
    teacher_id INT NOT NULL,
    total_marks DECIMAL(5,2) NOT NULL DEFAULT 40,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (teacher_id) REFERENCES users(id)
);

-- ========================================================
-- 8) Assessment Marks Table
-- ========================================================
CREATE TABLE assessment_marks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assessment_id INT NOT NULL,
    student_id INT NOT NULL,
    obtained_marks DECIMAL(5,2) NOT NULL DEFAULT 0,
    remarks TEXT DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_mark (assessment_id, student_id),
    FOREIGN KEY (assessment_id) REFERENCES assessments(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ========================================================
-- 9) Attendance Table (per class session)
-- ========================================================
CREATE TABLE attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_name VARCHAR(100) NOT NULL,
    semester VARCHAR(50) NOT NULL,
    department_id INT NOT NULL,
    teacher_id INT NOT NULL,
    class_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (teacher_id) REFERENCES users(id)
);

-- ========================================================
-- 10) Attendance Records (per student per session)
-- ========================================================
CREATE TABLE attendance_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attendance_id INT NOT NULL,
    student_id INT NOT NULL,
    status ENUM('present','absent','late') NOT NULL DEFAULT 'absent',
    UNIQUE KEY unique_record (attendance_id, student_id),
    FOREIGN KEY (attendance_id) REFERENCES attendance(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ========================================================
-- END OF BU LearnSpace SQL
-- ========================================================

-- ========================================================
-- Add password reset columns to users table
-- Run this if database already exists:
-- ALTER TABLE users ADD COLUMN reset_token VARCHAR(100) DEFAULT NULL;
-- ALTER TABLE users ADD COLUMN token_expiry DATETIME DEFAULT NULL;
-- ========================================================
ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_token VARCHAR(100) DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS token_expiry DATETIME DEFAULT NULL;
