# Estado del proyecto

## Último commit estable

feat: implementar carrito dinamico pos

Commit de cierre de POS etapa 4, con verificaciones automáticas y pruebas manuales en navegador correctas. Para obtener su hash: `git log -1 --format=%h --grep="^feat: implementar carrito dinamico pos$"`.

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

Modelo de datos y tablas de Caja + POS + Ventas creados manualmente en phpMyAdmin. Incluye Código EAN obligatorio y único, cajas, sesiones, medios de pago, ventas, detalles y pagos combinados con monto entregado y vuelto para efectivo.

## POS ETAPA 2 — TERMINADA

Pruebas manuales en navegador confirmadas como correctas.

- Administración de cajas: alta, edición, baja lógica, reactivación y nombre único
- Apertura con monto inicial y listado de sesiones abiertas
- Una sesión abierta por caja física; se permite abrir otras cajas y reabrir tras un cierre
- Cierre con total de ventas, total efectivo, efectivo esperado, efectivo contado y diferencia
- Histórico de sesiones de solo lectura; segundo cierre rechazado
- Transacciones con SELECT ... FOR UPDATE y rollback; importes calculados en servidor
- Gestión de cajas para DUEÑO/ADMINISTRADOR; sesiones para VENDEDOR/DUEÑO/ADMINISTRADOR

## POS ETAPA 3 — TERMINADA Y PROBADA

Pruebas manuales en navegador correctas. Base modificada manualmente: codigo_barras VARCHAR(50) NOT NULL, conservando UNIQUE.

- Código EAN obligatorio y único en alta, edición y listado de Productos
- Almacenado como texto, con conservación de ceros iniciales
- Búsqueda exacta por EAN y búsqueda parcial por nombre
- Acción JSON interna para el futuro POS, con permiso VENDEDOR
- Solo productos activos; stock cero identificado como SIN STOCK
- Consultas y edición de EAN sin modificar stock actual

## POS ETAPA 4 — TERMINADA Y PROBADA

Pruebas manuales en navegador correctas: terminal POS con sesión de caja seleccionada, escaneo EAN, búsqueda manual y carrito dinámico temporal en JavaScript. Permite sumar/restar cantidades y eliminar productos, limita visualmente por stock y calcula subtotales y total en centavos. Bloquea el cambio de sesión con carrito cargado. No modifica stock real; Finalizar venta permanece deshabilitado.

## En desarrollo

Ninguno.

## Próximo gran bloque

COBRO + CONFIRMACIÓN COMPLETA DE VENTA — agrupa las antiguas POS 5 y POS 6.

Medios de pago, pagos combinados, efectivo entregado, vuelto y validación de pagos; creación de venta, detalle_ventas y pagos_ventas, descuento de stock y movimientos_stock VENTA dentro de una transacción completa.

Luego: histórico/detalle de ventas, ticket e integración/pruebas finales. Alcance en [POS_CAJA_VENTAS.md](POS_CAJA_VENTAS.md).

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
