# Caja + POS + Ventas

Estado: POS etapas 1, 2 y 3 terminadas. Modelo de datos definido y tablas creadas manualmente en phpMyAdmin, según confirmación del usuario. POS 2 (Cajas + apertura/cierre) implementada y probada manualmente en navegador. POS 3 (Código EAN obligatorio y búsqueda de productos) terminado y probado manualmente. Siguiente: POS etapa 4, Punto de Venta + carrito dinámico. Este documento no crea tablas ni contiene SQL para ejecutar. Se mantiene PHP puro, MVC del profesor, PDO centralizado en DataBase, autorización con onlyUsers() y Bootstrap/Malbec, sin capas nuevas.

## 1. Objetivo general

El sistema debe funcionar como un comercio real. Un VENDEDOR podrá:

- Abrir una caja e ingresar el monto inicial.
- Acceder al Punto de Venta y escanear productos por código de barras.
- Buscar productos manualmente y armar un carrito.
- Aumentar o disminuir cantidades y eliminar productos.
- Ver subtotales y total, y finalizar la venta.
- Utilizar uno o varios medios de pago e imprimir un ticket.
- Consultar ventas realizadas y cerrar la caja.

## 2. Permisos

El POS corresponde funcionalmente a VENDEDOR y usará `SessionController::onlyUsers(['VENDEDOR'])`.

| Rol | Acceso |
|---|---|
| ADMINISTRADOR | Permitido |
| DUEÑO | Permitido |
| VENDEDOR | Permitido |
| RECEPCIONISTA | Denegado |

No se crea el rol CAJERO por ahora. Si el negocio necesita distinguir vendedor de cajero, podrá agregarse ese rol sin rehacer el módulo.

## 3. Cajas

Una caja representa una terminal o punto físico, por ejemplo Caja 1 o Caja 2. Puede estar ACTIVA o INACTIVA.

CAJA es la terminal física; SESIÓN DE CAJA es la apertura y cierre de un turno. Son conceptos diferentes.

## 4. Apertura de caja

Antes de vender debe existir una sesión de caja abierta. La apertura registra caja, usuario, fecha/hora de apertura y monto inicial.

Un usuario no debería abrir otra sesión en la misma caja si ya existe una sesión activa. La sesión permanece abierta durante las ventas.

## 5. Punto de Venta

El POS será una pantalla específica orientada al cajero. Debe permitir ingreso por código de barras, búsqueda manual, carrito, aumento y disminución de cantidades, eliminación de productos y visualización de precio unitario, subtotal y total.

La interfaz utilizará Bootstrap actual y JavaScript; puede usar jQuery si resulta conveniente y coherente con lo visto en clase. No se usarán frameworks JS grandes. El frontend mejora la experiencia; la validación definitiva siempre corresponde a PHP en servidor.

## 6. Código de barras

Productos incorpora `codigo_barras VARCHAR(50) NOT NULL UNIQUE`: Código EAN obligatorio, único y almacenado como texto para conservar ceros iniciales. PHP aplica trim y rechaza vacío; crear y editar exigen el código. La búsqueda para escaneo usa coincidencia exacta, no LIKE. La búsqueda por nombre es parcial y solo ofrece productos activos.

El lector se tratará inicialmente como entrada de teclado: escanear código → buscar producto → agregar al carrito. Si ya está en el carrito, se aumenta su cantidad. No se integra hardware especial en esta primera versión.

## 7. Stock

Antes de confirmar se comprueba que el producto esté activo, la cantidad solicitada y el stock disponible. No se permite stock negativo.

Al confirmar disminuye `productos.stock_actual` y se registra por cada producto un movimiento de tipo VENTA en movimientos_stock, con cantidad, stock anterior, stock nuevo, usuario y fecha. Todo forma parte de la misma transacción de venta.

## 8. Venta

Una venta tendrá cabecera y varios detalles. Campos definidos en el modelo aprobado:

| Tabla | Campos previstos |
|---|---|
| ventas | id_venta, id_caja_sesion, id_usuario, fecha_venta, total, estado |
| detalle_ventas | id_detalle_venta, id_venta, id_producto, cantidad, precio_unitario, subtotal |

`precio_unitario` conserva el precio histórico utilizado en esa venta, aunque después cambie `productos.precio_venta`. Una venta confirmada no admite edición normal.

## 9. Medios de pago

Existirá una tabla maestra de medios de pago con estos valores iniciales:

- EFECTIVO
- TRANSFERENCIA
- MERCADO PAGO
- DÉBITO
- CRÉDITO

Los medios deberán poder activarse o desactivarse en el futuro.

## 10. Pagos combinados

Una venta puede tener varios pagos: relación ventas 1 → N pagos_ventas. No se guarda un único medio de pago en ventas.

Campos de pagos_ventas: id_pago_venta, id_venta, id_medio_pago, monto, monto_entregado, vuelto y referencia opcional. Se mantiene UNIQUE (id_venta, id_medio_pago), consolidando cada medio en un único importe por venta.

Ejemplo de una venta de $100.000:

| Medio | Monto aplicado |
|---|---|
| Efectivo | $50.000 |
| Débito | $25.000 |
| Transferencia | $25.000 |
| Total pagado | $100.000 |

La suma de los pagos aplicados debe coincidir exactamente con el total de la venta.

## 11. Efectivo y vuelto

Se distingue el monto aplicado a la venta del dinero entregado por el cliente.

Si el total pendiente es $50.000 y el cliente entrega $60.000, el pago registrado como efectivo es $50.000 y el vuelto es $10.000. Solamente los $50.000 aplicados cuentan para cubrir el total.

El POS debe calcular visualmente el vuelto y conservar monto_entregado y vuelto como DECIMAL(12,2) nullable en pagos_ventas para reconstruir y reimprimir el ticket. Para EFECTIVO, vuelto = monto_entregado - monto; para otros medios ambos campos quedan NULL. El vuelto no es un medio de pago. La suma que coincide con ventas.total sigue siendo SUM(monto).

## 12. Finalización de venta

Todo el proceso debe ocurrir dentro de una única transacción:

1. Validar que la sesión de caja esté abierta.
2. Validar los productos.
3. Validar stock disponible.
4. Volver a tomar los precios desde el servidor.
5. Calcular subtotales.
6. Calcular el total.
7. Validar los pagos.
8. Crear la venta.
9. Crear detalle_ventas.
10. Crear pagos_ventas.
11. Descontar stock.
12. Crear movimientos_stock de tipo VENTA.
13. Confirmar con commit.

Cualquier fallo debe provocar rollback completo. No pueden quedar ventas sin detalle, parcialmente pagadas, stock descontado sin venta, pagos sin venta ni movimientos sin venta. Una sesión cerrada no admite nuevas ventas. El diseño definitivo deberá contemplar la consistencia entre cierre de caja y confirmaciones simultáneas.

## 13. Seguridad del total

PHP debe recalcular precios, subtotales y total a partir de datos reales del servidor. Los importes recibidos del navegador o calculados por JavaScript sirven solo para presentación y no son una fuente confiable para registrar la venta.

## 14. Histórico de ventas

La pantalla Ventas mostrará número/ID, fecha, caja, usuario, total, estado y acción Ver. El detalle mostrará productos, cantidades, precios históricos, subtotales, total y medios de pago.

## 15. Ticket

Después de confirmar se ofrecerá **Imprimir ticket**. La primera versión será una vista imprimible orientada a impresora térmica, mediante `window.print()`.

Contenido mínimo:

- Nombre del comercio y número de venta/ticket.
- Fecha y hora, caja y vendedor.
- Productos, cantidades, precio unitario y subtotales.
- Total, medios de pago y total pagado.

## 16. Impresora fiscal

