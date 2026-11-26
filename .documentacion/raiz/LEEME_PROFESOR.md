# Sistema de Gestión de Vinería — Identidad Singular

Requisitos: Apache/XAMPP/LAMPP, PHP 8.1 o superior con PDO MySQL y mbstring, MariaDB/MySQL. Internet para Bootstrap 5.3.2 y FontAwesome.

1. Descomprimir la carpeta `proyectofinaldesarrollo` dentro de `htdocs`.
2. Importar `database/instalacion_profesor.sql` en una instancia/base nueva desde phpMyAdmin. El script crea y selecciona `proyectofinaldesarrollo`. No ejecutar sobre una base existente.
3. Ajustar `.env` a la instalación local. La entrega incluye configuración demo de XAMPP: host `127.0.0.1`, base `proyectofinaldesarrollo`, usuario `root`, contraseña vacía. No contiene credenciales personales.
4. Habilitar `mod_rewrite` y `AllowOverride All` en Apache.
5. Abrir `http://localhost/proyectofinaldesarrollo/`.

Acceso demo: **admin@admin.com** / **admin1234**. Administrador activo, único principal y sin cambio obligatorio de contraseña. La contraseña está hasheada en el SQL.

El SQL representa directamente el esquema final: una instalación nueva no necesita aplicar migraciones. Incluye roles y medios de pago, sin productos ni operaciones comerciales de prueba. Las dependencias PHP están incluidas; no hace falta ejecutar Composer.

## Funcionalidad incluida

Incluye comentarios didácticos y las correcciones del profesor: protección del principal, Productos con stock, confirmaciones de baja, borrador de carrito por usuario/caja, rectificación transaccional y auditable de ventas en cajas abiertas, acceso del vendedor a ventas propias y badge de Reposición. El principal se identifica visualmente como `Principal`, con baja protegida.

Compras es exclusiva de ADMINISTRADOR/DUEÑO: crea pedidos pendientes sin mover stock. RECEPCIONISTA no accede a Compras; controla los pendientes desde Recepción y solo la aceptación incorpora stock. El rechazo no aumenta existencias. VENDEDOR no accede a Compras ni Recepción.

También incluye sesiones de caja simplificadas para vendedor, catálogo/categorías de consulta por rol, Alt+Q/Alt+O para consulta rápida, mantenimiento de productos para recepción, auditoría de catálogo y decomisos con responsable/motivo. La instalación final ya contiene la tabla de auditoría; no necesita migraciones.

El POS final mantiene el carrito con scroll interno, resumen compacto y Supr para quitar la fila seleccionada sin actuar al escribir o cobrar. Los estados del catálogo son compactos y las tablas/modal de consulta se adaptan a pantallas pequeñas.

El lector EAN reutilizable está disponible en Productos, Compras, Recepción y Reposición; la antigua consulta de Stock redirige a Productos. Los controles de cámara se muestran por debajo de 768 px. La cámara requiere permiso y HTTPS confiable al acceder desde otro dispositivo. Por HTTP sigue funcionando el ingreso manual. POS conserva lector físico, teclado y atajos, sin cámara.

Se usa BarcodeDetector cuando está disponible y ZXing local como alternativa, con licencia en `public/lib/zxing/LICENSE`. No requiere npm ni compilación.

La entrega se genera desde el estado actual del proyecto original, incluidos cambios pendientes de commit, y se valida aisladamente. No incluye bases personales, backups, pruebas ni documentos internos.
