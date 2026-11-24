"""Recorre pantallas reales por rol y verifica POS en la base temporal de la prueba integral."""
from pathlib import Path
import json,time,base64,os

RESOLUCIONES=[(1920,1080),(1600,900),(1440,900),(1366,768),(1280,720),(1024,768),(360,800),(390,844),(412,915)]

# Reduce memoria y servicios de fondo solo en el perfil descartable de Firefox.
def preparar_perfil(perfil):
    ajustes={'fission.autostart':False,'dom.ipc.processCount':1,'dom.ipc.processCount.webIsolated':1,
        'browser.sessionhistory.max_total_viewers':0,'browser.cache.memory.capacity':4096,
        'browser.startup.page':0,'browser.sessionstore.resume_from_crash':False,
        'browser.aboutwelcome.enabled':False,'browser.newtabpage.enabled':False,
        'app.normandy.enabled':False,'app.shield.optoutstudies.enabled':False,
        'datareporting.policy.dataSubmissionEnabled':False,'toolkit.telemetry.enabled':False,
        'browser.safebrowsing.downloads.enabled':False,'network.captive-portal-service.enabled':False,
        'network.connectivity-service.enabled':False,'browser.ml.enable':False}
    (perfil/'user.js').write_text('\n'.join('user_pref('+json.dumps(k)+', '+json.dumps(v)+');' for k,v in ajustes.items()))