La lógica de venta debe mantenerse independiente de una impresora concreta. La vista de ticket será el punto de salida inicial; el punto de integración fiscal se definirá sin acoplar la confirmación de venta a un dispositivo. No se integra marca ni modelo todavía. La emisión fiscal real dependerá del controlador fiscal, SDK/protocolo y configuración del comercio; el ticket académico no presupone esa integración.

## 17. Cierre de caja

Al cerrar se mostrarán fecha/hora de apertura y cierre, usuario, monto inicial, total vendido, total por medio de pago, efectivo vendido y efectivo esperado.

`efectivo esperado = monto inicial + ventas pagadas en efectivo`

En pagos combinados se contará solamente la parte efectivamente pagada en efectivo.

Se solicitará efectivo contado y se calculará:

`diferencia = efectivo contado - efectivo esperado`

El cierre se guardará y bloqueará nuevas ventas en esa sesión. Las reglas restantes de apertura y cierre se definirán junto con las tablas.

## 18. Modelo de datos definido y creado

Tablas aprobadas y creadas manualmente; estructura documentada en database/schema.sql:

| Tabla | Finalidad prevista |
|---|---|
| cajas | Identificar las cajas |
| caja_sesiones | Apertura y cierre, responsables e importes de sesión |
| ventas | Cabecera de venta vinculada a caja/sesión y usuario |
| detalle_ventas | Productos, cantidades, precios y subtotales históricos |
| medios_pago | Medios de pago disponibles |
| pagos_ventas | Importes por medio de pago, incluidos pagos combinados |

Productos incorpora codigo_barras VARCHAR(50) NOT NULL UNIQUE, según modificación manual confirmada. No se permiten códigos vacíos en la aplicación. Las PK/FK son INT UNSIGNED y los importes DECIMAL(12,2); se mantiene InnoDB y utf8mb4_unicode_ci. Caja_sesiones usa estados ABIERTA/CERRADA y datos de cierre nullable. Detalle_ventas conserva precios históricos y UNIQUE (id_venta, id_producto). Las FK preservan referencias mediante ON DELETE RESTRICT. La apertura concurrente se controlará transaccionalmente bloqueando la caja; confirmar ventas y cerrar sesión bloquearán la misma fila de caja_sesiones.

## 19. Orden de implementación

POS 1, POS 2 y POS 3 terminados; POS 2 y POS 3 probados manualmente. POS 4 es el siguiente y POS 5–9 permanecen pendientes. No implementar el bloque entero en un único cambio.

| Etapa | Alcance |
|---|---|
| POS 1 — TERMINADO | Diseño definitivo y estructura de base creada manualmente |
| POS 2 — TERMINADO | Cajas y apertura/cierre; probado manualmente |
| POS 3 — TERMINADO | Código EAN obligatorio y búsqueda de productos; probado manualmente |
| POS 4 — SIGUIENTE | Punto de Venta + carrito dinámico |
| POS 5 | Medios de pago y pagos combinados |
| POS 6 | Confirmación transaccional: venta, detalle, pagos, stock y movimiento |
| POS 7 | Histórico y detalle de ventas |
| POS 8 | Ticket imprimible |
| POS 9 | Pruebas completas |

Cada etapa sigue: implementar → probar → commit → siguiente etapa. En POS 1, el resultado es el diseño y el SQL para administrar manualmente desde phpMyAdmin; la aplicación no crea ni modifica el esquema automáticamente.

## 20. Fuera de alcance por ahora

- Clientes obligatorios y cuentas corrientes.
- Promociones complejas, puntos/fidelización y cuotas de tarjetas.
- Integraciones con Mercado Pago API y bancos.
- AFIP/ARCA web services e impresora fiscal concreta.
- Múltiples sucursales y comercio electrónico.

El diseño debe permitir evolución futura sin implementar estas funciones ahora. Las devoluciones permanecen fuera del bloque inmediato y pendientes de confirmar su inclusión.
