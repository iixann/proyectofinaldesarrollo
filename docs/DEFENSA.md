# Guía de defensa del proyecto

Repaso breve del código que ya existe. Incorporar conceptos nuevos solamente cuando se utilicen realmente en el sistema.

## Recorrido de una solicitud

Ejemplo: abrir `/proyectofinaldesarrollo/user/index`.

`.htaccess` → `public/index.php` → `App` → `UserController::actionIndex()` → `UserModel` → `DataBase` → `Response` → vista HTML.

El Autoloader carga las clases necesarias durante ese recorrido. Antes de listar usuarios, el controlador comprueba que la sesión corresponda a un ADMINISTRADOR.

## Arquitectura y arranque

- **MVC:** separa responsabilidades. `UserController` recibe la solicitud y valida; `UserModel` define las consultas y operaciones de usuarios; las vistas de `app/views/user/` muestran formularios y resultados.
- **.htaccess:** configura Apache. El archivo de la raíz dirige las solicitudes hacia `public/`; el de `public/` envía las rutas que no corresponden a archivos o directorios existentes a `index.php?url=...`.
- **Front Controller:** `public/index.php` es el punto de entrada común. Inicia la sesión, define las rutas, carga Composer y las variables de entorno con phpdotenv, registra el Autoloader e instancia `App`.
- **Autoloader:** `app/core/Autoloader.php` usa `spl_autoload_register()` para incluir clases cuando se necesitan. Las clases globales del núcleo se buscan en `CORE_PATH`; para las clases con namespace convierte las barras inversas en una ruta de archivo. Composer carga las dependencias externas.
- **Router App:** `app/core/App.php` lee `$_GET['url']` y determina el controlador, la acción y los parámetros. Por ejemplo, `user/crear` lleva a `UserController` y `actionCrear()`. Mantiene la convención `actionNombre()` del profesor.
- **namespace:** identifica a qué grupo pertenece una clase. `app\models\UserModel` distingue al modelo; `use app\models\UserModel` permite nombrarlo como `UserModel` dentro del controlador.

## Responsabilidades de las clases

- **Controller:** clase base de los controladores; ofrece `action404()` y `viewDir()`. `UserController` extiende esa base, comprueba permisos, valida el formulario y solicita operaciones al modelo.
- **Model:** clase base con `$tabla`, `$clavePrimaria`, `findId()` y `getAll()`. `UserModel` la extiende para buscar usuarios, obtener roles, listar y crear usuarios. Los modelos no crean conexiones PDO.
- **DataBase:** concentra la conexión y la ejecución de consultas. Sus métodos preparan sentencias, pasan parámetros, obtienen resultados y controlan las transacciones.
- **PDO:** objeto de PHP que permite comunicarse con la base. Solo se crea dentro de DataBase. `prepare()` y `execute($parametros)` mantienen los valores separados de la consulta, como `:correo` al buscar un usuario.
- **Response:** `Response::render()` recibe el directorio, el nombre de la vista y sus datos; convierte esos datos en variables e incluye el archivo PHP de la vista.
- **View:** archivo que presenta HTML. Por ejemplo, `app/views/user/index.php` recorre `$usuarios` y muestra una tabla. Usa `htmlspecialchars()` al mostrar textos para que se interpreten como contenido y no como HTML.

## Presentación compartida y Bootstrap

Las vistas incluyen fragmentos con `require APP_PATH . 'views/inc/head.php'` y el mismo mecanismo para `navbar.php` y `footer.php`. Así se comparten imports, estilos, navegación y pie sin crear otra capa ni un sistema de layouts. `$seccionActual` indica qué opción de la navbar resaltar; es un dato de presentación.

Bootstrap 5.3.2 se carga por CDN y aporta la grilla responsive, cards, formularios, tablas, botones y badges. Su bundle permite desplegar el menú en pantallas pequeñas. FontAwesome aporta los iconos. Estos recursos necesitan conexión a Internet para cargarse.

`head.php` sobrescribe variables y estilos de Bootstrap para usar Malbec `#6D1A36` en acciones principales, enlaces, iconos, badges y focus. La misma identidad se aplica al login, dashboard, listado y creación de usuarios.

