# Demostración — Identidad Singular

Versión demostrable implementada e integrada. Verificación automática con DataBase y DOM/fetch simulados; validación local de Cobro, histórico, ticket y circuito final aún pendiente. No se modificó la base real durante esas pruebas. Este documento es el recorrido para comprobarla antes de mostrarla al profesor.

## Preparación

1. Levantar Apache/MariaDB de LAMPP y verificar `.env` local, dependencias y las tablas documentadas. No ejecutar nuevamente schema/bootstrap sobre una base ya preparada.
2. Tener un ADMINISTRADOR y usuarios con los roles que se van a demostrar. La base inicial y credenciales se administran manualmente.
3. Disponer de una categoría y proveedor activos y un producto activo asociado al proveedor, con EAN textual único. Para el ejemplo: precio de venta 12.34, stock inicial 0. El ejemplo utiliza un producto nuevo; si se usa uno existente, anotar su stock previo y adaptar las cantidades.
4. Tener al menos una caja activa y medios EFECTIVO y DÉBITO activos. Para impresión, configurar el ancho del papel en el navegador/controlador; el contenido del ticket tiene 72 mm.

## Circuito corto frente al profesor

1. Ingresar como ADMINISTRADOR: mostrar panel, usuarios/roles y navbar. Explicar onlyUsers y la jerarquía.
2. Mostrar categoría, proveedor y producto. El EAN conserva ceros iniciales; stock actual no se edita desde Productos. Mostrar asociación N:M con proveedor.
3. Registrar compra del producto: 5 unidades a 5.00. Debe guardar total 25.00, aumentar stock de 0 a 5 y registrar COMPRA. No requiere un ajuste posterior.
4. Como VENDEDOR o ADMINISTRADOR, abrir caja con 5000.00. Intentar segunda apertura en la misma caja: debe rechazarse.
5. Entrar al POS. Elegir sesión si hay varias. Escanear el EAN dos veces: cantidad 2, precio 12.34, total 24.68. Mostrar búsqueda por nombre y controles de cantidad.
6. Aplicar EFECTIVO 10.00, entregado 20.00: vuelto 10.00. Aplicar DÉBITO 14.68. Total aplicado 24.68.
7. Confirmar una vez: mostrar número de venta; carrito y pagos se limpian, stock pasa de 5 a 3 y aparece movimiento VENTA. No volver a enviar por recargar una pantalla de éxito.
8. Abrir el enlace Ver venta: detalle con precio histórico 12.34, subtotal 24.68, efectivo aplicado 10.00 y débito 14.68. Abrir ticket y vista previa de impresión. Volver al histórico y reimprimir el mismo ticket: no debe crear una nueva venta.
9. Cerrar caja: si no hubo otras ventas, total ventas 24.68, efectivo vendido 10.00 y esperado 5010.00. Contar 5010.00: diferencia 0.00. El entregado 20.00 no se suma completo porque hubo vuelto.
10. Consultar sesión cerrada. No se puede volver a cerrarla ni vender en esa sesión; se puede abrir un nuevo turno.

## Validaciones breves

- Producto sin stock: puede aparecer, pero no se agrega ni se vende.
- EAN duplicado, incluso de producto inactivo: rechazado.
- Cantidad negativa, repetición de producto en payload y medio duplicado: rechazados en servidor.
- Pagos insuficientes/excedidos: no permiten confirmar; un POST manipulado también se rechaza.
- Cambio de precio/stock después de armar carrito: el servidor usa el dato actual. Si cambia el total, quitar y volver a buscar el producto para actualizar el carrito antes de corregir el cobro.
- Sesión cerrada desde otra pestaña: no debe aceptar una nueva venta.
- VENDEDOR no administra usuarios/maestros; RECEPCIONISTA no opera caja/POS/ventas; DUEÑO no administra usuarios.
- Ticket y detalle son de lectura: abrirlos no cambia stock ni pagos.

## Verificación realizada sin base real

Sintaxis PHP completa; rutas del dashboard/navbar; permisos y destino por rol; venta simple/combinada y rollback; consultas/vistas históricas y ticket con datos escapados; circuito de modelos apertura → compra → venta → cierre → reapertura con una misma DataBase simulada. DOM/fetch simulados para cobro, vuelto, bloqueo de doble envío y limpieza al confirmar.

La concurrencia real entre procesos, la conectividad local y la impresión física no quedan demostradas por fixtures. Se comprueban en el entorno local antes de la presentación. No hay cancelación/devolución ni emisión fiscal integrada.
