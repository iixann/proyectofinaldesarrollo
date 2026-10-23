# Estado del proyecto

## Último commit estable

docs: definir esquema de caja pos y ventas

Commit de cierre documental de POS etapa 1. Modelo aprobado y creación manual de tablas confirmada por el usuario. Para obtener su hash: `git log -1 --format=%h --grep="^docs: definir esquema de caja pos y ventas$"`.

Etapa visual anterior: `a0c1048` — `feat: mejorar interfaz visual del sistema`.

Base funcional anterior: `0050b9a` — `feat: implementar autenticacion y gestion inicial de usuarios`.

## Terminado

- Arquitectura MVC basada en la referencia del profesor
- .htaccess y Front Controller
- Autoloader
- Router App
- Conexión PDO mediante DataBase
- Base inicial con usuarios, roles y usuarios_roles
- Login
- Sesión
- Logout
- Panel ADMINISTRADOR
- Listado de usuarios
- Creación de usuarios
- Asignación de uno o varios roles
- Roles ADMINISTRADOR, DUEÑO, RECEPCIONISTA y VENDEDOR
- Autorización centralizada mediante `SessionController::onlyUsers()`
- Jerarquía funcional de roles implementada en memoria, sin guardar roles inferiores adicionales
- Panel y gestión de usuarios exclusivos de ADMINISTRADOR, incluso por URL directa
- Documentación SQL inicial
- Dashboard visual terminado con accesos rápidos y módulos pendientes sin enlaces a rutas inexistentes
- Login, gestión de usuarios y creación de usuarios con interfaz Bootstrap coherente
- Includes reutilizables de vistas: head.php, navbar.php y footer.php en app/views/inc/
- Identidad visual de vinería con color Malbec #6D1A36 aplicado en los estilos compartidos

## Categorías — TERMINADO

Pruebas manuales en navegador confirmadas como correctas.

- Listado, creación, edición, baja lógica y reactivación por POST
- Acceso mediante `onlyUsers(['DUEÑO'])`: ADMINISTRADOR y DUEÑO permitidos
- Validación de nombre, duplicados e ID; esquema SQL actualizado solo como documentación
- Vistas Bootstrap/Malbec y acceso desde la navbar para los roles autorizados

## Proveedores — TERMINADO

Listado, creación, edición, baja lógica y reactivación por POST; autorización DUEÑO/ADMINISTRADOR, CUIT opcional único y vistas Bootstrap/Malbec. Maestro de proveedores asociado a Productos mediante productos_proveedores. Pruebas manuales en navegador confirmadas como correctas.

## Productos — TERMINADO

Pruebas manuales en navegador confirmadas como correctas. Funcionalidad: listado con categoría y aviso de stock bajo; creación, edición, baja lógica y reactivación; selección de proveedores activos y transacciones. Acceso DUEÑO/ADMINISTRADOR. Esquema SQL documentado con los tipos y longitudes confirmados de las tablas reales.

## Stock / Movimientos — TERMINADO

Pruebas manuales en navegador confirmadas como correctas. Funcionalidad: listado de productos activos, estado de stock y movimientos generales o por producto; ajustes de entrada/salida por POST con usuario de sesión, transacción y bloqueo de fila. Acceso RECEPCIONISTA/DUEÑO/ADMINISTRADOR. Productos nuevos comienzan con stock 0 y su edición ya no modifica stock actual.

## Compras — TERMINADO

Pruebas manuales correctas: filtro de productos por proveedor y limpieza de selecciones inválidas; cabecera, detalle histórico y total calculado en servidor; incremento automático de stock y movimientos COMPRA dentro de una única transacción. Proveedores, productos y asociaciones activos. Acceso RECEPCIONISTA/DUEÑO/ADMINISTRADOR. Sin edición ni cancelación.

## POS ETAPA 1 — TERMINADA

Modelo de datos y tablas de Caja + POS + Ventas creados manualmente en phpMyAdmin. Incluye código de barras opcional y único, cajas, sesiones, medios de pago, ventas, detalles y pagos combinados con monto entregado y vuelto para efectivo.

## En desarrollo

Ninguno.

## Próximo gran bloque

Caja + POS + Ventas — POS etapa 1 terminada; funcionalidad PHP pendiente.

Próximo objetivo: POS ETAPA 2 — Cajas + apertura/cierre de caja.

Alcance y orden por etapas en [POS_CAJA_VENTAS.md](POS_CAJA_VENTAS.md). Etapas POS 3–9 pendientes.

## Reglas del proyecto

- PHP puro
- MVC del profesor
- PDO centralizado en DataBase
- Sin Service
- Sin Repository
- Sin ORM
- Sin DTO
- Sin frameworks de aplicación; Bootstrap solamente como capa visual
- Variables propias preferentemente en español
- HTML funcional primero
- Bootstrap/CSS aplicado al recorrido actual; mantener la funcionalidad como prioridad
- Sin autorregistro
- ADMINISTRADOR tiene todos los privilegios
- DUEÑO puede realizar funciones de DUEÑO, RECEPCIONISTA y VENDEDOR, pero no las exclusivas de ADMINISTRADOR

## Tecnologías

- PHP 8.2 / LAMPP
- MariaDB/MySQL
- PDO
- Composer
- phpdotenv
- HTML
- Bootstrap 5.3.2 por CDN
- FontAwesome 6.5.1 por CDN
- CSS compartido con variables de Bootstrap para la identidad Malbec
- Git / GitHub

## Dinámica de trabajo

Cada módulo debe seguir:

Objetivo concreto
→ implementación
→ prueba en navegador
→ corrección
→ explicación de la lógica importante
→ actualización de documentación
→ commit
→ siguiente módulo

Después de cada módulo terminado, actualizar:

- ESTADO_PROYECTO.md
- ROADMAP.md si corresponde
- DEFENSA.md con conceptos nuevos realmente utilizados

Mantener estos documentos breves y útiles para retomar el trabajo.
