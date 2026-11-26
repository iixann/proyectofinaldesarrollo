# Correcciones indicadas por el profesor

Registro de la implementación en el repositorio original. **Revisión posterior de Usuarios/Roles del 25/11/2026: cambios pendientes de prueba manual, sin commit ni push.** Los cierres anteriores se conservan como registro histórico; esta revisión se documenta en el punto 14.

Estado inicial: rama `main`, remoto `iixann/proyectofinaldesarrollo`, HEAD `b546999`.
Solo estaba pendiente `public/css/ean-scanner.css`: se conservó la regla que oculta la cámara desde 768 px.
No se encontró una marca de principal ni una regla de último administrador. Productos ya mostraba stock actual y mínimo; se conservaron esas columnas y se unificó su consulta y navegación.

Matriz final: **Compras solo ADMINISTRADOR/DUEÑO; RECEPCIONISTA controla pendientes desde Recepción; VENDEDOR no accede a ninguno de esos módulos.** Esta aclaración prevalece sobre los resultados históricos anteriores a la separación de responsabilidades.

Los números de este documento se obtuvieron con `nl -ba` después de completar la implementación. Corresponden al estado pendiente de revisión, no a futuros cambios de línea.

## Estado de la migración local

**Migración aplicada a la base local `proyectofinaldesarrollo`: administrador nuevo `admin@admin.com`, ID 9, único principal activo y sin cambio obligatorio. Los ocho usuarios anteriores quedaron intactos.**

Backup previo conservado: `/home/ivangl/backup_proyectofinal_antes_correcciones_20261121_022640.sql`. Las instrucciones siguientes corresponden a otras instalaciones existentes que todavía no migraron.

1. Hacer una copia de seguridad y detener el uso de la aplicación.
2. Seleccionar la base existente correcta con una cuenta autorizada para ALTER, CREATE ROUTINE y TRIGGER.
3. En la misma conexión, definir `SET @principal_email = 'correo-real-del-administrador';`.
4. Ejecutar `database/migracion_correcciones_profesor.sql` una sola vez, deteniéndose ante errores. No usar `--force`.
5. Verificar `SELECT id_usuario,email,estado,es_principal FROM usuarios WHERE es_principal=1;` y revisar las ventas existentes.
6. Reanudar la aplicación y realizar las pruebas manuales.

No hay un correo real predeterminado ni se elige el ID 1. El correo debe pertenecer a un administrador activo con rol vigente sin fecha de vencimiento. El precontrol aborta antes de los ALTER si falta esa condición. En la instalación local se eligió explícitamente `admin@admin.com`.

Para una **instalación nueva** se usa `database/instalacion_profesor.sql`, nunca la migración. Crea el demo `admin@admin.com` / `admin1234`, ADMINISTRADOR activo, sin cambio obligatorio y marcado como principal por su correo.

La entrega se genera desde el estado actual del repositorio original e incluye este SQL maestro final; no se recupera SQL viejo del ZIP. Este documento personal no debe incluirse en el ZIP salvo pedido expreso.

## 1. Proteger al usuario principal

**Solicitud:** impedir eliminación, baja, inactividad o pérdida del rol esencial del administrador principal.

**Solución:** marca explícita `es_principal`, unicidad por columna generada, CHECK de estado y seis triggers de protección de usuario y rol. Primero se crea usuario y rol; después se marca al principal. El modelo además serializa bajas y protege al último administrador. La interfaz no ofrece baja para el principal y el endpoint devuelve 403 si se fuerza el POST.

| Archivo / método | Líneas finales | Qué hacen |
|---|---:|---|
| `app/models/UserModel.php` — `listarUsuarios()`              | 124–131 | Incluye la marca principal en los datos de la vista. |
| `app/models/UserModel.php` — `baja()`              | 134–158 | Bloquea usuarios, rechaza principal y último administrador, y guarda o revierte la baja. |
| `app/controllers/UserController.php` — `cambiarEstado()`              | 114–146 | Mantiene permisos y POST; devuelve el rechazo del modelo sin desactivar. |
| `app/views/user/index.php`              | 41–65 | Estado/roles compactos, badge Principal y Principal protegido sin acciones administrativas. |
| `database/schema.sql`              | 23–27 | Marca, índice único y CHECK; no dependen del ID del usuario. |
| `database/schema.sql`              | 365–414 | Protecciones de eliminación, desactivación y pérdida del rol principal. |
| `database/instalacion_profesor.sql`              | 428–428 | Selecciona al principal demo por correo después de asignar el rol. |
| `database/migracion_correcciones_profesor.sql`              | 1–86 | Precontrol por correo, ALTER sin borrar datos, triggers y selección explícita del principal. |

**Cómo probar:** abrir Usuarios; el principal muestra “Baja protegida”. Forzar su POST de baja: 403, estado intacto. Dar de baja otro usuario: permitido. En una base temporal intentar DELETE, desactivación o quitar rol por SQL: rechazados. La prueba de migración usa un principal de ID 7 y verifica que un correo inexistente no altera tablas.

**Ajuste visual:** el usuario principal se identifica con el badge compacto `Principal` y la acción `Baja protegida` deshabilitada. Estado y roles usan `text-nowrap`; acciones con `btn-sm`, alineación centrada y `gap-1`. La tabla conserva su desplazamiento interno en móvil. No cambia la lógica.

## 2. Productos integra catálogo y existencias

**Solicitud:** consultar EAN y stock sin depender de otra sección de menú.

**Solución:** Productos concentra catálogo, EAN exacto, cámara móvil, stock actual/mínimo, estado y enlaces de movimientos/ajuste. El vendedor consulta productos activos sin acciones de escritura. Recepción puede mantener el catálogo operativo y consultar existencias, con auditoría de sus cambios (ampliación de esta etapa). `StockModel` permanece y `/stock/index` redirige conservando filtros. Se conserva el ingreso físico/manual y la cámara sigue oculta desde 768 px.

| Archivo / método | Líneas finales | Qué hacen |
|---|---:|---|
| `app/controllers/ProductoController.php` — `actionIndex()`              | 15–40 | Admite consulta de vendedor/recepción, separa mantenimiento operativo y filtra EAN/activos. |
| `app/models/ProductoModel.php` — `listarProductos()`              | 15–40 | Agrega EAN parametrizado y filtro interno de productos activos. |
| `app/controllers/StockController.php` — `actionIndex()`              | 15–30 | Valida filtros y conserva las URLs anteriores con redirección al catálogo. |
| `app/views/producto/index.php`              | 1–116 | Filtros, cámara compartida, resumen móvil, columnas de stock y acciones según permisos. |
| `app/views/inc/navbar.php`              | 40–42 | El menú ofrece Productos al rol que ya consultaba Stock; ya no duplica Stock. |
| `app/views/admin/index.php`              | 30–30 | Acceso único a catálogo y existencias; se retiró la tarjeta duplicada de Stock. |
| `app/views/inc/consulta_rapida.php`              | 12–12 | El atajo de existencias conserva su tecla y lleva al catálogo. |
| `app/controllers/SessionController.php`              | 41–41 | Inicio coherente para el recepcionista. |
| `public/css/ean-scanner.css`              | 3–6 | Cambio pendiente conservado: controles de cámara solo por debajo de 768 px. |

**Cómo probar:** buscar EAN en Productos desde PC y celular, consultar la tarjeta y las columnas de stock, abrir `/stock/index?codigo_barras=...` y comprobar el destino con filtros. Como vendedor consultar Productos y forzar Editar: 403. Recepción sí puede crear y editar según la ampliación operativa documentada en el punto 11. Enviar `stock_actual` a Editar Producto no altera existencias; se mantienen las operaciones válidas de stock.

## 3. Confirmación reutilizable de bajas

**Solicitud:** confirmar una baja antes de ejecutarla, especialmente la de un usuario.

**Solución:** atributo declarativo `data-confirmar-baja` en formularios de Usuario, Producto, Proveedor y Categoría. Un único modal muestra el nombre usando texto seguro. Cancelar descarta el formulario; Confirmar permite un único POST normal. Se conservan la autorización y las validaciones backend.

| Archivo / método | Líneas finales | Qué hacen |
|---|---:|---|
| `app/views/inc/confirmar_baja.php`              | 1–9 | Modal compartido con pregunta, Cancelar y Confirmar. |
| `public/js/confirmar-baja.js`              | 1–33 | Intercepta submit, exige confirmación y conserva el formulario y su ruta originales. |
| `app/views/inc/footer.php`              | 12–13 | Incluye el componente una sola vez por página. |
| `app/views/user/index.php`              | 61–63 | Identifica la baja y el nombre del registro; la reactivación no usa confirmación destructiva. |
| `app/views/producto/index.php`              | 90–95 | Identifica la baja y el nombre del registro; la reactivación no usa confirmación destructiva. |
| `app/views/proveedor/index.php`              | 48–48 | Identifica la baja y el nombre del registro; la reactivación no usa confirmación destructiva. |
| `app/views/categoria/index.php`              | 45–45 | Identifica la baja y el nombre del registro; la reactivación no usa confirmación destructiva. |

**Cómo probar:** pulsar Dar de baja, comprobar la pregunta y cancelar: no cambia la base. Repetir y confirmar: se envía el POST. Probar también productos, proveedores y categorías. El principal no tiene formulario de baja y su backend continúa protegido.

## 4. Borrador persistente del POS

**Solicitud:** conservar productos y cantidades al navegar, separando vendedores y cajas.

**Solución:** `sessionStorage` de la pestaña con clave por usuario, autenticación aleatoria y caja. Se guardan líneas y selección, nunca pagos ni credenciales. Volver restaura mediante las validaciones del carrito. Los valores guardados son referenciales: el modelo sigue recalculando precio y validando stock, actividad y caja al confirmar. Si cambiaron precios o stock, se debe revisar/recargar los productos que el backend rechace.

La venta confirmada o Vaciar carrito eliminan el borrador. Cajas inválidas se depuran con la lista autorizada enviada por PHP. Logout o nueva autenticación descartan borradores anteriores. El regreso desde caché de navegación revalida la sesión.

| Archivo / método | Líneas finales | Qué hacen |
|---|---:|---|
| `public/js/carrito-temporal.js`              | 1–59 | Crear, guardar, leer, vaciar y depurar borradores; limpieza de logout y caché de navegación. |
| `app/controllers/SessionController.php`              | 245–245 | Genera un contexto diferente en cada autenticación. |
| `app/controllers/PosController.php` — `actionIndex()`              | 15–55 | Envía sesiones propias disponibles y evita caché de una terminal anterior. |
| `app/views/pos/index.php`              | 41–41 | Asocia terminal, usuario y contexto al borrador. |
| `app/views/pos/index.php`              | 146–157 | Entrega sesiones autorizadas y depura borradores antes de iniciar el POS. |
| `app/views/inc/footer.php`              | 15–16 | Carga el almacén con identidad de la sesión actual, incluso para limpiar al salir. |
| `public/js/pos.js` — `dibujarCarrito()`              | 317–379 | Guarda líneas después de cada modificación visible. |
| `public/js/pos.js`              | 645–660 | Restaura el borrador completo o lo descarta si es inválido. |
| `public/js/pos.js`              | 556–559 | La confirmación exitosa elimina memoria y borrador. |

**Cómo probar:** cargar tres unidades, ir a Ventas y volver a la misma caja: se conservan. Abrir otra caja: no recibe esas líneas. Confirmar o vaciar: desaparece el borrador. Cerrar sesión e ingresar con otro vendedor: no ve las líneas anteriores. Cerrar una caja y volver al POS: no restaura ese borrador. F2–F10 siguen operando.

## 5. Rectificar ventas con trazabilidad

**Solicitud:** corregir productos, cantidades o pagos de una venta sin borrar el original ni dejar stock o caja inconsistentes.

