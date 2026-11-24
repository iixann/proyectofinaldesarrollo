"""Prueba separación Dueño compra / Recepcionista recibe en una MariaDB independiente; nunca carga la base real."""
from pathlib import Path
import os,sys,json,re,html,tempfile,socket,subprocess,time,shutil
import requests
sys.dont_write_bytecode=True
ROOT=Path(os.environ.get('COMPRA_PRUEBA_RAIZ',Path(__file__).resolve().parents[1]))
# Reserva puertos exclusivos para los dos servidores descartables.
def puerto():
    with socket.socket() as s:s.bind(('127.0.0.1',0));return s.getsockname()[1]
# Extrae el token real generado en la vista, sin eludir protección CSRF.
def token(pagina,nombre='csrf_compra'):
    m=re.search(r'name="'+re.escape(nombre)+r'"[^>]*value="([^"]+)"',pagina)
    if not m:raise AssertionError('No se encontró csrf_compra')
    return html.unescape(m[1])
# Toda escritura usa el socket temporal comprobado antes de importar el instalador.
def ejecutar():
    trabajo=Path(tempfile.mkdtemp(prefix='compra-recepcionista-',dir=os.environ.get('DIRECTORIO_PRUEBAS','/tmp')))
    datos=trabajo/'mariadb';datos.mkdir();sock=trabajo/'mysql.sock';db=None;web=None;checks=[];clientes={}
    def check(ok,nombre):
        if not ok:raise AssertionError(nombre)
        checks.append(nombre)
    def sql(texto):
        r=subprocess.run(['/opt/lampp/bin/mariadb','--no-defaults','--socket='+str(sock),'--user=root','--batch','--skip-column-names','--default-character-set=utf8mb4'],input=texto,text=True,capture_output=True)
        if r.returncode:raise RuntimeError(r.stderr)
        return r.stdout.strip()
    def negocio(texto):return sql('USE proyectofinaldesarrollo; '+texto)
    def pedir(rol,metodo,ruta,estado=200,**kw):
        r=clientes[rol].request(metodo,base+ruta,allow_redirects=False,timeout=20,**kw)
        check(r.status_code==estado,f'{rol}: {metodo} {ruta} → {r.status_code}')
        check(not any(x in r.text for x in ['Warning:','Fatal error:','SQLSTATE[']),'Respuesta sin errores PHP/SQL')
        return r
    try:
        r=subprocess.run(['/opt/lampp/bin/mariadb-install-db','--no-defaults','--basedir=/opt/lampp','--datadir='+str(datos),'--auth-root-authentication-method=normal','--skip-test-db'],capture_output=True,text=True)
        check(r.returncode==0,'Inicialización MariaDB aislada')
        portdb=puerto();logdb=(trabajo/'mysql.log').open('w')
        db=subprocess.Popen(['/opt/lampp/sbin/mysqld','--no-defaults','--basedir=/opt/lampp','--datadir='+str(datos),'--socket='+str(sock),'--port='+str(portdb),'--bind-address=127.0.0.1','--innodb-buffer-pool-size=16M','--innodb-log-file-size=8M','--aria-pagecache-buffer-size=8M','--key-buffer-size=1M'],stdout=logdb,stderr=logdb)
        for _ in range(100):
            if sock.exists():
                r=subprocess.run(['/opt/lampp/bin/mariadb','--no-defaults','--socket='+str(sock),'--user=root','-e','SELECT 1'],capture_output=True)
                if r.returncode==0:break
            if db.poll() is not None:raise RuntimeError((trabajo/'mysql.log').read_text())
            time.sleep(.1)
        else:raise RuntimeError('No inició la instancia aislada')
        check(str(datos) in sql('SELECT @@datadir'),'Instancia distinta de la base real')
        sql((ROOT/'database/instalacion_profesor.sql').read_text())
        negocio("INSERT INTO categorias(nombre_categoria) VALUES('Vinos'); INSERT INTO proveedores(razon_social) VALUES('Proveedor prueba'); INSERT INTO productos(nombre_producto,codigo_barras,id_categoria,precio_venta,stock_actual,stock_minimo) VALUES('Malbec prueba','4006381333931',1,100,10,2); INSERT INTO productos_proveedores(id_producto,id_proveedor) VALUES(1,1);")
        pw='pruebaaislada123';hashed=subprocess.check_output(['/opt/lampp/bin/php','-r',"echo password_hash('pruebaaislada123', PASSWORD_DEFAULT);"],text=True)
        for rol,email in [('DUEÑO','dueno@test.invalid'),('RECEPCIONISTA','recepcion@test.invalid'),('VENDEDOR','vendedor@test.invalid')]:
            negocio("INSERT INTO usuarios(nombre,apellido,email,password,estado,debe_cambiar_password) VALUES('Prueba','Aislada','"+email+"','"+hashed+"',1,0); INSERT INTO usuarios_roles(id_usuario,id_rol) SELECT LAST_INSERT_ID(),id_rol FROM roles WHERE nombre_rol='"+rol+"';")
        cfg={'DB_HOST':'127.0.0.1;port='+str(portdb),'DB_NAME':'proyectofinaldesarrollo','DB_USER':'root','DB_PASS':''};router=trabajo/'router.php'
        router.write_text("<?php\nchdir("+repr(str(ROOT))+");\n"+'\n'.join('$_ENV['+repr(k)+']='+repr(v)+';' for k,v in cfg.items())+'''\n$p=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);$p=substr($p,strlen('/proyectofinaldesarrollo/'));$f=realpath('public/'.$p);if($f&&str_starts_with($f,getcwd().'/public/')&&in_array(pathinfo($f,PATHINFO_EXTENSION),['css','js'])){header('Content-Type: '.(str_ends_with($f,'.css')?'text/css':'application/javascript'));readfile($f);exit;}$_GET['url']=$p;require 'public/index.php';''')
        portweb=puerto();sesiones=trabajo/'sesiones';sesiones.mkdir();logweb=(trabajo/'php.log').open('w')
        web=subprocess.Popen(['/opt/lampp/bin/php','-d','session.save_path='+str(sesiones),'-S','127.0.0.1:'+str(portweb),str(router)],cwd=ROOT,stdout=logweb,stderr=logweb);base='http://127.0.0.1:'+str(portweb)+'/proyectofinaldesarrollo/'
        for _ in range(50):
            try:requests.get(base+'session/login',timeout=1);break
            except requests.RequestException:time.sleep(.1)
        for rol,email,clave in [('ADMINISTRADOR','admin@admin.com','admin1234'),('DUEÑO','dueno@test.invalid',pw),('RECEPCIONISTA','recepcion@test.invalid',pw),('VENDEDOR','vendedor@test.invalid',pw)]:
            clientes[rol]=requests.Session();clientes[rol].trust_env=False
            pedir(rol,'POST','session/ingresar',302,data={'correo':email,'contrasena':clave})
        # Los recursos se comprueban desde la misma instalación aislada.
        for recurso in ['js/compra.js','js/ean-scanner.js','js/producto-acciones.js','js/pos.js','css/pos.css','css/ean-scanner.css']:
            pedir('ADMINISTRADOR','GET',recurso)
        for rol in ['ADMINISTRADOR','DUEÑO']:
            listado=pedir(rol,'GET','compra/index').text
            check('href="/proyectofinaldesarrollo/compra/index"' in listado,'Navbar Compras para '+rol)
            pedir(rol,'GET','compra/crear')
        # Recepción y vendedor no pueden ejecutar ninguna acción pública de Compra.
        for bloqueado in ['RECEPCIONISTA','VENDEDOR']:
            pagina=pedir(bloqueado,'GET','producto/index').text
            check('href="/proyectofinaldesarrollo/compra/index"' not in pagina,'Navbar sin Compras para '+bloqueado)
            for ruta in ['compra','compra/index','compra/crear','compra/ver/1']:pedir(bloqueado,'GET',ruta,403)
            for ruta in ['guardar','crearProveedorRapido','crearProductoRapido','consultarProductoRapido','asociarProductoRapido']:pedir(bloqueado,'POST','compra/'+ruta,403)
            pedir(bloqueado,'GET','compra/altaRapidaParaRecepcion',404)
        for ruta in ['recepcion','recepcion/index','recepcion/directa']:pedir('VENDEDOR','GET',ruta,403)
        for ruta in ['guardarDirecta','crearProveedorRapido','crearProductoRapido','consultarProductoRapido']:pedir('VENDEDOR','POST','recepcion/'+ruta,403)
        verificar_badge = None
        if os.environ.get('BADGE_RECEPCION')=='1':
            from badge_recepcion import probar
            verificar_badge = probar(pedir,negocio,token,check,base,trabajo)
        rol='DUEÑO';csrf=token(pedir(rol,'GET','compra/crear').text)
        pedir(rol,'POST','compra/guardar',403,data={'csrf_compra':'incorrecto'})
        proveedor=pedir(rol,'POST','compra/crearProveedorRapido',201,data={'csrf_compra':csrf,'razon_social':'Proveedor nuevo'}).json()['proveedor']['id_proveedor']
        consulta={'csrf_compra':csrf,'id_proveedor':str(proveedor),'codigo_barras':'4006381333931'}
        r=pedir(rol,'POST','compra/consultarProductoRapido',data=consulta).json();check(r['existe'] and not r['producto']['asociado'],'EAN existente detecta proveedor sin asociación')
        pedir(rol,'POST','compra/asociarProductoRapido',data={**consulta,'id_producto':'1'})
        r=pedir(rol,'POST','compra/consultarProductoRapido',data=consulta).json();check(r['producto']['asociado'],'Asociación operativa permitida')
        pedir(rol,'POST','compra/crearProductoRapido',201,data={'csrf_compra':csrf,'id_proveedor':str(proveedor),'codigo_barras':'7791234567890','nombre_producto':'Producto rápido','id_categoria':'1','precio_venta':'100.00','stock_minimo':'2'})
        check(negocio('SELECT stock_actual FROM productos WHERE id_producto=2')=='0','Alta rápida con stock cero')
        # Solo Recepción puede convertir una compra pendiente en ingreso físico de stock.
        for comprobante,decision in [('ACEPTAR','ACEPTADA'),('RECHAZAR','RECHAZADA')]:
            stock=negocio('SELECT stock_actual FROM productos WHERE id_producto=1');mov=negocio('SELECT COUNT(*) FROM movimientos_stock')
            pedir(rol,'POST','compra/guardar',303,data={'csrf_compra':csrf,'id_proveedor':str(proveedor),'numero_comprobante':comprobante,'renglones[0][id_producto]':'1','renglones[0][cantidad]':'2','renglones[0][precio_unitario]':'50.00'})
            cid=negocio("SELECT id_compra FROM compras WHERE numero_comprobante='"+comprobante+"'")
            check(negocio('SELECT estado_recepcion FROM compras WHERE id_compra='+cid)=='PENDIENTE','Compra creada PENDIENTE')
            check(negocio('SELECT stock_actual FROM productos WHERE id_producto=1')==stock and negocio('SELECT COUNT(*) FROM movimientos_stock')==mov,'Registrar compra no modifica stock ni movimientos')
            check(negocio('SELECT cantidad FROM detalle_compras WHERE id_compra='+cid)=='2','Detalle conserva las unidades compradas')
            for actor in ['ADMINISTRADOR','DUEÑO']:pedir(actor,'GET','compra/ver/'+cid)
            for bloqueado in ['RECEPCIONISTA','VENDEDOR']:pedir(bloqueado,'GET','compra/ver/'+cid,403)
            check(comprobante in pedir(rol,'GET','compra/index?numero_comprobante='+comprobante).text,'Filtro de compras disponible')
            check('/recepcion/compra/'+cid in pedir('RECEPCIONISTA','GET','recepcion/index').text,'Compra pendiente aparece en Recepción')
            formulario_recepcion=pedir('RECEPCIONISTA','GET','recepcion/compra/'+cid).text
            pedir('RECEPCIONISTA','POST','recepcion/guardarCompra/'+cid,303,data={'csrf_recepcion':token(formulario_recepcion,'csrf_recepcion'),'cantidades[1]':'2','decision':decision,'motivo_rechazo':'Daño en embalaje' if decision=='RECHAZADA' else ''})
            check(negocio('SELECT estado_recepcion FROM compras WHERE id_compra='+cid)==('RECEPCIONADA' if decision=='ACEPTADA' else 'RECHAZADA'),'Compra deja el estado correcto tras recibir')
            check(negocio('SELECT estado FROM recepciones WHERE id_compra='+cid)==decision,'Recepción guarda aceptación o rechazo')
            check(negocio('SELECT stock_actual FROM productos WHERE id_producto=1')==str(int(stock)+(2 if decision=='ACEPTADA' else 0)),'Stock aumenta solo en aceptación')
            check(negocio('SELECT COUNT(*) FROM movimientos_stock')==str(int(mov)+(1 if decision=='ACEPTADA' else 0)),'Movimiento se genera solo al aceptar')
            rid=negocio('SELECT id_recepcion FROM recepciones WHERE id_compra='+cid)
            detalle=pedir('RECEPCIONISTA','GET','recepcion/ver/'+rid).text
            check('/compra/ver/' not in detalle and 'Compra #'+cid in detalle,'Detalle de Recepción conserva origen sin enlace prohibido')
        # La recepción directa conserva sus altas y consulta sin usar URLs de Compra.
        directa=pedir('RECEPCIONISTA','GET','recepcion/directa').text
        check('/recepcion/crearProveedorRapido' in directa and '/recepcion/crearProductoRapido' in directa,'Formularios rápidos pertenecen a Recepción')
        csrf_directa=token(directa)
        pedir('RECEPCIONISTA','POST','recepcion/consultarProductoRapido',403,data={'csrf_compra':'incorrecto'})
        r=pedir('RECEPCIONISTA','POST','recepcion/consultarProductoRapido',data={'csrf_compra':csrf_directa,'id_proveedor':str(proveedor),'codigo_barras':'4006381333931'}).json()
        check(r['existe'] and r['producto']['asociado'],'Consulta EAN de Recepción sigue disponible')
        pd=pedir('RECEPCIONISTA','POST','recepcion/crearProveedorRapido',201,data={'csrf_compra':csrf_directa,'razon_social':'Proveedor directo'}).json()['proveedor']['id_proveedor']
        producto=pedir('RECEPCIONISTA','POST','recepcion/crearProductoRapido',201,data={'csrf_compra':csrf_directa,'id_proveedor':str(pd),'codigo_barras':'0000000000003','nombre_producto':'Producto directo','id_categoria':'1','precio_venta':'20.00','stock_minimo':'1'}).json()['producto']['id_producto']
        check(negocio('SELECT stock_actual FROM productos WHERE id_producto='+str(producto))=='0','Alta directa no adelanta stock')
        pedir('RECEPCIONISTA','POST','recepcion/guardarDirecta',303,data={'csrf_recepcion':token(directa,'csrf_recepcion'),'id_proveedor':str(pd),'decision':'ACEPTADA','renglones[0][id_producto]':str(producto),'renglones[0][cantidad]':'1'})
        check(negocio('SELECT stock_actual FROM productos WHERE id_producto='+str(producto))=='1','Recepción Directa conserva su ingreso válido')
        pedir('RECEPCIONISTA','POST','recepcion/asociarProductoRapido',404)
        if verificar_badge: verificar_badge(0)
        # La revisión de UI usa únicamente este servidor y sus usuarios descartables.
        if os.environ.get('COMPRA_PRUEBA_VISUAL')=='1':revisar_visual(base,trabajo,check)
        resultado={'resultado':'OK','comprobaciones':len(checks),'raiz':str(ROOT),'directorio':str(trabajo),'checks':checks}
        salida=Path(os.environ.get('COMPRA_PRUEBA_RESULTADO','/tmp/compra-recepcionista-original.json'));salida.write_text(json.dumps(resultado,ensure_ascii=False,indent=2));print(json.dumps({k:v for k,v in resultado.items() if k!='checks'}),flush=True)
    finally:
        if web:web.terminate();web.wait(timeout=10)
        if db:db.terminate();db.wait(timeout=20)
        shutil.rmtree(datos)
