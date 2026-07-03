-- Annual Performance Evaluation System Database
-- Run this SQL in your MySQL database

-- Admin users table
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('super_admin', 'admin', 'evaluator', 'viewer') DEFAULT 'admin',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Staff table
CREATE TABLE IF NOT EXISTS staff (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id VARCHAR(50) NOT NULL UNIQUE,
    surname VARCHAR(100) NOT NULL,
    first_name VARCHAR(100),
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    department VARCHAR(100),
    faculty VARCHAR(100),
    designation VARCHAR(100),
    grade_level VARCHAR(20),
    employment_status VARCHAR(50),
    years_of_service INT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    password VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Institution settings table
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) NOT NULL UNIQUE,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Academic sessions table
CREATE TABLE IF NOT EXISTS academic_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_name VARCHAR(20) NOT NULL,
    semester VARCHAR(20) NOT NULL,
    year INT NOT NULL,
    is_active TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Evaluations table
CREATE TABLE IF NOT EXISTS evaluations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    academic_session_id INT,
    evaluation_year INT,

    -- Teaching Performance
    teaching_1 INT, teaching_2 INT, teaching_3 INT, teaching_4 INT, teaching_5 INT, teaching_6 INT,

    -- Research Performance
    research_1 INT, research_2 INT, research_3 INT, research_4 INT, research_5 INT,

    -- Administrative Duties
    admin_1 INT, admin_2 INT, admin_3 INT, admin_4 INT, admin_5 INT,

    -- Community Service
    community_1 INT, community_2 INT, community_3 INT,

    -- Professional Development
    professional_1 INT, professional_2 INT, professional_3 INT, professional_4 INT,

    -- Calculated scores
    total_score INT DEFAULT 0,
    average_score DECIMAL(5,2) DEFAULT 0,
    percentage DECIMAL(5,2) DEFAULT 0,
    performance_grade VARCHAR(20),
    performance_status VARCHAR(50),

    -- Supervisor Assessment
    supervisor_name VARCHAR(100),
    supervisor_designation VARCHAR(100),
    supervisor_remarks TEXT,
    overall_rating VARCHAR(50),
    recommendation VARCHAR(50),
    supervisor_signature VARCHAR(255),
    supervisor_date DATE,

    -- Registrar/Management
    registrar_name VARCHAR(100),
    registrar_remarks TEXT,
    approval_status VARCHAR(50),
    registrar_signature VARCHAR(255),
    registrar_date DATE,

    -- Status
    status ENUM('draft', 'submitted', 'approved', 'rejected') DEFAULT 'draft',
    evaluated_by INT,
    evaluated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
    FOREIGN KEY (academic_session_id) REFERENCES academic_sessions(id),
    FOREIGN KEY (evaluated_by) REFERENCES admins(id)
);

-- Insert default settings
INSERT INTO settings (setting_key, setting_value) VALUES
('institution_name', 'Your Institution Name'),
('institution_logo', ''),
('academic_session', '2025/2026'),
('semester', 'First'),
('evaluation_year', '2025'),
('email_from', 'noreply@yourdomain.com'),
('email_to', 'evaluation@yourdomain.com'),
('smtp_host', 'smtp.gmail.com'),
('smtp_port', '587'),
('smtp_username', ''),
('smtp_password', '');

-- Insert sample academic sessions
INSERT INTO academic_sessions (session_name, semester, year, is_active) VALUES
('2025/2026', 'First', 2025, 1),
('2025/2026', 'Second', 2025, 0),
('2024/2025', 'First', 2024, 0),
('2024/2025', 'Second', 2024, 0);

-- Insert default admins with roles (password: Aper@2026)
INSERT INTO admins (name, email, password, role) VALUES
('Super Administrator', 'super@admin.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin'),
('Admin User', 'admin@aper.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('Evaluator Staff', 'evaluator@aper.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'evaluator'),
('Report Viewer', 'viewer@aper.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'viewer');

-- Insert sample staff (password will be bcrypt of surname)
INSERT INTO staff (staff_id, surname, first_name, email, department, faculty, designation, grade_level, employment_status, years_of_service, password) VALUES
('STF001', 'Adebayo', 'John', 'john.adebayo@school.edu', 'Computer Science', 'Science', 'Lecturer I', 'Level 5', 'Permanent', 5, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('STF002', 'Okonkwo', 'Chioma', 'chioma.okonkwo@school.edu', 'Mathematics', 'Science', 'Senior Lecturer', 'Level 7', 'Permanent', 8, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('STF003', 'Ibrahim', 'Fatima', 'fatima.ibrahim@school.edu', 'Physics', 'Science', 'Professor', 'Level 10', 'Permanent', 15, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('STF004', 'Oyewole', 'David', 'david.oyewole@school.edu', 'Chemistry', 'Science', 'Lecturer II', 'Level 4', 'Contract', 3, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('STF005', 'Mwangi', 'Grace', 'grace.mwangi@school.edu', 'Biology', 'Science', 'Assistant Lecturer', 'Level 3', 'Permanent', 2, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Add staff_category field (academic or non-teaching)
ALTER TABLE staff ADD COLUMN staff_category ENUM('academic', 'non-teaching') DEFAULT 'academic';

-- Add staff_category to evaluations table
ALTER TABLE evaluations ADD COLUMN staff_category ENUM('academic', 'non-teaching') DEFAULT 'academic';