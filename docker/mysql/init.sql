-- Alabiq Database Initialization Script
-- This runs on first container startup

-- Create database if not exists (handled by MYSQL_DATABASE env var)
-- CREATE DATABASE IF NOT EXISTS `alabiq` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create additional user for read replicas if needed
-- CREATE USER IF NOT EXISTS 'alabiq_readonly'@'%' IDENTIFIED BY 'readonly_password';
-- GRANT SELECT ON `alabiq`.* TO 'alabiq_readonly'@'%';

-- Set timezone
SET GLOBAL time_zone = '+03:00';
SET SESSION time_zone = '+03:00';

-- Optimize for Laravel
SET GLOBAL innodb_strict_mode = ON;
SET GLOBAL sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- Create uuid extension (MySQL 8.0+ has UUID())
-- No extension needed, use UUID() function

FLUSH PRIVILEGES;