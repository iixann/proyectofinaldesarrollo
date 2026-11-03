# Guía de estudio y defensa — Identidad Singular

Leer primero [DEMOSTRACION.md](DEMOSTRACION.md) y recorrer el código con los ejemplos. [DEFENSA.md](DEFENSA.md) sirve como repaso corto. Esta guía explica lo implementado, sin atribuirle funciones todavía fuera de alcance.

## 1. Arquitectura: cómo llega una solicitud

Abrir `/producto/editar/1`: Apache aplica los .htaccess y deriva a public/index.php. Ese Front Controller inicia sesión, carga entorno/Composer y Autoloader e instancia App. App lee `$_GET['url']`, encuentra ProductoController, actionEditar y el parámetro 1. El controlador autoriza, valida el ID y pide el producto al modelo. DataBase prepara SQL y ejecuta parámetros mediante PDO. Response convierte datos en variables de la vista; la vista genera HTML escapado.

MVC separa responsabilidades: Controller decide el flujo; Model describe operaciones de datos; View presenta. App resuelve rutas, no permisos. Autoloader carga archivos por nombre de clase; Composer carga dependencias externas. Los namespaces separan clases y permiten `use`. No hay Service/Repository/ORM ocultando el recorrido.

Preguntas del profesor: ¿por qué un formulario no consulta la base? ¿Quién interpreta la URL? ¿Qué diferencia hay entre Autoloader y App? Responder mostrando archivos del recorrido, no solamente definiciones.

## 2. Persistencia y seguridad comunes

DataBase es el único lugar que crea PDO. `prepare()` mantiene SQL separado de valores; `execute($parametros)` evita concatenar entradas en las consultas. Las filas se obtienen como objetos. Las vistas aplican htmlspecialchars; el JS presenta nombres con textContent. La sesión guarda identidad y roles, no contraseña. password_hash genera hashes y password_verify comprueba la contraseña.

Autenticación responde quién ingresa. Autorización decide qué puede hacer. onlyUsers centraliza la sesión y roles: ADMINISTRADOR accede a todo; DUEÑO agrega sus funciones y las de RECEPCIONISTA/VENDEDOR; los otros conservan sus funciones. Un enlace oculto no sustituye el control de la acción. Tras el login se elige una ruta utilizable por el rol.

La baja lógica cambia estado y fecha_baja; reactivar conserva el mismo ID. Un UNIQUE sigue reservando los valores de registros inactivos. Una relación N:M usa tabla intermedia y puede tener estado propio.

Preguntas: ¿por qué escapar si ya se usan prepared statements? Son problemas distintos: HTML y consultas. ¿Un rol equivale a un controlador? No; cada acción declara qué rol funcional puede ejecutarla.

## 3. Usuarios y roles

- Problema: acceso con identidad y alta controlada de usuarios, sin autorregistro.
- Tablas: usuarios, roles, usuarios_roles.
- Controladores: SessionController y UserController; AdminController protege el panel.
- Modelo: UserModel. Vistas: session/login y user/index/crear.
- Flujo: validar credenciales y usuario activo → consultar roles activos/vigentes → regenerar ID de sesión → guardar identidad → elegir inicio.
- Alta: solo ADMINISTRADOR; valida datos, correo único y roles válidos. Una transacción guarda usuario con hash y asignaciones, o revierte todo.
- Evita usuario parcial sin sus roles y contraseñas en texto plano. La sesión conserva los roles cargados al ingresar; no consulta sus cambios en cada acción.
- Defensa: explicar el JOIN para roles vigentes y por qué varios roles requieren usuarios_roles.
- Preguntas: ¿qué ocurre si falla la segunda asignación? Rollback. ¿Por qué no guardar el password en la sesión? La navegación no lo necesita.

## 4. Maestros: categorías y proveedores

- Problema: clasificar el catálogo y mantener datos comerciales reutilizables.
- Tablas: categorias y proveedores.
- Controladores/modelos: CategoriaController/CategoriaModel y ProveedorController/ProveedorModel. Vistas: respectivas carpetas con index, crear y editar.
- Permiso: DUEÑO (también ADMINISTRADOR). Altas, edición, baja y reactivación exigen POST.
- Categoría: nombre obligatorio, hasta 100 caracteres, único incluso entre inactivas. Editar excluye su propio ID.
- Proveedor: razón social obligatoria; CUIT opcional y único como string, email opcional válido. Opcionales vacíos se guardan NULL. Se respetan longitudes del esquema.
- Baja/reactivación no borran referencias históricas. No necesitan transacción para una sola actualización independiente.
- Defensa: diferenciar NULL de cadena vacía y explicar por qué un proveedor sin CUIT no debe bloquear otros sin CUIT.
- Preguntas: ¿por qué no DELETE? Compras/productos pueden referenciarlo. ¿Por qué reactivar en vez de crear otro registro? Conserva identidad y evita duplicados.

