"""Amplía la prueba integral con permisos, disponibilidad, decomiso y auditoría aislados."""
import re

# Usa clientes autenticados reales y la misma base temporal del ejecutor principal.
def probar_roles(pedir, comprobar, sql, token, admin, dueno, vendedor, recepcion):
    for usuario in [vendedor, recepcion]:
        categorias = pedir(usuario, 'GET', 'categoria/index').text
        comprobar('/categoria/crear' not in categorias and '/categoria/editar/' not in categorias, 'Categorías solo consulta en UI')
        for ruta in ['crear', 'editar/1']:
            pedir(usuario, 'GET', 'categoria/'+ruta, 403)
        for ruta in ['guardar', 'actualizar/1', 'baja/1', 'reactivar/1']:
            pedir(usuario, 'POST', 'categoria/'+ruta, 403, data={'nombre_categoria':'No permitido'})
    catalogo = pedir(vendedor, 'GET', 'producto/index').text
    comprobar('Stock actual' in catalogo and 'Proveedores' in catalogo and '/producto/crear' not in catalogo
        and '/stock/ajustar/' not in catalogo and '/stock/decomiso/' not in catalogo, 'Vendedor consulta catálogo sin acciones de escritura')
    for ruta in ['crear', 'editar/1', 'historial/1']:
        pedir(vendedor, 'GET', 'producto/'+ruta, 403)
    for ruta in ['guardar', 'actualizar/1', 'baja/1', 'reactivar/1']:
        pedir(vendedor, 'POST', 'producto/'+ruta, 403, data={'nombre_producto':'Intento'})
    for ruta in ['stock/guardarAjuste/1', 'stock/guardarDecomiso/1', 'compra/asociarProductoRapido']:
        pedir(vendedor, 'POST', ruta, 403)
    for ruta in ['compra/index','recepcion/index','reposicion/index','user/index','stock/decomiso/1']:
        pedir(vendedor, 'GET', ruta, 403)
    consultar = pedir(vendedor, 'GET', 'pos/consultarProducto?ean=4006381333931').json()
    comprobar(consultar['ok'] and consultar['producto']['categoria']=='Vinos'
        and consultar['producto']['proveedores'], 'Consulta rápida vendedor devuelve producto, EAN, precio, stock, categoría y proveedores')
    pedir(vendedor, 'POST', 'pos/consultarProducto', 405)
    for c in [admin,dueno]:
        listado = pedir(c,'GET','sesioncaja/index').text
        comprobar('Monto inicial' in listado and 'Usuario apertura' in listado, 'Supervisor conserva sesiones administrativas completas')
        pedir(c,'GET','sesioncaja/ver/2')
    operativa = pedir(vendedor,'GET','sesioncaja/index').text
    comprobar(all(t in operativa for t in ['Mi sesión abierta','No disponible','Disponible','Ir al POS','Cerrar mi sesión'])
        and all(t not in operativa for t in ['vendedor2','Monto inicial','Usuario apertura','observacion_cierre','/sesioncaja/ver/2']),
        'Listado vendedor no entrega identidad, montos ni acciones de sesiones ajenas')
    pedir(vendedor,'GET','sesioncaja/ver/1')
    for ruta in ['ver/2','cerrar/2']:
        pedir(vendedor,'GET','sesioncaja/'+ruta,403)
    pedir(vendedor,'POST','sesioncaja/guardarCierre/2',403,data={'efectivo_contado':'0'})
    pedir(vendedor,'POST','sesioncaja/guardarApertura',422,data={'id_caja':'2','monto_inicial':'0'})
    libres=pedir(vendedor,'GET','sesioncaja/abrir?caja=3').text
    comprobar('value="3" selected' in libres and 'value="2"' not in libres, 'Abrir solo ofrece cajas libres y conserva selección')
    # Alta/edición/baja/reactivación sobre otro producto, sin alterar la venta de regresión.
    datos={'nombre_producto':'Producto operativo','codigo_barras':'0000000000002','id_categoria':'1','precio_venta':'20.00','stock_minimo':'0','proveedores[]':'1'}
    pedir(recepcion,'GET','producto/crear')
    pedir(recepcion,'POST','producto/guardar',303,data=datos)
    pid=sql("SELECT id_producto FROM productos WHERE codigo_barras='0000000000002'")
    pedir(recepcion,'GET','producto/editar/'+pid)
    datos['descripcion']='Cambio operativo';datos['stock_actual']='999'
    pedir(recepcion,'POST','producto/actualizar/'+pid,303,data=datos)
    comprobar(sql('SELECT stock_actual FROM productos WHERE id_producto='+pid)=='0','Recepción edita catálogo sin stock arbitrario')
    pedir(recepcion,'POST','producto/baja/'+pid,303)
    comprobar(sql('SELECT estado FROM productos WHERE id_producto='+pid)=='0','Baja solo afecta catálogo')
    pedir(recepcion,'POST','producto/reactivar/'+pid,303)
    actor=sql("SELECT id_usuario FROM usuarios WHERE email='recepcion@test.invalid'")
    acciones=sql('SELECT accion,id_usuario FROM auditoria_operaciones WHERE id_entidad='+pid+' ORDER BY id_auditoria')
    comprobar(acciones=='\n'.join(a+'\t'+actor for a in ['ALTA','EDICION','BAJA','REACTIVACION']), 'Cuatro cambios de catálogo auditados con usuario autenticado')
    historial=pedir(recepcion,'GET','producto/historial/'+pid).text
    comprobar(all(a in historial for a in ['ALTA','EDICION','BAJA','REACTIVACION']), 'Historial administrativo consultable por recepción')
    pedir(recepcion,'POST','stock/guardarAjuste/'+pid,303,data={'tipo':'AJUSTE_ENTRADA','cantidad':'5','observacion':'Ingreso de prueba'})
    pagina=pedir(recepcion,'GET','stock/decomiso/'+pid).text
    csrf=token(pagina,'csrf_decomiso')
    pedir(recepcion,'POST','stock/guardarDecomiso/'+pid,403,data={'csrf_decomiso':'otro','cantidad':'1','motivo':'Rotura'})
    for cantidad,motivo in [('6','Rotura'),('0','Rotura'),('1',''),('1','x'*256),('abc','Rotura')]:
        pedir(recepcion,'POST','stock/guardarDecomiso/'+pid,422,data={'csrf_decomiso':csrf,'cantidad':cantidad,'motivo':motivo})
    comprobar(sql('SELECT stock_actual FROM productos WHERE id_producto='+pid)=='5', 'Decomisos inválidos no modifican stock')
    pedir(recepcion,'POST','stock/guardarDecomiso/'+pid,303,data={'csrf_decomiso':csrf,'cantidad':'2','motivo':'Rotura de botellas'})
    comprobar(sql('SELECT stock_actual,estado FROM productos WHERE id_producto='+pid)=='3\t1','Decomiso descuenta unidades sin dar de baja producto')
    movimiento=sql("SELECT cantidad,stock_anterior,stock_nuevo,id_usuario,observacion FROM movimientos_stock WHERE tipo_movimiento='DECOMISO' AND id_producto="+pid)
    comprobar(movimiento=='2\t5\t3\t'+actor+'\tRotura de botellas','Decomiso trazable con unidades, responsable, motivo y fecha por defecto')
    pedir(recepcion,'POST','stock/guardarDecomiso/'+pid,403,data={'csrf_decomiso':csrf,'cantidad':'2','motivo':'Reenvío'})
    pedir(recepcion,'GET','stock/movimientos/'+pid)
    for c in [admin,dueno]:pedir(c,'GET','stock/decomiso/'+pid)
    pedir(recepcion,'GET','compra/index',403)
    for ruta in ['recepcion/index','reposicion/index']:pedir(recepcion,'GET',ruta)
    # Si falta la auditoría, la transacción debe impedir guardar un producto sin responsable.
    antes=sql('SELECT COUNT(*) FROM productos')
    sql('RENAME TABLE auditoria_operaciones TO auditoria_reserva')
    try:
        intento=dict(datos, codigo_barras='0000000000003', nombre_producto='No debe persistir')
        pedir(recepcion,'POST','producto/guardar',500,data=intento)
    finally:
        sql('RENAME TABLE auditoria_reserva TO auditoria_operaciones')
    comprobar(sql('SELECT COUNT(*) FROM productos')==antes, 'Fallo de auditoría revierte el alta completa y sus asociaciones')
