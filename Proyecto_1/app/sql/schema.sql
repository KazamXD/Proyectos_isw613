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
    dias_max_prestamo INT NOT NULL DEFAULT 7
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
    desactivado_en  TIMESTAMP NULL DEFAULT NULL, /*para saber cuanto tiempo lleva desconectado*/
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
    fecha_limite     DATE NOT NULL, 						 /*cuando se debe devolver o tiempo limite*/
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








