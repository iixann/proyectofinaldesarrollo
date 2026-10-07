# Proyecto Final Desarrollo

Base académica PHP puro + PDO + MariaDB + Composer/phpdotenv, siguiendo el MVC
del profesor. La referencia de `docs/referencia_mvc_profesor/MVC/` no es parte de
la aplicación y permanece intacta.

## Entorno LAMPP

Usar PHP de LAMPP, no el PHP del sistema:

```sh
/opt/lampp/bin/php /usr/bin/composer install --working-dir=app/librerias
```

Composer y sus dependencias están en `app/librerias/`. Copiar `.env.example` a
`.env` y completar las credenciales locales (`DB_HOST`, `DB_NAME`, `DB_USER`,
`DB_PASS`). El archivo `.env` no se versiona. Si se utilizaba la configuración
anterior, reemplazar `DB_PASSWORD` por `DB_PASS`.

La base se administra manualmente desde phpMyAdmin. `database/schema.sql` y
`database/bootstrap.sql` son solo documentación/referencia: la aplicación no
los ejecuta, no crea la base y no importa tablas. El listado espera una tabla
`usuarios` con las columnas `id`, `nombre` y `email`.

Las únicas tablas previstas son `usuarios`, `roles` y `usuarios_roles`. Los roles
son ADMINISTRADOR, DUEÑO, RECEPCIONISTA y VENDEDOR. El administrador inicial del
SQL es opcional y no tiene credenciales de login.

## Acceso con Apache de LAMPP

Con el proyecto en `/opt/lampp/htdocs/proyectofinaldesarrollo` (o un enlace a este
repositorio), `mod_rewrite` habilitado y `AllowOverride All`:

- `http://localhost/proyectofinaldesarrollo/`: HomeController, vista de inicio.
- `http://localhost/proyectofinaldesarrollo/home/index`: el mismo inicio.
- `http://localhost/proyectofinaldesarrollo/user/index`: usuarios existentes.

El `.htaccess` raíz dirige las solicitudes a `public/`. El de `public/` utiliza
`RewriteBase /proyectofinaldesarrollo/public/` para resolver `index.php` dentro
del directorio público y pasa la ruta lógica mediante `?url=...`. La URL visible
no necesita incluir `public`. No se usa REQUEST_URI como router.

## Recorrido MVC

`public/index.php` establece el directorio de trabajo y las constantes del
profesor, carga Composer/phpdotenv y Autoloader, e instancia `App`.
`App` resuelve controlador, acción y parámetros en su constructor a partir de
`$_GET['url']`, con HomeController y actionIndex como predeterminados.
Conserva la búsqueda en subdirectorios y la invocación con call_user_func_array.
Las rutas físicas usan `/` y los namespaces usan `\`.

El listado sigue este recorrido:

```text
App → UserController::actionIndex()
    → UserModel::getAllUsers() → Model::getAll()
    → DataBase::getRecords() → query() → connection() → PDO → MariaDB
    → UserController → Response::render() → app/views/user/index.php
```

Model define la tabla y delega las consultas en DataBase. DataBase ofrece
connection(), query(), getRecord() y getRecords(). Las filas son objetos;
getRecords() devuelve status y resultado, como en la referencia. Un fallo de
consulta muestra un error 500, no un falso listado vacío; el detalle se registra
en PHP. Response recibe directorio, nombre de vista y variables.

La vista lista ID, nombre y correo, escapados con htmlspecialchars. No consulta
ni asigna roles. Sin filas muestra un mensaje de listado vacío. Home no consulta
la base. Las rutas inexistentes pasan por action404(), sin redirección circular.

## Alcance

No hay login, autorregistro, altas, edición, eliminación ni asignación de roles
por interfaz. Los usuarios serán creados posteriormente por un ADMINISTRADOR.
No se incluyen otros módulos. No se inicia sesión porque esta etapa no la usa.

Para comprobar sintaxis de la aplicación (excluyendo dependencias y referencia):

```sh
find app/core app/controllers app/models app/views public -name '*.php' -print0 | xargs -0 -n1 /opt/lampp/bin/php -l
```

La prueba del listado real requiere que la base y la tabla hayan sido preparadas
manualmente en phpMyAdmin y que `.env` tenga las credenciales correctas.
