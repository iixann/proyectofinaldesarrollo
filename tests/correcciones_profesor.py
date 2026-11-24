"""Prueba las correcciones y la estabilización sobre el original y una MariaDB descartable."""
from pathlib import Path
import subprocess, tempfile, socket, time, json, re, os, html
import requests
import shutil
import signal
import sys
# Los helpers de prueba no dejan bytecode dentro del repositorio.
sys.dont_write_bytecode = True

ROOT = Path(__file__).resolve().parents[1]
RESULTADOS = []

# Una interrupción también libera servidores y la base descartable mediante finally.
def interrumpir_prueba(_senal, _marco):
    raise SystemExit('Prueba interrumpida; se liberan sus recursos temporales.')

signal.signal(signal.SIGTERM, interrumpir_prueba)

# Registra condiciones de negocio y detiene la prueba ante cualquier diferencia.
def comprobar(condicion, nombre):
    if not condicion:
        raise AssertionError(nombre)
    RESULTADOS.append(nombre)
    print('OK ' + nombre, flush=True)

# Reserva puertos locales sin depender de la configuración de la base real.
def puerto_libre():
    with socket.socket() as conexion:
        conexion.bind(('127.0.0.1', 0))
        return conexion.getsockname()[1]

# Cada cliente mantiene su propia sesión autenticada.
def cliente():
    sesion = requests.Session()
    sesion.trust_env = False
    return sesion

# Extrae el token generado por el controlador real.
def token(pagina, nombre):
    encontrado = re.search(r'name="' + re.escape(nombre) + r'"[^>]*value="([^"]+)"', pagina)
    assert encontrado, nombre
    return html.unescape(encontrado[1])