# Usa el navegador y la conexión descartable ya verificados por el ejecutor principal.
def revisar(control, contexto, base, trabajo, sql, comprobar):
    registros=[];capturas=trabajo/'revision_visual';capturas.mkdir(exist_ok=True)
    # Evalúa condiciones en la página real.
    def ev(s):return control.evaluar(contexto,s)
    # Navega a una ruta de la instancia temporal.
    def ir(ruta):control.comando('browsingContext.navigate',{'context':contexto,'url':base+ruta,'wait':'complete'})
    # Espera una condición visible sin modificar datos.
    def esperar(s):
        for _ in range(100):
            if ev(s):return
            time.sleep(.05)
        raise AssertionError(s)
    # Cambia el tamaño del navegador para medir el responsive.
    def viewport(w,h):control.comando('browsingContext.setViewport',{'context':contexto,'viewport':{'width':w,'height':h},'devicePixelRatio':1})
    # Envía el atajo al elemento que tiene el foco.
    def tecla(k,**extra):
        ev("(()=>{document.activeElement.dispatchEvent(new KeyboardEvent('keydown',"+json.dumps({'key':k,'bubbles':True,'cancelable':True,**extra})+"));return true})()")
    # Autentica cada rol con el formulario real.
    def entrar(correo,clave='prueba12345'):
        ir('session/logout');ir('session/login');ev("(()=>{document.querySelector('[name=correo]').value="+json.dumps(correo)+";document.querySelector('[name=contrasena]').value="+json.dumps(clave)+";document.querySelector('form').requestSubmit();return true})()")
        esperar("!location.pathname.endsWith('/session/login')")
    # Conserva una captura fuera del repositorio para revisión.
    def capturar(nombre):
        r=control.comando('browsingContext.captureScreenshot',{'context':contexto})
        (capturas/(nombre+'.png')).write_bytes(base64.b64decode(r['data']))
    # Los datos largos y el stock de revisión se crean solamente en la instancia temporal.
    ids=[]
    for i in range(25):
        code='0123456'+str(i).zfill(5);code+=str((-sum(int(x)*(1 if j%2==0 else 3) for j,x in enumerate(code)))%10)
        name=f'SKU presentación {i+1:02d}'
        if i==24:name+=' · '+('NombreLargo'*8)
        sql("INSERT IGNORE INTO productos(nombre_producto,codigo_barras,id_categoria,precio_venta,stock_actual,stock_minimo) VALUES('"+name+"','"+code+"',1,25,100,3)")
        pid=sql("SELECT id_producto FROM productos WHERE codigo_barras='"+code+"'")
        sql('INSERT IGNORE INTO productos_proveedores(id_producto,id_proveedor) VALUES('+pid+',1)')
        ids.append({'id_producto':int(pid),'codigo_barras':code,'nombre_producto':name,'precio_unitario':'25.00','stock_disponible':100,'cantidad':1})
    pid=str(ids[0]['id_producto'])
    sql("INSERT IGNORE INTO cajas(nombre) VALUES('Caja revisión admin'),('Caja revisión dueño')")
    for caja,correo in [('Caja revisión admin','admin@admin.com'),('Caja revisión dueño','dueno@test.invalid')]:
        sql("INSERT INTO caja_sesiones(id_caja,id_usuario_apertura,monto_inicial,estado) SELECT c.id_caja,u.id_usuario,0,'ABIERTA' FROM cajas c JOIN usuarios u ON u.email='"+correo+"' WHERE c.nombre='"+caja+"' AND NOT EXISTS (SELECT 1 FROM caja_sesiones cs WHERE cs.id_caja=c.id_caja AND cs.estado='ABIERTA')")
    sesiones={correo:sql("SELECT MAX(id_caja_sesion) FROM caja_sesiones s JOIN usuarios u ON u.id_usuario=s.id_usuario_apertura WHERE u.email='"+correo+"' AND s.estado='ABIERTA'") for correo in ['admin@admin.com','dueno@test.invalid','vendedor1@test.invalid']}
    # Compra pendiente adicional para revisar también el formulario de recepción sin enviarlo.
    sql("INSERT IGNORE INTO compras(id_proveedor,id_usuario,numero_comprobante,estado_recepcion,total) VALUES(1,1,'Revisión visual','PENDIENTE',25)")
    compra=sql("SELECT id_compra FROM compras WHERE id_proveedor=1 AND numero_comprobante='Revisión visual'")
    sql('INSERT INTO detalle_compras(id_compra,id_producto,cantidad,precio_unitario,subtotal) SELECT '+compra+','+pid+',1,25,25 WHERE NOT EXISTS (SELECT 1 FROM detalle_compras WHERE id_compra='+compra+' AND id_producto='+pid+')')
    comunes=['producto/index','categoria/index','session/cambiarPassword']
    operativas=['producto/crear','producto/editar/'+pid,'producto/historial/'+pid,'compra/index','compra/crear','compra/ver/1',
        'recepcion/index','recepcion/compra/'+compra,'recepcion/directa','recepcion/ver/1','reposicion/index','stock/ajustar/'+pid,'stock/decomiso/'+pid,'stock/movimientos/'+pid]
    gestion=['admin/index','categoria/crear','categoria/editar/1','proveedor/index','proveedor/crear','proveedor/editar/1','caja/index','caja/crear','caja/editar/1']
    caja=['sesioncaja/index','sesioncaja/abrir','venta/index','venta/ver/4','venta/ticket/4','venta/rectificar/4']
    roles=[('ADMINISTRADOR','admin@admin.com',comunes+operativas+gestion+caja+['user/index','user/crear','user/restablecerPassword/2']),
        ('DUEÑO','dueno@test.invalid',comunes+operativas+gestion+caja),('RECEPCIONISTA','recepcion@test.invalid',comunes+[r for r in operativas if not r.startswith('compra/')]),
        ('VENDEDOR','vendedor1@test.invalid',comunes+caja)]
    sql("INSERT IGNORE INTO productos(nombre_producto,codigo_barras,id_categoria,precio_venta,stock_actual,stock_minimo,estado) VALUES('Producto inactivo de revisión','0000000000099',1,25,0,0,0)")
    # Incluye estados inactivos y una reposición real para revisar sus badges/tablas.
    sql('UPDATE productos SET stock_actual=2 WHERE id_producto='+str(ids[-1]['id_producto']))
    ids[-1]['stock_disponible']=2
    sql("INSERT IGNORE INTO categorias(nombre_categoria,estado) VALUES('Categoría de demostración inactiva',0)")
    sql("INSERT IGNORE INTO proveedores(razon_social,estado) VALUES('Proveedor de demostración inactivo',0)")
    sql("INSERT INTO auditoria_operaciones(id_usuario,accion,entidad,id_entidad,detalle) VALUES(5,'ALTA','PRODUCTO',"+pid+",'Carga exclusiva de revisión temporal con un detalle de auditoría largo para comprobar su lectura.')")
    sql("INSERT INTO movimientos_stock(id_producto,id_usuario,tipo_movimiento,cantidad,stock_anterior,stock_nuevo,observacion) VALUES("+pid+",5,'AJUSTE_ENTRADA',100,0,100,'Carga exclusiva de revisión temporal para presentar el historial de stock.')")
    # Permite aislar un rol o POS al diagnosticar; por defecto recorre todos.
    rol_unico=os.environ.get('SOLO_ROL')
    if rol_unico: roles=[] if rol_unico=='POS' else [r for r in roles if r[0]==rol_unico]
    rutas_dirigidas=os.environ.get('RUTAS_REVISION')
    if rutas_dirigidas:
        permitidas=rutas_dirigidas.split(';')
        roles=[(r,c,[p for p in rutas if p in permitidas]) for r,c,rutas in roles]
    # Mide límites visibles; el desplazamiento interno de tablas sí está permitido.
    medir="""(()=>{const visible=e=>e.getClientRects().length&&getComputedStyle(e).visibility!=='hidden';const badges=[...document.querySelectorAll('.badge')].filter(visible);
    return {width:innerWidth,client:document.documentElement.clientWidth,height:innerHeight,page:document.documentElement.scrollWidth,title:document.title,
    error:/Fatal error:|Warning:|No se pudo completar la solicitud|Acceso denegado/.test(document.body.innerText),
    badges:badges.filter(e=>e.getBoundingClientRect().height>parseFloat(getComputedStyle(e).lineHeight)+parseFloat(getComputedStyle(e).paddingTop)+parseFloat(getComputedStyle(e).paddingBottom)+2).map(e=>e.textContent),
    userLink:!!document.querySelector('nav a[href$="/user/index"]'),actions:!!document.querySelector('main a[href*="/producto/editar/"]'),
    cameras:[...document.querySelectorAll('[data-escanear-ean]')].map(e=>e.getBoundingClientRect().height>0)};})()"""
    for rol,correo,rutas in roles:
        entrar(correo,'admin1234' if rol=='ADMINISTRADOR' else 'prueba12345')
        if correo in sesiones and not rutas_dirigidas:
            sid=sesiones[correo];rutas=rutas+['sesioncaja/ver/'+sid,'sesioncaja/cerrar/'+sid,'pos/index?sesion='+sid]
        # Mantiene cada resolución mientras recorre las vistas, evitando cientos de redimensiones.
        for w,h in RESOLUCIONES:
            viewport(w,h)
            for ruta in rutas:
                ir(ruta);m=ev(medir);m.update(rol=rol,ruta=ruta)
                assert not m['error'] and m['title'],m
                assert m['page']<=m['client']+1,('Overflow horizontal',m)
                assert not m['badges'],('Badge partido',m)
                if rol!='ADMINISTRADOR':assert not m['userLink'],m
                if rol=='VENDEDOR':assert not m['actions'],m
                if m['cameras']:assert all(m['cameras'])==(w<768),(rol,ruta,w,m['cameras'])
                registros.append(m)
                if w in [1366,390]:
                    capturar(rol+'-'+ruta.replace('/','_')+'-'+str(w))
            print(rol, str(w)+'x'+str(h), len(rutas), 'pantallas OK', flush=True)
        # Revisa también el menú móvil desplegado y la consulta con texto largo.
        ir('producto/index');viewport(390,844)
        ev("(()=>{document.querySelector('.navbar-toggler').click();return true})()")
        esperar("document.querySelector('.navbar-collapse').classList.contains('show')")
        assert ev("document.documentElement.scrollWidth<=innerWidth+1"),(rol,'navbar móvil')
        capturar(rol+'-navbar-movil')
        ev("(()=>{document.querySelector('.navbar-toggler').click();return true})()")
        time.sleep(.4)
        tecla('q',altKey=True)
        esperar("document.getElementById('consultaRapida').classList.contains('show')")
        ev("(()=>{document.getElementById('eanConsultaRapida').value="+json.dumps(ids[-1]['codigo_barras'])+";document.getElementById('formConsultaRapida').requestSubmit();return true})()")
        esperar("document.getElementById('resultadoConsultaRapida').textContent.includes('NombreLargo')")
        modal=ev("(()=>{const m=document.querySelector('#consultaRapida .modal-content'),b=m.querySelector('.modal-body'),r=m.getBoundingClientRect();return {top:r.top,bottom:r.bottom,height:innerHeight,width:b.clientWidth,scroll:b.scrollWidth}})()")
        assert modal['scroll']<=modal['width']+1 and modal['top']>=0 and modal['bottom']<=modal['height']+1,(rol,'Consulta modal larga',modal)
        capturar(rol+'-consulta-movil')
        ev("(()=>{bootstrap.Modal.getInstance(document.getElementById('consultaRapida')).hide();return true})()")
        time.sleep(.4)
        comprobar(True,rol+': '+str(len(rutas))+' pantallas sin overflow ni badges partidos a nueve resoluciones')
    # Los lotes de pantallas no repiten ventas ni pruebas de carrito ya verificadas.
    if os.environ.get('SOLO_PANTALLAS') == '1':
        (trabajo/'estabilizacion_visual.json').write_text(json.dumps({'pantallas':len(registros),'resoluciones':RESOLUCIONES,'vistas':registros,'pos':[],'capturas':str(capturas)},ensure_ascii=False,indent=2))
        return
    # El carrito se restaura con datos de productos reales, sin confirmar ninguna venta todavía.
    entrar('admin@admin.com','admin1234');ruta_pos='pos/index?sesion='+sesiones['admin@admin.com'];ir(ruta_pos)
    # Prepara un borrador de carrito con productos de la base temporal.
    def cargar(n,seleccion='primera'):
        sel=str(ids[0]['id_producto']) if seleccion=='primera' and n else seleccion if n else None
        ev("(()=>{const t=document.getElementById('terminalPos');CarritoTemporal.crear(t.dataset.usuario,t.dataset.sesion,t.dataset.contexto).guardar("+json.dumps(ids[:n])+","+json.dumps(sel)+");return true})()")
        ir(ruta_pos);esperar('document.querySelectorAll("#lineasCarrito tr").length==='+str(n))
    geometria="""(()=>{const rect=s=>{const r=document.querySelector(s).getBoundingClientRect();return {top:r.top,bottom:r.bottom,height:r.height}};
    return {page:document.documentElement.scrollHeight,width:document.documentElement.scrollWidth,table:rect('#tablaCarrito'),summary:rect('.pos-total-venta'),button:rect('#abrirCobro'),rows:document.querySelectorAll('#lineasCarrito tr').length};})()"""
    estados=[]
    for n in [0,1,5,15,25]:
        cargar(n)
        for w,h in RESOLUCIONES[:6]:
            viewport(w,h);g=ev(geometria)
            assert g['page']<=h+1 and g['width']<=w+1,('POS fuera de pantalla',n,w,g)
            assert g['summary']['height']<=65 and g['button']['bottom']<=h,('Resumen no compacto',n,w,g)
            if n:assert g['table']['height']>=190 and g['table']['bottom']<=g['summary']['top']+1,('Resumen tapa carrito',n,w,g)
            estados.append({'filas':n,'w':w,'h':h,**g})
            if w in [1366,1280,1024] and n in [0,5,25]:capturar('POS-'+str(w)+'-'+str(n))
    comprobar(True,'POS: 0/1/5/15/25 filas en seis resoluciones, scroll interno y resumen sin superposición')
    viewport(1366,768)
    # Lee el orden actual de las líneas del carrito.
    def filas():return ev("[...document.querySelectorAll('#lineasCarrito tr')].map(e=>e.dataset.producto)")
    # Selecciona una fila como al hacer clic en el nombre.
    def pulsar_celda(indice):ev("(()=>{document.querySelectorAll('#lineasCarrito tr')["+str(indice)+"].cells[0].click();return true})()")
    for indice in [0,2,4]:
        cargar(5);antes=filas();pulsar_celda(indice);tecla('Delete');despues=filas()
        comprobar(despues==antes[:indice]+antes[indice+1:], 'Supr elimina solo fila '+str(indice+1))
        esperar("document.querySelector('.pos-seleccionada')!==null")
        comprobar(ev("document.getElementById('cantidadArticulos').textContent==='4 artículos' && document.getElementById('totalCarrito').textContent.includes('100,00')"),'Supr recalcula unidades/total')
        comprobar(ev("(()=>{const k=Object.keys(sessionStorage).find(k=>k.startsWith('pos-carrito:v1:')&&k.endsWith(':"+sesiones['admin@admin.com']+"'));const d=JSON.parse(sessionStorage.getItem(k));return d.lineas.length===4&&d.seleccionada===document.querySelector('.pos-seleccionada').dataset.producto})()"),'Supr persiste borrador y selección vecina')
    cargar(1);pulsar_celda(0);tecla('Delete');comprobar(not filas(),'Supr elimina la única fila')
    cargar(5,None);ev("(()=>{document.activeElement.blur();return true})()");tecla('Delete');comprobar(len(filas())==5,'Supr sin selección no elimina nada')
    cargar(5);pulsar_celda(1)
    for selector in ['#codigo','#nombre','#lineasCarrito input','#sesionPos']:
        ev("(()=>{const e=document.querySelector("+json.dumps(selector)+");e.dispatchEvent(new KeyboardEvent('keydown',{key:'Delete',bubbles:true,cancelable:true}));return true})()")
        comprobar(len(filas())==5,'Supr no actúa al editar '+selector)
    ev("(()=>{const e=document.createElement('textarea');e.id='textoPrueba';document.getElementById('contenido').append(e);e.focus();return true})()");tecla('Delete');comprobar(len(filas())==5,'Supr no actúa en textarea');ev("(()=>{document.getElementById('textoPrueba').remove();return true})()")
    pulsar_celda(1);tecla('Delete',repeat=True);comprobar(len(filas())==5,'Mantener Supr pulsado no borra varias filas')
    pulsar_celda(2);ir('producto/index');ir(ruta_pos);comprobar(len(filas())==5 and ev("document.querySelector('.pos-seleccionada').dataset.producto")==str(ids[2]['id_producto']),'Restaura carrito y selección al volver')
    pulsar_celda(2);tecla('Delete');comprobar(len(filas())==4,'Supr funciona tras restaurar carrito')
    tecla('F8');esperar("document.getElementById('cobroPos').classList.contains('show')");tecla('Delete');comprobar(len(filas())==4,'Supr no elimina durante pagos');tecla('Escape')
    # Confirma una venta de revisión separada, con búsqueda real, pago combinado y vuelto.
    cargar(0);tecla('F2')
    for _ in range(2):
        ev("(()=>{document.getElementById('codigo').value="+json.dumps(ids[0]['codigo_barras'])+";document.getElementById('buscarEan').requestSubmit();return true})()")
        esperar("document.querySelector('#lineasCarrito input') && document.querySelector('#lineasCarrito input').value==='"+str(_+1)+"'")
    comprobar(len(filas())==1,'EAN repetido suma cantidad sin duplicar fila')
    tecla('F3');ev("(()=>{document.getElementById('nombre').value='SKU presentación 02';document.getElementById('buscarNombre').requestSubmit();return true})()")
    esperar("document.querySelector('#productosEncontrados button')!==null");ev("(()=>{document.querySelector('#productosEncontrados button').click();return true})()");esperar("document.querySelectorAll('#lineasCarrito tr').length===2")
    tecla('F8');esperar("document.getElementById('cobroPos').classList.contains('show')");tecla('F4')
    ev("(()=>{const e=document.querySelector('[data-entregado]');e.value='100.00';e.dispatchEvent(new Event('input',{bubbles:true}));return true})()")
    comprobar(ev("document.querySelector('[data-vuelto]').textContent.includes('25,00')"),'Efectivo calcula vuelto correcto')
    ev("(()=>{document.getElementById('volverVenta').click();return true})()");esperar("!document.getElementById('cobroPos').classList.contains('show')")
    comprobar(len(filas())==2,'Volver a Armado conserva productos')
    tecla('F8');esperar("document.getElementById('cobroPos').classList.contains('show')");tecla('F5')
    ev("(()=>{const e=document.querySelector('.pos-medio:not([hidden]) [data-monto]');e.value='25.00';e.dispatchEvent(new Event('input',{bubbles:true}));return true})()")
    tecla('F4');ev("(()=>{const e=document.querySelector('[data-entregado]');e.value='100.00';e.dispatchEvent(new Event('input',{bubbles:true}));return true})()")
    comprobar(ev("document.querySelector('[data-vuelto]').textContent.includes('50,00') && !document.getElementById('confirmarVenta').disabled"),'Pago combinado conserva importe aplicado y vuelto')
    tecla('F8');esperar("document.querySelectorAll('#lineasCarrito tr').length===0")
    nueva=sql('SELECT MAX(id_venta) FROM ventas')
    comprobar(sql('SELECT total FROM ventas WHERE id_venta='+nueva)=='75.00','Venta de revisión confirmada con total correcto')
    comprobar(sql('SELECT SUM(monto) FROM pagos_ventas WHERE id_venta='+nueva)=='75.00','Pagos combinados coinciden con total backend')
    comprobar(ev("(()=>{const t=document.getElementById('terminalPos');return CarritoTemporal.crear(t.dataset.usuario,t.dataset.sesion,t.dataset.contexto).leer()===null})()"),'Venta de revisión limpia borrador persistente')
    link=ev("document.getElementById('verUltimaVenta').getAttribute('href')");comprobar('/venta/ticket/' in link,'F10 tiene ticket de la venta confirmada')
    ir('venta/ticket/'+nueva);comprobar(ev("document.body.innerText.includes('75.00')"),'Ticket muestra el total confirmado')
    (trabajo/'estabilizacion_visual.json').write_text(json.dumps({'pantallas':len(registros),'resoluciones':RESOLUCIONES,'vistas':registros,'pos':estados,'capturas':str(capturas)},ensure_ascii=False,indent=2))
    print('REVISIÓN VISUAL:',len(registros),'combinaciones de rol/pantalla/resolución;',len(estados),'estados POS',flush=True)
