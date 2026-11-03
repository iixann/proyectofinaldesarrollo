# Identidad Singular — Sistema de Gestión de Vinería

Proyecto académico en PHP puro, MVC del profesor, PDO/MariaDB, Bootstrap 5 y JavaScript. No utiliza framework de aplicación, ORM, Service ni Repository. La referencia `docs/referencia_mvc_profesor/MVC/` permanece intacta.

## Funcionalidad disponible

- Login, logout, sesiones, usuarios con múltiples roles y autorización centralizada.
- Categorías, proveedores y productos: alta, edición, baja lógica y reactivación.
- Código EAN obligatorio, único y textual; categorías y proveedores N:M del producto.
- Stock actual, mínimos/deseables, ajustes y trazabilidad mediante movimientos.
- Compras con detalles históricos, incremento de stock y movimientos COMPRA.
- Cajas físicas y sesiones de apertura/cierre con arqueo e histórico.
- POS: sesión abierta, escaneo EAN, búsqueda manual, carrito y cobro combinado.
- Venta transaccional: precios reales, detalle, pagos, descuento de stock y movimientos VENTA.
- Histórico de ventas con filtro de fechas, detalle y ticket imprimible/reimpresión mediante `window.print()`.

El circuito está integrado y verificado con DataBase/DOM simulados. Los módulos previos hasta el carrito fueron probados manualmente por el usuario. Cobro, histórico, ticket e integración final requieren todavía comprobarse en el navegador y la MariaDB local del comercio; no se ejecutaron ventas de prueba en la base real desde Codex.

## Entorno local

PHP 8.2 de LAMPP con PDO MySQL y mbstring, Apache con mod_rewrite y MariaDB. Bootstrap 5.3.2 y FontAwesome 6.5.1 se cargan por CDN, por lo que la presentación completa requiere Internet.

Dependencias:

```sh
/opt/lampp/bin/php /usr/bin/composer install --working-dir=app/librerias
```

Copiar `.env.example` a `.env` y completar `DB_HOST`, `DB_NAME`, `DB_USER` y `DB_PASS`. No versionar credenciales.

La base se administra **manualmente desde phpMyAdmin**. `database/schema.sql` documenta el esquema y `database/bootstrap.sql` los roles y medios iniciales; la aplicación no crea tablas ni importa SQL automáticamente. No hay credenciales iniciales públicas ni autorregistro. El administrador inicial se prepara manualmente con contraseña hasheada compatible con `password_verify()`.

Con el repositorio en `/opt/lampp/htdocs/proyectofinaldesarrollo` o un enlace equivalente, `AllowOverride All` y mod_rewrite habilitados, entrar a:

`http://localhost/proyectofinaldesarrollo/`

La raíz redirige al login. Tras autenticar, el inicio depende del rol: administrador → panel; dueño → categorías; vendedor → sesiones de caja; recepcionista → stock. La navbar muestra los módulos permitidos y cada acción valida autorización en servidor.

## Rutas principales

Todas bajo `/proyectofinaldesarrollo`:

| Ruta | Función |
|---|---|
| `/session/login` | Ingreso |
| `/admin/index` | Panel exclusivo de administrador |
| `/user/index` | Usuarios, exclusivo de administrador |
| `/categoria/index`, `/proveedor/index`, `/producto/index`, `/caja/index` | Administración, rol funcional DUEÑO |
| `/compra/index`, `/stock/index`, `/stock/movimientos` | Recepción/stock, rol RECEPCIONISTA |
| `/sesioncaja/index`, `/sesioncaja/abrir` | Apertura, cierre e histórico, rol VENDEDOR |
| `/pos/index` | POS con sesión ABIERTA en caja activa |
| `/venta/confirmar` | POST JSON interno protegido con sesión, rol y token |
| `/venta/index`, `/venta/ver/{id}`, `/venta/ticket/{id}` | Histórico, detalle e impresión, rol VENDEDOR |

ADMINISTRADOR hereda todos los permisos. DUEÑO hereda RECEPCIONISTA y VENDEDOR, sin los permisos exclusivos de ADMINISTRADOR. No existe rol CAJERO.

## Recorrido técnico

`.htaccess → public/index.php → App → Controller → Model → DataBase → PDO → MariaDB`

`Controller → Response → View`

El router conserva `$_GET['url']`, namespaces y acciones `actionNombre()`. Las consultas pasan parámetros separados del SQL. Los importes transaccionales se calculan en centavos enteros y se guardan como DECIMAL(12,2). El carrito es temporal; PHP reconstruye la venta desde precios y stock reales.

## Verificación y defensa

```sh
find app/core app/controllers app/models app/views public -name '*.php' -print0 | xargs -0 -n1 /opt/lampp/bin/php -l
git diff --check
```

- [Estado](docs/ESTADO_PROYECTO.md)
- [Roadmap](docs/ROADMAP.md)
- [Alcance POS](docs/POS_CAJA_VENTAS.md)
- [Demostración y pruebas locales](docs/DEMOSTRACION.md)
- [Guía de estudio](docs/GUIA_ESTUDIO.md)
- [Repaso breve de defensa](docs/DEFENSA.md)

La impresión académica no integra un controlador fiscal. No se implementaron cancelaciones, devoluciones, cuentas corrientes ni integraciones bancarias/fiscales. Los nombres de producto/caja/medio se consultan desde maestros y pueden reflejar renombrados; los precios, cantidades, importes, entregado y vuelto históricos permanecen guardados.