**Solución:** flujo `Venta → Corregir / Rectificar → Confirmar rectificación`, solo para ventas vigentes y cajas ABIERTAS. El vendedor solo opera sobre una venta propia. Administrador y dueño pueden operar sobre las autorizadas, conservando al vendedor original en la nueva venta; el actor de la corrección queda registrado aparte.

La transacción bloquea sesión, caja, original y unión de productos ordenados. Revierte el stock original con movimientos `RECTIFICACION_ENTRADA`, valida el nuevo detalle y cobro con la lógica de confirmación existente, registra la nueva venta y marca el original `RECTIFICADA` con `estado=0`, fecha, actor y motivo. Una FK enlaza la nueva venta con el original y un UNIQUE impide dos reemplazos del mismo original. Ante cualquier fallo se revierte todo.

No se borran detalle ni pagos anteriores. Los resúmenes existentes de caja suman solamente ventas `estado=1`; por eso excluyen el original y cuentan el reemplazo vigente. No se permiten cambios sobre cajas cerradas, ni siquiera enviando manualmente un POST. Las sucesivas correcciones forman una cadena verificable.

| Archivo / método | Líneas finales | Qué hacen |
|---|---:|---|
| `app/models/VentaModel.php` — `esSupervisor()`              | 72–75 | Reconoce ADMINISTRADOR y DUEÑO, sin roles enviados por navegador. |
| `app/models/VentaModel.php` — `exigirRectificable()`              | 78–83 | Verifica propiedad, caja abierta y original vigente. |
| `app/models/VentaModel.php` — `rectificar()`              | 86–93 | Valida ID y motivo y deriva al guardado transaccional. |
| `app/models/VentaModel.php` — `confirmar()`              | 103–301 | Bloqueos, reversión, validación de precios/stock/pagos, nueva venta y auditoría atómica. |
| `app/controllers/VentaController.php` — `actionRectificar()`              | 98–110 | Prepara el formulario con propiedad y estado verificados. |
| `app/controllers/VentaController.php` — `formularioRectificacion()`              | 113–123 | Carga catálogo y medios actuales y conserva entradas rechazadas. |
| `app/controllers/VentaController.php` — `actionGuardarRectificacion()`              | 126–163 | Valida POST/CSRF, normaliza pagos, invoca el modelo y redirige al nuevo detalle. |
| `app/views/venta/rectificar.php`              | 1–50 | Formulario explícito de motivo, productos, cantidades y nuevos pagos; no cambia tablas directamente. |
| `public/js/rectificar-venta.js`              | 1–38 | Filas de borrador y total estimado; el cálculo definitivo siempre es del backend. |
| `app/views/venta/ver.php`              | 8–15 | Muestra rectificación, fecha, actor, motivo y vínculo al reemplazo o al original. |
| `app/views/venta/ticket.php`              | 24–31 | Muestra rectificación, fecha, actor, motivo y vínculo al reemplazo o al original. |
| `app/views/venta/index.php`              | 28–37 | Lista el estado rectificado sin ocultar el original. |
| `database/schema.sql`              | 242–273 | Estado explícito, relación entre ventas, autor, fecha y motivo de corrección. |
| `database/instalacion_profesor.sql`              | 246–277 | El instalador nuevo contiene el mismo esquema de trazabilidad. |
| `database/migracion_correcciones_profesor.sql`              | 23–33 | Agrega columnas y relaciones sin alterar ventas históricas; quedan VIGENTES por defecto. |
| `app/models/CajaSesionModel.php` — `resumen()`              | 127–141 | El cálculo conserva su lógica: suma solo ventas vigentes por estado=1. |

**Cómo probar:** vender dos unidades, rectificar a una y ajustar pagos. El stock debe recuperar una unidad neta. El original conserva pagos/detalle, queda rectificado y enlaza al nuevo. Probar monto incorrecto: 422 y ningún cambio de stock/movimientos. Probar original ya rectificado y caja cerrada: 409. Cerrar caja con el neto de ventas vigentes y comprobar diferencia cero. Revisar detalle y ticket de ambas versiones.

## 6. Vendedor solo ve sus ventas

**Solicitud:** filtrar en backend listado, detalle, ticket y rectificación según el usuario real conectado.

**Solución:** el controlador fija el ID desde sesión para vendedores y usa filtro nulo solo para dueño/administrador. El modelo aplica ese parámetro en SQL. El detalle carga primero la venta autorizada antes de consultar productos o pagos. Un ID ajeno devuelve 403; no se confía en `id_usuario` del GET o POST.

| Archivo / método | Líneas finales | Qué hacen |
|---|---:|---|
| `app/controllers/VentaController.php` — `filtroUsuario()`              | 85–88 | Deriva alcance de consulta de la identidad y roles reales de sesión. |
| `app/controllers/VentaController.php` — `actionIndex()`              | 15–39 | Pasa el filtro obligatorio al listado SQL. |
| `app/controllers/VentaController.php` — `datosVenta()`              | 60–82 | Autoriza la venta antes de entregar detalle, pagos o ticket. |
| `app/controllers/VentaController.php` — `actionGuardarRectificacion()`              | 126–163 | Reutiliza la misma autorización antes del modelo transaccional. |
| `app/models/VentaModel.php` — `listarVentas()`              | 21–44 | Agrega WHERE id_usuario para vendedores, incluyendo búsqueda por número. |
| `app/models/VentaModel.php` — `buscarVenta()`              | 47–55 | Restringe el registro por ID y usuario autorizado. |

**Revalidación por rol:** listado, detalle, ticket, GET y POST de rectificación siguen usando la autorización existente; no se duplicó ni modificó VentaController/VentaModel en esta etapa. La prueba integral usa dos vendedores y supervisores reales en una base temporal.

**Revalidación por rol:** listado, detalle, ticket, GET y POST de rectificación siguen usando la autorización existente; no se duplicó ni modificó VentaController/VentaModel en esta etapa. La prueba integral usa dos vendedores y supervisores reales en una base temporal.

**Revalidación por rol:** listado, detalle, ticket, GET y POST de rectificación siguen usando la autorización existente; no se duplicó ni modificó VentaController/VentaModel en esta etapa. La prueba integral usa dos vendedores y supervisores reales en una base temporal.

**Revalidación por rol:** listado, detalle, ticket, GET y POST de rectificación siguen usando la autorización existente; no se duplicó ni modificó VentaController/VentaModel en esta etapa. La prueba integral usa dos vendedores y supervisores reales en una base temporal.

**Revalidación por rol:** listado, detalle, ticket, GET y POST de rectificación siguen usando la autorización existente; no se duplicó ni modificó VentaController/VentaModel en esta etapa. La prueba integral usa dos vendedores y supervisores reales en una base temporal.

**Cómo probar:** ingresar con dos vendedores y generar una venta para cada uno. Cada listado contiene solo la propia, incluso con `?id_usuario=otro`. Forzar detalle, ticket, GET y POST de rectificación ajenos: 403. Dueño y administrador pueden consultar ambas, y su rectificación queda limitada a caja abierta.

## 7. Badge de Reposición

**Solicitud:** mostrar en el menú la cantidad de productos activos con `stock_actual <= stock_minimo`.

**Solución:** COUNT ligero en ReposicionModel y badge compartido solo para roles que acceden a Reposición. Con cero se oculta. Se consulta en cada carga de página, sin polling.

| Archivo / método | Líneas finales | Qué hacen |
|---|---:|---|
| `app/models/ReposicionModel.php` — `contarParaReposicion()`              | 15–19 | Cuenta activos que alcanzaron el mínimo con la misma regla del listado. |
| `app/views/inc/navbar.php`              | 12–16 | Consulta el contador solo con permisos de operaciones. |
| `app/views/inc/navbar.php`              | 59–59 | Renderiza el badge accesible y lo oculta cuando el total es cero. |

**Cómo probar:** dejar un producto activo en el mínimo y comprobar badge 1; recibir mercadería y recargar: desaparece si el stock supera el mínimo. Los inactivos no cuentan. Un vendedor sin acceso a Reposición no ve el enlace ni el contador.

## Pruebas y límites de esta etapa

- `python3 tests/correcciones_profesor.py`: instancia MariaDB nueva con socket/datadir/puerto propios, instalación limpia, migración histórica, endpoints y Firefox sobre los archivos originales. El router aborta si la conexión no apunta al datadir temporal.
- El esquema histórico para migrar se obtiene del commit fijo `b5469999da0fb068ce1886faacedf1201a9d60ce`; la prueba sigue siendo reproducible después del futuro commit.
- `php -l` con PHP de XAMPP sobre todos los PHP propios; `git diff --check`.
- Pruebas existentes: `tests/compra_producto_existente.php`, `tests/ean_scanner_reposicion.php`, `tests/compra_producto_existente.js`.
- La prueba integral vuelve a ejecutar las 52 comprobaciones del lector EAN con cámara simulada y decodificación ZXing real, más los anchos 360/390/412/768/1366.
- `tests/ean_scanner_vistas.php` usa un contador ficticio para el badge, sin conectarse a la base real.
- Resultados detallados: `/tmp/correcciones-profesor-resultados.json`. Los temporales y perfiles del navegador quedan fuera del repositorio.
- Los tests requieren XAMPP, Firefox, Python requests y los recursos Bootstrap 5.3.2 de prueba en `/tmp/ean-bootstrap.min.css` y `/tmp/ean-bootstrap.bundle.min.js`; no cambian la infraestructura del sistema.

| Archivo / método | Líneas finales | Qué hacen |
|---|---:|---|
| `tests/correcciones_profesor.py`              | 1–413 | ejecutar() y probar_navegador(): instalación, migración, permisos, transacciones, interfaz, carrito y EAN. |

**Revisión manual pendiente:** el propietario continúa probando las siete correcciones. La migración local ya fue aplicada. Sin commit ni push. Cada corrección validada se replica a la entrega limpia y se valida aisladamente antes de reemplazar el ZIP.

## Ajuste visual y entrega sincronizada (23/11/2026)

Se verificaron con `nl -ba` las 59 referencias anteriores; la vista conserva sus posiciones de línea. La referencia visual del punto 1 se amplió a las líneas 40–52 para incluir estado, roles y acciones. Firefox comprobó 360, 390, 412, 768 y 1366 px: etiquetas de una línea, baja deshabilitada y sin desbordamiento horizontal de la página; en móvil se conserva el desplazamiento interno de la tabla.

La entrega se generó desde el original sin commit, con aplicación, dependencias y SQL idénticos. Pasó 32 comprobaciones en una instancia MariaDB nueva y Firefox: integridad ZIP, instalación directa del esquema final, login demo, principal protegido, páginas principales, JS/CSS y responsive. No se modificó el SQL por este ajuste visual ni se conectó a la base real. `LEEME_PROFESOR.md` se conserva en el original como fuente de las instrucciones de entrega.

Para las próximas correcciones: original → pruebas → este registro y líneas exactas → SQL final si corresponde → copia limpia desde el original → ZIP candidato → validación aislada → reemplazo de `/home/ivangl/proyectofinalprueba.zip`. No se usa el ZIP como fuente de código.

## 8. Sesiones de caja según rol

**Pedido:** vendedor con vista operativa; dueño/admin con información administrativa completa.

**Solución:** consulta separada de cajas activas que solo devuelve ID/nombre, disponibilidad y el ID de una sesión si pertenece al usuario conectado. No consulta usuarios ni importes ajenos. El detalle y las rutas de cierre filtran por propietario en SQL antes de cargar sus datos; un ID ajeno devuelve 403. Supervisores conservan listado y detalle completos. El formulario de apertura ofrece solo cajas libres; la transacción vuelve a validar la disponibilidad.