La separación entre lógica y presentación se mantiene: los controladores y modelos conservan autenticación, permisos, consultas y validaciones. Las vistas presentan los datos y mensajes existentes, sin consultar DataBase. Los módulos pendientes solo se muestran como “Próximamente”.

## Lectura rápida de PHP orientado a objetos

| Elemento | Significado y ejemplo del proyecto |
|---|---|
| `public` | Permite acceder al método desde fuera de la clase. App puede llamar a `actionIndex()` de un controlador. No confundir con la carpeta `public/`, que contiene el punto de entrada web. |
| `protected` | Permite acceso desde la clase y sus clases hijas. `$tabla` y `$clavePrimaria` pertenecen a Model y se definen en UserModel. |
| `private` | Limita el acceso a la propia clase. `$conexion` pertenece internamente a DataBase; `mostrarFormulario()` es un método interno de UserController. |
| `static` | Permite usar un método o propiedad de clase sin crear una instancia para esa llamada. Ejemplo: `DataBase::query()`. |
| `->` | Accede a un método o propiedad de un objeto: `$conexion->prepare(...)` o `$usuario->email`. |
| `::` | Accede a miembros de clase o constantes: `DataBase::insert(...)` y `PDO::FETCH_OBJ`. |
| `$this` | Es el objeto actual. En un controlador, `$this->viewDir(__NAMESPACE__)` obtiene su directorio de vistas. |
| `self` | Se refiere a la clase donde está escrito. `self::$conexion` usa la propiedad estática de DataBase; `self::asignarRoles()` llama al método de UserModel. |

## Sesiones y acceso

`session_start()` en `public/index.php` recupera o inicia la sesión. Tras validar el login, `SessionController` regenera su identificador y guarda en `$_SESSION['usuario']` el ID, nombre, apellido, email y roles; no guarda la contraseña.

`sesionValida()` comprueba la estructura mínima de esos datos. `esAdministrador()` verifica que la lista incluya `ADMINISTRADOR`. `soloAdministradores()` se conserva como wrapper: delega en `onlyUsers(['ADMINISTRADOR'])` sin duplicar lógica.

Autenticar es comprobar quién ingresa mediante correo y contraseña. Autorizar es decidir qué operaciones puede realizar ese usuario según sus roles.

`SessionController::onlyUsers($rolesPermitidos)` recibe un array de roles funcionales. Primero verifica la sesión: si falta, redirige al login y ejecuta `exit`. Después aplica la jerarquía y permite continuar si cumple al menos uno de los roles solicitados. Si no tiene permiso, responde con HTTP 403, muestra “Acceso denegado” y ejecuta `exit`.

Centralizamos este control para evitar repetir condiciones y mantener la misma regla en todas las acciones. El panel y las acciones de gestión de usuarios llaman a `onlyUsers(['ADMINISTRADOR'])` antes de ejecutar operaciones, por lo que también quedan protegidos al ingresar directamente por URL.

El logout vacía `$_SESSION`, elimina la cookie de sesión cuando corresponde, destruye la sesión y redirige al login.

## Roles y usuarios_roles

`roles` contiene ADMINISTRADOR, DUEÑO, RECEPCIONISTA y VENDEDOR. `usuarios_roles` vincula cada usuario con uno o varios roles: usuarios 1 → N usuarios_roles N → 1 roles. Cada asignación tiene su ID, estado y fechas de vigencia.

`UserModel::obtenerRolesUsuario()` une ambas tablas y obtiene roles activos con asignaciones activas y vigentes. La creación de usuarios permite seleccionar varios roles y guarda una asignación por cada uno. No existe autorregistro: la creación está protegida para administradores.

La jerarquía funcional ya está implementada en `onlyUsers()`:

- ADMINISTRADOR: todas las operaciones.
- DUEÑO: operaciones de DUEÑO, RECEPCIONISTA y VENDEDOR; sin acceso a las exclusivas de ADMINISTRADOR.
- RECEPCIONISTA: operaciones de RECEPCIONISTA.
- VENDEDOR: operaciones de VENDEDOR.

Esta herencia se calcula en memoria; no requiere guardar roles inferiores en `usuarios_roles`. Si el usuario tiene varios roles asignados, se combinan sus permisos. Los futuros módulos reutilizarán este método indicando los roles permitidos para cada operación.

## Categorías

