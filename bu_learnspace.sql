-- ========================================================
-- BU LearnSpace (FINAL SQL)
-- ========================================================

-- 1) Database
CREATE DATABASE IF NOT EXISTS bu_learnspace;
USE bu_learnspace;

-- ========================================================
-- 2) Users Table (Admin, Teacher, Student)
-- ========================================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(100) NOT NULL,
    role ENUM('admin','teacher','student') NOT NULL
);

-- Default Admin
INSERT INTO users (name, email, password, role)
VALUES ('Admin', 'admin@bu.com', 'admin123', 'admin');

-- Example Teacher
INSERT INTO users (name, email, password, role)
VALUES ('Teacher One', 'teacher@bu.com', 'teacher123', 'teacher');

-- Example Student
INSERT INTO users (name, email, password, role)
VALUES ('Student One', 'student@bu.com', 'student123', 'student');

-- ========================================================
-- 3) Departments Table
-- ========================================================
CREATE TABLE departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL
);

-- BU Departments (You can edit/add more)
INSERT INTO departments (name) VALUES
('CSE'),
('EEE'),
('BBA'),
('English');

-- ========================================================
-- 4) Materials Table (Final Version)
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
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
);

-- ========================================================
-- END OF BU LearnSpace SQL
-- ========================================================
