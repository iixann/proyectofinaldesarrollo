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

- Listado de stock con mínimo y aviso visual STOCK BAJO
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

POS 1 terminado: diseño definitivo y estructura de base creados manualmente. POS 2 terminado y probado manualmente: Cajas + apertura/cierre. POS 3 terminado y probado manualmente: EAN obligatorio y búsqueda de productos. POS 4 terminado y probado: Punto de Venta + carrito dinámico. Cobro + Confirmación (POS 5 + POS 6) implementado y verificado con simulaciones. Histórico/detalle y ticket implementados con pruebas simuladas. Integración automática verificada; siguiente: validación local y demostración. Alcance completo en [POS_CAJA_VENTAS.md](POS_CAJA_VENTAS.md). No se trata de un CRUD simple: incluye operación de caja, carrito, pagos, confirmación transaccional, histórico y ticket.

Orden de avance; POS 1–4 terminados y siguientes bloques pendientes:

1. POS 1 — TERMINADO: diseño definitivo y estructura de base.
2. POS 2 — TERMINADO: cajas y apertura/cierre; pruebas manuales correctas.
3. POS 3 — TERMINADO: Código EAN obligatorio y búsqueda de productos; pruebas manuales correctas.
4. POS 4 — TERMINADO: Punto de Venta + carrito dinámico; probado manualmente.
5. POS 5 + POS 6 — TERMINADO: COBRO + CONFIRMACIÓN DE VENTA. Medios y pagos combinados, efectivo entregado/vuelto; venta, detalle, pagos, stock y movimiento en una transacción completa.
6. POS 7 — TERMINADO: histórico y detalle de ventas.
7. POS 8 — TERMINADO: ticket imprimible.
8. POS 9 — IMPLEMENTADO/VERIFICADO CON SIMULACIONES: integración; pendiente de recorrido local e impresión.

Cada etapa: implementar → probar → commit → siguiente etapa. No implementar todo en un único cambio. La estructura de base de POS 1 ya fue creada manualmente. POS 4 está terminado y probado manualmente. POS 5 y POS 6 se cerraron como un bloque funcional con pruebas simuladas; POS 7 y POS 8 terminados con fixtures; POS 9 verificado con simulaciones; recorrido local pendiente.

Las etapas 2 a 5 forman la prioridad funcional. Verificar la integración al terminar cada etapa.

## ETAPA 6 - CONDICIONAL

Devoluciones solamente si siguen dentro del alcance final. Confirmar su inclusión antes de implementarlas. Caja y pagos de ventas ya forman parte del bloque POS; las cuentas corrientes e integraciones externas siguen fuera de alcance por ahora.

## ETAPA 7 - INTEGRACIÓN AUTOMÁTICA VERIFICADA; VALIDACIÓN LOCAL PENDIENTE

Circuito de modelos apertura/compra/venta/cierre, dinero, stock, permisos, rollback, rutas y vistas verificados con fixtures. Completar recorrido en navegador/MariaDB e impresión física siguiendo DEMOSTRACION.md; no se probaron escrituras sobre la base real desde Codex.

## ETAPA 8 - PRESENTACIÓN APLICADA AL CIRCUITO ACTUAL

Bootstrap/Malbec en los módulos actuales, dashboard con accesos reales, inicio por rol y ticket imprimible. Comprobar visualmente la presentación final en el equipo de demostración.

## ETAPA 9 - DOCUMENTACIÓN PREPARADA; DEFENSA/DEMO PENDIENTES

README actualizado, GUIA_ESTUDIO.md y DEMOSTRACION.md disponibles. Practicar explicación y preguntas; demostrar el circuito al profesor y ajustar según sus correcciones.

## Mejoras de gestión y Reposición — IMPLEMENTADAS

Filtros combinables de Productos; búsqueda de Categorías/Proveedores; ID de Ventas y búsqueda de Compras por ID/comprobante. Usuarios con baja/reactivación administrativa y contraseña nueva de mínimo 9 caracteres.

Stock usa solo `stock_actual` y `stock_minimo`. Reposición lista productos activos con `stock_actual <= stock_minimo` y todos sus proveedores asociados vigentes, sin modificar existencias ni generar compras. Verificaciones automáticas con simulaciones; pendientes la eliminación manual de la columna anterior y las pruebas de navegador de este bloque. No se inician funcionalidades adicionales.

## Criterio de avance

Un módulo queda terminado cuando cumple su objetivo concreto, fue probado en navegador, se corrigieron los errores, se explicó su lógica importante, se actualizó la documentación y se creó su commit.

Si el tiempo se reduce, ajustar el alcance opcional antes de sumar complejidad al núcleo. No adelantar nuevos módulos sin cerrar el actual.