# Captura el listado y formulario reales con una sesión de recepción aislada.
def revisar_visual(base,trabajo,check):
    import base64
    from ean_scanner_visual import Bidi
    from estabilizacion_visual import preparar_perfil
    perfil=trabajo/'firefox';perfil.mkdir();preparar_perfil(perfil);log=(trabajo/'firefox.log').open('w')
    firefox=subprocess.Popen(['/snap/firefox/current/usr/lib/firefox/firefox','--headless','--no-remote','--profile',str(perfil),'--remote-debugging-port','9223','about:blank'],stdout=log,stderr=log)
    try:
        for _ in range(180):
            try:c=Bidi();break
            except OSError:time.sleep(.25)
        else:raise RuntimeError('No inició Firefox')
        c.comando('session.new',{'capabilities':{}});ctx=c.comando('browsingContext.getTree',{})['contexts'][0]['context']
        def ev(s):return c.evaluar(ctx,s)
        c.comando('browsingContext.navigate',{'context':ctx,'url':base+'session/login','wait':'complete'})
        ev("(()=>{document.querySelector('[name=correo]').value='recepcion@test.invalid';document.querySelector('[name=contrasena]').value='pruebaaislada123';document.querySelector('form').requestSubmit();return true})()")
        for _ in range(100):
            if ev("location.pathname.endsWith('/producto/index')"):break
            time.sleep(.1)
        for w,h in [(1366,768),(390,844)]:
            c.comando('browsingContext.setViewport',{'context':ctx,'viewport':{'width':w,'height':h},'devicePixelRatio':1})
            for ruta in ['recepcion/index','recepcion/directa']:
                c.comando('browsingContext.navigate',{'context':ctx,'url':base+ruta,'wait':'complete'})
                check(ev("document.documentElement.scrollWidth<=innerWidth+1 && !document.body.innerText.includes('Acceso denegado')"),'Vista de recepción utilizable '+ruta+' '+str(w))
                (trabajo/(ruta.replace('/','-')+'-'+str(w)+'.png')).write_bytes(base64.b64decode(c.comando('browsingContext.captureScreenshot',{'context':ctx,'origin':'viewport'})['data']))
        # Ejecuta la regresión JavaScript existente contra el script de la copia probada.
        fuente=(Path(__file__).resolve().parent/'compra_producto_existente.js').read_text()
        resultado=ev('(async()=>{'+fuente+'\nreturn await probarCompraProductoExistente('+json.dumps((ROOT/'public/js/compra.js').read_text())+');})()')
        check(resultado['resultado']=='OK' and resultado['comprobaciones']>=22,'22 comprobaciones JavaScript de Compra/Recepción correctas')
        check(ev('(()=>{new Function('+json.dumps((ROOT/'public/js/compra.js').read_text())+');return true})()'),'Sintaxis JavaScript del módulo compartido correcta')
    finally:
        firefox.terminate();firefox.wait(timeout=20);log.close();shutil.rmtree(perfil)
if __name__=='__main__':ejecutar()
