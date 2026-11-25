-- Sistema de Gestión de Biblioteca - esquema inicial reproducible.
-- Base exclusiva del proyecto; no elimina bases de datos ni tablas existentes.
CREATE DATABASE IF NOT EXISTS biblioteca_kodigo
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE biblioteca_kodigo;

CREATE TABLE IF NOT EXISTS libros (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    autor VARCHAR(255) NOT NULL,
    isbn VARCHAR(20) NULL UNIQUE,
    cantidad INT UNSIGNED NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS usuarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    telefono VARCHAR(20) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS prestamos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    libro_id INT UNSIGNED NOT NULL,
    usuario_id INT UNSIGNED NOT NULL,
    fecha_prestamo DATE NOT NULL,
    fecha_devolucion DATE NULL,
    estado ENUM('activo', 'devuelto') NOT NULL DEFAULT 'activo',
    CONSTRAINT fk_prestamos_libros FOREIGN KEY (libro_id) REFERENCES libros(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_prestamos_usuarios FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_prestamos_estado (estado),
    INDEX idx_prestamos_libro (libro_id),
    INDEX idx_prestamos_usuario (usuario_id)
) ENGINE=InnoDB;

-- Datos de demostración ficticios. No contienen información personal real.
INSERT IGNORE INTO libros (titulo, autor, isbn, cantidad) VALUES
('Cuentos de barro', 'Salarrué', '9789992300001', 3),
('El principito', 'Antoine de Saint-Exupéry', '9780156012195', 2),
('La casa de los espíritus', 'Isabel Allende', '9788401352836', 4);

INSERT IGNORE INTO usuarios (nombre, email, telefono) VALUES
('Ana Martínez', 'ana.martinez@example.test', '7000-1001'),
('Luis Rivera', 'luis.rivera@example.test', '7000-1002');
