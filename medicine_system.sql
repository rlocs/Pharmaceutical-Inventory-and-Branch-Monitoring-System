-- medicine_system.sql
CREATE DATABASE IF NOT EXISTS medicine_system;
USE medicine_system;

CREATE TABLE IF NOT EXISTS medicines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    price DECIMAL(10,2) NOT NULL
);

INSERT INTO medicines (name, price) VALUES
('Medicine A', 15.00),
('Medicine B', 15.00),
('Medicine C', 15.00),
('Medicine D', 20.50),
('Medicine E', 9.99);
