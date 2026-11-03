CREATE DATABASE IF NOT EXISTS equipo_computo;
USE equipo_computo;


CREATE TABLE rol (
    id_rol      INT PRIMARY KEY AUTO_INCREMENT,
    nombre_rol  VARCHAR(50) NOT NULL UNIQUE
);

CREATE TABLE permisos (
    id_permiso  INT PRIMARY KEY AUTO_INCREMENT,
    clave       VARCHAR(100) NOT NULL UNIQUE
);

CREATE TABLE categorias (
    categoria_id     INT PRIMARY KEY AUTO_INCREMENT,
    categoria_nombre VARCHAR(50) NOT NULL,
    dias_max_prestamo INT NOT NULL DEFAULT 7,
    eliminado_en TIMESTAMP NULL DEFAULT NULL;
);

CREATE TABLE usuarios (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    rol_id          INT NOT NULL,
    nombre          VARCHAR(50) NOT NULL,
    apellido        VARCHAR(50) NOT NULL,
    email           VARCHAR(100) NOT NULL UNIQUE,
    nom_usuario     VARCHAR(50) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    intento_ingreso INT NOT NULL DEFAULT 3,
    desactivado_en  TIMESTAMP NULL DEFAULT NULL, /*para saber cuando se desactivó*/
    activo          BOOLEAN AS (desactivado_en IS NULL) VIRTUAL,
    CONSTRAINT fk_usuarios_rol
        FOREIGN KEY (rol_id) REFERENCES rol(id_rol)
);

CREATE TABLE equipos (
    id_equipo     INT PRIMARY KEY AUTO_INCREMENT,
    id_categoria  INT NOT NULL,
    codigo        VARCHAR(30) NOT NULL UNIQUE,
    nombre_estado VARCHAR(50) NOT NULL DEFAULT 'Disponible',
    ruta_imagen   VARCHAR(254) NULL DEFAULT NULL,
    eliminado_en  TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_equipos_categoria_id 
        FOREIGN KEY (id_categoria) REFERENCES categorias(categoria_id)
);


CREATE TABLE rol_permiso (
    rol_id      INT NOT NULL,
    permiso_id  INT NOT NULL,
    PRIMARY KEY (rol_id, permiso_id), /*para que pueda existir la relacion N:M, se tiene que hacer compuesta*/
    CONSTRAINT fk_rol_permiso_rol
        FOREIGN KEY (rol_id) REFERENCES rol(id_rol) ON DELETE CASCADE,
    CONSTRAINT fk_rol_permiso_permiso
        FOREIGN KEY (permiso_id) REFERENCES permisos(id_permiso) ON DELETE CASCADE
);

CREATE TABLE bitacora (
    id              BIGINT PRIMARY KEY AUTO_INCREMENT,
    usuario_id      INT NULL,                         
    accion          VARCHAR(50) NOT NULL,             
    entidad         VARCHAR(50) NOT NULL,             
    entidad_id      INT NULL,                        
    valores_antes   JSON NULL,                   
    valores_despues JSON NULL,                      
    ip              VARCHAR(45) NULL,                  
    creado_en       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    /*relacion con usuarios*/
    CONSTRAINT fk_bitacora_usuario 
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) 
        ON DELETE SET NULL /*si se elimina el usuario, el historial no se borra*/
);

CREATE TABLE auth_tokens (
    id              BIGINT PRIMARY KEY AUTO_INCREMENT,
    usuario_id      INT NOT NULL,
    selector        CHAR(12) NOT NULL UNIQUE,     /*buscador token*/
    validator_hash  CHAR(64) NOT NULL,            /*Validador hash256*/
    expira_en       TIMESTAMP NOT NULL,
    CONSTRAINT fk_auth_tokens_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON DELETE CASCADE
);


CREATE TABLE prestamos (
    id_prestamo      INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id       INT NOT NULL,
    operador_id      INT NOT NULL,
    fecha_entrega    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,	 /*cuando se dio el equipo, fue prestado*/
    fecha_limite     DATETIME NOT NULL, 						 /*cuando se debe devolver o tiempo limite*/
    fecha_devolucion TIMESTAMP NULL DEFAULT NULL,			 /*Cuando devolvieron el equipo*/
    estado           ENUM('prestado', 'devuelto', 'retrasado') DEFAULT 'prestado',
    CONSTRAINT fk_prestamos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    CONSTRAINT fk_prestamos_operador FOREIGN KEY (operador_id) REFERENCES usuarios(id)
);


