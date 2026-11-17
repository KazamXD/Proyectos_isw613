# SIGEL

## Sistema de Gestión de Préstamo de Equipos de Laboratorio

SIGEL es una aplicación web desarrollada en PHP y MariaDB para la
administracion de equipos de laboratorio, gestionar préstamos, 
controlar solicitudes y consultar reportes.

El sistema implementa autenticación, autorización por roles,
registro de actividades y medidas de seguridad para proteger
la información.

## 1. Tecnologías utilizadas

- PHP 8.2
- MariaDB 10.4
- Apache (LAMPP)
- HTML5
- CSS3
- Bootstrap 5
- JavaScript
- PDO para la conexión a la base de datos

## 2. Funcionalidades

### Gestión de usuarios

- Registrar usuarios.
- Consultar usuarios con búsqueda y paginación.
- Modificar información de usuarios.
- Desactivar usuarios mediante eliminación lógica.
- Asignar roles y permisos.

### Gestión de categorías

- Registrar categorías.
- Consultar categorías.
- Editar categorías.
- Eliminar categorías lógicamente.
- Configurar los días máximos de préstamo.

### Gestión de equipos

- Registrar equipos.
- Consultar equipos mediante filtros y ordenamiento.
- Modificar equipos.
- Eliminar equipos lógicamente.
- Adjuntar imágenes.
- Controlar estados de disponibilidad y mantenimiento.

### Gestión de préstamos

- Registrar préstamos de uno o varios equipos.
- Establecer fechas y horas límite.
- Registrar devoluciones.
- Identificar préstamos retrasados.
- Impedir modificaciones de equipos con préstamos activos.

### Solicitudes de préstamo

- Crear solicitudes de equipos.
- Seleccionar fechas de inicio y devolución.
- Validar los días máximos según la categoría.
- Aprobar solicitudes.
- Rechazar solicitudes.
- Cancelar solicitudes pendientes.

### Restricción por préstamos retrasados

Los usuarios que tengan préstamos vencidos pendientes de devolución
no podrán registrar nuevas solicitudes de préstamo.

La restricción se valida en el servidor.

### Reportes

- Inventario de equipos.
- Préstamos retrasados.
- Historial de préstamos.
- Consulta de bitácora.
- Exportación de reportes a CSV.

## 3. Roles de usuario

### Administrador

Puede administrar usuarios, categorías y equipos, además de
consultar reportes y gestionar las operaciones autorizadas.

### Operador de Préstamos

Puede consultar equipos, gestionar préstamos, registrar devoluciones
y atender solicitudes.

### Usuario Estándar

Puede consultar equipos, realizar solicitudes de préstamo,
consultar sus solicitudes y cancelar solicitudes pendientes propias.

Los permisos se validan del lado del servidor mediante
las funciones de autorización.

## 4. Seguridad

El sistema implementa las siguientes medidas:

- Consultas preparadas mediante PDO.
- Prevención de SQL Injection.
- Contraseñas protegidas mediante password_hash().
- Verificación mediante password_verify().
- Regeneración del identificador de sesión.
- Expiración de sesiones por inactividad.
- Cookies HttpOnly y SameSite.
- Cookies Secure cuando se utiliza HTTPS.
- Protección CSRF mediante tokens.
- Protección XSS mediante htmlspecialchars().
- Validación de datos del lado del servidor.
- Control de acceso mediante roles y permisos.
- Limitación de intentos fallidos de autenticación.
- Bloqueo temporal de cuentas.
- Validación de imágenes mediante tipo MIME y tamaño.
- Neutralización de fórmulas en archivos CSV.
- Registro de operaciones en bitácora.

## 5. Requisitos de instalación

Para ejecutar el sistema se necesita:

- Apache.
- PHP 8.2 o compatible.
- MariaDB 10.4 o compatible.
- Extensión PDO MySQL habilitada.
- Extensión fileinfo habilitada.
- Navegador web.

