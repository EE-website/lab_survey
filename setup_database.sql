-- Create Database
CREATE DATABASE IF NOT EXISTS lab_survey CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE lab_survey;

-- Create Courses Table
CREATE TABLE IF NOT EXISTS courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_code VARCHAR(50) NOT NULL UNIQUE COMMENT 'Course Code (科號)',
    course_name VARCHAR(255) NOT NULL COMMENT 'Course Name',
    instructor_name VARCHAR(255) NOT NULL COMMENT 'Instructor Name',
    description TEXT COMMENT 'Course Description',
    semester INT COMMENT 'Semester (1=上學期, 2=下學期)',
    academic_year INT COMMENT 'Academic Year (ROC)',
    is_active TINYINT DEFAULT 1 COMMENT 'Is Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Created At',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Updated At',
    INDEX idx_course_code (course_code),
    INDEX idx_instructor (instructor_name),
    INDEX idx_active (is_active),
    INDEX idx_academic_year (academic_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Courses Table';

-- Create Questions Table
CREATE TABLE IF NOT EXISTS questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL COMMENT 'Question Title',
    type ENUM('rating', 'multiple_choice', 'text') NOT NULL DEFAULT 'rating' COMMENT 'Question Type',
    allow_multiple TINYINT DEFAULT 0 COMMENT 'Allow Multiple Selection',
    is_required TINYINT DEFAULT 1 COMMENT 'Is Required',
    description TEXT COMMENT 'Question Description',
    options JSON COMMENT 'Options (JSON Format)',
    is_active TINYINT DEFAULT 1 COMMENT 'Is Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Created At',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Updated At',
    INDEX idx_type (type),
    INDEX idx_active (is_active),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Survey Questions Table';

-- Create Course-Question Junction Table (Many-to-Many Relationship)
CREATE TABLE IF NOT EXISTS course_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL COMMENT 'Course ID',
    question_id INT NOT NULL COMMENT 'Question ID',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Created At',
    UNIQUE KEY unique_course_question (course_id, question_id),
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_course_id (course_id),
    INDEX idx_question_id (question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Course-Question Association Table';

-- Create Responses Table
CREATE TABLE IF NOT EXISTS responses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT NOT NULL COMMENT 'Question ID',
    course_id INT COMMENT 'Course ID',
    answer LONGTEXT NOT NULL COMMENT 'Response Content',
    respondent VARCHAR(255) COMMENT 'Respondent Name',
    student_id VARCHAR(50) COMMENT 'Student ID',
    academic_year INT COMMENT 'Academic Year (ROC)',
    semester TINYINT COMMENT 'Semester (1=上學期, 2=下學期)',
    ip_address VARCHAR(45) COMMENT 'IP Address',
    user_agent VARCHAR(255) COMMENT 'User Agent',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Response Time',
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_question (question_id),
    INDEX idx_course_id (course_id),
    INDEX idx_respondent (respondent),
    INDEX idx_student_id (student_id),
    INDEX idx_academic_year (academic_year),
    INDEX idx_semester (semester),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='User Responses Table';

-- Create System Settings Table
CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    academic_year INT COMMENT 'Current Academic Year (ROC)',
    semester TINYINT COMMENT 'Current Semester (1=上學期, 2=下學期)',
    status ENUM('open', 'closed') DEFAULT 'open' COMMENT 'Survey Status (open=開放, closed=關閉)',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last Updated',
    updated_by VARCHAR(255) COMMENT 'Updated By',
    INDEX idx_academic_year (academic_year),
    INDEX idx_semester (semester)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='System Settings Table';