CREATE TABLE prestamo_detalle (
    prestamo_id     INT NOT NULL,
    equipo_id       INT NOT NULL,
    cantidad        INT NOT NULL DEFAULT 1,
    PRIMARY KEY (prestamo_id, equipo_id), 
    CONSTRAINT fk_detalle_prestamo FOREIGN KEY (prestamo_id) REFERENCES prestamos(id_prestamo) ON DELETE CASCADE,
    CONSTRAINT fk_detalle_equipo FOREIGN KEY (equipo_id) REFERENCES equipos(id_equipo) 
);


SELECT VERSION();

SELECT r.nombre_rol, p.clave
FROM rol_permiso rp
JOIN rol r ON r.id_rol = rp.rol_id
JOIN permisos p ON p.id_permiso = rp.permiso_id
WHERE p.clave = 'usuarios.ver';



select * from rol


DESCRIBE prestamos;


SHOW CREATE TABLE prestamos;

SHOW TABLES;

SELECT
    r.nombre_rol,
    p.clave
FROM rol r
INNER JOIN rol_permiso rp
    ON r.id_rol = rp.rol_id
INNER JOIN permisos p
    ON rp.permiso_id = p.id_permiso
WHERE r.id_rol = 1
  AND p.clave = 'prestamos.ver';



SELECT
    p.id_prestamo,
    pd.equipo_id,
    e.codigo
FROM prestamos p
LEFT JOIN prestamo_detalle pd
    ON p.id_prestamo = pd.prestamo_id
LEFT JOIN equipos e
    ON pd.equipo_id = e.id_equipo;


SELECT *
FROM prestamos
ORDER BY id_prestamo DESC;

SELECT *
FROM prestamo_detalle
ORDER BY prestamo_id DESC;


SELECT
    usuario_id,
    accion,
    entidad,
    entidad_id,
    valores_despues,
    creado_en
FROM bitacora
WHERE accion = 'CREATE'
  AND entidad = 'usuarios'
ORDER BY creado_en DESC
LIMIT 5;

select * from usuarios where id = 5;



SELECT
    usuario_id,
    accion,
    entidad,
    entidad_id,
    valores_despues,
    creado_en
FROM bitacora
WHERE entidad = 'prestamos'
ORDER BY creado_en DESC;


SELECT
    id_prestamo,
    estado,
    fecha_devolucion
FROM prestamos
WHERE id_prestamo = 5;


SELECT * FROM prestamos;

SELECT
    id_prestamo,
    fecha_limite,
    fecha_devolucion,
    estado
FROM prestamos
ORDER BY id_prestamo DESC;


SELECT
    accion,
    entidad,
    entidad_id,
    valores_antes,
    valores_despues,
    creado_en
FROM bitacora
WHERE entidad = 'prestamos'
ORDER BY creado_en DESC;


SELECT
    accion,
    entidad,
    entidad_id,
    valores_despues,
    creado_en
FROM bitacora
WHERE entidad = 'equipos'
ORDER BY creado_en DESC;

SELECT
    usuario_id,
    accion,
    entidad,
    entidad_id,
    valores_antes,
    valores_despues,
    creado_en
FROM bitacora
WHERE entidad = 'equipos'
  AND accion = 'UPDATE'
ORDER BY creado_en DESC;


SELECT
    r.nombre_rol,
    p.clave
FROM rol r
INNER JOIN rol_permiso rp
    ON r.id_rol = rp.rol_id
INNER JOIN permisos p
    ON rp.permiso_id = p.id_permiso
WHERE r.id_rol = 1
  AND p.clave = 'equipos.eliminar';

INSERT INTO rol_permiso (rol_id, permiso_id)
SELECT 1, p.id_permiso
FROM permisos p
WHERE p.clave = 'equipos.eliminar'
  AND NOT EXISTS (
      SELECT 1
      FROM rol_permiso rp
      WHERE rp.rol_id = 1
        AND rp.permiso_id = p.id_permiso
  );


SELECT * FROM permisos;


SELECT id_permiso, clave
FROM permisos
WHERE clave LIKE 'categorias.%'
ORDER BY clave;




