# Inicializa y detiene servidores privados; nunca usa la conexión configurada en .env.
def ejecutar():
    # Permite usar disco cuando /tmp es RAM y el equipo tiene poca memoria.
    trabajo = Path(tempfile.mkdtemp(prefix='correcciones-profesor-', dir=os.environ.get('DIRECTORIO_PRUEBAS')))
    datadir = trabajo / 'mariadb'
    socket_db = trabajo / 'mysql.sock'
    datadir.mkdir()
    inicializar = subprocess.run(['/opt/lampp/bin/mariadb-install-db', '--no-defaults', '--basedir=/opt/lampp', '--datadir='+str(datadir), '--auth-root-authentication-method=normal', '--skip-test-db'], capture_output=True, text=True)
    (trabajo/'init.log').write_text(inicializar.stdout+inicializar.stderr)
    assert inicializar.returncode == 0, inicializar.stderr
    puerto_db, puerto_http = puerto_libre(), puerto_libre()
    log_db = open(trabajo/'mariadb.log', 'w')
    proceso_db = subprocess.Popen(['/opt/lampp/sbin/mysqld','--no-defaults','--basedir=/opt/lampp','--datadir='+str(datadir),'--socket='+str(socket_db),'--pid-file='+str(trabajo/'mysql.pid'),'--log-error='+str(trabajo/'mysql-error.log'),'--bind-address=127.0.0.1','--port='+str(puerto_db),'--innodb-buffer-pool-size=16M'], stdout=log_db, stderr=log_db)
    proceso_php = None
    log_php = None

    # Toda sentencia se conecta explícitamente al socket descartable.
    def sql(sentencia, base='proyectofinaldesarrollo', error=False):
        comando = ['/opt/lampp/bin/mariadb','--no-defaults','--protocol=SOCKET','--socket='+str(socket_db),'--user=root','--batch','--skip-column-names','--default-character-set=utf8mb4']
        if base:
            comando += ['--database='+base]
        resultado = subprocess.run(comando, input=sentencia, capture_output=True, text=True)
        if error:
            comprobar(resultado.returncode != 0, 'SQL rechaza operación protegida')
            return resultado
        assert resultado.returncode == 0, resultado.stderr
        return resultado.stdout.strip()

    # Comprueba estado HTTP y ausencia de errores PHP en el HTML.
    def pedir(sesion, metodo, ruta, estado=200, **datos):
        respuesta = sesion.request(metodo, base_http+ruta, timeout=20, allow_redirects=False, **datos)
        comprobar(respuesta.status_code == estado, metodo+' '+ruta+' → '+str(estado))
        assert not any(x in respuesta.text for x in ['Fatal error:', 'Warning:', 'Uncaught ']), respuesta.text[:800]
        return respuesta

    # Usa el mismo login que opera en la instalación real.
    def login(sesion, correo, clave='prueba12345'):
        pedir(sesion, 'POST', 'session/ingresar', 302, data={'correo':correo,'contrasena':clave})

    # Abre el POS y devuelve su token del servidor.
    def abrir_pos(sesion, caja):
        pagina = pedir(sesion, 'GET', 'pos/index?sesion='+str(caja)).text
        return html.unescape(re.search(r'data-token="([^"]+)"', pagina)[1])

    # Confirma una venta con importes reales y pagos completos.
    def vender(sesion, caja, cantidad=2):
        csrf = abrir_pos(sesion, caja)
        total = cantidad*25
        payload = {'id_caja_sesion':int(caja),'productos':[{'id_producto':1,'cantidad':cantidad}], 'pagos':[{'id_medio_pago':1,'monto':f'{total}.00','monto_entregado':f'{total}.00'}]}
        resultado = pedir(sesion,'POST','venta/confirmar',json=payload,headers={'X-CSRF-Token':csrf}).json()
        comprobar(resultado['ok'], 'Venta confirmada transaccionalmente')
        return resultado['id_venta']

    # Prepara una corrección completa, sin modificar directamente tablas de negocio.
    def rectificar(sesion, venta, cantidad=1, monto='25.00', estado=303):
        pagina = pedir(sesion,'GET','venta/rectificar/'+str(venta)).text
        datos = {'csrf_rectificacion':token(pagina,'csrf_rectificacion'),'motivo':'Corrección de prueba', 'renglones[0][id_producto]':'1','renglones[0][cantidad]':str(cantidad), 'pagos[0][id_medio_pago]':'1','pagos[0][monto]':monto,'pagos[0][monto_entregado]':monto}
        return pedir(sesion,'POST','venta/guardarRectificacion/'+str(venta),estado,data=datos)

    try:
        for _ in range(150):
            if proceso_db.poll() is not None:
                raise RuntimeError((trabajo/'mysql-error.log').read_text())
            if socket_db.exists():
                try:
                    if sql('SELECT 1',None) == '1': break
                except AssertionError: pass
            time.sleep(.1)
        comprobar(str(datadir) in sql('SELECT @@datadir',None), 'Instancia MariaDB completamente aislada')
        # Prueba la migración sobre el esquema anterior, con principal de ID distinto de 1.
        anterior = subprocess.check_output(['git','show','b5469999da0fb068ce1886faacedf1201a9d60ce:database/schema.sql'],cwd=ROOT,text=True)
        sql('CREATE DATABASE anterior CHARACTER SET utf8mb4;',None)
        sql(anterior,'anterior')
        sql("INSERT INTO roles(nombre_rol) VALUES('ADMINISTRADOR'),('VENDEDOR'); INSERT INTO usuarios(id_usuario,nombre,apellido,email,password) VALUES(1,'Otro','Usuario','otro@test.invalid','x'),(7,'Principal','Anterior','principal@test.invalid','x'); INSERT INTO usuarios_roles(id_usuario,id_rol) VALUES(7,1);",'anterior')
        migracion = (ROOT/'database/migracion_correcciones_profesor.sql').read_text()
        sql("SET @principal_email='no-existe@test.invalid';\n"+migracion,'anterior',error=True)
        comprobar(sql("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema='anterior' AND column_name='es_principal'",None)=='0','Migración inválida no altera las tablas')
        sql("SET @principal_email='principal@test.invalid';\n"+migracion,'anterior')
        comprobar(sql('SELECT id_usuario FROM usuarios WHERE es_principal=1','anterior')=='7','Migración selecciona principal por correo, no por ID 1')
        sql('UPDATE usuarios SET estado=0 WHERE id_usuario=7','anterior',error=True)
        # La instalación nueva se ejecuta por separado, sin reaprovechar tablas migradas.
        sql((ROOT/'database/instalacion_profesor.sql').read_text(),None)
        comprobar(sql("SELECT es_principal FROM usuarios WHERE email='admin@admin.com'")=='1','Instalación demo identifica al principal')
        for sentencia in ["DELETE FROM usuarios WHERE es_principal=1", "UPDATE usuarios SET estado=0 WHERE es_principal=1", "UPDATE usuarios SET es_principal=0 WHERE es_principal=1", "DELETE FROM usuarios_roles WHERE id_usuario=(SELECT id_usuario FROM usuarios WHERE es_principal=1)", "UPDATE usuarios_roles SET estado=0 WHERE id_usuario=(SELECT id_usuario FROM usuarios WHERE es_principal=1)", "UPDATE roles SET estado=0 WHERE nombre_rol='ADMINISTRADOR'"]:
            sql(sentencia,error=True)
        # El modelo conserva también la regla del último administrador sin marca principal.
        instalacion=(ROOT/'database/instalacion_profesor.sql').read_text().replace('proyectofinaldesarrollo','ultimo').replace("UPDATE usuarios SET es_principal=1 WHERE email='admin@admin.com';", '')
        sql(instalacion,None)
        codigo_php = "$_ENV=['DB_HOST'=>'127.0.0.1;port="+str(puerto_db)+"','DB_NAME'=>'ultimo','DB_USER'=>'root','DB_PASS'=>'']; require 'app/core/Model.php'; require 'app/core/DataBase.php'; require 'app/models/UserModel.php'; try { \\app\\models\\UserModel::baja('1'); echo 'INCORRECTO'; } catch (DomainException $e) { echo $e->getCode(); }"
        prueba=subprocess.run(['/opt/lampp/bin/php','-r',codigo_php],cwd=ROOT,capture_output=True,text=True)
        comprobar(prueba.stdout=='409','Modelo impide desactivar al último administrador aunque no sea principal')
        # La migración de auditoría preserva las filas y no altera movimientos_stock.
        previo=(ROOT/'database/instalacion_profesor.sql').read_text().split('-- Auditoría administrativa de productos;')[0].replace('proyectofinaldesarrollo','roles_prev')
        sql(previo,None)
        sql((ROOT/'database/migracion_roles_auditoria.sql').read_text(),'roles_prev')
        comprobar(sql('SELECT COUNT(*) FROM usuarios','roles_prev')=='1' and sql('SELECT COUNT(*) FROM auditoria_operaciones','roles_prev')=='0','Migración de auditoría agrega tabla vacía sin tocar usuarios')
        # El router de prueba verifica la conexión ANTES de arrancar la aplicación.
        router = trabajo/'router.php'
        config = {'DB_HOST':f'127.0.0.1;port={puerto_db}','DB_NAME':'proyectofinaldesarrollo','DB_USER':'root','DB_PASS':''}
        php_config = '\n'.join("$_ENV["+repr(k)+"]="+repr(v)+";" for k,v in config.items())
        router.write_text('''<?php
$ruta=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
$prefijo='/proyectofinaldesarrollo/';
if (!str_starts_with($ruta,$prefijo)) { http_response_code(404); exit; }
$relativa=substr($ruta,strlen($prefijo));
if (in_array($relativa,['__bootstrap.css','__bootstrap.js','__iconos.css'],true)) {
 header('Content-Type: '.(str_ends_with($relativa,'.css')?'text/css':'application/javascript'));
 if ($relativa!=='__iconos.css') readfile(str_ends_with($relativa,'.css')?'/tmp/ean-bootstrap.min.css':'/tmp/ean-bootstrap.bundle.min.js');
 exit;
}
$raiz='''+repr(str(ROOT))+''';
$estatica=str_starts_with($relativa,'public/')?substr($relativa,7):$relativa;
$archivo=realpath($raiz.'/public/'.$estatica);
if ($archivo && str_starts_with($archivo,$raiz.'/public/') && is_file($archivo) && in_array(pathinfo($archivo,PATHINFO_EXTENSION),['js','css'],true)) {
 header('Content-Type: '.(str_ends_with($archivo,'.css')?'text/css':'application/javascript')); readfile($archivo); exit;
}
'''+php_config+'''
require_once $raiz.'/app/core/DataBase.php';
$directorio=DataBase::connection()->query('SELECT @@datadir')->fetchColumn();
if (!str_starts_with($directorio,'''+repr(str(datadir))+''')) { http_response_code(503); exit('Conexión fuera del aislamiento'); }
$_GET['url']=$relativa;
ob_start(static fn($texto)=>str_replace(['https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css','https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js','https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css'],['/proyectofinaldesarrollo/__bootstrap.css','/proyectofinaldesarrollo/__bootstrap.js','/proyectofinaldesarrollo/__iconos.css'],$texto));
require $raiz.'/public/index.php';
''')
        carpeta_sesion=trabajo/'sesiones';carpeta_sesion.mkdir()
        log_php=open(trabajo/'php.log','w')
        proceso_php=subprocess.Popen(['/opt/lampp/bin/php','-d','session.save_path='+str(carpeta_sesion),'-S',f'127.0.0.1:{puerto_http}',str(router)],cwd=ROOT,stdout=log_php,stderr=log_php)
        base_http=f'http://127.0.0.1:{puerto_http}/proyectofinaldesarrollo/'
        admin=cliente()
        for _ in range(100):
            try:
                if admin.get(base_http+'session/login',timeout=1).status_code==200: break
            except requests.RequestException: pass
            time.sleep(.1)
        login(admin,'admin@admin.com','admin1234')
        pagina=pedir(admin,'GET','user/index').text
        comprobar('Baja protegida' in pagina and '/user/baja/1' not in pagina,'Principal sin botón de baja en la interfaz')
        pedir(admin,'POST','user/baja/1',403)
        # Crea usuarios para comprobar baja ordinaria y jerarquías reales.
        for correo,rol in [('vendedor1@test.invalid',4),('vendedor2@test.invalid',4),('dueno@test.invalid',2),('recepcion@test.invalid',3),('baja@test.invalid',4),('admin2@test.invalid',1)]:
            pedir(admin,'POST','user/guardar',303,data={'nombre':correo.split('@')[0],'apellido':'Prueba','correo':correo,'contrasena':'prueba12345','roles[]':str(rol)})
        id_baja=sql("SELECT id_usuario FROM usuarios WHERE email='baja@test.invalid'")
        pedir(admin,'POST','user/baja/'+id_baja,303)
        comprobar(sql('SELECT estado FROM usuarios WHERE id_usuario='+id_baja)=='0','Usuario ordinario puede darse de baja')
        v1,v2,dueno,recepcion=cliente(),cliente(),cliente(),cliente()
        for c,correo in [(v1,'vendedor1@test.invalid'),(v2,'vendedor2@test.invalid'),(dueno,'dueno@test.invalid'),(recepcion,'recepcion@test.invalid')]:login(c,correo)
        pedir(admin,'POST','categoria/guardar',303,data={'nombre_categoria':'Vinos'})
        for nombre in ['Proveedor A','Proveedor B']:pedir(admin,'POST','proveedor/guardar',303,data={'razon_social':nombre})
        pedir(admin,'POST','producto/guardar',303,data={'nombre_producto':'Producto prueba','codigo_barras':'4006381333931','id_categoria':'1','precio_venta':'25.00','stock_minimo':'3','proveedores[]':'1'})
        r=pedir(recepcion,'GET','producto/index?codigo_barras=4006381333931')
        comprobar('Stock actual' in r.text and 'Stock mínimo' in r.text and '/producto/editar/1' in r.text,'Recepción consulta y gestiona el catálogo operativo')
        pedir(recepcion,'GET','producto/editar/1')
        redireccion=pedir(recepcion,'GET','stock/index?codigo_barras=4006381333931&escaneado=1',302)
        comprobar('/producto/index?' in redireccion.headers['Location'] and '4006381333931' in redireccion.headers['Location'],'URL anterior Stock conserva filtros y escáner')
        comprobar('aria-label="1 productos para reponer"' in pedir(admin,'GET','producto/index').text,'Badge cuenta el stock bajo real')
        # Compra y recepción continúan siendo la vía de ingreso de mercadería.
        compra=pedir(dueno,'GET','compra/crear').text;csrf=token(compra,'csrf_compra')
        pedir(dueno,'POST','compra/asociarProductoRapido',data={'csrf_compra':csrf,'id_producto':'1','id_proveedor':'2','codigo_barras':'4006381333931'})
        pedir(dueno,'POST','compra/guardar',303,data={'csrf_compra':csrf,'id_proveedor':'2','numero_comprobante':'PRUEBA','renglones[0][id_producto]':'1','renglones[0][cantidad]':'10','renglones[0][precio_unitario]':'10.00'})
        comprobar(sql('SELECT stock_actual FROM productos WHERE id_producto=1')=='0','Compra pendiente no mueve stock')
        pagina=pedir(recepcion,'GET','recepcion/compra/1').text
        pedir(recepcion,'POST','recepcion/guardarCompra/1',303,data={'csrf_recepcion':token(pagina,'csrf_recepcion'),'cantidades[1]':'10','decision':'ACEPTADA'})
        comprobar(sql('SELECT stock_actual FROM productos WHERE id_producto=1')=='10','Recepción aumenta stock correctamente')
        pedir(admin,'POST','producto/actualizar/1',303,data={'nombre_producto':'Producto prueba','codigo_barras':'4006381333931','id_categoria':'1','precio_venta':'25.00','stock_minimo':'3','proveedores[]':'1','stock_actual':'999'})
        comprobar(sql('SELECT stock_actual FROM productos WHERE id_producto=1')=='10','Editar Producto ignora un stock arbitrario enviado manualmente')
        comprobar('aria-label="1 productos para reponer"' not in pedir(admin,'GET','producto/index').text,'Badge cambia al recargar después de la recepción')
        for caja in ['Caja 1','Caja 2','Caja 3']:pedir(admin,'POST','caja/guardar',303,data={'nombre':caja})
        pedir(v1,'POST','sesioncaja/guardarApertura',303,data={'id_caja':'1','monto_inicial':'100.00'})
        pedir(v2,'POST','sesioncaja/guardarApertura',303,data={'id_caja':'2','monto_inicial':'0.00'})
        # Extiende permisos y trazabilidad sin tocar los registros de venta de regresión.
        from roles_operativos import probar_roles
        probar_roles(pedir, comprobar, sql, token, admin, dueno, v1, recepcion)
        a,b=vender(v1,1),vender(v2,2)
        # El navegador no puede ampliar el filtro usando un ID de usuario ajeno.
        listado=pedir(v1,'GET','venta/index?id_usuario=3').text
        comprobar(f'/venta/ver/{a}' in listado and f'/venta/ver/{b}' not in listado,'Vendedor recibe solo sus ventas desde backend')
        for ruta in ['ver','ticket','rectificar']:pedir(v1,'GET',f'venta/{ruta}/{b}',403)
        propio=pedir(v1,'GET',f'venta/rectificar/{a}').text
        pedir(v1,'POST',f'venta/guardarRectificacion/{b}',403,data={'csrf_rectificacion':token(propio,'csrf_rectificacion'),'motivo':'Intento ajeno'})
        pedir(v1,'POST',f'venta/guardarRectificacion/{a}',403,data={'csrf_rectificacion':'invalido','motivo':'Token inválido'})
        for c in [admin,dueno]:
            pagina=pedir(c,'GET','venta/index').text
            comprobar(f'/venta/ver/{a}' in pagina and f'/venta/ver/{b}' in pagina,'Supervisor ve todas las ventas')
            pedir(c,'GET',f'venta/ticket/{b}')
        stock=sql('SELECT stock_actual FROM productos WHERE id_producto=1')
        movimientos=sql('SELECT COUNT(*) FROM movimientos_stock')
        rectificar(v1,a,cantidad=1,monto='1.00',estado=422)
        comprobar(sql('SELECT stock_actual FROM productos WHERE id_producto=1')==stock and sql('SELECT COUNT(*) FROM movimientos_stock')==movimientos,'Rectificación inválida revierte stock y movimientos')
        comprobar(sql('SELECT estado FROM ventas WHERE id_venta='+str(a))=='1','Fallo de corrección conserva original vigente')
        cambio=rectificar(v1,a)
        nueva=int(cambio.headers['Location'].rsplit('/',1)[1])
        comprobar(sql('SELECT stock_actual FROM productos WHERE id_producto=1')=='7','Rectificación devuelve dos unidades y descuenta una')
        comprobar(sql('SELECT estado_rectificacion,estado FROM ventas WHERE id_venta='+str(a))=='RECTIFICADA\t0','Original queda rectificada, sin borrarse')
        comprobar(sql('SELECT id_venta_original FROM ventas WHERE id_venta='+str(nueva))==str(a),'Nueva venta referencia su original')
        comprobar(sql('SELECT COUNT(*) FROM detalle_ventas WHERE id_venta='+str(a))=='1' and sql('SELECT monto FROM pagos_ventas WHERE id_venta='+str(a))=='50.00','Original conserva detalle y pagos')
        comprobar(sql('SELECT monto,monto_entregado,vuelto FROM pagos_ventas WHERE id_venta='+str(nueva))=='25.00\t25.00\t0.00','Nuevo pago y vuelto coherentes')
        pedir(v1,'GET',f'venta/rectificar/{a}',409)
        pedir(v2,'GET',f'venta/ver/{nueva}',403)
        ticket=pedir(v1,'GET',f'venta/ticket/{a}').text
        comprobar('VENTA RECTIFICADA' in ticket,'Ticket original informa la rectificación')
        corregida=rectificar(admin,nueva,cantidad=2,monto='50.00')
        ultima=int(corregida.headers['Location'].rsplit('/',1)[1])
        comprobar(sql('SELECT id_usuario FROM ventas WHERE id_venta='+str(ultima))==sql('SELECT id_usuario FROM ventas WHERE id_venta='+str(a)),'Supervisor conserva el vendedor original en la nueva venta')
        corregida_dueno=rectificar(dueno,b,cantidad=1,monto='25.00')
        vigente_b=int(corregida_dueno.headers['Location'].rsplit('/',1)[1])
        comprobar(sql('SELECT id_usuario_rectifica FROM ventas WHERE id_venta='+str(b))==sql("SELECT id_usuario FROM usuarios WHERE email='dueno@test.invalid'"),'Dueño rectifica una venta autorizada y queda registrado como actor')
        # La revisión visual aislada prepara el mismo total de caja sin repetir atajos ya probados.
        if os.environ.get('SOLO_REVISION_VISUAL') == '1': vender(v1,1,cantidad=3)
        # Pruebas de navegador usan los mismos controladores y esta base temporal.
        probar_navegador(base_http,trabajo,pedir,admin,sql)
        resumen=pedir(v1,'GET','sesioncaja/cerrar/1').text
        comprobar('225.00' in resumen,'Caja suma solo la última versión vigente de la venta')
        pedir(v1,'POST','sesioncaja/guardarCierre/1',303,data={'efectivo_contado':'225.00'})
        comprobar(sql('SELECT efectivo_esperado,diferencia FROM caja_sesiones WHERE id_caja_sesion=1')=='225.00\t0.00','Cierre mantiene efectivo esperado y diferencia cero')
        pedir(admin,'GET',f'venta/rectificar/{ultima}',409)
        # El POST también debe rechazar una caja cerrada aunque se conserve un token anterior.
        pedir(admin,'POST',f'venta/guardarRectificacion/{ultima}',409,data={'csrf_rectificacion':token(pedir(admin,'GET',f'venta/rectificar/{vigente_b}').text,'csrf_rectificacion'),'motivo':'Cerrada','renglones[0][id_producto]':'1','renglones[0][cantidad]':'1','pagos[0][id_medio_pago]':'1','pagos[0][monto]':'25.00','pagos[0][monto_entregado]':'25.00'})
        esperadas = '6' if os.environ.get('OMITIR_REVISION_VISUAL') == '1' or os.environ.get('SOLO_PANTALLAS') == '1' else '7'
        comprobar(sql('SELECT COUNT(*) FROM ventas')==esperadas,'Rectificar caja cerrada no crea una venta adicional a la revisión POS')
        resultado={'resultado':'OK','comprobaciones':len(RESULTADOS),'detalles':RESULTADOS,'directorio_aislado':str(trabajo)}
        (trabajo/'resultados.json').write_text(json.dumps(resultado,ensure_ascii=False,indent=2))
        Path('/tmp/correcciones-profesor-resultados.json').write_text(json.dumps(resultado,ensure_ascii=False,indent=2))
        print(json.dumps({'resultado':'OK','comprobaciones':len(RESULTADOS),'directorio_aislado':str(trabajo)}),flush=True)
    finally:
        if proceso_php:proceso_php.terminate();proceso_php.wait(timeout=10)
        if log_php:log_php.close()
        proceso_db.terminate();proceso_db.wait(timeout=20);log_db.close()
        # Libera solo la base descartable ya detenida; /tmp puede usar RAM.
        shutil.rmtree(datadir)