| Archivo / método | Líneas finales | Qué hacen |
|---|---:|---|
| `app/models/CajaSesionModel.php` — `disponibilidadOperativa()`         | 24–32 | Proyección mínima por caja; nunca entrega usuario, montos ni ID de sesión ajena. |
| `app/models/CajaSesionModel.php` — `buscarSesion()`         | 42–51 | Aplica alcance de propietario para evitar cargar un detalle ajeno. |
| `app/models/CajaSesionModel.php` — `cajasDisponibles()`         | 35–39 | Ofrece únicamente cajas activas sin sesión abierta. |
| `app/controllers/SesioncajaController.php` — `actionIndex()`         | 13–26 | Elige consulta y vista según supervisor o vendedor. |
| `app/controllers/SesioncajaController.php` — `esSupervisor()`         | 128–131 | Reconoce dueño/admin desde la sesión autenticada. |
| `app/controllers/SesioncajaController.php` — `buscarSesion()`         | 150–170 | Restringe por propietario y rechaza ID ajeno antes de mostrar datos. |
| `app/controllers/SesioncajaController.php` — `formularioAbrir()`         | 195–199 | Carga cajas libres y conserva errores/selección. |
| `app/views/caja_sesion/operativa.php`         | 1–34 | Tarjetas Disponible, No disponible o Mi sesión; acciones solo sobre la propia. |

**Cómo probar:** abrir dos cajas con vendedores distintos y dejar otra libre. El vendedor ve los tres estados, sin identidad ni importes ajenos. Forzar `sesioncaja/ver/ID_AJENO`, `cerrar/ID_AJENO` y POST de cierre: 403. Abrir una ocupada: 422. Dueño/admin ven los datos completos.

## 9. Atajos y consulta rápida del vendedor

**Pedido:** Alt+Q y Alt+O deben abrir la consulta de solo lectura, manteniendo F2–F10.

**Diagnóstico:** footer/modal y endpoint ya autorizaban VENDEDOR. Alt+O no estaba implementado. No se reprodujo una prohibición por rol para Alt+Q: el navegador de prueba lo abre correctamente en la pantalla operativa y en POS. Ambos alias ahora usan la misma función y también reconocen `KeyboardEvent.code`; no se agregó otra consulta ni escritura. El modal requiere que Bootstrap cargue correctamente, como antes.

| Archivo / método | Líneas finales | Qué hacen |
|---|---:|---|
| `public/js/atajos.js`         | 1–136 | Abre, consulta por GET y devuelve el foco; agrega Alt+O junto con Alt+Q y mantiene F9. |
| `app/views/inc/consulta_rapida.php`         | 1–39 | Ayuda correcta de ambos alias y Alt+S disponible al vendedor, sin ofrecer Reposición. |
| `app/views/inc/footer.php`         | 1–16 | Incluye el modal y atajos para los roles habilitados; no fue necesario ampliar permisos. |
| `app/controllers/PosController.php` — `actionConsultarProducto()`         | 117–151 | Endpoint existente GET de consulta: producto, EAN, precio, stock, categoría y proveedores. |

**Cómo probar:** como vendedor, en Sesiones y en POS, pulsar Alt+Q y Alt+O; buscar EAN y verificar los seis datos. Cerrar vuelve al foco/cobro anterior. No agrega líneas al carrito. Repetir F2–F10 y F9; la consulta no altera stock ni pagos.

## 10. Productos y Categorías según rol

**Pedido:** vendedor consulta catálogo/stock y categorías, sin mantenerlos; recepción consulta categorías.

**Solución:** permisos separados en cada acción del controlador. Vendedor entra a listados y consulta EAN móvil, sin enlaces de mantenimiento/stock/auditoría. Las escrituras y formularios de gestión rechazan 403, incluidos POST manuales. Categorías se mantiene solo por dueño/admin. El menú y Alt+S muestran la consulta a los roles habilitados sin otorgar acceso a Compras, Recepción ni Reposición al vendedor. El listado de Productos agrega nombres de proveedores en una subconsulta, sin una consulta por fila.

| Archivo / método | Líneas finales | Qué hacen |
|---|---:|---|
| `app/controllers/ProductoController.php` — `actionIndex()`         | 15–40 | Consulta para vendedor/recepción; calcula mantenimiento con roles del servidor. |
| `app/controllers/CategoriaController.php` — `actionIndex()`         | 13–33 | Autoriza consulta y calcula permiso de mantenimiento sin usar datos del navegador. |
| `app/models/ProductoModel.php` — `listarProductos()`         | 15–40 | Obtiene categoría y proveedores con stock/precio/EAN. |
| `app/views/producto/index.php`         | 1–116 | Solo muestra acciones operativas a quien puede mantener productos. |
| `app/views/categoria/index.php`         | 1–65 | Oculta alta/edición/baja/reactivación y su columna para consulta. |
| `app/views/inc/navbar.php`         | 1–74 | Distingue consulta de catálogo de gestión operativa. |

**Cómo probar:** vendedor abre Productos/Categorías, busca nombre/EAN y consulta stock/proveedores. Forzar GET crear/editar y POST guardar/actualizar/baja/reactivar en ambos módulos: 403. Probar POST de ajuste, decomiso o asociación: 403. Recepción obtiene 403 en mantenimiento de Categorías.

## 11. Gestión operativa de Productos para Recepcionista

**Pedido:** recepción puede crear, editar, asociar proveedores, dar de baja y reactivar productos con responsable identificable.

**Solución:** esas acciones de Producto autorizan RECEPCIONISTA además de dueño/admin. Conservan validaciones de catálogo y proveedores y nunca aceptan stock desde editar. Las altas, ediciones, bajas, reactivaciones y asociaciones rápidas registran al usuario autenticado en la misma transacción. No se amplían permisos de Categorías, Usuarios ni Ventas.

| Archivo / método | Líneas finales | Qué hacen |
|---|---:|---|
| `app/controllers/ProductoController.php` — `actionCrear()`         | 43–48 | Valida permiso operativo y deriva al modelo/auditoría con usuario de sesión. |
| `app/controllers/ProductoController.php` — `actionGuardar()`         | 51–57 | Valida permiso operativo y deriva al modelo/auditoría con usuario de sesión. |
| `app/controllers/ProductoController.php` — `actionEditar()`         | 60–70 | Valida permiso operativo y deriva al modelo/auditoría con usuario de sesión. |
| `app/controllers/ProductoController.php` — `actionActualizar()`         | 73–80 | Valida permiso operativo y deriva al modelo/auditoría con usuario de sesión. |
| `app/controllers/ProductoController.php` — `actionBaja()`         | 83–92 | Valida permiso operativo y deriva al modelo/auditoría con usuario de sesión. |
| `app/controllers/ProductoController.php` — `actionReactivar()`         | 95–104 | Valida permiso operativo y deriva al modelo/auditoría con usuario de sesión. |
| `app/controllers/ProductoController.php` — `guardar()`         | 165–218 | Valida permiso operativo y deriva al modelo/auditoría con usuario de sesión. |
| `app/controllers/ProductoController.php` — `actionHistorial()`         | 107–113 | Valida permiso operativo y deriva al modelo/auditoría con usuario de sesión. |
| `app/models/ProductoModel.php` — `crearProducto()`         | 230–252 | Guarda catálogo/asociaciones y responsable de manera atómica. |
| `app/models/ProductoModel.php` — `actualizarProducto()`         | 255–278 | Guarda catálogo/asociaciones y responsable de manera atómica. |
| `app/models/ProductoModel.php` — `cambiarEstadoAuditado()`         | 305–320 | Guarda catálogo/asociaciones y responsable de manera atómica. |
| `app/models/ProductoModel.php` — `asociarProveedorExistente()`         | 80–126 | Guarda catálogo/asociaciones y responsable de manera atómica. |
| `app/controllers/CompraController.php` — `actionCrearProductoRapido()`         | 116–157 | El alta rápida de Compra (solo dueño/admin) conserva stock cero e informa el actor a ProductoModel. |
| `app/controllers/CompraController.php` — `actionAsociarProductoRapido()`         | 187–220 | La asociación de Compra (solo dueño/admin) conserva validación y actor autenticado. |

**Cómo probar:** como recepción crear con proveedores, editar datos, dar de baja y reactivar. El stock permanece igual. Consultar Auditoría: ALTA, EDICION, BAJA y REACTIVACION con el usuario responsable. Como recepción, administrar proveedores desde Editar Producto y revisar EDICION. La asociación rápida de Compra es exclusiva de dueño/admin. Si falla la auditoría, la operación completa debe revertirse.

## 12. Decomiso y trazabilidad de productos

**Pedido:** registrar pérdida de unidades con motivo obligatorio, sin confundirla con baja de catálogo; auditar mantenimiento de productos.

**Solución:** movimientos_stock ya dispone de tipo VARCHAR(30), cantidad, stock anterior/nuevo, usuario, fecha y observación. Se agrega DECOMISO reutilizando la transacción de ajuste: bloquea producto, valida activo/cantidad/stock, descuenta sin permitir negativos y guarda el movimiento. Solo recepción/dueño/admin. Formulario con token CSRF que se consume al confirmar. La baja del catálogo no registra decomiso ni altera unidades.

Se agrega únicamente `auditoria_operaciones` para acciones administrativas; FK al usuario, entidad/ID, acción, fecha, detalle e índice de consulta. La vista de auditoría muestra las últimas 100 operaciones; los decomisos se consultan en Movimientos. No se inventa autoría de cambios anteriores.

**Migración local APLICADA (24/11/2026):** `database/migracion_roles_auditoria.sql` se ejecutó correctamente una sola vez sobre `proyectofinaldesarrollo`, después de un backup completo nuevo. Solo creó `auditoria_operaciones`, inicialmente vacía; las 18 tablas anteriores conservaron conteos, datos y estructura idénticos. Una instalación nueva usa directamente `instalacion_profesor.sql`, que ya crea la tabla final; no requiere migraciones.

| Archivo / método | Líneas finales | Qué hacen |
|---|---:|---|
| `app/models/StockModel.php` — `registrarDecomiso()`         | 68–75 | Exige motivo y utiliza salida DECOMISO. |
| `app/models/StockModel.php` — `registrarAjuste()`         | 78–119 | Bloquea stock, evita negativos y guarda salida física y responsable en transacción. |
| `app/controllers/StockController.php` — `actionDecomiso()`         | 100–104 | Autoriza personal operativo y carga producto activo. |
| `app/controllers/StockController.php` — `actionGuardarDecomiso()`         | 107–134 | Valida POST/token, usa actor real, maneja errores y consume token exitoso. |
| `app/controllers/StockController.php` — `formularioDecomiso()`         | 137–142 | Genera token y carga errores para corregir. |
| `app/models/AuditoriaModel.php` — `registrarProducto()`         | 12–17 | Inserta responsable/acción/producto en la transacción del llamador. |
| `app/models/AuditoriaModel.php` — `listarProducto()`         | 28–33 | Consulta últimas operaciones con responsable. |
| `app/views/stock/decomiso.php`         | 1–23 | Formulario de unidades/motivo; el backend valida todo. |
| `app/views/producto/historial.php`         | 1–25 | Consulta de responsable, acción y fecha con acceso a movimientos. |
| `database/migracion_roles_auditoria.sql`         | 1–14 | Migración aditiva para la instalación local existente. |
| `tests/roles_operativos.py`         | 1–84 | Pruebas de matriz de permisos, privacidad, auditoría, rollback y decomiso. |
| `tests/ean_scanner_visual.py`         | 1–177 | Mantiene pruebas EAN existentes; amplía espera de Firefox y deja perfiles en /tmp. |
| `database/schema.sql`         | 417–427 | Esquema final de auditoría sin migración necesaria en instalación nueva. |
| `database/instalacion_profesor.sql`         | 431–441 | Esquema final de auditoría sin migración necesaria en instalación nueva. |

**Cómo probar:** ingresar 5 unidades en una base temporal y decomisar 2 con motivo: stock 3, producto activo, movimiento DECOMISO con actor/fecha/motivo. Motivo vacío, cantidad inválida, exceso de stock o token incorrecto no alteran datos. Reenviar el token consumido: 403. Vendedor no accede al formulario ni al POST. Verificar historial de catálogo y comprobar rollback completo si falla la inserción de auditoría.

### Validación de esta ampliación de roles

