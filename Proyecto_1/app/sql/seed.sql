
USE equipo_computo;

-- 1. ROLES
INSERT INTO rol (nombre_rol) VALUES 
('Administrador'),
('Operador de Préstamos'),
('Usuario Estándar');

-- 2. PERMISOS
INSERT INTO permisos (clave) VALUES 
('usuarios.crear'),
('usuarios.editar'),
('usuarios.eliminar'),
('equipos.crear'),
('equipos.editar'),
('prestamos.crear'),
('prestamos.devolver'),
('reportes.ver');

INSERT INTO permisos (clave) VALUES
('equipos.eliminar');

-- 3. ROL_PERMISO (Asignando permisos a roles)
-- Administrador (tiene todos los permisos 1 al 8)
INSERT INTO rol_permiso (rol_id, permiso_id) VALUES 
(1, 1), (1, 2), (1, 3), (1, 4), (1, 5), (1, 6), (1, 7), (1, 8);

-- Operador (solo gestiona equipos y préstamos)
INSERT INTO rol_permiso (rol_id, permiso_id) VALUES 
(2, 4), (2, 5), (2, 6), (2, 7);

-- Usuario Estándar (solo puede ver, aunque en este caso no tiene permisos asignados)

-- 4. CATEGORIAS DE EQUIPOS
INSERT INTO categorias (categoria_nombre, dias_max_prestamo) VALUES 
('Laptops', 7),
('Proyectores', 3),
('Cables y Adaptadores', 1),
('Tablets', 5);

-- 5. USUARIOS
-- (Nota: Los hashes son inventados solo para que la BD no marque error)
INSERT INTO usuarios (rol_id, nombre, apellido, email, nom_usuario, password_hash, desactivado_en) VALUES 
(1, 'Carlos', 'Admin', 'carlos@empresa.com', 'cadmin', '$2y$10$XE0wNra/.p078Yu9YSyOSuN5n/7.y2gkA5.LGnVAFpU4CN0A2OK6K', NULL), -- Activo
(2, 'Ana', 'Operadora', 'ana@empresa.com', 'aoperadora', '$2y$10$e8.wT.wT.wT.wT.wT.wT.wu', NULL), -- Activa
(3, 'Luis', 'Empleado', 'luis@empresa.com', 'lempleado', '$2y$10$e8.wT.wT.wT.wT.wT.wT.wu', NULL), -- Activo
(3, 'Marta', 'Inactiva', 'marta@empresa.com', 'minactiva', '$2y$10$e8.wT.wT.wT.wT.wT.wT.wu', '2023-10-01 10:00:00'); -- Desactivada

UPDATE usuarios
SET password_hash = '$2y$10$fDaokWln/eoTwAF9ybjTreIRw/WGtxDl.xQfXPyHVpS8SHP0vmHh6'
WHERE nom_usuario = 'cadmin';

UPDATE usuarios
SET password_hash = CASE nom_usuario
    WHEN 'aoperadora' THEN '$2y$12$6ZA.zltbJfFmQb9XWJAUbucl0tGBIk6/hK/EnqCucsb3fATXfZ5SC'
    WHEN 'lempleado' THEN '$2y$12$Dmk34j0V01jNDawEHrmqb.QZE9UxI1uDQaY2RY6e.WP0gH55ISaY.'
    ELSE password_hash
END
WHERE nom_usuario IN ('aoperadora', 'lempleado');

SELECT * FROM usuarios WHERE id  = 1;

-- 6. EQUIPOS
INSERT INTO equipos (id_categoria, codigo, nombre_estado, ruta_imagen) VALUES 
(1, 'LAP-001', 'Disponible', '/img/lap001.jpg'),
(1, 'LAP-002', 'Disponible', '/img/lap002.jpg'),
(1, 'LAP-003', 'Mantenimiento', NULL),
(2, 'PROY-001', 'Disponible', '/img/proy001.jpg'),
(3, 'CABLE-HDMI-01', 'Disponible', NULL);

INSERT INTO equipos (id_categoria, codigo, nombre_estado)
SELECT categoria_id, 'LAP-004', 'Disponible'
FROM categorias
WHERE categoria_nombre = 'Laptops';

INSERT INTO equipos (id_categoria, codigo, nombre_estado)
SELECT categoria_id, 'LAP-005', 'Disponible'
FROM categorias
WHERE categoria_nombre = 'Laptops';

INSERT INTO equipos (id_categoria, codigo, nombre_estado)
SELECT categoria_id, 'PROY-002', 'Disponible'
FROM categorias
WHERE categoria_nombre = 'Proyectores';

INSERT INTO equipos (id_categoria, codigo, nombre_estado)
SELECT categoria_id, 'PROY-003', 'Disponible'
FROM categorias
WHERE categoria_nombre = 'Proyectores';

INSERT INTO equipos (id_categoria, codigo, nombre_estado)
SELECT categoria_id, 'CABLE-HDMI-02', 'Disponible'
FROM categorias
WHERE categoria_nombre = 'Cables y Adaptadores';

INSERT INTO equipos (id_categoria, codigo, nombre_estado)
SELECT categoria_id, 'CABLE-HDMI-03', 'Disponible'
FROM categorias
WHERE categoria_nombre = 'Cables y Adaptadores';

