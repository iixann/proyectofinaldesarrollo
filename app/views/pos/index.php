<!DOCTYPE html>
<html lang="es">
<head>
    <title>Consulta de productos | Sistema de Gestión de Vinería</title>
    <?php require APP_PATH . 'views/inc/head.php'; ?>
</head>
<body class="d-flex flex-column min-vh-100">
    <?php $seccionActual = 'consulta_pos'; require APP_PATH . 'views/inc/navbar.php'; ?>
    <main class="container py-4 py-md-5 flex-grow-1" id="contenido">
        <h1 class="h2 fw-bold mb-2">Consulta de productos</h1>
        <p class="text-secondary mb-4">Buscá por código exacto o por nombre para consultar precio y disponibilidad.</p>
        <section class="card border-0 shadow-sm rounded-4 mb-4"><div class="card-body p-4">
            <div class="row g-4">
                <div class="col-lg-6">
                    <form action="/proyectofinaldesarrollo/pos/buscar" method="get" class="formulario-busqueda">
                        <label class="form-label" for="codigo">Código EAN exacto</label>
                        <div class="input-group">
                            <input class="form-control" type="text" id="codigo" name="codigo" maxlength="50" autocomplete="off" required autofocus>
                            <button class="btn btn-primary" type="submit">Buscar EAN</button>
                        </div>
                    </form>
                </div>
                <div class="col-lg-6">
                    <form action="/proyectofinaldesarrollo/pos/buscar" method="get" class="formulario-busqueda">
                        <label class="form-label" for="nombre">Nombre del producto</label>
                        <div class="input-group">
                            <input class="form-control" type="text" id="nombre" name="nombre" maxlength="150" required>
                            <button class="btn btn-outline-primary" type="submit">Buscar nombre</button>
                        </div>
                    </form>
                </div>
            </div>
        </div></section>
        <p id="mensajeBusqueda" role="status" aria-live="polite" class="text-secondary">Ingresá una búsqueda. Por nombre se muestran hasta 30 resultados.</p>
        <section class="card border-0 shadow-sm rounded-4" id="resultadosBusqueda" hidden><div class="card-body p-3 p-md-4">
            <div class="table-responsive"><table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th scope="col">ID</th><th scope="col">Código EAN</th><th scope="col">Producto</th><th scope="col">Categoría</th><th scope="col">Precio</th><th scope="col">Stock</th><th scope="col">Disponibilidad</th></tr></thead>
                <tbody id="productosEncontrados"></tbody>
            </table></div>
        </div></section>
    </main>
    <?php require APP_PATH . 'views/inc/footer.php'; ?>
    <script>
        const mensajeBusqueda = document.getElementById('mensajeBusqueda');
        const resultadosBusqueda = document.getElementById('resultadosBusqueda');
        const productosEncontrados = document.getElementById('productosEncontrados');
        let ultimaConsulta = 0;
        document.querySelectorAll('.formulario-busqueda').forEach(function (formulario) {
            formulario.addEventListener('submit', async function (evento) {
                evento.preventDefault();
                const consulta = ++ultimaConsulta;
                const parametros = new URLSearchParams(new FormData(formulario));
                mensajeBusqueda.textContent = 'Buscando…';
                resultadosBusqueda.hidden = true;
                productosEncontrados.replaceChildren();
                try {
                    const respuesta = await fetch(formulario.action + '?' + parametros.toString(), {headers: {'Accept': 'application/json'}});
                    if (consulta !== ultimaConsulta) return;
                    if (respuesta.redirected) {
                        mensajeBusqueda.textContent = 'Iniciá sesión nuevamente para consultar productos.';
                        return;
                    }
                    if (!(respuesta.headers.get('content-type') || '').includes('application/json')) {
                        mensajeBusqueda.textContent = respuesta.status === 403 ? 'Acceso denegado.' : 'No se pudo consultar los productos.';
                        return;
                    }
                    const datos = await respuesta.json();
                    if (consulta !== ultimaConsulta) return;
                    if (!datos.ok) {
                        mensajeBusqueda.textContent = datos.mensaje;
                        return;
                    }
                    const productos = datos.producto ? [datos.producto] : datos.productos;
                    mensajeBusqueda.textContent = productos.length ? 'Productos encontrados: ' + productos.length + '.' : 'No se encontraron productos.';
                    productos.forEach(function (producto) {
                        const fila = document.createElement('tr');
                        [producto.id_producto, producto.codigo_barras ?? '', producto.nombre_producto, producto.categoria, '$ ' + producto.precio_venta, producto.stock_actual].forEach(function (valor) {
                            const celda = document.createElement('td');
                            celda.textContent = valor;
                            fila.appendChild(celda);
                        });
                        const disponibilidad = document.createElement('td');
                        const badge = document.createElement('span');
                        badge.className = 'badge ' + (producto.stock_actual <= 0 ? 'bg-danger-subtle text-danger-emphasis' : 'bg-success-subtle text-success-emphasis');
                        badge.textContent = producto.estado_stock;
                        disponibilidad.appendChild(badge);
                        fila.appendChild(disponibilidad);
                        productosEncontrados.appendChild(fila);
                    });
                    resultadosBusqueda.hidden = !productos.length;
                } catch (error) {
                    if (consulta === ultimaConsulta) mensajeBusqueda.textContent = 'No se pudo consultar los productos. Intentá nuevamente.';
                }
            });
        });
    </script>
</body>
</html>