La prueba integral final aprobó **219 comprobaciones** en MariaDB temporal y Firefox, incluidos Alt+Q/Alt+O fuera y dentro del POS, privacidad de sesiones, permisos de catálogo, decomiso, auditoría, rollback, carrito, rectificación, badge, recepción, cierre y 52 comprobaciones internas del lector EAN. Las pruebas existentes de Compra aprobaron 32 casos PHP y 22 JavaScript; Reposición EAN aprobó 8 casos PHP. La sintaxis de 113 PHP propios y 8 JavaScript fue correcta. Un intento de Firefox agotó el arranque por presión de memoria; se limpiaron solo bases temporales detenidas y se agregó su limpieza automática, sin cambiar lógica de aplicación.

En la etapa original de ampliación no se modificó la base local. La migración de auditoría se aplicó posteriormente el 24/11/2026, según el registro de cierre al final de este documento. La entrega nueva instala directamente la tabla final.

La revisión visual EAN adicional aprobó 18 vistas (seis módulos × tres anchos móviles), 18 comprobaciones de integración de módulos y 52 del lector, incluida decodificación ZXing real. Las 105 referencias finales del registro fueron verificadas con `nl -ba`.

La entrega final de esta etapa aprobó 27 comprobaciones aisladas de instalación/login/páginas/recursos/sintaxis e igualdad con el original. ZIP reemplazado: `/home/ivangl/proyectofinalprueba.zip`, 404801 bytes, 201 archivos, carpeta interna `proyectofinaldesarrollo`. No incluye pruebas, documentación interna ni datos personales. No se hizo commit ni push.

## 13. Ajustes finales de estabilidad y presentación

**Pedido:** conservar permisos y operaciones, dar protagonismo al carrito, reparar Supr y estabilizar estados/tablas/navegación para todos los roles y resoluciones.

**POS — diagnóstico y solución:** la medición inicial a 1366/1280/1024 no reprodujo una superposición con 25 filas; el resumen ocupaba unos 60–62 px y en móvil era sticky. Se compactó y se reservaron tres filas de grid: cabecera, carrito con `minmax(0,1fr)` y resumen. El carrito conserva scroll interno y el resumen deja de ser flotante en móvil. En la medición de 25 filas, el resumen pasó de 60–62 px a 47,4 px; el área visible del carrito ganó unos 9–10 px, además de la cabecera de tabla más compacta. No se altera el cobro ni el backend.

**Supr — causa y solución:** seleccionar una celda no movía el foco desde EAN; la protección de inputs rechazaba Delete. La fila ahora puede recibir foco y lo toma al seleccionar una celda, pero no al editar cantidad. Se guarda también la selección al navegar. Quitar el seleccionado elige el siguiente o el anterior y mantiene foco en el carrito cuando se usa Supr. Se ignoran filas ya eliminadas, selecciones obsoletas, teclas repetidas, campos editables y modales de pagos/consulta. Se conserva la protección al escribir y los atajos existentes.

**Estados y tablas:** Productos, Categorías y Proveedores usan `text-nowrap` en ACTIVO/INACTIVO. La regla compartida mantiene badges en una línea mientras sus contenedores pueden envolver. Productos aprovecha un contenedor ancho, con mínimos legibles para nombre/EAN, precio sin cortes y acciones agrupadas. Los mensajes vacíos ya no sugieren crear a quien solo consulta. La revisión detectó scroll horizontal en Consulta rápida con un nombre largo (858 px de contenido en 372 px): la regla compartida de modal permite envolver texto. No se amplían permisos, no se agregan acciones a consultas de solo lectura ni se rediseñan pantallas.

**Migración local:** durante la estabilización visual la tabla todavía estaba ausente. El 24/11/2026 se aplicó la migración pendiente con backup completo y sin escrituras comerciales, según el registro de cierre. El esquema final de instalación ya incluía la tabla y no necesitó modificaciones.

| Archivo / método o regla | Líneas finales | Qué hacen |
|---|---:|---|
| `public/js/pos.js` — `seleccionarLinea()` | 288–298 | Valida y persiste selección; marca la fila activa. |
| `public/js/pos.js` — `quitarLinea()` | 300–315 | Elimina solo el ID elegido, calcula vecino y devuelve foco adecuado. |
| `public/js/pos.js` — `dibujarCarrito()` | 317–379 | Descarta selección obsoleta, permite foco por fila y guarda carrito/selección. |
| `public/js/pos.js` | 597–645 | Atajos existentes y Delete protegido ante edición, repetición o modales. |
| `public/css/pos.css` | 1–128 | Grid de carrito/resumen, foco visible, tamaños compactos y responsive sin sticky. |
| `app/views/inc/head.php` | 58–65 | Reglas compartidas de badges, tablas anchas, columnas legibles y columna de acciones compacta. |
| `app/views/inc/head.php` | 82–83 | Textos largos de modales envuelven sin scroll horizontal. |
| `app/views/producto/index.php` | 78–78 | ACTIVO/INACTIVO mantienen una línea sin cambiar permisos ni acciones. |
| `app/views/categoria/index.php` | 40–40 | ACTIVO/INACTIVO mantienen una línea sin cambiar permisos ni acciones. |
| `app/views/proveedor/index.php` | 44–44 | ACTIVO/INACTIVO mantienen una línea sin cambiar permisos ni acciones. |
| `tests/estabilizacion_visual.py` | 1–202 | Recorrido por rol/pantalla/resolución; POS vacío/1/5/15/25, Supr, persistencia y cobro combinado. |
| `app/views/producto/index.php` | 10–10 | Contenedor ancho del catálogo para aprovechar escritorios grandes. |
| `app/views/producto/index.php` | 68–99 | Nombre, EAN y precio legibles con acciones agrupadas y permisos intactos. |
| `tests/correcciones_profesor.py` | 1–413 | Ejecuta regresión de permisos, venta, caja, EAN y revisión global en base temporal. |

**Cómo probar manualmente:**

1. Abrir POS con sesión propia en 1920×1080, 1600×900, 1440×900, 1366×768, 1280×720 y 1024×768. Revisar 0, 1, 5, 15 y 25 productos: resumen compacto, varias filas visibles, scroll solo dentro del carrito en escritorio.
2. Hacer clic en el nombre de una fila y pulsar Supr: primera/intermedia/última/única. Verificar unidades, total y vecino seleccionado. Repetir después de navegar y volver. Sin selección no elimina; al editar EAN/nombre/cantidad/selector/textarea/pagos, tampoco.
3. Probar EAN repetido, búsqueda por nombre, F2–F10, Alt+Q/Alt+O, Esc, volver de Cobro a Armado, efectivo, vuelto y combinado. Confirmar solo en base de prueba; el borrador se borra y el ticket refleja total/pagos.
4. Ingresar por separado con administrador, dueño, recepción y vendedor. Revisar pantallas permitidas en los seis escritorios y a 360/390/412 px. Tablas desplazan internamente cuando hace falta; consulta de solo lectura no muestra mantenimiento ni columnas de acciones vacías. Cámara visible solo por debajo de 768 px.
5. Revisar menús móviles desplegados y consulta rápida con nombres largos; mantener los permisos documentados en los puntos anteriores.

Las capturas y el informe de medidas se generan fuera del repositorio en la carpeta temporal de la prueba (`revision_visual/`, `estabilizacion_visual.json`); no se incluyen en la entrega. La prueba permite `SOLO_REVISION_VISUAL=1` para repetir el recorrido global sin repetir todos los atajos. Los datos y las ventas de revisión pertenecen únicamente a MariaDB temporal.

### Resultado final de estabilización (24/11/2026)

- Administrador: 38 pantallas × 9 resoluciones aprobadas en el recorrido integral; después se revalidó Productos específicamente en los 9 tamaños con el agrupado final de acciones.
- Dueño: 35 pantallas × 9; recepción: 17 × 9; vendedor: 12 × 9. Incluye menús móviles desplegados y consulta con texto largo. En total, 918 combinaciones del recorrido por roles, más la revisión dirigida de catálogo.
- POS: 30 estados de layout (0/1/5/15/25 filas × seis escritorios). Se verificaron Supr y selección/persistencia, protección de campos/pagos, EAN repetido, búsqueda por nombre, efectivo/vuelto, combinado, venta confirmada y ticket sobre una caja temporal separada.
- Regresión final: 219 comprobaciones OK, incluidos F2–F10, Alt+Q/Alt+O, Esc, EAN (52 internas), permisos, principal, auditoría, compra/recepción, rectificación y cierre. Pruebas existentes: 32 PHP de Compra, 8 PHP EAN/Reposición y 22 JavaScript de Compra. Sintaxis de 86 PHP propios (app/public/tests; excluye vendor y referencias externas) y 8 JavaScript correcta; diff sin errores de espacios.
- Los tiempos de espera de Firefox se trataron como problemas del entorno de pruebas: datos/perfiles en disco cuando /tmp usa RAM, recorridos por resolución y lotes por rol. La prueba F9/Esc/F10 espera los eventos/foco reales del modal en lugar de asumir 400 ms. No se cambió la lógica de esos atajos por una animación lenta.
- Se revisaron capturas de catálogo completo y de solo lectura, categorías, proveedores, sesiones operativas, decomiso móvil, modal largo y POS con 25 filas. No se rediseñaron pantallas que ya eran utilizables.
- Las 119 referencias de código se recalcularon con `nl -ba`. Los informes/capturas están fuera del repositorio. La consulta de auditoría local de ese bloque devolvió tabla ausente; ese estado quedó resuelto por la migración registrada al final. No se cambió el esquema por el bloque visual.

**Entrega sincronizada:** candidato generado desde el original sin commit, aplicación/SQL/dependencias idénticos, instalación desde cero y login de los cuatro roles aprobados. Validación final en lotes: 70 comprobaciones de instalación/roles/recursos/sintaxis/igualdad, más 24 vistas ZIP a 1366 y 390 px. Se revisaron capturas del POS de escritorio y Categorías de recepción en móvil. ZIP reemplazado: `/home/ivangl/proyectofinalprueba.zip`, 405713 bytes y 201 archivos. CRC correcto y exclusiones conservadas. Sin commit/push.


### Aplicación controlada de auditoría en la base local (24/11/2026)

- Repositorio original en `main`, HEAD `b5469999da0fb068ce1886faacedf1201a9d60ce`, sin commit ni push; todos los cambios pendientes conservados, incluido el CSS EAN desde 768 px.
- Base: `proyectofinaldesarrollo`, conexión local, puerto 3306 y socket `/opt/lampp/var/mysql/mysql.sock`, usuario SQL `root@localhost`. No se cambiaron otras bases.
- Backup completo previo: `/home/ivangl/backup_proyectofinal_antes_auditoria_20261124_180519.sql`, 40303 bytes, permisos 0600, tablas/datos/triggers/rutinas/eventos; fin de dump verificado. SHA-256: `0f85017d99bd2235565587dd3c55ab6d5a972fb626e18c782ef259821aa6a7dd`. No se sobrescribieron backups.
- Se ejecutó exclusivamente el SQL de `database/migracion_roles_auditoria.sql`, sin errores ni SQL alternativo. Nueva tabla InnoDB, charset `utf8mb4`, collation `utf8mb4_unicode_ci`; columnas `id_auditoria`, `id_usuario`, `accion`, `entidad`, `id_entidad`, `fecha`, `detalle`. PK en `id_auditoria`; índice compuesto `idx_auditoria_entidad`; índice/FK `fk_auditoria_usuario` hacia `usuarios(id_usuario)`. Tabla inicialmente vacía.
- Comparación final después de navegar: las 18 tablas previas mantienen conteos, SHA-256 de todas sus filas y SHA-256 de sus estructuras. Stock, contraseñas y operaciones existentes intactos. No se hicieron ventas, decomisos, bajas ni escrituras de catálogo reales.

