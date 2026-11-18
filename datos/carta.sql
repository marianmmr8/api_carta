-- La carta de La Alacena: una sola tabla para la API de la práctica
-- IFCD0210 · UF1846 · Práctica guiada «La carta del restaurante» · MySQL 8.4 (WAMP)
-- Importar en phpMyAdmin: pestaña «Importar», elegir este archivo y pulsar «Importar».
-- Crea la base de datos `carta` (si ya existía, la borra y la vuelve a crear).

DROP DATABASE IF EXISTS carta;
CREATE DATABASE carta CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci;
USE carta;

CREATE TABLE platos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(80) NOT NULL UNIQUE,
  categoria ENUM('entrante', 'principal', 'postre') NOT NULL,   -- solo admite estos tres valores
  precio DECIMAL(6,2) NOT NULL CHECK (precio > 0),
  disponible BOOLEAN NOT NULL DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

INSERT INTO platos (id, nombre, categoria, precio, disponible) VALUES
  (1, 'Croquetas caseras', 'entrante', 8.50, TRUE),
  (2, 'Ensalada de la huerta', 'entrante', 7.00, TRUE),
  (3, 'Salmorejo', 'entrante', 6.50, FALSE),
  (4, 'Arroz con verduras', 'principal', 13.00, TRUE),
  (5, 'Merluza a la plancha', 'principal', 16.50, TRUE),
  (6, 'Carrillada al vino tinto', 'principal', 15.00, TRUE),
  (7, 'Tarta de queso', 'postre', 5.50, TRUE),
  (8, 'Natillas caseras', 'postre', 4.50, TRUE),
  (9, 'Fruta del tiempo', 'postre', 3.50, TRUE);