## 5. Productos, EAN y proveedores

- Problema: catálogo con precio comercial, clasificación, códigos y opciones de abastecimiento.
- Tablas: productos, categorias, proveedores y productos_proveedores.
- Controlador/modelo: ProductoController/ProductoModel. Vistas: producto/index, crear y editar.
- Permiso: DUEÑO. Categoría activa obligatoria; proveedores activos opcionales; nombre único dentro de categoría, EAN obligatorio único; precio compatible con DECIMAL(12,2), mínimos/deseables enteros con mínimo <= deseable.
- Código EAN usa VARCHAR para conservar ceros iniciales. PHP aplica trim, longitud y duplicados; NOT NULL/UNIQUE son protección de base, pero NOT NULL no impide una cadena vacía.
- Crear/actualizar producto y asociaciones es transaccional. Asociaciones quitadas se desactivan; volver a seleccionarlas recupera el mismo par único, sin DELETE.
- Stock inicial es 0 y no se cambia desde edición. Precio de compra no va aquí: queda en detalle_compras.
- Defensa: explicar N:M y por qué un producto puede comprarse a distintos proveedores.
- Preguntas: ¿por qué 000123 no es un entero? El cero forma parte del identificador. ¿Qué pasa si una categoría anterior se inactiva? El formulario pide una activa para guardar.

## 6. Stock y movimientos

- Problema: existencias con trazabilidad de cada cambio.
- Tablas: productos.stock_actual y movimientos_stock; no existe otra tabla de saldo.
- Controlador/modelo: StockController/StockModel. Vistas: stock/index, ajustar y movimientos.
- Permiso: RECEPCIONISTA (heredado por DUEÑO/ADMINISTRADOR).
- Entrada/salida: producto activo, cantidad entera positiva, observación hasta 255. Comienza transacción, lee y bloquea producto con FOR UPDATE, calcula saldo, rechaza negativo/desbordamiento, actualiza saldo e inserta movimiento. Commit o rollback.
- Una transacción hace atómicas las operaciones. FOR UPDATE impide que dos procesos calculen sobre un mismo saldo anterior al mismo tiempo; el bloqueo dura hasta terminar la transacción. InnoDB proporciona estas garantías.
- STOCK BAJO es aviso visual cuando actual <= mínimo; no es una notificación externa ni una compra automática.
- Defensa: cantidad siempre positiva; el tipo define suma/resta. Ajuste, COMPRA y VENTA dejan usuario, fecha y saldos.
- Preguntas: ¿qué pasa si falla insertar el movimiento? Se revierte el saldo. ¿Por qué leer antes del BEGIN no alcanza? Otro proceso podría cambiarlo antes de escribir.

## 7. Compras

- Problema: registrar recepción y costo histórico sin perder coherencia de stock.
- Tablas: compras, detalle_compras, productos, proveedores, productos_proveedores y movimientos_stock.
- Controlador/modelo: CompraController/CompraModel. Vistas: compra/index, crear y ver.
- Permiso: RECEPCIONISTA. Proveedor/productos/asociaciones activos, cantidad positiva, precio no negativo, sin producto repetido; comprobante opcional único por proveedor.
- El formulario permite varios renglones y filtra productos al cambiar proveedor. Esa UX no sustituye la validación del modelo.
- Una transacción bloquea y valida, guarda cabecera/detalles, suma stock y movimientos COMPRA. Precios/subtotales/total se calculan en centavos en servidor y se guardan como cadenas decimales; no se aceptan totales del formulario.
- No modifica precio de venta. La compra confirmada es histórica y no se edita ni cancela.
- Defensa: recorrer un renglón desde formulario hasta saldo/movimiento. Explicar par producto/proveedor y comprobante NULL.
- Preguntas: ¿por qué no llamar registrarEntrada de StockModel? Abriría otra transacción; CompraModel hace sus operaciones bajo una sola. ¿Dónde queda el precio real de compra? En el detalle histórico.

## 8. Cajas y sesiones

- Problema: distinguir terminal física de turno y registrar arqueo.
- Tablas: cajas, caja_sesiones, usuarios; ventas/pagos para recalcular cierre.
- Administración: CajaController/CajaModel y vistas caja, permiso DUEÑO.
- Operación: SesioncajaController/CajaSesionModel y vistas caja_sesion, permiso VENDEDOR.
- Apertura: bloquear caja activa, comprobar que no tenga sesión ABIERTA e insertar monto inicial/usuario. No se limita el número de cajas por usuario.
- Cierre: bloquear sesión ABIERTA, recalcular ventas activas y efectivo aplicado, calcular esperado = inicial + efectivo y diferencia = contado - esperado. Guardar cierre o rollback. Cerrada es de solo lectura; se puede abrir un nuevo turno.
- El bloqueo de la caja sirve incluso cuando todavía no hay sesiones. Venta y cierre bloquean la misma sesión para no confirmarse simultáneamente de forma inconsistente.
- Defensa: efectivo entregado no es efectivo vendido, porque hay vuelto.
- Preguntas: ¿por qué no un UNIQUE(id_caja, estado)? Impediría varias sesiones históricas CERRADAS. ¿Una baja física borra turnos anteriores? No.