`CategoriaController` protege sus seis acciones con `onlyUsers(['DUEÑO'])`. El listado consulta `CategoriaModel::listarCategorias()`; el modelo extiende Model y usa DataBase para acceder a la tabla `categorias`. La búsqueda por ID reutiliza `findId()` de Model. Response entrega los resultados a las vistas de `app/views/categoria/`.

Crear, actualizar y dar de baja requieren POST; otro método recibe HTTP 405 y `Allow: POST`. Se valida un ID positivo dentro del rango de la columna y la existencia del registro. Un ID inválido recibe 400; uno inexistente, 404. El nombre se recorta, debe contener entre 1 y 100 caracteres y no puede repetirse, incluso si la categoría anterior está inactiva. Al editar se excluye el propio ID de la comprobación de duplicados.

La baja lógica conserva el registro: cambia `estado` a 0 y establece `fecha_baja`, sin DELETE. El listado mantiene visibles las categorías inactivas. Tras guardar se redirige con HTTP 303 al listado y se muestra un mensaje predefinido según la query string; no se imprime texto arbitrario recibido por URL. El SQL de documentación no se ejecuta automáticamente.

## Proveedores

Proveedores sigue el mismo recorrido y protección que Categorías. `ProveedorModel` extiende Model, reutiliza `findId()` y delega consultas parametrizadas en DataBase. Razón social es obligatoria; email y CUIT son opcionales. El CUIT se conserva como string y se comprueba su duplicidad excluyendo el propio ID al editar, incluso entre proveedores inactivos. Los opcionales vacíos se guardan como NULL, permitiendo varios proveedores sin CUIT bajo el índice UNIQUE. La baja es lógica, sin DELETE. Es un maestro independiente, sin relación con Productos todavía.

## Productos

`ProductoController` usa `onlyUsers(['DUEÑO'])` y exige POST para guardar, actualizar y dar de baja. `ProductoModel` lista con JOIN de categoría y comprueba el nombre dentro de la misma categoría excluyendo el propio ID al editar. Categoría y proveedores seleccionados deben estar activos; el precio es no negativo y los stocks son enteros no negativos, con mínimo menor o igual al deseable. Stock actual puede estar por debajo del mínimo; el listado lo señala como STOCK BAJO.

Crear o actualizar el producto y sus proveedores es una transacción. Al actualizar asociaciones, se desactivan las anteriores y se insertan o recuperan las seleccionadas mediante el par único producto/proveedor, sin DELETE. Categorías, Proveedores y Productos admiten reactivación por POST: validan la existencia y actualizan estado a 1 y fecha_baja a NULL sobre el mismo registro, conservando sus claves únicas. El formulario avisa si una categoría o proveedor anterior dejó de estar activo. Los nuevos productos comienzan con stock 0 y su edición solo configura stock mínimo y deseable; los cambios de stock actual se registran desde Stock. No hay precio de compra en el producto.

## Código EAN y consulta POS

Código EAN se guarda en codigo_barras como VARCHAR(50) para conservar ceros iniciales. NOT NULL y UNIQUE protegen la base; PHP exige texto no vacío, aplica trim, limita longitud y valida duplicados excluyendo el propio producto al editar. NOT NULL por sí solo no impide cadenas vacías.

El escaneo busca el EAN con igualdad exacta; el nombre usa LIKE preparado, con hasta 30 resultados. Solo se consultan productos activos y se indica SIN STOCK cuando corresponde, sin cambiar existencias. PosController ofrece una acción JSON interna para JavaScript/jQuery con permiso VENDEDOR; no es una API REST ni implementa todavía carrito o ventas.

## Stock y movimientos

`StockController` protege sus acciones con `onlyUsers(['RECEPCIONISTA'])`. Ajustar requiere un producto activo y guardar exige POST, tipo válido y cantidad entera positiva. El usuario del movimiento se toma de la sesión. El historial incluye productos inactivos y se ordena por fecha e ID descendentes.

`StockModel` inicia una transacción y lee el producto con `SELECT ... FOR UPDATE`: bloquea esa fila hasta finalizar para que dos ajustes no calculen sobre el mismo saldo anterior. Una entrada suma y una salida resta; se rechazan saldos negativos y desbordamientos del INT. Luego actualiza el stock e inserta el movimiento con cantidad, saldo anterior y nuevo. Confirma ambas operaciones o hace rollback si algo falla. Las tablas deben usar InnoDB para estas garantías. Productos ya no actualiza stock actual: cada cambio manual pasa por este recorrido y conserva trazabilidad.