| Tabla | Antes | Después |
|---|---:|---:|
| `caja_sesiones` | 5 | 5 |
| `cajas` | 2 | 2 |
| `categorias` | 3 | 3 |
| `compras` | 5 | 5 |
| `detalle_compras` | 5 | 5 |
| `detalle_recepciones` | 3 | 3 |
| `detalle_ventas` | 9 | 9 |
| `medios_pago` | 5 | 5 |
| `movimientos_stock` | 20 | 20 |
| `pagos_ventas` | 8 | 8 |
| `productos` | 5 | 5 |
| `productos_proveedores` | 6 | 6 |
| `proveedores` | 4 | 4 |
| `recepciones` | 3 | 3 |
| `roles` | 4 | 4 |
| `usuarios` | 9 | 9 |
| `usuarios_roles` | 10 | 10 |
| `ventas` | 5 | 5 |
| `auditoria_operaciones` | No existía | 0 |

**Comprobaciones de cierre:** 469 comprobaciones HTTP/recursos/permisos/ZIP correctas. Login real `admin@admin.com / admin1234` correcto. Dueño (ID 2), recepción (ID 5) y vendedor (ID 6) se probaron con sesiones temporales privadas construidas con sus roles reales activos, sin conocer ni modificar sus contraseñas. No se afirma haber probado el login por contraseña de esos tres usuarios.

Se abrieron las pantallas permitidas de catálogo/categorías, dashboard y usuarios administrativos, compras, recepción/directa, reposición, decomiso, historial/movimientos, cajas/sesiones, POS y ventas. Se recorrieron detalle/ticket/rectificación de ventas existentes; los rechazos por venta ajena o estado no rectificable se consideran respuestas de protección, no fallos. Vendedor conserva catálogo de consulta y sesiones sin datos administrativos ajenos; recepción conserva catálogo operativo y Categorías de consulta. Proveedores conserva su permiso actual: no se amplió acceso a recepción. La consulta rápida de un EAN inexistente devuelve su 404 normal. No se repitió la auditoría visual ni se alteró el POS.

Sintaxis: 86 PHP propios correctos; Compra 32 comprobaciones PHP y EAN/Reposición 8 correctas; `git diff --check` correcto. No hay Node instalado en este entorno: la ejecución directa de pruebas JavaScript/sintaxis JS no pudo repetirse (comando ausente); no hubo cambios JavaScript. Se conserva la regresión JavaScript/Firefox aprobada en la estabilización anterior, sin presentarla como ejecutada nuevamente. Las operaciones auditadas y su rollback mantienen las pruebas aisladas anteriores; no se ejecutaron sobre la base personal.

**Entrega:** ZIP existente íntegro (CRC), 201 archivos, 405713 bytes, SHA-256 `a6d40baa8f0f0d80eb4476a60308c76cefa873c50bd3125705a09ab7cc710285`. Los 199 archivos entregables distintos de las dos configuraciones demo son idénticos al original; no falta ningún archivo de app/public. Las configuraciones demo seguras son la diferencia intencional. La tabla de auditoría del instalador es idéntica a la migración; el instalador incluye rectificación y protección del principal. El hash demo extraído del ZIP pasó `password_verify('admin1234', hash)`. `tipo_movimiento VARCHAR(30)` ya permite DECOMISO sin cambiar su esquema. No hay migraciones, tests ni docs personales en el ZIP. No fue necesario regenerarlo ni reemplazarlo: solo se migró la PC y se actualizó este documento interno. La instalación aislada/login de cuatro roles y vistas del ZIP conservan la validación previa registrada arriba; esta vez se verificaron integridad/igualdad, no se repitió esa instalación.

En la etapa de aplicación de la migración no se modificó código fuente ni SQL entregable y no fue necesario recalcular líneas. La corrección posterior del panel de resultados se registra a continuación.


### Corrección manual final: resultados de búsqueda y espacio del carrito (24/11/2026)

**Pedido:** en notebook 1366×768 mostrar dos productos completos, varias filas con cinco productos y scroll interno con 15/25. Cerrar los resultados después de agregar y recuperar el espacio para el carrito, sin rediseñar el POS.

**Causa real:** `.card` de Bootstrap declara `display: flex`, que prevalece sobre la regla de presentación predeterminada de `[hidden]`. Por eso la tarjeta de Resultados seguía dibujándose/ocupando altura incluso al iniciar el POS o pulsar Esc. Además, `agregar()` no cerraba los resultados después de agregar desde la búsqueda. El ajuste anterior del resumen no resolvía esas dos causas.

**Solución:** una regla específica respeta `hidden` para esa tarjeta; solo cuando hay resultados activos ocupa espacio. Después de agregar correctamente, se oculta/vacía el listado, se limpia Nombre y se vuelve a EAN. El contador de búsqueda invalida respuestas anteriores pendientes para que no reabran un listado viejo. Si agregar falla, el listado no se descarta. F3 continúa enfocando Nombre y una nueva consulta vuelve a mostrar resultados. Se conserva el flex/grid existente: el carrito recupera el espacio liberado sin imponerle una altura fija nueva. No se cambian cobro, precios, stock, permisos, backend ni SQL.

| Archivo / función o regla | Líneas finales | Qué hacen |
|---|---:|---|
| `public/css/pos.css` — `#resultadosBusqueda[hidden]` | 46–47 | Retira efectivamente del layout la tarjeta oculta, por encima del display de Bootstrap. |
| `public/js/pos.js` — `agregar()` | 393–401 | Tras agregar/redibujar, invalida búsquedas viejas, oculta/vacía resultados, limpia Nombre y enfoca EAN. |
| `tests/pos_resultados_vista.php` | 1–31 | Dibuja la vista real con identidad/caja/medios ficticios, sin cargar .env ni base real. |
| `tests/pos_resultados_visual.py` | 1–93 | Firefox: 36 estados de layout, búsqueda/agregado repetido, dos filas completas, borrador, Supr y sintaxis JS; permite verificar la copia limpia. |

**Resultado medido en 1366×768:**

| Productos | Filas completas visibles | Scroll del carrito | Total/Cobrar |
|---:|---:|---|---|
| 0 | Carrito vacío | No | Visibles |
| 1 | 1 | No | Visibles |
| 2 | 2 | No | Visibles |
| 5 | 5 | No | Visibles |
| 15 | 6 | Interno | Visibles |
| 25 | 6 | Interno | Visibles |

Área visible del carrito con productos: 364 px, resultado de la distribución disponible, no una altura CSS fija. En 1920×1080, 1600×900, 1440×900 y 1024×768 entran los cinco productos de prueba completos; en 1280×720 entran cuatro y el desplazamiento queda dentro del carrito. En los seis tamaños se ven dos filas completas con dos productos, el resumen no se superpone y no hay scroll general. Se revisaron capturas reales de Firefox con dos/cinco/25 filas, resultados abiertos y después de agregar dos productos distintos. No se volvió a hacer una auditoría visual global porque no cambió el resto de las pantallas.

**Pruebas:** 121 comprobaciones dirigidas sobre el original y las mismas 121 sobre la copia limpia. Se buscan/agregan dos productos distintos, comprobando cierre/limpieza/foco, dos filas visibles, persistencia al volver y Supr sobre la selección restaurada. Los ocho JavaScript propios compilan correctamente en Firefox (no depende de Node). Sintaxis de 87 PHP propios correcta; 32 comprobaciones PHP de Compra y 8 de EAN/Reposición correctas; `git diff --check` correcto. La validación aislada de la copia aprobó 71 comprobaciones de instalación desde cero, auditoría vacía, principal demo, login de cuatro roles, páginas, permisos y recursos. Las cuentas adicionales de roles existen solo en la base descartable de la prueba; no se incluyen en el SQL/ZIP. No se tocó la base real.

**Cómo probar:** abrir POS en notebook 1366×768 y agregar dos productos distintos: ambas filas completas. Probar cinco, luego 15/25: scroll solo dentro del carrito; Total/Cobrar visibles. Pulsar F3, buscar por nombre y Agregar: desaparece la tarjeta, Nombre queda vacío y el foco vuelve a EAN. Repetir con otro producto. Navegar y regresar: conserva carrito; seleccionar una fila y Supr elimina solo esa línea. Repetir en los seis tamaños indicados.

Las referencias afectadas de `pos.js` (+5 líneas) y `pos.css` (+2 líneas) se recalcularon a partir de `nl -ba`; no se modificó el esquema ni `instalacion_profesor.sql`. Capturas/métricas fuera del repositorio: `/tmp/pos-resultados-final` y `/tmp/pos-resultados-entrega`.

**Entrega actualizada y validada:** 201 archivos, 405821 bytes, carpeta interna `proyectofinaldesarrollo`; CRC correcto y código/SQL/dependencias idénticos al original, con configuración demo segura. SHA-256: `295683a7d0da8bd63ab27182347dd20454677cb3c8a29685c424633227e74460`. Reemplazado atómicamente `/home/ivangl/proyectofinalprueba.zip` después de las validaciones; hash final verificado. Sin commit ni push.


### Productos: acciones compactas por rol (24/11/2026)

**Pedido:** reemplazar los botones de mantenimiento de cada fila por un único `Acciones ▾`, sin agregar acciones ni cambiar permisos, y evitar recortes dentro de `table-responsive`. El POS queda expresamente fuera de este ajuste.

**Solución:** dropdown Bootstrap existente, con botón `btn-sm` y menú alineado al extremo. Se conserva la condición `$puedeGestionar` del controlador sin modificarla: administrador/dueño/recepción mantienen el mismo conjunto actual; vendedor no recibe ni botón ni cabecera/celda de acciones ni carga el inicializador del menú. No se agregan Proveedores/Asociaciones como acciones nuevas: la gestión existente continúa desde Editar.

- Producto activo: Editar, Auditoría, Movimientos, Ajustar stock, Registrar decomiso y Dar de baja.
- Producto inactivo: Editar, Auditoría, Movimientos y Reactivar.
- Dar de baja conserva su formulario POST, nombre del producto y modal reutilizable; el botón interno usa `text-danger`. Cancelar no envía la operación. Reactivar conserva el POST anterior.

**Tabla responsive:** Popper de Bootstrap usa `strategy: fixed`, límites `viewport`, `preventOverflow` y `flip` para mantener el menú en la ventana y abrir hacia arriba cuando corresponde. No se implementa un menú propio, no se cambia el overflow de las tablas ni se agregan z-index arbitrarios. La regla específica de columna reemplaza el mínimo anterior de 18 rem por ancho de contenido (`width: 1%` y `nowrap`); se midieron aproximadamente 114–118 px. Badges, EAN, precio y stock conservan su presentación.

| Archivo / función o regla | Líneas finales | Qué hacen |
|---|---:|---|
| `app/views/producto/index.php` | 62–62 | Omite también la cabecera Acciones para quien no gestiona. |
| `app/views/producto/index.php` | 79–99 | Agrupa exactamente las acciones existentes, según permiso y estado del producto. |
| `app/views/producto/index.php` | 90–95 | Conserva formularios de baja/reactivación; baja en rojo y con confirmación existente. |
| `app/views/producto/index.php` | 111–114 | Carga el inicializador solo para mantenimiento, con versión por contenido. |
| `app/views/inc/head.php` | 65–65 | Columna compacta exclusiva de Productos, sin cambiar estilos del POS. |
| `public/js/producto-acciones.js` | 1–22 | Inicializa Bootstrap y configura Popper respecto de la ventana para evitar recortes. |
| `tests/producto_acciones_vista.php` | 1–40 | Renderiza la vista original con roles/productos ficticios, sin .env ni base real. |
| `tests/producto_acciones_visual.py` | 1–91 | Comprueba cuatro roles, cinco tamaños, tres posiciones de fila, hit-testing, confirmación y capturas. |

