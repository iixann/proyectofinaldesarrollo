# Roadmap del proyecto

Objetivo: completar el núcleo funcional durante octubre, antes de entrar en la etapa final de noviembre. Reservar noviembre principalmente para pruebas, interfaz y defensa.

El sistema académico debe ser simple y defendible. El diseño de datos y módulos debe permitir evolucionarlo después hacia un sistema real para una vinería, manteniendo ahora el MVC del profesor y evitando ampliar el alcance sin necesidad.

## ETAPA 1 - COMPLETA

Autenticación y usuarios: login, sesión, logout, panel ADMINISTRADOR, listado y creación de usuarios con uno o varios roles.

Commit estable: `0050b9a` — `feat: implementar autenticacion y gestion inicial de usuarios`.

## ETAPA 2 - EN CURSO

Completar en este orden:

1. Categorías — COMPLETA: listado, creación, edición y baja lógica; pruebas manuales correctas.
2. Proveedores — próximo módulo.
3. Productos — pendiente.

Cerrar y probar cada módulo antes de iniciar el siguiente.

## ETAPA 3 - PENDIENTE

- Stock
- Movimientos de stock
- Stock mínimo
- Stock deseable
- Alertas/reposición

## ETAPA 4 - PENDIENTE

- Compras
- Recepción de mercadería
- Actualización de stock

## ETAPA 5 - PENDIENTE

- Ventas
- Descuento automático de stock

Las etapas 2 a 5 forman la prioridad funcional de octubre. Verificar su integración mientras se termina cada módulo.

## ETAPA 6 - CONDICIONAL

Caja, pagos y devoluciones solamente si siguen dentro del alcance final. Confirmar su inclusión antes de implementarlos, sin retrasar el cierre del núcleo funcional.

## ETAPA 7 - PENDIENTE

Integración general y pruebas: recorrer los circuitos completos de usuarios, productos, compras, stock y ventas; comprobar permisos y corregir errores. Comenzar durante octubre y reforzar en noviembre.

## ETAPA 8 - PENDIENTE

Interfaz visual con Bootstrap/CSS y usabilidad: aplicar una presentación consistente sobre los flujos funcionales ya probados. Prioridad de noviembre.

## ETAPA 9 - PENDIENTE

Documentación, pruebas finales y preparación de defensa: mantener los documentos al día, practicar la explicación del código y preparar una demostración del sistema. Prioridad de noviembre.

## Criterio de avance

Un módulo queda terminado cuando cumple su objetivo concreto, fue probado en navegador, se corrigieron los errores, se explicó su lógica importante, se actualizó la documentación y se creó su commit.

Si el tiempo se reduce, ajustar el alcance opcional antes de sumar complejidad al núcleo. No adelantar nuevos módulos sin cerrar el actual.
