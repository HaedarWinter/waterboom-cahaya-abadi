-- bikin database dulu --
CREATE DATABASE IF NOT EXISTS db_ujikom;
USE db_ujikom;

-- tabel buku tamu --
CREATE TABLE IF NOT EXISTS buku_tamu (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    no_hp VARCHAR(20) NOT NULL,
    email VARCHAR(100) NOT NULL,
    tanggal DATE NOT NULL
);

-- tabel admin --
CREATE TABLE IF NOT EXISTS admin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
);

-- data admin default, password: admin123
INSERT INTO admin (username, password) VALUES 
('admin', '$2y$10$CORdP3vDBPyQCstjixZBBOB8x/5eTueycqrP4pKKVqTxXbflIm3JG');

-- data dummy buku tamu
INSERT INTO buku_tamu (nama, no_hp, email) VALUES
('pengunjung1', '0888888888', 'pengunjung1@gmail.com');