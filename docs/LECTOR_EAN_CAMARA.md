# Lector EAN por cámara

Un componente (`public/js/ean-scanner.js`) pide la cámara trasera, muestra el vídeo
con Bootstrap y devuelve **un string** al módulo. Reconoce EAN-13 y EAN-8, valida
el dígito de control y exige dos fotogramas coincidentes. No convierte el código
completo a número: conserva ceros iniciales.

Cierra todas las pistas al detectar, cancelar, cerrar el modal, salir de la página
o pasar a segundo plano. Si el permiso llega después de cancelar, también cierra
ese stream. Cada apertura entrega como máximo un código. No abre cámaras en
segundo plano ni cambia datos directamente.

## Reconocimiento y compatibilidad

Primero consulta `BarcodeDetector.getSupportedFormats()`. Si ambos EAN están
soportados, usa la API nativa. Si falta la API, algún formato o falla la detección,
carga **ZXing 0.21.3** desde `public/lib/zxing/`, con licencia incluida. Solo lee
fotogramas del vídeo: nuestro componente conserva el control del stream.

Se eligió esta distribución de navegador para evitar un build y evitar depender
de un CDN durante la operación. Son aproximadamente 328 KiB sin comprimir,
descargados solo cuando hace falta la alternativa. No se modificó el código de
terceros ni se agregó un framework.

En Android se espera la API nativa donde esté disponible y ZXing como alternativa.
En iPhone se utiliza la alternativa cuando el navegador no ofrece ambos formatos.
La elección depende de capacidades reales, no de detectar la marca del teléfono.
Permiso, enfoque, iluminación y rendimiento deben probarse con el celular real.

Referencias:
- [API BarcodeDetector y contexto seguro](https://developer.mozilla.org/en-US/docs/Web/API/BarcodeDetector).
- [Implementación ZXing usada](https://github.com/zxing-js/library/tree/v0.21.3).

## HTTPS

Desde otro dispositivo, `http://IP-del-servidor/...` normalmente no es un contexto
seguro. El lector avisa que necesita HTTPS y mantiene disponible el ingreso
manual. `localhost` en la computadora suele ser una excepción segura, pero
`localhost` en el teléfono apunta al propio teléfono, no a la computadora.

No se cambiaron Apache, certificados, rutas ni infraestructura de red. Para la
prueba física se necesita acceder desde el teléfono a un origen HTTPS confiable
que ya sirva la aplicación y autorizar la cámara. No se garantiza la cámara en
navegadores integrados de otras aplicaciones.

## Uso por módulo

| Pantalla | Resultado de escanear |
| --- | --- |
| Crear Producto | Completa el campo EAN; no envía ni guarda el formulario. Edición no incorpora cámara. |
| Nueva Compra | Exige proveedor; consulta el endpoint actual. Si está asociado, selecciona una fila conservando cantidades. Si existe sin asociación, abre el panel actual para confirmar la asociación. Si no existe, abre alta rápida con EAN precargado. |
| Recepción de Compra | Compara el EAN con las líneas enviadas por el controlador, resalta la encontrada y enfoca cantidad sin cambiarla. Un producto ajeno muestra un error. |
| Recepción Directa | Selecciona un producto asociado al proveedor o permite alta rápida si no existe. Si existe sin asociación, informa que debe asociarse desde Productos, respetando el flujo anterior de esta pantalla. |
| Stock | Ejecuta la búsqueda GET exacta, limpia otros filtros y muestra una tarjeta móvil con producto, EAN, categoría, stock actual, mínimo y estado activo. |
| Reposición | Ejecuta la búsqueda exacta, resalta el resultado y distingue stock suficiente de un EAN desconocido reutilizando StockModel. |

`ean-modulos.js` conecta botones y formularios. Compra procesa el evento
`ean:detectado` en su propio JavaScript y reutiliza sus consultas y modales.
El componente y sus adaptadores solo se incluyen en estas cinco pantallas
(Compra y Recepción Directa comparten vista). POS, Ventas, Cobro y Ticket no los
cargan. No cambian permisos, CSRF, SQL ni modelos de escritura.

Escanear no confirma cantidades, no crea productos ni asociaciones y no guarda
compras o recepciones. Las confirmaciones explícitas siguen pasando por los
controladores y modelos existentes. Compra continúa PENDIENTE; solo aceptar la
recepción mueve stock. Todos los valores enviados siguen validándose en backend
con los controles actuales y consultas preparadas.

## Verificación

- PHP: `/opt/lampp/bin/php tests/compra_producto_existente.php`.
- Consulta de reposición: `/opt/lampp/bin/php tests/ean_scanner_reposicion.php`.
- Compra y recepción directa: `node tests/compra_producto_existente.js` (también ejecutable en V8).
- Prueba real de navegador y cámara simulada: `python3 tests/ean_scanner_visual.py`.

La prueba visual usa PHP de XAMPP, Firefox con WebDriver BiDi y dos recursos de
Bootstrap 5.3.2 en `/tmp/ean-bootstrap.min.css` y
`/tmp/ean-bootstrap.bundle.min.js`. No instala dependencias Python. Renderiza las
vistas originales mediante `tests/ean_scanner_vistas.php`, con datos ficticios y
sin `.env`. El servidor de prueba solo escucha en `127.0.0.1:8766`.

Las capturas y resultados van a `/tmp/ean-*.png` y
`/tmp/ean-scanner-resultados.json`. El perfil aislado del navegador se borra al
finalizar. Ninguna prueba requiere escribir en la base del comercio.

Para la prueba manual: ingresar con cada rol autorizado, abrir y cancelar la
cámara, rechazar el permiso, escanear un EAN-13 y EAN-8, probar reflejos y distancia,
verificar los seis flujos de la tabla y confirmar que la cámara se apague.
Repetir una consulta con cámara desde HTTPS y otra desde HTTP. Verificar además
POS, F2–F10 y consulta rápida, que conservan sus archivos y eventos anteriores.
