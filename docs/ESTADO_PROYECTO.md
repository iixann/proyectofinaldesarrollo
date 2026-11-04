# Estado del proyecto

## Último bloque funcional

`feat: agregar restablecimiento seguro de contrasenas`: restablecimiento administrativo, cambio obligatorio y herramienta técnica CLI implementados y verificados con base simulada; pendiente de prueba manual local.

- Solo ADMINISTRADOR restablece contraseñas desde Usuarios, con temporal de mínimo 9 caracteres y confirmación, almacenada como hash.
- Login temporal dirige a cambio obligatorio; onlyUsers comprueba el estado real antes de autorizar, bloqueando evasión por URL. Sesiones anteriores al reset dejan de ser utilizables.
- Cambio de contraseña propio por POST y token de sesión; exige una clave distinta de la temporal y deja `debe_cambiar_password = 0`. Usuarios inactivos siguen inactivos.
- `tools/reset_admin_password.php`: recuperación CLI de un ADMINISTRADOR existente por email, sin contraseña maestra ni correo. Directorio protegido para Apache y script restringido a CLI.
- Esquema documentado: se renombra el indicador existente `requiere_cambio_password` a `debe_cambiar_password` (DEFAULT 0), sin duplicarlo. Único cambio manual de este bloque: ALTER de README; conserva valores previos.
- Buscadores de Productos, Stock, Proveedores, Categorías, Compras y Ventas; Usuarios con baja/reactivación y Reposición siguen disponibles. Stock usa solo `stock_actual` y `stock_minimo`; Reposición aplica actual <= mínimo.

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

Pruebas manuales en navegador correctas: terminal POS con sesión de caja seleccionada, escaneo EAN, búsqueda manual y carrito dinámico temporal en JavaScript. Permite sumar/restar cantidades y eliminar productos, limita visualmente por stock y calcula subtotales y total en centavos. Bloquea el cambio de sesión con carrito cargado. Al cerrar POS 4 no modificaba stock real y Finalizar venta permanecía deshabilitado; el bloque de cobro posterior agregó la confirmación real.

## Cobro + Confirmación — TERMINADO

Medios activos y pagos combinados; efectivo entregado y vuelto. PHP toma usuario de sesión y reconstruye productos, precios y total con centavos. Una transacción guarda venta, detalles, pagos, stock y movimientos VENTA; bloquea sesión y productos con FOR UPDATE y revierte todo ante fallos. Token de formulario y bloqueo de doble clic. Pruebas esenciales con DataBase/DOM simulados correctas; pendiente de validación local sobre la base real.

## Histórico + detalle + ticket — TERMINADO

Ventas con filtro por fechas (hasta 100 resultados), detalle de solo lectura y ticket térmico de 72 mm mediante window.print(), reimprimible desde histórico y accesible tras confirmar. Precios, cantidades, importes, entregado y vuelto provienen de los registros guardados. Nombres de maestros se consultan por JOIN; no se guardan copias de nombres. Probado con fixtures, pendiente de validación local de impresión/base real.

## Versión demostrable — IMPLEMENTADA E INTEGRADA

- Login lleva a cada rol a una pantalla permitida; navbar y dashboard enlazan módulos existentes
- Circuito simulado apertura → compra → venta combinada → stock/movimientos → cierre → reapertura correcto
- Cierre y consulta de sesión muestran distribución de ventas por medio de pago
- 63 PHP sin errores de sintaxis; 28 pantallas renderizadas con fixtures, rutas/permisos y lógica esencial comprobados
- Validaciones de Producto ajustadas a longitudes, DECIMAL(12,2) y rango INT del esquema
- README actualizado y guías de demostración/estudio disponibles

## En desarrollo

Validación local del circuito nuevo en navegador/MariaDB e impresión física. Los módulos hasta POS 4 ya tienen prueba manual confirmada; los bloques posteriores se verificaron con DataBase/DOM simulados.

## Próximo objetivo

Aplicar manualmente el renombrado del indicador de contraseña y probar los flujos web/CLI en entorno local. Luego pulido final y recorrido de DEMOSTRACION.md para defensa. Si sigue pendiente, la eliminación de la columna anterior de stock pertenece al bloque previo. Desarrollo automático detenido al publicar este bloque; no se agregan más funcionalidades.

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