## Compras

`CompraController` usa `onlyUsers(['RECEPCIONISTA'])` y guardar exige POST. `CompraModel` valida proveedor, productos activos y asociación activa con el proveedor; rechaza productos repetidos y comprobantes duplicados por proveedor. Los comprobantes vacíos se guardan como NULL. El modelo entrega asociaciones activas al formulario para filtrar productos con JavaScript al cambiar proveedor; el backend vuelve a validar la asociación dentro de la transacción.

Una sola transacción guarda cabecera, detalles, total, stock y movimientos COMPRA. Se bloquean proveedor, productos y asociaciones con FOR UPDATE; los productos se procesan por ID para reducir interbloqueos. Ante un fallo se revierte todo. No se llama a los métodos de ajuste que abren su propia transacción. Cada movimiento identifica al usuario de sesión y lleva “Compra #ID”.

Precio, subtotal y total se calculan en centavos enteros y se envían como cadenas decimales con dos posiciones, sin confiar en importes calculados por el navegador ni usar float. Se controla el límite de DECIMAL(12,2). El precio de compra queda en el detalle histórico; el precio de venta no cambia. Las compras registradas solo se consultan, sin edición ni cancelación.

## Cajas y sesiones

Caja es la terminal física; caja_sesiones registra cada apertura y cierre. CajaController protege la administración con DUEÑO; SesioncajaController protege apertura, cierre e histórico con VENDEDOR. Las cajas usan baja lógica y reactivación, sin borrar su historial. No hay límite de sesiones por usuario, solamente una abierta por caja física.

CajaSesionModel abre una transacción y bloquea la caja con SELECT ... FOR UPDATE antes de comprobar sesiones abiertas e insertar: evita que dos aperturas simultáneas creen turnos para la misma caja. Para cerrar bloquea la sesión para impedir cierres simultáneos, verifica ABIERTA y recalcula ventas activas y pagos EFECTIVO (monto aplicado). PHP toma estos totales de la base, sin confiar en el navegador. Efectivo esperado = fondo inicial + efectivo vendido; diferencia = contado - esperado. Calcula en centavos, guarda el cierre completo o hace rollback. Sin ventas las sumas devuelven cero. El cierre histórico es de solo lectura. Las ventas futuras deberán bloquear esa misma sesión antes de confirmarse.

## Contraseñas

- `password_hash($contrasena, PASSWORD_DEFAULT)` se usa en `UserModel::crearUsuario()` para generar el hash que se guarda en `usuarios.password`. No se guarda la contraseña en texto plano; el hash no se descifra.
- `password_verify($contrasena, $usuario->password)` se usa en el login para comparar la contraseña ingresada con el hash guardado. También se exige que el usuario esté activo.

## Transacciones y conexión única

Crear un usuario y asignar sus roles es una sola operación lógica:

`iniciarTransaccion()` → INSERT del usuario → INSERT de cada rol en usuarios_roles → `confirmarTransaccion()`.

Si una operación dentro del bloque falla, se llama a `cancelarTransaccion()` y se relanza el error. Así se evita guardar parcialmente el usuario y sus asignaciones; las tablas documentadas usan InnoDB.

DataBase crea PDO en una variable local, configura la conexión y recién después la guarda en `self::$conexion`. Durante la solicitud, las llamadas posteriores a `connection()` devuelven ese mismo objeto. Por eso `beginTransaction()`, los INSERT, `lastInsertId()`, `commit()` y `rollBack()` usan la misma conexión. La reutilización del objeto la garantiza la propiedad estática, no la opción de conexión persistente.

## Ejemplo para explicar en la defensa

“Cuando el administrador envía el formulario, `UserController::actionGuardar()` comprueba permisos y valida los datos y roles. `UserModel::crearUsuario()` genera el hash y coordina la creación dentro de una transacción. DataBase ejecuta las consultas parametrizadas sobre la misma conexión. Si todo termina correctamente, el controlador redirige al listado; si falla, muestra el formulario con un mensaje de error.”