Se recomienda utilizar LAMPP debido a que el sistema
fue desarrollado utilizando una distribución de linux
basado en Fedora 44. El sistema es compatible con XAMPP.

## 6. Instalación

### Paso 1. Obtener el proyecto

Clonar el repositorio:

```bash
git clone https://github.com/KazamXD/Proyectos_isw613/
```

También se puede descargar el proyecto como archivo ZIP.

### Paso 2. Configurar Apache

Ubicar el proyecto en una carpeta accesible para Apache.

En LAMPP, una ubicación habitual es:

```text
/opt/lampp/htdocs/
```

También se puede configurar un VirtualHost personalizado.
En dicho caso la ubicación la elige el usuario.

### Paso 3. Crear la base de datos

Ejecutar el archivo:

```text
schema.sql
```

Este archivo crea la base de datos `equipo_computo`
y sus tablas correspondientes.

Después ejecutar:

```text
seed.sql
```

Este archivo registra los roles, permisos y datos iniciales.

IMPORTANTE:

El archivo seed.sql está diseñado para ejecutarse sobre
una base de datos recién creada.

### Paso 4. Configurar la conexión

Abrir:

```text
app/config/connection.php
```

Configurar los siguientes datos:

- Servidor de MariaDB.
- Nombre de la base de datos.
- Usuario de MariaDB.
- Contraseña de MariaDB.

La conexión utiliza PDO con consultas preparadas.

### Paso 5. Configurar permisos de archivos

Verificar que Apache tenga permisos de escritura sobre:

```text
app/public/uploads/equipment/
```

Esta carpeta almacena las imágenes de los equipos.

### Paso 6. Iniciar el servidor

Iniciar Apache y MariaDB desde LAMPP.

Después abrir el sistema en el navegador.

Ejemplo:

```text
http://localhost/Proyecto_1/app/public/
```

La dirección puede variar según la configuración
del servidor Apache y la ubicación del proyecto.

## 7. Autenticación

El sistema permite iniciar sesión mediante nombre de usuario
y contraseña.

También incluye la opción "Recordarme", que utiliza
tokens persistentes con el esquema selector:validador.

Los usuarios desactivados no pueden iniciar sesión.

Después de varios intentos fallidos de autenticación,
se aplica un bloqueo temporal.

## 8. Base de datos

El sistema utiliza las siguientes tablas:

- rol
- permisos
- rol_permiso
- usuarios
- categorias
- equipos
- prestamos
- prestamo_detalle
- solicitudes
- solicitud_detalle
- auth_tokens
- bitacora

Las relaciones se establecen mediante claves primarias
y foráneas.

## 9. Bitácora

La bitácora registra operaciones realizadas en el sistema.

Incluye:

- Usuario responsable.
- Tipo de acción.
- Entidad afectada.
- Identificador del registro.
- Valores anteriores.
- Valores posteriores.
- Dirección IP.
- Fecha y hora.

Se registran principalmente las operaciones de creación,
modificación y eliminación, además de eventos de autenticación.

## 10. Eliminación lógica

Los usuarios, categorías y equipos no se eliminan
físicamente de la base de datos durante las operaciones
normales de eliminación.

En su lugar, se registra la fecha de desactivación
o eliminación.

Esto permite conservar la información histórica
y las relaciones entre registros.

## 11. Consideraciones

- El sistema debe ejecutarse mediante Apache.
- No se recomienda abrir los archivos PHP directamente.
- Las operaciones protegidas requieren una sesión autenticada.
- Los permisos se verifican en cada petición correspondiente.
- El archivo de conexión debe configurarse según el entorno.
- La zona horaria del servidor debe estar configurada correctamente.
- Los archivos SQL deben mantenerse actualizados.

## 12. Autor

Proyecto académico desarrollado para el curso ISW613
de la Universidad Técnica Nacional (UTN).

Sistema de Gestión de Préstamo de Equipos de Laboratorio (SIGEL).