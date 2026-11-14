# Reconocimiento EAN alternativo

- Biblioteca: `@zxing/library`, versión fija `0.21.3`.
- Archivo original: https://cdn.jsdelivr.net/npm/@zxing/library@0.21.3/umd/index.min.js
- Código fuente: https://github.com/zxing-js/library/tree/v0.21.3
- Licencia Apache-2.0 incluida en `LICENSE`.

El archivo distribuido se conserva sin modificaciones. `ean-scanner.js` lo carga
solo si BarcodeDetector no admite EAN-13 y EAN-8, o falla al detectar. Se utiliza
el lector de imágenes de ZXing; la apertura y cierre de cámara siguen a cargo
de nuestro componente. No requiere npm, compilación ni conexión al CDN al escanear.
No carga frameworks ni envía imágenes a servicios externos.

SHA-256 de `index.min.js`: `d7cc8f69dd70bdcf3ac00c9ae572bf2acb9f4132ba379c72df842e4db918652d`.