**Pruebas dirigidas:** 251 comprobaciones en el original y las mismas 251 en la copia limpia. Administrador, dueño, recepción y vendedor a 1920×1080, 1366×768, 1280×720, 1024×768 y 390×844. Para cada rol de mantenimiento se abrieron primera/intermedia/última fila, con acciones exactas para activo/inactivo. Rectángulos dentro del viewport y hit-testing de cada opción confirmaron que los menús completos son accesibles y no quedan detrás de la tabla. Vendedor conserva diez columnas sin Acciones. Se comprobó apertura del modal de baja, nombre correcto y cancelación sin baja; no se confirmó ninguna operación real. Sintaxis del JavaScript nuevo comprobada en Firefox.

La instalación candidata desde cero aprobó 87 comprobaciones en MariaDB temporal: login de cuatro roles, catálogo, dropdown según rol, Editar para administrador/dueño/recepción, rechazo de creación por URL y de los cuatro POST de mantenimiento del vendedor, categorías de consulta para recepción, páginas/recursos y configuración demo principal. Los usuarios/productos artificiales solo viven en esa base temporal y no se incorporan al ZIP. Sintaxis de 88 PHP propios correcta; Compra 32 y EAN/Reposición 8 comprobaciones PHP correctas; `git diff --check` correcto. Controladores, modelos, permisos backend y SQL no se modificaron. La base real no recibió escrituras.

**Capturas revisadas:** admin 1366 cerrado/abierto, recepción con menú, vendedor sin acciones y recepción móvil 390 con menú completo. Se conservan fuera del repositorio en `/tmp/producto-acciones-original` y `/tmp/producto-acciones-entrega`; las capturas de la copia usan scroll instantáneo para no fotografiar una animación intermedia.

**Cómo probar:** abrir Productos con cada rol, desplegar Acciones en primera/intermedia/última fila en los cinco tamaños. Comprobar opciones según producto activo/inactivo, pulsar Editar y regresar. Dar de baja debe abrir la pregunta con el nombre correcto; Cancelar conserva el registro. En móvil desplazar internamente la tabla hasta Acciones y comprobar que todo el menú queda visible. Vendedor no debe tener botón ni columna. Probar manualmente las URLs protegidas del vendedor sin ampliar sus permisos.

Se recalcularon con `nl -ba` únicamente las referencias afectadas de Productos y se comprobaron los rangos nuevos. La regla de `head.php` conserva su número de línea. Los hashes de `public/js/pos.js`, `public/css/pos.css` y `app/views/pos/index.php` coinciden con la entrega anterior aprobada: el POS no se modificó ni se rediseñó.

**Entrega actualizada y validada:** 202 archivos, 406644 bytes, carpeta interna `proyectofinaldesarrollo`, configuración demo segura. CRC correcto, código/SQL/dependencias idénticos al original; incluye el nuevo inicializador. SHA-256: `e93862f559bd2a22f9dfe1842418219bb8f40c98203dea0cf10ea2cdeafcdcfd`. Sin migración necesaria ni modificación del instalador. Reemplazado atómicamente `/home/ivangl/proyectofinalprueba.zip` después de validar, con hash final confirmado. Sin commit ni push.


### Matriz final: Recepcionista no compra; Recepcionista recepciona (24/11/2026)

**Problema y aclaración definitiva:** el estado anterior autorizaba RECEPCIONISTA en las ocho acciones públicas de CompraController. La matriz solicitada finalmente separa responsabilidades: ADMINISTRADOR/DUEÑO registran compras; RECEPCIONISTA solo controla esos pendientes desde Recepción. La investigación anterior de acceso a Compras queda reemplazada por esta regla, no pendiente de más datos.

| Módulo | ADMINISTRADOR | DUEÑO | RECEPCIONISTA | VENDEDOR |
|---|---|---|---|---|
| Compras | Sí | Sí | **No** | No |
| Recepción / Directa | Sí | Sí | **Sí** | No |
| Productos | Gestión | Gestión | Gestión operativa | Consulta |
| Categorías | Gestión | Gestión | Consulta | Consulta |
| Ventas | Todas | Todas | No | Propias |
| Sesiones de caja | Completas | Completas | No | Operativa propia |
| POS | Sí | Sí | No | Sí |
| Reposición | Sí | Sí | Sí | No |
| Stock | Productos / operaciones válidas | Productos / operaciones válidas | Productos / operaciones válidas | Consulta en Productos |
| Decomiso | Sí | Sí | Sí | No |
| Usuarios | Sí | No | No | No |

**Solución aplicada:** `onlyUsers(['DUEÑO'])` en listado, crear, guardar, detalle y las cuatro acciones rápidas de Compra. La regla existente de SessionController mantiene acceso de ADMINISTRADOR. Se eliminó RECEPCIONISTA de todas esas autorizaciones; VENDEDOR continúa rechazado. La navbar usa `$puedeAdministrarMaestros` exclusivamente para Compras; Recepción/Reposición conservan `$puedeGestionarOperaciones`. No cambia la herencia global de roles ni permisos de otros módulos.

**Dependencia estrictamente necesaria:** Recepción Directa compartía los endpoints de alta de proveedor/producto y consulta EAN de Compra. Para conservarla, se habilitan sus tres entradas en RecepcionController, que reutilizan internamente el código validado anterior: mismos modelos, token, POST, validaciones, auditoría y stock inicial cero. El helper de reutilización no tiene prefijo `action`, no es una ruta y exige rol operativo; su lista cerrada solo contiene proveedor/producto/consulta, nunca guardar compras ni asociar existentes. Acceder a `/compra/altaRapidaParaRecepcion` es 404; las acciones reales de Compra son 403 para recepción. No se agrega asociación rápida desde Recepción. Los formularios y la consulta EAN compartidos usan el módulo actual. El detalle de Recepción conserva el número de compra como texto para recepción, sin ofrecer un enlace al módulo prohibido; dueño/admin mantienen ese enlace.

**Acciones de Compra que antes admitían recepción y ahora son solo dueño/admin:** `index`, `crear`, `guardar`, `ver`, `crearProveedorRapido`, `crearProductoRapido`, `consultarProductoRapido`, `asociarProductoRapido`. El cambio afecta autorización del módulo, no sus reglas de negocio.

| Archivo / método o regla | Líneas finales | Qué hacen |
|---|---:|---|
| `app/controllers/CompraController.php` — `actionIndex()` | 16–19 | Restringe listado/filtros a dueño y administrador. |
| `app/controllers/CompraController.php` — `actionCrear()` | 32–35 | Restringe Nueva compra. |
| `app/controllers/CompraController.php` — `actionGuardar()` | 40–45 | Restringe guardado; mantiene POST y CSRF. |
| `app/controllers/CompraController.php` — `actionCrearProveedorRapido()` | 85–96 | Protege el endpoint y reutiliza el alta validada. |
| `app/controllers/CompraController.php` — `actionCrearProductoRapido()` | 116–127 | Protege el endpoint y mantiene alta con stock cero/auditoría. |
| `app/controllers/CompraController.php` — `actionConsultarProductoRapido()` | 160–171 | Protege la consulta del módulo Compra. |
| `app/controllers/CompraController.php` — `actionAsociarProductoRapido()` | 187–192 | Excluye recepción de asociaciones rápidas propias de Compra. |
| `app/controllers/CompraController.php` — `actionVer()` | 280–285 | Impide abrir detalle del módulo Compra por ID manual. |
| `app/controllers/CompraController.php` — `altaRapidaParaRecepcion()` | 209–220 | Reutilización interna de tres operaciones sin abrir URLs de Compra. |
| `app/controllers/RecepcionController.php` | 71–87 | Conserva altas/consulta de Directa con autorización operativa del helper. |
| `app/views/inc/navbar.php` | 52–54 | Compras solo para dueño/admin; no deja huecos para otros roles. |
| `app/views/compra/altas_rapidas.php` | 1–8 | Envía proveedor al módulo que abrió el formulario. |
| `app/views/compra/altas_rapidas.php` | 23–26 | Producto también se crea en el módulo actual. |
| `public/js/compra.js` | 296–331 | La búsqueda escaneada consulta el módulo actual sin modificar el reconocimiento EAN. |
| `app/views/recepcion/ver.php` | 15–17 | Origen visible sin enlace a Compras para recepción. |
| `tests/compra_recepcionista.py` | 1–164 | Regresión de matriz, compra pendiente, aceptación/rechazo, Directa y recursos. |
| `tests/correcciones_profesor.py` | 201–206 | Dueño registra y recepción recibe en la regresión existente. |
| `tests/roles_operativos.py` | 74–75 | Recepción obtiene 403 en Compras y conserva Recepción/Reposición. |
| `tests/estabilizacion_visual.py` | 70–71 | El recorrido de recepción excluye las pantallas Compra. |

**Prueba funcional exacta en MariaDB temporal:** login real de cada rol de prueba. Dueño crea proveedor/producto, consulta/asocia el EAN y registra la compra de dos unidades. Queda PENDIENTE: stock 10 y cero movimientos. Recepción ve ese pendiente en su pantalla, acepta y queda stock 12 con un movimiento RECEPCION; compra RECEPCIONADA y recepción ACEPTADA. Dueño crea otra compra: stock 12 y un movimiento. Recepción la rechaza: stock 12 y un movimiento, compra/recepción RECHAZADA. No se mezclan ambos pasos.

Recepcionista obtiene 403 en GET `/compra`, `/compra/index`, `/compra/crear`, `/compra/ver/ID`, POST guardar y los cuatro endpoints rápidos. Vendedor obtiene los mismos rechazos y además 403 en Recepción, Directa y sus escrituras. Dueño/admin mantienen listado, formulario y detalle de Compra; recepción mantiene listado de pendientes, control, aceptación/rechazo y Directa. La prueba de Directa conserva proveedor/producto/consulta, exige token, mantiene stock cero hasta aceptar y no expone asociación.

**Pruebas del original:** 173 comprobaciones específicas (incluidas vistas de Recepción/Directa a 1366×768 y 390×844) y 219 de regresión existente, sin repetir el recorrido visual gigante. Compra PHP: 32; EAN/Reposición PHP: 8. Sintaxis de 88 PHP correcta; `git diff --check` correcto. La regresión cubrió caja/cierre, ventas, rectificación, carrito, atajos, auditoría/decomiso y lector EAN. No se modificaron sus archivos funcionales, salvo la dependencia de enrutado compartido de Directa descrita arriba.

**Cómo probar manualmente:** entrar como dueño/admin, crear una compra y comprobar PENDIENTE y stock intacto. Entrar como recepción: Compras no figura; forzar sus GET/POST debe ser rechazado. Desde Recepción abrir el pendiente y aceptar o rechazar; solo aceptar mueve stock. Probar Directa y sus altas; después comprobar que vendedor no accede a ninguno de los dos módulos. No ejecutar operaciones artificiales en la base real durante pruebas automáticas.

**Documentación coherente:** se reemplazó el apartado de investigación anterior que autorizaba Compras a recepción, se aclaró la matriz al inicio y se corrigió la prueba de asociaciones del punto 11. LEEME_PROFESOR también describe la separación final. Referencias afectadas recalculadas con `nl -ba`; no se cambió esquema, migración ni `database/instalacion_profesor.sql`. La base local real no recibió conexiones de escritura ni operaciones comerciales en esta etapa. Sin commit ni push.

**Validación de la entrega final:** 193 comprobaciones correctas desde una extracción del ZIP candidato: instalación final desde cero, login demo/admin y de los otros tres roles de prueba, Compra solo dueño/admin, pendientes/aceptación/rechazo para recepción, rechazo de vendedor, Directa, recursos JS/CSS, detalle de Recepción sin enlace prohibido y 22 comprobaciones JavaScript internas de Compra/Directa. Se compiló el script compartido en Firefox. Capturas en `/tmp/compra-recepcionista-tzhah5gj`. Candidato íntegro: 407449 bytes, 202 archivos, carpeta interna `proyectofinaldesarrollo`, SHA-256 `04709975ac72f6dcd584d7c406ba5c416c00e7a6952e620b697ba7b058136050`. Código funcional y SQL idénticos al original; solo las configuraciones demo son distintas intencionalmente. Tests/documentación interna/migraciones/backups/credenciales personales excluidos. Validación aprobada; reemplazado atómicamente `/home/ivangl/proyectofinalprueba.zip`, con hash final verificado. Sin commit ni push.