## 9. POS, JavaScript y JSON interno

- Problema: operar con lector/teclado sin recargar toda la pantalla.
- PosController entrega sesiones y medios; ProductoModel busca por igualdad de EAN o LIKE de nombre. La acción JSON devuelve solo campos necesarios y hasta 30 resultados por nombre, con productos activos.
- Vista pos/index y public/js/pos.js: carrito temporal en un Map por ID. Repetir EAN incrementa; controla cantidad de 1 a stock consultado, permite eliminar y calcula en centavos. Stock 0 se muestra, pero no se agrega.
- Permiso VENDEDOR; requiere sesión abierta en caja activa. Si hay varias se elige explícitamente. Cambiar sesión se bloquea mientras hay productos o consultas pendientes.
- fetch consulta una acción de nuestro MVC, no una API REST ni una SPA. Se usa JavaScript nativo; no se agregó jQuery porque no era necesario.
- Defensa: UI y límite visual son UX. El usuario puede manipular JS; PHP debe validar otra vez todo. El carrito se pierde al recargar y no reserva stock.
- Preguntas: ¿qué pasa con otra venta mientras armo carrito? No hay reserva; la confirmación usa stock real. ¿Por qué centavos? Para evitar igualdad monetaria con float.

## 10. Venta y pagos combinados

- Problema: cobrar una compra de varios productos, con varios medios, sin estados parciales.
- Tablas: ventas, detalle_ventas, pagos_ventas, medios_pago, caja_sesiones, cajas, productos y movimientos_stock.
- VentaController/ VentaModel; el cobro está en pos/index y pos.js. Confirmar es POST JSON, permiso VENDEDOR y token ligado a la sesión. Usuario se toma de PHP, nunca del payload.
- El backend recibe IDs/cantidades y pagos. Rechaza productos/medios repetidos, cantidades inválidas, medios inactivos y montos no positivos.
- Dentro de una transacción bloquea sesión abierta y caja activa, productos por ID y medios por ID. Reconstruye precios actuales y subtotales en centavos, comprueba stock y suma exacta de pagos, y guarda venta, detalles, saldo, movimientos VENTA y pagos. Cualquier excepción revierte todo.
- EFECTIVO guarda monto aplicado, monto_entregado y vuelto calculado. Otros medios guardan entregado/vuelto NULL. UNIQUE(venta, medio) consolida cada medio una vez.
- JavaScript bloquea el doble clic, conserva datos al fallar y limpia solo al recibir éxito. No hay claves complejas de idempotencia: una respuesta perdida requiere revisar histórico antes de reenviar.
- Defensa: no confiar en precio, subtotal, total, stock ni usuario del navegador. Explicar por qué guardar el precio en detalle_ventas.
- Preguntas: ¿si el precio cambió? PHP usa el real; si no coinciden los pagos, rechaza. ¿Si falla el último pago después del stock? Rollback de toda la venta.

## 11. Histórico y ticket

- Problema: consultar y reimprimir ventas sin repetir sus efectos.
- VentaController index/ver/ticket y VentaModel listarVentas/buscarVenta/detallesVenta/pagosVenta. Vistas venta/index, ver y ticket. Permiso VENDEDOR.
- Filtro de fechas validado y preparado, hasta 100 ventas recientes. Detalle usa precios/subtotales históricos y muestra pagos, entregado, vuelto y referencia.
- Ticket de 72 mm con window.print(), comercio Identidad Singular, venta, fecha/hora, caja, vendedor, líneas, total y pagos. Se reimprime desde histórico; solo realiza SELECT.
- No integra impresora fiscal ni llama a hardware desde la transacción. Nombres se leen por JOIN y pueden reflejar renombrados posteriores; los importes históricos permanecen guardados.
- Defensa: separar confirmar, consultar e imprimir. Una reimpresión no es una nueva venta.
- Preguntas: ¿por qué el ticket no usa productos.precio_venta? Podría haber cambiado. ¿Es facturación fiscal? No se implementó integración fiscal.

## 12. Qué comprobar antes de defender

Seguir DEMOSTRACION.md, explicar un permiso denegado, un rollback y el precio histórico. Las pruebas automáticas usan simulaciones: no equivalen a probar concurrencia real ni impresora física. No afirmar funciones que no existen: cancelaciones, devoluciones, cuentas corrientes, clientes obligatorios, promociones y servicios fiscales/bancarios están fuera del bloque implementado.