# Usa WebDriver BiDi para probar formularios, navegación y almacenamiento reales.
def probar_navegador(base,trabajo,pedir,admin,sql):
    espacio={'__file__':str(ROOT/'tests/ean_scanner_visual.py'),'__name__':'bidi_pruebas'}
    exec(compile((ROOT/'tests/ean_scanner_visual.py').read_text(),str(ROOT/'tests/ean_scanner_visual.py'),'exec'),espacio)
    perfil=trabajo/'firefox';perfil.mkdir()
    from estabilizacion_visual import preparar_perfil
    preparar_perfil(perfil)
    log=open(trabajo/'firefox.log','w')
    navegador=subprocess.Popen(['/snap/firefox/current/usr/lib/firefox/firefox','--headless','--no-remote','--profile',str(perfil),'--remote-debugging-port','9223','about:blank'],stdout=log,stderr=log)
    try:
        control=None
        for _ in range(480):
            try:control=espacio['Bidi']();break
            except (OSError,RuntimeError):time.sleep(.25)
        assert control, (trabajo/'firefox.log').read_text()
        # Firefox puede tardar en inicializar un perfil nuevo en esta máquina.
        control.socket.settimeout(120)
        control.comando('session.new',{'capabilities':{}})
        contexto=control.comando('browsingContext.create',{'type':'tab'})['context']

        # Permite repetir la auditoría global sin repetir toda la interacción del POS.
        if os.environ.get('SOLO_REVISION_VISUAL') == '1':
            from estabilizacion_visual import revisar
            revisar(control, contexto, base, trabajo, sql, comprobar)
            control.comando('session.end',{})
            return

        # Evalúa una condición en la página actual.
        def evaluar(expresion):return control.evaluar(contexto,expresion)
        # Navega al controlador real de la instancia temporal.
        def ir(ruta):control.comando('browsingContext.navigate',{'context':contexto,'url':base+ruta,'wait':'complete'})
        # Espera el resultado visible de una consulta o navegación.
        def esperar(expresion):
            for _ in range(100):
                if evaluar(expresion):return
                time.sleep(.05)
            raise AssertionError('Tiempo agotado: '+expresion)
        # Autentica al navegador sin inyectar sesiones ficticias.
        def acceder(correo,clave='prueba12345'):
            ir('session/logout');ir('session/login')
            evaluar("(() => {document.querySelector('[name=correo]').value="+json.dumps(correo)+";document.querySelector('[name=contrasena]').value="+json.dumps(clave)+";document.querySelector('form').requestSubmit();return true;})()")
            esperar("!location.pathname.endsWith('/session/login')")
        # Envía un atajo al elemento que tiene el foco, igual que el teclado.
        def tecla(nombre):evaluar("(() => {document.activeElement.dispatchEvent(new KeyboardEvent('keydown',{key:"+json.dumps(nombre)+",bubbles:true,cancelable:true}));return true;})()")

        acceder('admin@admin.com','admin1234');ir('user/index')
        bajas_antes=sql('SELECT COUNT(*) FROM usuarios WHERE estado=0')
        evaluar("(() => {document.querySelector('form[data-confirmar-baja] button').click();return true;})()")
        esperar("document.getElementById('confirmarBaja').classList.contains('show')")
        comprobar('¿Desea dar de baja al usuario' in evaluar("document.querySelector('[data-pregunta-baja]').textContent"),'Modal compartido pregunta antes de dar de baja')
        evaluar("(() => {document.querySelector('#confirmarBaja [data-bs-dismiss]').click();return true;})()")
        time.sleep(.4)
        comprobar(sql('SELECT COUNT(*) FROM usuarios WHERE estado=0')==bajas_antes,'Cancelar confirmación no modifica usuarios')
        id_otro_admin=sql("SELECT id_usuario FROM usuarios WHERE email='admin2@test.invalid'")
        evaluar("(() => {document.querySelector("+json.dumps("form[action$='/user/baja/"+id_otro_admin+"'] button")+").click();return true;})()")
        esperar("document.getElementById('confirmarBaja').classList.contains('show')")
        time.sleep(.35)
        evaluar("(() => {document.querySelector('[data-confirmar-baja-ahora]').click();return true;})()")
        esperar("location.search.includes('resultado=baja')")
        comprobar(sql('SELECT estado FROM usuarios WHERE id_usuario='+id_otro_admin)=='0','Confirmar modal envía la baja ordinaria una sola vez')
        acceder('vendedor1@test.invalid');ir('sesioncaja/index')
        for letra in ['q','o']:
            evaluar("(() => {document.dispatchEvent(new KeyboardEvent('keydown',{key:"+json.dumps(letra)+",altKey:true,bubbles:true,cancelable:true}));return true;})()")
            esperar("document.getElementById('consultaRapida').classList.contains('show')")
            comprobar(evaluar("document.getElementById('consultaRapida').classList.contains('show')"),'Alt+'+letra.upper()+' abre consulta como vendedor fuera del POS')
            time.sleep(.35)
            evaluar("(() => {document.getElementById('eanConsultaRapida').value='4006381333931';document.getElementById('formConsultaRapida').requestSubmit();return true;})()")
            esperar("document.getElementById('resultadoConsultaRapida').textContent.includes('Producto prueba')")
            evaluar("(() => {bootstrap.Modal.getInstance(document.getElementById('consultaRapida')).hide();return true;})()")
            time.sleep(.4)
        ir('pos/index?sesion=1')
        for letra in ['q','o']:
            evaluar("(() => {document.dispatchEvent(new KeyboardEvent('keydown',{key:"+json.dumps(letra)+",altKey:true,bubbles:true,cancelable:true}));return true;})()")
            esperar("document.getElementById('consultaRapida').classList.contains('show')")
            comprobar(evaluar("document.querySelectorAll('#lineasCarrito tr').length===0"),'Alt+'+letra.upper()+' consulta en POS sin agregar al carrito')
            time.sleep(.35)
            evaluar("(() => {bootstrap.Modal.getInstance(document.getElementById('consultaRapida')).hide();return true;})()")
            time.sleep(.4)
        evaluar("(() => {document.getElementById('codigo').value='4006381333931';document.getElementById('buscarEan').requestSubmit();return true;})()")
        esperar("document.querySelectorAll('#lineasCarrito tr').length===1")
        evaluar("(() => {const cantidad=document.querySelector('#lineasCarrito input');cantidad.value='3';cantidad.dispatchEvent(new Event('change'));return true;})()")
        ir('venta/index');ir('pos/index?sesion=1')
        comprobar(evaluar("document.querySelector('#lineasCarrito input').value==='3'"),'POS conserva productos y cantidades al navegar y volver')
        # Otra caja propia empieza con otro borrador.
        comprobar(evaluar("(async()=>{const r=await fetch('/proyectofinaldesarrollo/sesioncaja/guardarApertura',{method:'POST',body:new URLSearchParams({id_caja:'3',monto_inicial:'0.00'})});return r.ok;})()"),'Abre una segunda caja propia para aislar borradores')
        ir('pos/index?sesion=3')
        comprobar(evaluar("!document.getElementById('terminalPos') || document.querySelectorAll('#lineasCarrito tr').length===0"),'Caja no válida no restaura el borrador')
        evaluar("(() => {document.getElementById('codigo').value='4006381333931';document.getElementById('buscarEan').requestSubmit();return true;})()")
        esperar("document.querySelectorAll('#lineasCarrito tr').length===1")
        comprobar(evaluar("Array.from({length:sessionStorage.length},(_,i)=>sessionStorage.key(i)).some(k=>k.startsWith('pos-carrito:v1:') && k.endsWith(':3'))"),'Segunda caja guarda su propio borrador')
        comprobar(evaluar("(async()=>{const r=await fetch('/proyectofinaldesarrollo/sesioncaja/guardarCierre/3',{method:'POST',body:new URLSearchParams({efectivo_contado:'0.00'})});return r.ok;})()"),'Cierra segunda caja sin confirmar su borrador')
        ir('pos/index?sesion=3')
        comprobar(evaluar("!Array.from({length:sessionStorage.length},(_,i)=>sessionStorage.key(i)).some(k=>k.startsWith('pos-carrito:v1:') && k.endsWith(':3'))"),'Caja cerrada elimina el borrador persistido al volver')
        ir('pos/index?sesion=1')
        comprobar(evaluar("document.querySelectorAll('#lineasCarrito tr').length===1"),'Borrador de caja válida sigue disponible')
        for key,ident in [('F2','codigo'),('F3','nombre')]:
            tecla(key);comprobar(evaluar('document.activeElement.id=='+json.dumps(ident)),key+' mantiene su foco')
        tecla('F8');time.sleep(.4)
        for key in ['F4','F5','F6','F7']:
            tecla(key);comprobar(evaluar("document.getElementById('cobroPos').classList.contains('show')"),key+' mantiene el cobro')
        tecla('Escape');esperar("!document.body.classList.contains('modal-open')");tecla('F9')
        # Espera shown/hidden reales: con poca RAM la animación puede superar 400 ms.
        esperar("document.activeElement.id==='eanConsultaRapida'")
        comprobar(evaluar("document.getElementById('consultaRapida').classList.contains('show')"),'F9 conserva la consulta rápida')
        tecla('Escape');esperar("!document.body.classList.contains('modal-open') && document.activeElement.id==='codigo'")
        tecla('F10');esperar("document.getElementById('mensajePos').textContent.includes('No hay una venta reciente')")
        comprobar(evaluar("document.getElementById('mensajePos').textContent.includes('No hay una venta reciente')"),'F10 mantiene el ticket de última venta')
        # Confirma por la interfaz: se usa solo un medio para cubrir el nuevo total.
        tecla('F8');time.sleep(.4)
        evaluar("(() => {document.querySelectorAll('#filasPago input').forEach(i=>i.value='');const entrega=document.querySelector('[data-entregado]');entrega.value='75.00';entrega.dispatchEvent(new Event('input',{bubbles:true}));document.getElementById('confirmarVenta').click();return true;})()")
        esperar("document.querySelectorAll('#lineasCarrito tr').length===0")
        comprobar(evaluar("Array.from({length:sessionStorage.length},(_,i)=>sessionStorage.key(i)).filter(k=>k.startsWith('pos-carrito:v1:')).length===0"),'Venta confirmada elimina el carrito persistido')
        # Un segundo usuario no recibe el borrador del primero.
        evaluar("(() => {document.getElementById('codigo').value='4006381333931';document.getElementById('buscarEan').requestSubmit();return true;})()")
        esperar("document.querySelectorAll('#lineasCarrito tr').length===1")
        acceder('vendedor2@test.invalid');ir('pos/index?sesion=2')
        comprobar(evaluar("document.querySelectorAll('#lineasCarrito tr').length===0"),'Otro vendedor no recupera el carrito ajeno')
        # La cámara continúa siendo móvil en la consulta unificada.
        acceder('admin@admin.com','admin1234')
        for ancho in [360,390,412,768,1366]:
            control.comando('browsingContext.setViewport',{'context':contexto,'viewport':{'width':ancho,'height':844},'devicePixelRatio':1})
            ir('producto/index')
            visible=evaluar("document.querySelector('[data-escanear-ean]').getBoundingClientRect().height>0")
            comprobar(visible==(ancho<768),'Cámara en Productos responde al ancho '+str(ancho))
        control.comando('browsingContext.setViewport',{'context':contexto,'viewport':{'width':390,'height':844},'devicePixelRatio':1})
        ir('producto/crear')
        codigo=(ROOT/'tests/ean_scanner.js').read_text()
        lector=evaluar('(async()=>{'+codigo+'\nreturn await probarEscanerEan();})()')
        comprobar(lector['resultado']=='OK' and lector['comprobaciones']>=52,'Lector EAN conserva todas sus comprobaciones móviles')
        # La regresión de atajos puede ejecutarse separada de los lotes visuales largos.
        if os.environ.get('OMITIR_REVISION_VISUAL') != '1':
            from estabilizacion_visual import revisar
            revisar(control, contexto, base, trabajo, sql, comprobar)
        control.comando('session.end',{})
    finally:
        navegador.terminate();navegador.wait(timeout=15);log.close()

if __name__=='__main__':
    ejecutar()
