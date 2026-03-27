CREATE DATABASE IF NOT EXISTS tvirtualgaming
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE tvirtualgaming;

-- Placeholder for future schema

-- Tabla de usuarios para administración
CREATE TABLE IF NOT EXISTS usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  nombre VARCHAR(100),
  email VARCHAR(100),
  telefono VARCHAR(50) DEFAULT NULL,
  rol ENUM('admin','empleado','influencer','usuario') DEFAULT 'usuario',
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Usuario admin por defecto (clave: admin123)
INSERT INTO usuarios (username, password, nombre, email, rol)
VALUES ('admin', '$2y$10$wH8QwQwQwQwQwQwQwQwQwOQwQwQwQwQwQwQwQwQwQwQwQwQwQw', 'Administrador', 'admin@localhost', 'admin')
ON DUPLICATE KEY UPDATE username=username;

-- Nota: El password está hasheado con password_hash('admin123', PASSWORD_DEFAULT)

-- Tabla de juegos
CREATE TABLE IF NOT EXISTS juegos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  descripcion TEXT,
  precio DECIMAL(10,2) NOT NULL,
  imagen VARCHAR(255),
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de pedidos
CREATE TABLE IF NOT EXISTS pedidos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  juego_id INT NOT NULL,
  cantidad INT NOT NULL DEFAULT 1,
  total DECIMAL(10,2) NOT NULL,
  estado ENUM('pendiente','pagado','enviado','cancelado') DEFAULT 'pendiente',
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
  FOREIGN KEY (juego_id) REFERENCES juegos(id)
);