### Badge de recepciones pendientes (24/11/2026)

**Pedido:** mostrar junto a Recepción cuántas compras todavía deben controlarse, con el mismo estilo y actualización por navegación del badge de Reposición.

**Patrón reutilizado:** `ReposicionModel::contarParaReposicion()` ejecuta COUNT, navbar lo consulta solo para roles operativos y muestra `badge rounded-pill text-bg-danger` únicamente cuando es mayor que cero. Recepción usa exactamente esas clases y el mismo criterio; no se agregan CSS, JavaScript, polling, WebSockets ni otra arquitectura. Los dos contadores permanecen independientes.

**Qué cuenta:** nuevo `RecepcionModel::contarPendientes()`, con `SELECT COUNT(*) AS total FROM compras c`, los mismos INNER JOIN a proveedores/usuarios que `comprasPendientes()` y exactamente `WHERE c.estado = 1 AND c.estado_recepcion = 'PENDIENTE'`. No modifica el listado existente. Excluye compras inactivas, recepcionadas/rechazadas y recepciones directas, ya que estas últimas no son compras pendientes. Retorna un entero y no carga todos los detalles. Se recalcula en cada carga del menú.

**Roles:** administrador/dueño/recepción ven Recepción y su badge cuando hay pendientes. Vendedor no recibe enlace ni contador; la llamada al modelo también queda dentro del permiso operativo. Se mantiene Compras solo para dueño/admin y Recepción para recepción. No se cambian permisos backend.

| Archivo / método o regla | Líneas finales | Qué hacen |
|---|---:|---|
| `app/models/RecepcionModel.php` — `comprasPendientes()` | 14–21 | Listado existente usado como referencia funcional. |
| `app/models/RecepcionModel.php` — `contarPendientes()` | 23–31 | COUNT con los mismos JOIN/filtros del listado; devuelve entero. |
| `app/views/inc/navbar.php` | 14–15 | Cuenta solo para usuarios con acceso a Recepción. |
| `app/views/inc/navbar.php` | 55–57 | Badge Bootstrap junto a Recepción, etiqueta accesible y oculto cuando es cero. |
| `app/views/inc/navbar.php` | 58–60 | Reposición conserva consulta y badge propios. |
| `tests/badge_recepcion.py` | 1–99 | Recorrido real del contador, SQL/listado/roles, independencia y 20 vistas de navbar. |
| `tests/compra_recepcionista.py` | 1–164 | Integra prueba opcional del badge en la instancia temporal y revalida cero tras Directa. |
| `tests/ean_scanner_vistas.php` | 8–12 | Carga el modelo con el COUNT ficticio del fixture; nunca conecta la base real. |
| `tests/pos_resultados_vista.php` | 12–16 | Contador ficticio para que la vista de prueba no consulte compras reales. |
| `tests/producto_acciones_vista.php` | 15–19 | Misma adaptación aislada del fixture de catálogo. |

**Prueba funcional exacta:** instancia MariaDB nueva con el instalador final y login real de los cuatro roles de prueba. Se mantiene además una compra inactiva con estado PENDIENTE para comprobar que no suma. Dueño crea dos compras por HTTP; recepción acepta la primera y rechaza la segunda. En cada paso se comparan número de filas del listado operativo, SQL y badge de los tres roles autorizados.

| Paso | Pendientes reales/listado/badge | Reposición |
|---|---:|---:|
| Inicial | 0; sin badge | 1 |
| Primera compra del dueño | 1 | 1 |
| Segunda compra del dueño | 2 | 1 |
| Recepción acepta la primera | 1 | 1 |
| Recepción rechaza la segunda | 0; sin badge | 1 |

Registrar compras conserva stock y movimientos; solo aceptar genera la entrada. El ensayo posterior de Recepción Directa también conserva cero pendientes, comprobando que no se cuenta como una compra pendiente. Se mantienen los rechazos de GET/POST de Compra a recepción y de ambos módulos a vendedor. Los únicos datos artificiales se crean en MariaDB temporal; la base real no se consulta ni modifica para estas pruebas.

**Resultado del original:** 419 comprobaciones correctas, incluida la regresión de matriz/Compra/Recepción/Directa y recursos. Navbar revisada para administrador, dueño, recepción y vendedor en 1920×1080, 1366×768, 1280×720, 1024×768 y 390×844: 20 combinaciones, con menú expandido también en móvil. Sin overflow general; badges pequeños, visibles y sin cortes. Se revisaron capturas admin 1366 y recepción 390, con Recepción 2 y Reposición 1. Capturas e informes en `/tmp/compra-recepcionista-h8qw2i9v`; etapas en `badge-etapas.json` y medidas en `badge-visual.json`.

Sintaxis de 88 PHP propios correcta; pruebas existentes de Compra PHP 32 y EAN/Reposición 8 correctas. Ocho fixtures de vistas renderizados sin errores con datos ficticios, tras agregar el contador simulado que necesita la navbar compartida. `git diff --check` correcto. No se modifica lógica de POS, carrito, dropdown, ventas, rectificación, caja, EAN, auditoría o decomiso; la única diferencia de aplicación es el modelo de conteo y su presentación en navbar. No hay cambio de esquema ni migración. Se revisó LEEME_PROFESOR: no requiere cambios de instalación ni matriz por este badge.

**Cómo probar:** en base de prueba, partir sin pendientes; crear dos compras como dueño y recargar entre ellas (sin badge → 1 → 2). Como recepción, controlar y aceptar una (1) y rechazar la otra (sin badge). Comparar siempre con el listado Pendientes. Comprobar que Reposición mantiene su cantidad y que vendedor no tiene Recepción. En 390 px desplegar el menú y verificar ambos números; repetir en los cuatro escritorios indicados.

Referencias afectadas de navbar y fixtures recalculadas con `nl -ba`; los archivos de aplicación modificados son únicamente `app/models/RecepcionModel.php` y `app/views/inc/navbar.php`. Sin commit ni push.

**Entrega validada desde extracción:** 374 comprobaciones funcionales correctas desde el ZIP candidato, importando el instalador en una instancia nueva y autenticando los cuatro roles. Secuencia 0→1→2→1→0, igualdad con listado, exclusión de inactivas/Directa, independencia de Reposición, recursos y permisos finales correctos. La revisión visual completa corresponde al original (20 combinaciones aprobadas); un intento visual del candidato se interrumpió en el entorno y se liberaron únicamente sus procesos/datos temporales antes de repetir la validación funcional completa. No se contabilizó ese intento como aprobado. Informe final: `/tmp/badge-recepcion-entrega.json`. Candidato íntegro: 407606 bytes y 202 archivos, carpeta interna `proyectofinaldesarrollo`, SHA-256 `dd1a7a8eb0cb5b23bba605ef5395d51c2576d5de2edf8c93d3486e06a283086f`. Código/SQL/dependencias idénticos al original; configuraciones demo seguras intencionales y exclusiones conservadas. ZIP reemplazado atómicamente en `/home/ivangl/proyectofinalprueba.zip`; hash final verificado. Sin commit ni push.


### Validación definitiva y congelamiento para presentación (24/11/2026)

El propietario aprobó la prueba manual final y autorizó commit y push al terminar la verificación. No se agregó funcionalidad ni se modificó código de aplicación durante este cierre; solo se registra este resultado. Los números de línea del código conservan las referencias finales.

- Repositorio original, rama main y origin `https://github.com/iixann/proyectofinaldesarrollo.git` confirmados; referencia remota actualizada antes de preparar el commit. HEAD previo: `b5469999da0fb068ce1886faacedf1201a9d60ce`. Se revisaron los 66 archivos pendientes intencionales; ningún backup/temporal/cache/log/credencial personal pertenece al commit. .env personal sigue ignorado.
- `git diff --check` correcto. Sintaxis de 89 PHP propios (incluye herramientas propias sin ejecutarlas) y de todos los PHP extraídos, incluidas dependencias, correcta. Pruebas Python compilan. JavaScript propio compilado con Firefox en la prueba dirigida de POS; no depende de Node.
- Regresión principal: 219 comprobaciones aprobadas en MariaDB temporal y Firefox, sin la auditoría visual global. Incluye principal, último administrador, permisos, confirmaciones, stock integrado, caja/cierre, privacidad de sesiones, ventas propias, rectificación transaccional, ticket, persistencia, consulta rápida/atajos, auditoría/decomiso y 52 comprobaciones internas EAN.
- Instalación desde la extracción del ZIP: 486 comprobaciones aprobadas con base temporal nueva, usando exclusivamente PHP/SQL/JS/CSS de esa extracción. Importación directa del instalador final, seis triggers, rectificación y FK de auditoría correctos. Antes de cargar fixtures solo existe el admin demo y no hay datos comerciales/personales. Login y password_verify de admin@admin.com/admin1234 correctos: administrador activo, principal y sin cambio obligatorio. Se abrieron Dashboard, Usuarios, Productos, Categorías, Proveedores, Compras, Recepción, Reposición, Cajas, Sesiones, POS y Ventas, según permisos; se autenticaron los cuatro roles.
- Matriz final comprobada: Compras solo dueño/admin; recepción mantiene Productos operativo, Categorías consulta, Recepción/Directa, Reposición y Decomiso; vendedor mantiene Productos/Categorías de consulta, caja operativa, POS y ventas propias, sin Compras/Recepción. No se concede Usuarios al dueño, recepción ni vendedor.
- Compra/Recepción y badges desde extracción: Dueño crea PENDIENTE sin stock/movimientos; recepción acepta con ingreso o rechaza sin ingreso; badges 0→1→2→1→0 coinciden con listado y Reposición permanece independiente. Directa conserva sus operaciones.
- POS dirigido de la extracción a 1366×768: 21 comprobaciones aprobadas (dos filas completas, búsqueda de dos productos, cierre de resultados, foco EAN, Total/Cobrar, restauración y Supr, más sintaxis de scripts propios). Datos de interfaz ficticios y scripts/vista del ZIP, sin ventas ni escrituras reales. No se repitieron 36 estados/resoluciones que ya habían sido aprobados.
- Productos dirigido de la extracción a 1366×768: 14 comprobaciones aprobadas de menú Admin completo/accesible/compacto y vendedor sin menú ni columna; incluye las 22 comprobaciones JavaScript internas de Compra/Directa. No se repitieron todas las posiciones/resoluciones previamente aprobadas y sin cambios.
- Pruebas PHP existentes: Compra 32 y EAN/Reposición 8 correctas. Recursos Bootstrap 5.3.2 CSS/JS y FontAwesome 6.5.1 CSS respondieron HTTP 200 en sus CDN. LEEME ya documenta Internet, carpeta, requisitos, SQL, .env demo y acceso; no necesitó cambios. Cámara física EAN conserva la validación manual satisfactoria del propietario; no se repitió físicamente.
- ZIP auditado: íntegro por CRC, raíz única `proyectofinaldesarrollo`, 202 archivos, 407606 bytes, SHA-256 `dd1a7a8eb0cb5b23bba605ef5395d51c2576d5de2edf8c93d3486e06a283086f`. Sus 200 archivos distintos de .env/.env.example son idénticos al original; las dos configuraciones son demo local root/contraseña vacía, deliberadas y verificadas. Todos los archivos app/public están presentes. Dependencias PHP y ZXing/licencia incluidos; solo un SQL: instalacion_profesor.sql. Sin migraciones, tests, docs internos, dumps personales, backups, logs, capturas, caches ni temporales.
- Búsqueda explícita de nombres sospechosos: solo .env y .env.example demo seguros, dos .gitkeep vacíos de carpetas de la aplicación y session/login.php (coincidencia textual de log). No son credenciales privadas ni artefactos peligrosos. La búsqueda de rutas personales, claves privadas y formatos de tokens reconocibles en entregables no encontró coincidencias. .git/.gitignore no se incluyen.
- El ZIP validado no necesitó regeneración: su contenido ya coincide y se conservará sin tocar después del commit. Los tests e informes se ejecutan fuera de la entrega. La base local real y los backups anteriores permanecen intactos.

