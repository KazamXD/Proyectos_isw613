
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
SET password_hash = '$2y$10$XE0wNra/.p078Yu9YSyOSuN5n/7.y2gkA5.LGnVAFpU4CN0A2OK6K'
WHERE nom_usuario = 'cadmin';

SELECT * FROM usuarios WHERE id  = 1;

-- 6. EQUIPOS
INSERT INTO equipos (id_categoria, codigo, nombre_estado, ruta_imagen) VALUES 
(1, 'LAP-001', 'Disponible', '/img/lap001.jpg'),
(1, 'LAP-002', 'Disponible', '/img/lap002.jpg'),
(1, 'LAP-003', 'Mantenimiento', NULL),
(2, 'PROY-001', 'Disponible', '/img/proy001.jpg'),
(3, 'CABLE-HDMI-01', 'Disponible', NULL);

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
    p.id_prestamo,
    u.nombre AS usuario,
    op.nombre AS operador,
    e.codigo AS equipo,
    p.estado,
    p.fecha_limite
FROM prestamos p
JOIN usuarios u ON p.usuario_id = u.id
JOIN usuarios op ON p.operador_id = op.id
JOIN prestamo_detalle pd ON p.id_prestamo = pd.prestamo_id
JOIN equipos e ON pd.equipo_id = e.id_equipo;












