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

## ETAPA 5 - CAJA + POS + VENTAS — EN CURSO

POS 1 terminado: diseño definitivo y estructura de base creados manualmente. POS 2 terminado y probado manualmente: Cajas + apertura/cierre. POS 3 terminado y probado manualmente: EAN obligatorio y búsqueda de productos. POS 4 terminado y probado: Punto de Venta + carrito dinámico. Siguiente bloque: COBRO + CONFIRMACIÓN DE VENTA (POS 5 + POS 6). Alcance completo en [POS_CAJA_VENTAS.md](POS_CAJA_VENTAS.md). No se trata de un CRUD simple: incluye operación de caja, carrito, pagos, confirmación transaccional, histórico y ticket.

Orden de avance; POS 1–4 terminados y siguientes bloques pendientes:

1. POS 1 — TERMINADO: diseño definitivo y estructura de base.
2. POS 2 — TERMINADO: cajas y apertura/cierre; pruebas manuales correctas.
3. POS 3 — TERMINADO: Código EAN obligatorio y búsqueda de productos; pruebas manuales correctas.
4. POS 4 — TERMINADO: Punto de Venta + carrito dinámico; probado manualmente.
5. POS 5 + POS 6 — SIGUIENTE: COBRO + CONFIRMACIÓN DE VENTA. Medios y pagos combinados, efectivo entregado/vuelto; venta, detalle, pagos, stock y movimiento en una transacción completa.
6. POS 7 — PENDIENTE: histórico y detalle de ventas.
7. POS 8 — PENDIENTE: ticket imprimible.
8. POS 9 — PENDIENTE: integración y pruebas finales.

Cada etapa: implementar → probar → commit → siguiente etapa. No implementar todo en un único cambio. La estructura de base de POS 1 ya fue creada manualmente. POS 4 está terminado y probado manualmente. Las antiguas POS 5 y POS 6 se implementarán juntas como un bloque funcional; POS 7–9 continúan pendientes.

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
