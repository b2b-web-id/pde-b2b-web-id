-- Buat pengguna
CREATE USER 'pde'@'localhost' IDENTIFIED BY PASSWORD '*7FA837C32FA9B00B7B3AC891E050E63C84F3D83B';

-- Tetapkan hak umum pengguna
GRANT USAGE ON *.* TO 'pde'@'localhost';

-- Buat basis data pde
CREATE DATABASE `pde`;

-- Tetapkan hak pengguna atas basis data pde
GRANT ALL PRIVILEGES ON `pde`.* TO 'pde'@'localhost';