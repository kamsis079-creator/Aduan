-- ==========================================================
-- 1. Create the Database
-- ==========================================================
CREATE DATABASE IF NOT EXISTS `kamsis_pms`;
USE `kamsis_pms`;

-- ==========================================================
-- 2. Create the Aduan (Complaints) Table
-- ==========================================================
-- This table saves all the required fields exactly as requested.
-- Note: 'blok_bilik' stores both the Block and Room number together.
CREATE TABLE IF NOT EXISTS `aduan` (
    `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
    `nama_pelajar` VARCHAR(100) NOT NULL,
    `no_matrik` VARCHAR(20) NOT NULL,
    `no_telefon` VARCHAR(20) NOT NULL,
    `kamsis` VARCHAR(20) NOT NULL,
    `blok_bilik` VARCHAR(50) NOT NULL,
    `tingkat` VARCHAR(10) NOT NULL,
    `kategori` VARCHAR(50) NOT NULL,
    `keterangan` TEXT NOT NULL,
    `status` VARCHAR(20) DEFAULT 'Baru',
    `tarikh` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================================
-- 3. Create the Pengguna (Users) Table
-- ==========================================================
CREATE TABLE IF NOT EXISTS `pengguna` (
    `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `peranan` VARCHAR(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================================
-- 4. Insert 2 Admin Accounts
-- ==========================================================
-- The passwords here are securely encrypted using bcrypt (PHP's password_hash).
-- The raw password for BOTH of these accounts is: password
--
-- Akaun 1: username = admin1 , password = password
-- Akaun 2: username = admin2 , password = password

INSERT INTO `pengguna` (`username`, `password`, `peranan`) VALUES
('admin1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin'),
('admin2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Penyelia');