-- 7. PRESTAMOS
-- Préstamo 1: A Luis, operado por Ana. Salió hoy, vence en 7 días. Aún no lo devuelve.
INSERT INTO prestamos (usuario_id, operador_id, fecha_entrega, fecha_limite, fecha_devolucion, estado) VALUES 
(3, 2, CURRENT_TIMESTAMP, DATE_ADD(CURDATE(), INTERVAL 7 DAY), NULL, 'prestado');

-- Préstamo 2: A Luis, operado por Ana. Salió hace 10 días, vencía hace 3 días. Aún no lo devuelve (Retrasado).
INSERT INTO prestamos (usuario_id, operador_id, fecha_entrega, fecha_limite, fecha_devolucion, estado) VALUES 
(3, 2, DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 10 DAY), DATE_SUB(CURDATE(), INTERVAL 3 DAY), NULL, 'retrasado');

-- Préstamo 3: A Carlos, operado por Ana. Ya fue devuelto el mismo día.
INSERT INTO prestamos (usuario_id, operador_id, fecha_entrega, fecha_limite, fecha_devolucion, estado) VALUES 
(1, 2, DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 2 DAY), DATE_ADD(CURDATE(), INTERVAL 5 DAY), DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 1 DAY), 'devuelto');

-- 8. PRESTAMO_DETALLE
-- Detalle del Préstamo 1 (Llevó una Laptop y un cable)
INSERT INTO prestamo_detalle (prestamo_id, equipo_id, cantidad) VALUES 
(1, 1, 1), 
(1, 5, 2);

-- Detalle del Préstamo 2 (Llevó un proyector)
INSERT INTO prestamo_detalle (prestamo_id, equipo_id, cantidad) VALUES 
(2, 4, 1);

-- Detalle del Préstamo 3 (Llevó una Laptop, ya devuelta)
INSERT INTO prestamo_detalle (prestamo_id, equipo_id, cantidad) VALUES 
(3, 2, 1);

-- 9. BITACORA
INSERT INTO bitacora (usuario_id, accion, entidad, entidad_id, valores_antes, valores_despues, ip) VALUES 
(1, 'LOGIN', 'usuarios', 1, NULL, NULL, '192.168.1.100'),
(2, 'CREAR', 'prestamos', 1, NULL, '{"usuario_id": 3, "equipo": "LAP-001"}', '192.168.1.105'),
(1, 'EDITAR', 'equipos', 3, '{"nombre_estado": "Disponible"}', '{"nombre_estado": "Mantenimiento"}', '192.168.1.100');





SELECT
    r.nombre_rol,
    p.clave
FROM rol r
INNER JOIN rol_permiso rp
    ON r.id_rol = rp.rol_id
INNER JOIN permisos p
    ON rp.permiso_id = p.id_permiso
WHERE r.id_rol = 2
ORDER BY p.clave;

SELECT id_permiso, clave
FROM permisos
WHERE clave IN ('equipos.ver', 'prestamos.ver');


INSERT INTO rol_permiso (rol_id, permiso_id)
SELECT 2, p.id_permiso
FROM permisos p
WHERE p.clave IN ('equipos.ver', 'prestamos.ver')
AND NOT EXISTS (
    SELECT 1
    FROM rol_permiso rp
    WHERE rp.rol_id = 2
      AND rp.permiso_id = p.id_permiso
);


SELECT
    e.id_equipo,
    e.codigo,
    c.categoria_nombre,
    e.nombre_estado,
    e.ruta_imagen
FROM equipos e
INNER JOIN categorias c
    ON e.id_categoria = c.categoria_id
WHERE e.eliminado_en IS NULL
ORDER BY e.id_equipo ASC;


SELECT
    r.nombre_rol,
    p.clave
FROM rol r
INNER JOIN rol_permiso rp
    ON r.id_rol = rp.rol_id
INNER JOIN permisos p
    ON rp.permiso_id = p.id_permiso
WHERE r.id_rol = 1
ORDER BY p.clave;


INSERT INTO rol_permiso (rol_id, permiso_id)
SELECT 1, p.id_permiso
FROM permisos p
WHERE p.clave = 'equipos.ver'
AND NOT EXISTS (
    SELECT 1
    FROM rol_permiso rp
    WHERE rp.rol_id = 1
      AND rp.permiso_id = p.id_permiso
);

INSERT IGNORE INTO permisos (clave)
VALUES ('usuarios.ver');

INSERT IGNORE INTO rol_permiso (rol_id, permiso_id)
SELECT r.id_rol, p.id_permiso
FROM rol r
JOIN permisos p ON p.clave = 'usuarios.ver'
WHERE r.nombre_rol = 'Administrador';


INSERT IGNORE INTO rol_permiso (rol_id, permiso_id)
SELECT 1, id_permiso
FROM permisos
WHERE clave = 'usuarios.ver';


INSERT INTO rol_permiso (rol_id, permiso_id)
SELECT 1, p.id_permiso
FROM permisos p
WHERE p.clave = 'prestamos.ver'
  AND NOT EXISTS (
      SELECT 1
      FROM rol_permiso rp
      WHERE rp.rol_id = 1
        AND rp.permiso_id = p.id_permiso
  );

SELECT
    id_equipo,
    codigo,
    nombre_estado,
    eliminado_en
FROM equipos
ORDER BY id_equipo DESC;

SELECT * FROM bitacora;













