-- Create database if not exists (user to run: CREATE DATABASE pharma_db;)
-- Then use pharmaceutical_db;

CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('Admin', 'Staff') NOT NULL,
    dob DATE NOT NULL
);

-- Sample users with hashed passwords (password for all: 'password123')
-- Hashes generated via password_hash('password123', PASSWORD_DEFAULT)

INSERT INTO users (username, password, role, dob) VALUES
('admin1', '$2y$10$L12QTm99BTjPO.fShbumJ.TAtcLvYLotPpei4YGUN4pcEB5JVri4W', 'Admin', '1990-01-01'),
('staff1', '$2y$10$HxlRwSLqlm4JHSu0Crj9xOvoJ6diX/299zG58w7KknWYVB3RhLaRe', 'Staff', '1985-05-15');
