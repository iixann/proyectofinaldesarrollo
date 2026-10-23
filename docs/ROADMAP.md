# Roadmap del proyecto

Objetivo: completar el núcleo funcional durante octubre, antes de entrar en la etapa final de noviembre. Reservar noviembre principalmente para pruebas, interfaz y defensa.

El sistema académico debe ser simple y defendible. El diseño de datos y módulos debe permitir evolucionarlo después hacia un sistema real para una vinería, manteniendo ahora el MVC del profesor y evitando ampliar el alcance sin necesidad.

## ETAPA 1 - COMPLETA

Autenticación y usuarios: login, sesión, logout, panel ADMINISTRADOR, listado y creación de usuarios con uno o varios roles.

Commit estable: `0050b9a` — `feat: implementar autenticacion y gestion inicial de usuarios`.

## ETAPA 2 - COMPLETA

Completar en este orden:

1. Categorías — COMPLETA: listado, creación, edición y baja lógica; pruebas manuales correctas.
2. Proveedores — COMPLETO: listado, creación, edición y baja lógica; pruebas manuales correctas.
3. Productos — COMPLETO: listado, creación, edición, baja lógica y asociaciones con proveedores; pruebas manuales correctas.

Cerrar y probar cada módulo antes de iniciar el siguiente.

## ETAPA 3 - COMPLETA

- Listado de stock con mínimo, deseable y aviso visual STOCK BAJO
- Ajustes de entrada/salida con trazabilidad y usuario de sesión
- Transacciones, bloqueo de fila y rollback; sin saldos negativos
- Historial general y por producto
- Productos nuevos con stock 0; cambios manuales mediante movimientos
- Pruebas manuales correctas

El aviso de stock bajo es visual; Compras permite registrar la reposición.

## ETAPA 4 - COMPLETA

- Compras con cabecera y múltiples productos asociados al proveedor
- Detalle histórico de cantidades y precios de compra
- Actualización automática de stock y movimientos COMPRA
- Una sola transacción y rollback completo ante fallos
- Pruebas manuales correctas

## ETAPA 5 - CAJA + POS + VENTAS — PENDIENTE, PRÓXIMO BLOQUE

Primera tarea: definir las tablas definitivas del POS. Alcance completo en [POS_CAJA_VENTAS.md](POS_CAJA_VENTAS.md). No se trata de un CRUD simple: incluye operación de caja, carrito, pagos, confirmación transaccional, histórico y ticket.

Orden obligatorio, con todas las etapas pendientes:

1. POS 1: diseño definitivo de tablas y SQL manual.
2. POS 2: cajas y apertura/cierre.
3. POS 3: código de barras y búsqueda de productos.
4. POS 4: carrito dinámico.
5. POS 5: medios de pago y pagos combinados.
6. POS 6: confirmación transaccional de venta, detalle, pagos, stock y movimiento.
7. POS 7: histórico y detalle de ventas.
8. POS 8: ticket imprimible.
9. POS 9: pruebas completas.

Cada etapa: implementar → probar → commit → siguiente etapa. No implementar todo en un único cambio. Por ahora solo se documenta; aún no se escribe SQL ni código del POS.

Las etapas 2 a 5 forman la prioridad funcional. Verificar la integración al terminar cada etapa.

## ETAPA 6 - CONDICIONAL

Devoluciones solamente si siguen dentro del alcance final. Confirmar su inclusión antes de implementarlas. Caja y pagos de ventas ya forman parte del bloque POS; las cuentas corrientes e integraciones externas siguen fuera de alcance por ahora.

## ETAPA 7 - PENDIENTE

Integración general y pruebas: recorrer los circuitos completos de usuarios, productos, compras, stock y ventas; comprobar permisos y corregir errores. Comenzar durante octubre y reforzar en noviembre.

## ETAPA 8 - PENDIENTE

Interfaz visual con Bootstrap/CSS y usabilidad: aplicar una presentación consistente sobre los flujos funcionales ya probados. Prioridad de noviembre.

## ETAPA 9 - PENDIENTE

Documentación, pruebas finales y preparación de defensa: mantener los documentos al día, practicar la explicación del código y preparar una demostración del sistema. Prioridad de noviembre.

## Criterio de avance

Un módulo queda terminado cuando cumple su objetivo concreto, fue probado en navegador, se corrigieron los errores, se explicó su lógica importante, se actualizó la documentación y se creó su commit.

Si el tiempo se reduce, ajustar el alcance opcional antes de sumar complejidad al núcleo. No adelantar nuevos módulos sin cerrar el actual.