Informes y capturas de este cierre permanecen fuera del repositorio: `/tmp/cierre-final-manifiesto.json`, `/tmp/cierre-final-instalacion.json`, `/tmp/cierre-final-pos`, `/tmp/cierre-final-dropdown` y resultado de regresión principal. El estado definitivo del commit/push se confirma mediante Git y en el informe al propietario; no se reescribe el proyecto después de ese cierre.


## 14. Usuarios: roles exclusivos, cambio posterior y acciones compactas (25/11/2026)

**Pedido:** ADMINISTRADOR y DUEÑO son exclusivos; VENDEDOR y RECEPCIONISTA pueden combinarse. Permitir cambios posteriores con protección de administradores y un único dropdown Acciones por fila. Esta revisión se realiza solamente en el original, pendiente de prueba manual: sin commit, push ni actualización de la entrega.

**Estado previo y datos:** usuarios_roles ya es N:M, con estado y vigencia por fechas. La creación aceptaba combinaciones de jerarquía y no había formulario para cambiar roles. La consulta de solo lectura a la base local encontró 9 usuarios y ninguna combinación vigente inválida. No se modificaron datos locales ni SQL; no se necesita migración. No se cambian las capacidades funcionales de cada rol.

| Archivo y líneas finales | Método/regla y explicación |
|---|---|
| app/models/UserModel.php:44–63 | validarCombinacionRoles: IDs activos válidos, selección obligatoria, ADMINISTRADOR/DUEÑO únicos y combinación operativa permitida. |
| app/models/UserModel.php:66–80 | usuarioConRoles y puedeCambiarRoles: autoriza con datos actuales; un admin común no cambia a ningún administrador; el principal puede cambiar a otros, nunca al propio principal. |
| app/models/UserModel.php:83–121 | cambiarRoles: bloqueo de usuarios, control de último administrador activo, cierre de asignaciones anteriores y nuevas asignaciones con auditoría en la misma transacción. También cancela asignaciones futuras para evitar reactivaciones inesperadas. |
| app/models/UserModel.php:241–277 | crearUsuario/asignarRoles: la creación transaccional también usa la validación central; una llamada directa al modelo no elude las combinaciones válidas. |
| app/models/AuditoriaModel.php:20–25 | registrarCambioRoles: guarda actor, CAMBIO_ROLES, entidad USUARIO, objetivo y nombres de roles anteriores/nuevos; la fecha la proporciona el esquema existente. |
| app/controllers/UserController.php:13–29 | actionIndex: calcula por usuario si corresponde ofrecer Cambiar roles y muestra el resultado de la operación. |
| app/controllers/UserController.php:149–210 | usuarioParaCambiarRoles, actionCambiarRoles, actionGuardarRoles y mostrarCambioRoles: protegen GET/POST, objetivo real, token CSRF y errores; POST correcto redirige al listado. |
| app/controllers/UserController.php:264–270 | actionGuardar: valida los roles de creación mediante la misma regla del modelo. |
| app/controllers/SessionController.php:102–109 | exigirSesion: refresca roles vigentes para que un cambio surta efecto también en sesiones ya abiertas; si no quedan roles, vuelve al login. Dependencia necesaria de seguridad; no cambia la matriz de permisos. |
| app/views/user/_roles.php:1–17 | Selector compartido con explicación de exclusividad y alerta de selección vacía. |
| app/views/user/crear.php:51–63 | Reutiliza el selector y carga usuarios.js para el alta. |
| app/views/user/cambiar_roles.php:1–32 | Pantalla MVC con nombre/email, roles actuales, token, errores, guardar y cancelar. |
| app/views/user/index.php:41–65 | Estado y roles compactos; Principal junto al rol y un único Acciones. Cambiar roles depende de autorización real; mantiene restablecimiento, baja/reactivación y principal sin dropdown. No existía Editar y no se inventa esa acción. |
| app/views/user/index.php:76 | Carga el script específico de Usuarios, sin modificar Productos. |
| public/js/usuarios.js:4–27 | actualizar y eventos: seleccionar jerarquía desmarca/bloquea otros roles, desmarcarla los habilita; las dos funciones operativas pueden combinarse; evita envío vacío. |
| public/js/usuarios.js:28–43 | Bootstrap Dropdown/Popper con estrategia fixed y límite viewport, siguiendo Productos: menú completo aunque la tabla tenga scroll. |
| public/css/usuarios.css:1–6 | Columna Acciones compacta y mínimos de celdas para mantener filas legibles en móvil mediante scroll interno de tabla. |

**Protecciones:** no se ofrece ni acepta Cambiar roles sobre el principal. El admin común no modifica administradores, incluidos él mismo; se comprueba tanto en controlador como en modelo. El principal puede degradar a otros administradores. No puede quedar sin administrador activo ni perderse la identidad principal. Se conservan baja protegida, último administrador, confirmación existente y restablecimiento de contraseña. El formulario no acepta es_principal ni estado desde el navegador. Ante fallo de auditoría se revierten el cierre y la creación de asignaciones.

**Pruebas nuevas:** tests/usuarios_roles.py y tests/usuarios_roles_visual.py, siempre en MariaDB temporal, con instalación desde cero y sesiones HTTP reales. Cobertura de cinco combinaciones válidas, cinco inválidas, selección vacía/desconocida, creación directa inválida sin fila parcial, cambios del admin común y principal, intentos GET/POST manipulados, CSRF y método, único admin en esquema heredado, auditoría y rollback inducido, restablecimiento y protección del principal. Una sesión existente recibe inmediatamente su nueva unión de permisos: operativo mixto tiene POS y Recepción, pero no Compras ni Usuarios. tests/compra_producto_existente.php adapta únicamente su fixture SQLite con roles reales para el refresco de sesión; no cambia aplicación de Compras.

**Revisión visual:** principal y administrador común frente a siete objetivos (principal, admin secundario, vendedor, recepcionista, mixto, dueño e inactivo), en 1366×768, 1024×768 y 390×844: 42 combinaciones. Menús sin cortes, acciones correctas, baja roja, confirmación cancelable, sin overflow general y filas compactas. Formularios de creación/cambio prueban bloqueo exclusivo, combinación operativa y selección vacía. Una ejecución completa aprobó 488 comprobaciones; la repetición final centrada en formularios aprobó 262, incluida su regresión funcional de 233 comprobaciones. Son ejecuciones solapadas, no se suman como casos independientes. Capturas e informes fuera del repositorio: /tmp/usuarios-roles-1k8a0avo y /tmp/usuarios-roles-oypbu_4u.

**Cómo probar manualmente:** como principal, crear usuarios con cada combinación permitida; intentar combinaciones inválidas mediante POST y comprobar rechazo. Como admin común, cambiar vendedor a recepción y después a ambos; comprobar que no ofrece ni acepta cambiar a otro admin. Como principal, cambiar un admin secundario a dueño; comprobar que su propia fila no ofrece Cambiar roles y que su POST forzado se rechaza. Abrir Acciones sobre filas superiores/inferiores y usuario inactivo en las tres resoluciones; cancelar baja sin alterar datos. Verificar restablecimiento por su flujo habitual. Con una sesión del usuario objetivo ya abierta, cambiar sus roles y comprobar que en la siguiente navegación usa solo sus roles vigentes. Consultar auditoria_operaciones para revisar actor/objetivo/antes/después. Los escenarios de último administrador y fallo inducido de auditoría deben ejecutarse exclusivamente en base temporal.

Referencias obtenidas del código final con nl -ba. Se actualizaron también las referencias anteriores afectadas de UserModel, UserController, SessionController, AuditoriaModel y listado de Usuarios. LEEME y SQL no requieren cambios. No se tocó ZIP, base comercial, POS, Productos, Compras, Recepción, Ventas, Caja, EAN ni otras funciones.

**Verificación final:** regresión existente de correcciones aprobada: 219 comprobaciones, incluido POS/atajos, EAN, ventas/rectificación y cierre de caja, en /tmp/correcciones-profesor-rt5dr3gt. Sintaxis de 91 PHP propios correcta; JavaScript nuevo compilado en Firefox; Python correcto; Compra PHP 32 y EAN/Reposición PHP 8 correctas; git diff --check sin errores. El primer intento de regresión no tenía sus recursos Bootstrap temporales y se descartó; al disponer de esos recursos la repetición completa pasó, sin cambios de aplicación por ese incidente.



### Administrador Principal protegido contra acciones administrativas de otros administradores

**Problema:** restablecer contraseña validaba el rol ADMINISTRADOR del actor y la existencia del objetivo, pero no es_principal. Así un administrador secundario podía abrir el formulario y cambiar el hash del principal. La reactivación tampoco tenía rechazo explícito del principal.

**Solución final:** Gestión de Usuarios no administra al principal, incluso desde su propia sesión. Su cambio personal de contraseña conserva el método existente, separado del restablecimiento administrativo. No se cambia esquema ni datos reales. El principal sigue administrando normalmente a terceros y a otros administradores según las reglas anteriores.

| Archivo y líneas finales | Protección |
|---|---|
| app/controllers/UserController.php:69–89 | usuarioParaRestablecer valida ID/existencia y rechaza es_principal con 403 antes de formulario o cambio de hash; también lo reutiliza el cambio de roles. |
| app/controllers/UserController.php:32–65 | GET restablecer y POST guardar contraseña pasan por el objetivo protegido; se mantienen autenticación, POST y token existentes. |
| app/models/UserModel.php:188–205 | restablecerContrasena rechaza al principal antes de password_hash, UPDATE y cambio obligatorio; defensa adicional en modelo. |
| app/models/UserModel.php:161–168 | reactivar rechaza al principal antes del UPDATE. |
| app/models/UserModel.php:83–121 y 134–158 | Se conservan cambio de roles y baja protegidos, bloqueo transaccional y último administrador. |
| app/views/user/index.php:50–65 | Principal protegido sustituye todo el dropdown para el objetivo principal; terceros mantienen Cambiar roles autorizado, Restablecer contraseña y baja/reactivar. Bootstrap/Popper no cambia. |

**Pruebas temporales:** tests/usuarios_roles.py fuerza GET y POST con token válido desde admin común y desde principal contra el objetivo principal: restablecimiento, baja y reactivación devuelven 403; las pruebas anteriores verifican también cambio de roles rechazado. Se compara la fila completa del principal (incluido hash, flags y estado), todas las asignaciones y toda la auditoría antes/después: idénticas, sin registros falsos. Admin común y principal pueden seguir dando de baja, reactivando y restableciendo a terceros. La prueba visual verifica que el principal no tenga botón ni acciones en los tres tamaños; los otros menús conservan sus comprobaciones de permisos y recorte.

**Prueba manual:** entrar como administrador secundario, abrir Usuarios y comprobar Principal protegido sin menú. Obtener un formulario válido de restablecimiento de un tercero y forzar su POST al ID principal: debe responder 403 sin cambiar contraseña ni exigir cambio obligatorio. Verificar también GET de restablecimiento, cambio de roles, POST baja/reactivar. Repetir administración permitida de un usuario de prueba. No usar la base comercial para bajas/restablecimientos artificiales. Sin ZIP, commit o push.

**Resultado de esta corrección:** 516 comprobaciones correctas en MariaDB y Firefox temporales, informe/capturas en /tmp/usuarios-roles-sdfkz89p. Principal y admin común, tres resoluciones (1366×768, 1024×768, 390×844), ausencia de menú principal, terceros administrables y POST forzado rechazado sin cambio de hash/datos/roles/auditoría. Sintaxis PHP de los tres archivos de aplicación afectados y git diff --check correctos. El método personal cambiarContrasenaObligatoria conserva su implementación. No se accedió al ZIP ni se hicieron commit/push.
