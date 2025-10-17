-- Drop existing database if it exists (for clean re-imports during dev)
DROP DATABASE IF EXISTS dkuscheduler1;
CREATE DATABASE dkuscheduler1;
USE dkuscheduler1;

-- =========================
-- USERS TABLE
-- =========================
-- Stores all users (admin, department head, instructor, student)
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL, -- hashed password
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE,
    role ENUM('admin', 'department_head', 'instructor', 'student') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =========================
-- DEPARTMENTS TABLE
-- =========================
CREATE TABLE departments (
    department_id INT AUTO_INCREMENT PRIMARY KEY,
    department_name VARCHAR(100) NOT NULL UNIQUE
);

-- =========================
-- COURSES TABLE
-- =========================
CREATE TABLE courses (
    course_id INT AUTO_INCREMENT PRIMARY KEY,
    course_code VARCHAR(20) NOT NULL UNIQUE,
    course_name VARCHAR(100) NOT NULL,
    credit_hours INT NOT NULL,
    department_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(department_id)
        ON DELETE CASCADE
);

-- =========================
-- ROOMS TABLE
-- =========================
CREATE TABLE rooms (
    room_id INT AUTO_INCREMENT PRIMARY KEY,
    room_name VARCHAR(50) NOT NULL UNIQUE,
    capacity INT NOT NULL,
    building VARCHAR(100)
);

-- =========================
-- SCHEDULE TABLE
-- =========================
CREATE TABLE schedule (
    schedule_id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    instructor_id INT NOT NULL,
    room_id INT NOT NULL,
    academic_year VARCHAR(20) NOT NULL, -- e.g. "2025/2026"
    semester ENUM('Fall', 'Spring', 'Summer') NOT NULL,
    day_of_week ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
    FOREIGN KEY (instructor_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES rooms(room_id) ON DELETE CASCADE
);

-- =========================
-- ENROLLMENTS TABLE
-- =========================
-- (students register for courses/schedules)
CREATE TABLE enrollments (
    enrollment_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    schedule_id INT NOT NULL,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (schedule_id) REFERENCES schedule(schedule_id) ON DELETE CASCADE
);


-- =========================
-- SAMPLE DATA
-- =========================

-- Admin user (password = "admin123" hashed later in PHP)
INSERT INTO users (username, password, full_name, email, role)
VALUES ('admin', 'changeme', 'System Admin', 'admin@dku.edu', 'admin');

-- Departments
INSERT INTO departments (department_name) VALUES
('Computer Science'),
('Electrical Engineering'),
('Business Administration');


-- Admin user (username: admin, password: changeme)
INSERT INTO users (username, password, full_name, email, role)
VALUES ('admin', 'changeme', 'System Administrator', 'admin@dku.edu', 'admin');

-- Sample instructors
INSERT INTO users (username, password, full_name, email, role) VALUES
('jdoe', 'changeme', 'John Doe', 'jdoe@dku.edu', 'instructor'),
('asmith', 'changeme', 'Alice Smith', 'asmith@dku.edu', 'instructor');

-- Sample students
INSERT INTO users (username, password, full_name, email, role) VALUES
('sstudent', 'changeme', 'Sara Student', 'sstudent@dku.edu', 'student');

-- Courses
INSERT INTO courses (course_code, course_name, credit_hours, department_id) VALUES
('CS101', 'Introduction to Computer Science', 3, 1),
('CS201', 'Database Systems', 3, 1),
('EE101', 'Circuit Analysis', 4, 2),
('BUS101', 'Principles of Management', 3, 3);

-- Rooms
INSERT INTO rooms (room_name, capacity, building) VALUES
('Room A101', 40, 'Building A'),
('Room B202', 60, 'Building B');

-- Example Schedule
INSERT INTO schedule (course_id, instructor_id, room_id, academic_year, semester, day_of_week, start_time, end_time)
VALUES
(1, 2, 1, '2025/2026', 'Fall', 'Monday', '09:00:00', '10:30:00');
