# Estado del proyecto

## Último commit estable

feat: mejorar interfaz visual del sistema

Commit de cierre visual de esta etapa. Para obtener su hash: `git log -1 --format=%h --grep="^feat: mejorar interfaz visual del sistema$"`.

Base funcional anterior: `0050b9a` — `feat: implementar autenticacion y gestion inicial de usuarios`.

## Terminado

- Arquitectura MVC basada en la referencia del profesor
- .htaccess y Front Controller
- Autoloader
- Router App
- Conexión PDO mediante DataBase
- Base inicial con usuarios, roles y usuarios_roles
- Login
- Sesión
- Logout
- Panel ADMINISTRADOR
- Listado de usuarios
- Creación de usuarios
- Asignación de uno o varios roles
- Roles ADMINISTRADOR, DUEÑO, RECEPCIONISTA y VENDEDOR
- Documentación SQL inicial
- Dashboard visual terminado con accesos rápidos y módulos pendientes sin enlaces a rutas inexistentes
- Login, gestión de usuarios y creación de usuarios con interfaz Bootstrap coherente
- Includes reutilizables de vistas: head.php, navbar.php y footer.php en app/views/inc/
- Identidad visual de vinería con color Malbec #6D1A36 aplicado en los estilos compartidos

## En desarrollo

Ninguno.

## Próximo objetivo

Módulo Categorías completo.

## Siguiente objetivo

Módulo Proveedores completo.

## Reglas del proyecto

- PHP puro
- MVC del profesor
- PDO centralizado en DataBase
- Sin Service
- Sin Repository
- Sin ORM
- Sin DTO
- Sin frameworks de aplicación; Bootstrap solamente como capa visual
- Variables propias preferentemente en español
- HTML funcional primero
- Bootstrap/CSS aplicado al recorrido actual; mantener la funcionalidad como prioridad
- Sin autorregistro
- ADMINISTRADOR tiene todos los privilegios
- DUEÑO puede realizar funciones de DUEÑO, RECEPCIONISTA y VENDEDOR, pero no las exclusivas de ADMINISTRADOR

## Tecnologías

- PHP 8.2 / LAMPP
- MariaDB/MySQL
- PDO
- Composer
- phpdotenv
- HTML
- Bootstrap 5.3.2 por CDN
- FontAwesome 6.5.1 por CDN
- CSS compartido con variables de Bootstrap para la identidad Malbec
- Git / GitHub

## Dinámica de trabajo

Cada módulo debe seguir:

Objetivo concreto
→ implementación
→ prueba en navegador
→ corrección
→ explicación de la lógica importante
→ actualización de documentación
→ commit
→ siguiente módulo

Después de cada módulo terminado, actualizar:

- ESTADO_PROYECTO.md
- ROADMAP.md si corresponde
- DEFENSA.md con conceptos nuevos realmente utilizados

Mantener estos documentos breves y útiles para retomar el trabajo.
