# Estado del proyecto

## Bloque actual — IMPLEMENTADO, PENDIENTE DE SQL Y PRUEBA MANUAL

Compra ≠ recepción. Compra guarda pedido, detalle/costos históricos y estado PENDIENTE; no suma stock ni genera COMPRA. Recepción de mercadería acepta cantidades exactas o rechaza con motivo; permite entregas directas sin compra. Solo aceptar crea entrada de stock y movimiento RECEPCION, dentro de una única transacción con cabecera, detalle, saldos y estado. No hay recepción parcial ni segundo procesamiento de la misma compra.

- RecepcionController/RecepcionModel y vistas: pendientes, revisión, directa e histórico de solo lectura. Permiso RECEPCIONISTA/DUEÑO/ADMINISTRADOR; POST y token de sesión. Altas rápidas reutilizadas, sin crear categorías ni modificar permisos de CRUD.
- Cierre definitivo: ADMINISTRADOR/DUEÑO cualquier ABIERTA; VENDEDOR propia; RECEPCIONISTA denegado. Auditoría conserva id_usuario_apertura y registra el usuario real de cierre. POS permanece en sesiones propias.
- SQL manual: database/recepcion_manual.sql, una vez con aplicación detenida. Existentes RECEPCIONADAS; futuras PENDIENTES. Nunca duplicar stock ni fabricar recepciones históricas.
- Pruebas con base simulada: compra sin cambios de inventario, aceptar/rechazar, directa, cantidades, doble recepción, rollback, múltiples productos, altas rápidas, ventas y cierres. Base real intacta. NO commit ni push hasta prueba manual.

## Último bloque publicado (58ccbb7)

`feat: mejorar operacion de cajas compras y reposicion`: implementado y verificado con fixtures y simulación JavaScript; pendiente de recorrido manual en navegador.

- La comprobación de cierre ahora aplica la regla definitiva descrita arriba: supervisores cualquier sesión abierta y vendedor solo propia; conserva controles GET/POST y FOR UPDATE.
- POS lista y valida sesiones abiertas propias; la confirmación de venta revalida la propiedad dentro de su transacción.
- Reposición combina nombre, EAN exacto, categoría y proveedor, manteniendo actual <= mínimo y todos los proveedores asociados sin duplicar filas.
- Compras combina proveedor con ID/comprobante. Nueva compra ofrece altas rápidas de proveedor y producto mediante modales, POST, token de sesión y JSON interno, sin abandonar la compra.
- Altas rápidas permitidas para RECEPCIONISTA/DUEÑO/ADMINISTRADOR; CRUD completos mantienen DUEÑO/ADMINISTRADOR. Validaciones compartidas en modelos; el producto nace con stock 0 y asociación activa al proveedor elegido.
- La compra confirmada conserva su transacción habitual. El bloque actual reemplaza la entrada inmediata por recepción aceptada; venta y cierre conservan su comportamiento; rollback del alta de producto si falla su asociación. No hay cambios de esquema en este bloque ni escrituras sobre la base real desde Codex.

## Restablecimiento de contraseñas — bloque anterior

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

## Compras / Recepción — NUEVA LÓGICA PENDIENTE DE PRUEBA MANUAL

Compras conserva filtros, altas rápidas, cabecera, detalle y total calculado en servidor, dentro de una transacción. Nuevas compras PENDIENTES, sin modificar stock. Recepción aceptada incorpora stock y genera RECEPCION; rechazadas guardan motivo sin entrada física. Acceso RECEPCIONISTA/DUEÑO/ADMINISTRADOR. Históricos sin edición ni cancelación.

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
- Circuito simulado apertura → compra → recepción aceptada → venta combinada → stock/movimientos → cierre → reapertura correcto
- Cierre y consulta de sesión muestran distribución de ventas por medio de pago
- 63 PHP sin errores de sintaxis; 28 pantallas renderizadas con fixtures, rutas/permisos y lógica esencial comprobados
- Validaciones de Producto ajustadas a longitudes, DECIMAL(12,2) y rango INT del esquema
- README actualizado y guías de demostración/estudio disponibles

## En desarrollo

Validación local del circuito nuevo en navegador/MariaDB e impresión física. Los módulos hasta POS 4 ya tienen prueba manual confirmada; los bloques posteriores se verificaron con DataBase/DOM simulados.

## Próximo objetivo

Aplicar manualmente database/recepcion_manual.sql con la aplicación detenida; probar Compras → Recepción y recepción directa, rechazos, cantidades, stock e histórico y la matriz definitiva de cierre. No hacer commit/push hasta esa prueba. Si siguen pendientes cambios de esquema de bloques previos, revisarlos por separado; este bloque agrega solo estado_recepcion y dos tablas.

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
