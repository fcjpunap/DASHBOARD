-- Estructura de tabla para el historial de SIDPOL
CREATE TABLE IF NOT EXISTS `sidpol_hechos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `anio` INT NOT NULL,
  `mes` INT NOT NULL,
  `ubigeo_hecho` VARCHAR(10),
  `dpto_hecho` VARCHAR(50),
  `prov_hecho` VARCHAR(50),
  `dist_hecho` VARCHAR(50),
  `tipo_delito` VARCHAR(100),
  `sub_tipo_delito` VARCHAR(100),
  `modalidad_delito` VARCHAR(150),
  `es_delito_general` VARCHAR(50) DEFAULT '1.Delitos', -- Campo para clasificar (Delitos, Faltas, Violencia, etc.)
  `cantidad` INT DEFAULT 1,
  `fecha_importacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `hash_unico` VARCHAR(32) UNIQUE, -- Para evitar duplicados: MD5(anio+mes+ubigeo+modalidad+etc)
  INDEX `idx_anio_mes` (`anio`, `mes`),
  INDEX `idx_dpto` (`dpto_hecho`),
  INDEX `idx_tipo` (`tipo_delito`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla simple para usuarios administradores
CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) UNIQUE NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL, -- Usaremos password_hash() de PHP
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insertar usuario por defecto (admin / admin123) - CAMBIAR LUEGO
-- El hash es de 'admin123' generado con password_hash()
INSERT INTO `admin_users` (`username`, `password_hash`) VALUES 
('admin', '$2y$10$8.K/1.8/8.K/1.8/8.K/1.8/8.K/1.8/8.K/1.8/8.K/1.8'); 
