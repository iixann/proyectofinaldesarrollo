"""Prueba Usuarios/Roles en MariaDB temporal; nunca usa la base real."""
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
    if not m:raise AssertionError('No se encontró '+nombre)
    return html.unescape(m[1])
# Toda escritura usa el socket temporal comprobado antes de importar el instalador.
def ejecutar():
    trabajo=Path(tempfile.mkdtemp(prefix='usuarios-roles-',dir=os.environ.get('DIRECTORIO_PRUEBAS','/tmp')))
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
        texto_router=router.read_text().replace("$_GET['url']=$p;require 'public/index.php';", "if(in_array($p,['__bootstrap.css','__bootstrap.js','__iconos.css'])){header('Content-Type: '.(str_ends_with($p,'.css')?'text/css':'application/javascript'));if($p!=='__iconos.css')readfile(str_ends_with($p,'.css')?'/tmp/usuarios-bootstrap.css':'/tmp/usuarios-bootstrap.js');exit;}$_GET['url']=$p;ob_start(fn($html)=>str_replace(['https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css','https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js','https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css'],['/proyectofinaldesarrollo/__bootstrap.css','/proyectofinaldesarrollo/__bootstrap.js','/proyectofinaldesarrollo/__iconos.css'],$html));require 'public/index.php';")
        router.write_text(texto_router)
        portweb=puerto();sesiones=trabajo/'sesiones';sesiones.mkdir();logweb=(trabajo/'php.log').open('w')
        web=subprocess.Popen(['/opt/lampp/bin/php','-d','session.save_path='+str(sesiones),'-S','127.0.0.1:'+str(portweb),str(router)],cwd=ROOT,stdout=logweb,stderr=logweb);base='http://127.0.0.1:'+str(portweb)+'/proyectofinaldesarrollo/'
        for _ in range(50):
            try:requests.get(base+'session/login',timeout=1);break
            except requests.RequestException:time.sleep(.1)
        for rol,email,clave in [('ADMINISTRADOR','admin@admin.com','admin1234'),('DUEÑO','dueno@test.invalid',pw),('RECEPCIONISTA','recepcion@test.invalid',pw),('VENDEDOR','vendedor@test.invalid',pw)]:
            clientes[rol]=requests.Session();clientes[rol].trust_env=False
            pedir(rol,'POST','session/ingresar',302,data={'correo':email,'contrasena':clave})
        probar_usuarios(pedir,negocio,sql,clientes,base,trabajo,check)
        resultado={'resultado':'OK','comprobaciones':len(checks),'directorio':str(trabajo),'checks':checks}
        Path('/tmp/usuarios-roles-resultados.json').write_text(json.dumps(resultado,ensure_ascii=False,indent=2))
        print(json.dumps({k:v for k,v in resultado.items() if k!='checks'}),flush=True)
    finally:
        if web:web.terminate();web.wait(timeout=10)
        if db:db.terminate();db.wait(timeout=20)
        shutil.rmtree(datos)

# Ejecuta altas y cambios por HTTP; solo el escenario heredado usa el modelo directamente.
def probar_usuarios(pedir,sql,sql_servidor,clientes,base,trabajo,check):
    ids={nombre:int(sql("SELECT id_rol FROM roles WHERE nombre_rol='"+nombre+"'")) for nombre in ['ADMINISTRADOR','DUEÑO','VENDEDOR','RECEPCIONISTA']}
    def crear(email,nombres,estado=303):
        antes=sql('SELECT COUNT(*) FROM usuarios')
        r=pedir('ADMINISTRADOR','POST','user/guardar',estado,data={'nombre':email.split('@')[0],'apellido':'Prueba','correo':email,'contrasena':'pruebaaislada123','roles[]':[str(ids[n]) for n in nombres]})
        if estado!=303:check(sql('SELECT COUNT(*) FROM usuarios')==antes,'Alta inválida no deja usuario parcial');return None
        return int(sql("SELECT id_usuario FROM usuarios WHERE email='"+email+"'"))
    validos={}
    for etiqueta,nombres in [('admin',['ADMINISTRADOR']),('dueno',['DUEÑO']),('vendedor',['VENDEDOR']),('recepcion',['RECEPCIONISTA']),('mixto',['VENDEDOR','RECEPCIONISTA'])]:
        validos[etiqueta]=crear(etiqueta+'-valido@test.invalid',nombres)
    for i,nombres in enumerate([['ADMINISTRADOR','VENDEDOR'],['ADMINISTRADOR','RECEPCIONISTA'],['ADMINISTRADOR','DUEÑO'],['DUEÑO','VENDEDOR'],['DUEÑO','RECEPCIONISTA'],[]]):crear('invalido'+str(i)+'@test.invalid',nombres,422)
    pedir('ADMINISTRADOR','POST','user/guardar',422,data={'nombre':'Forzado','apellido':'Prueba','correo':'desconocido@test.invalid','contrasena':'pruebaaislada123','roles[]':['999999']})
    a=crear('admin-a@test.invalid',['ADMINISTRADOR']);b=validos['vendedor'];c=validos['admin'];principal=int(sql("SELECT id_usuario FROM usuarios WHERE es_principal=1"))
    inactivo=crear('inactivo@test.invalid',['VENDEDOR']);pedir('ADMINISTRADOR','POST','user/baja/'+str(inactivo),303)
    # Mantiene sesiones abiertas para verificar que los cambios quitan permisos antiguos.
    for rol,email in [('COMUN','admin-a@test.invalid'),('B','vendedor-valido@test.invalid'),('C','admin-valido@test.invalid'),('MIXTO','mixto-valido@test.invalid')]:
        clientes[rol]=requests.Session();clientes[rol].trust_env=False
        pedir(rol,'POST','session/ingresar',302,data={'correo':email,'contrasena':'pruebaaislada123'})
    def roles(uid):return sql("SELECT r.nombre_rol FROM usuarios_roles ur JOIN roles r USING(id_rol) WHERE ur.id_usuario="+str(uid)+" AND ur.estado=1 AND ur.fecha_desde<=CURRENT_TIMESTAMP AND (ur.fecha_hasta IS NULL OR ur.fecha_hasta>CURRENT_TIMESTAMP) ORDER BY r.nombre_rol")
    def cambiar(actor,uid,nombres,estado=303,extra=None):
        pagina=pedir(actor,'GET','user/cambiarRoles/'+str(uid)).text
        data={'csrf_roles':token(pagina,'csrf_roles'),'roles[]':[str(ids[n]) for n in nombres]}
        if extra:data.update(extra)
        return pedir(actor,'POST','user/guardarRoles/'+str(uid),estado,data=data)
    cambiar('COMUN',b,['RECEPCIONISTA'])
    check(roles(b)=='RECEPCIONISTA','Admin común cambia vendedor a recepción')
    pedir('B','GET','pos/index',403);pedir('B','GET','recepcion/index')
    cambiar('COMUN',b,['VENDEDOR','RECEPCIONISTA'])
    check(set(roles(b).splitlines())=={'VENDEDOR','RECEPCIONISTA'},'Roles operativos combinados guardados')
    for cliente in ['B','MIXTO']:
        for ruta in ['pos/index','sesioncaja/index','recepcion/index','producto/index','categoria/index']:pedir(cliente,'GET',ruta)
        pagina=pedir(cliente,'GET','producto/index').text
        check('/pos/index' in pagina and '/recepcion/index' in pagina and '/compra/index' not in pagina,'Navbar combina operaciones sin Compras')
        pedir(cliente,'GET','compra/index',403);pedir(cliente,'GET','user/index',403);pedir(cliente,'GET','categoria/crear',403)
    cambiar('COMUN',b,['DUEÑO'])
    check(roles(b)=='DUEÑO','Admin común puede asignar Dueño exclusivo')
    pedir('B','GET','compra/index');pedir('B','GET','user/index',403)
    pagina=pedir('COMUN','GET','user/index').text
    check('/user/cambiarRoles/'+str(c)+'"' not in pagina and '/user/cambiarRoles/'+str(principal)+'"' not in pagina,'Admin común no recibe Cambiar roles sobre administradores')
    csrf=token(pedir('COMUN','GET','user/cambiarRoles/'+str(b)).text,'csrf_roles')
    for uid in [a,c,principal]:
        pedir('COMUN','GET','user/cambiarRoles/'+str(uid),403)
        pedir('COMUN','POST','user/guardarRoles/'+str(uid),403,data={'csrf_roles':csrf,'roles[]':[str(ids['VENDEDOR'])],'es_principal':'1','id_actor':str(principal)})
    check(roles(c)=='ADMINISTRADOR','POST forzado no cambia otro administrador')
    pagina=pedir('ADMINISTRADOR','GET','user/index').text
    check('/user/cambiarRoles/'+str(c)+'"' in pagina and '/user/cambiarRoles/'+str(principal)+'"' not in pagina,'Principal puede cambiar otro administrador, nunca a sí mismo')
    cambiar('ADMINISTRADOR',c,['DUEÑO'])
    check(roles(c)=='DUEÑO','Principal cambia administrador secundario a dueño')
    pedir('C','GET','user/index',403);pedir('C','GET','compra/index')
    csrf=token(pedir('ADMINISTRADOR','GET','user/cambiarRoles/'+str(b)).text,'csrf_roles')
    for seleccion in [[str(ids['DUEÑO'])],[]]:
        pedir('ADMINISTRADOR','GET','user/cambiarRoles/'+str(principal),403)
        pedir('ADMINISTRADOR','POST','user/guardarRoles/'+str(principal),403,data={'csrf_roles':csrf,'roles[]':seleccion})
    pedir('ADMINISTRADOR','POST','user/baja/'+str(principal),403)
    check(sql('SELECT es_principal,estado FROM usuarios WHERE id_usuario='+str(principal))=='1\t1' and roles(principal)=='ADMINISTRADOR','Principal activo y administrativo intacto')
    for uid in [b,c]:
        cambiar('ADMINISTRADOR',uid,['VENDEDOR','RECEPCIONISTA'])
        cambiar('ADMINISTRADOR',uid,['ADMINISTRADOR'])
        check(roles(uid)=='ADMINISTRADOR','Promoción respeta exclusividad')
    # Repite guardado válido de administrador y cambios de un usuario inactivo.
    cambiar('ADMINISTRADOR',c,['ADMINISTRADOR'])
    cambiar('ADMINISTRADOR',inactivo,['RECEPCIONISTA'])
    check(sql('SELECT estado FROM usuarios WHERE id_usuario='+str(inactivo))=='0','Cambiar roles no reactiva al usuario')
    cambiar('ADMINISTRADOR',b,['VENDEDOR'])
    pedir('ADMINISTRADOR','GET','user/guardarRoles/'+str(b),405)
    pedir('ADMINISTRADOR','POST','user/guardarRoles/'+str(b),403,data={'csrf_roles':'otro','roles[]':[str(ids['DUEÑO'])]})
    for nombres in [['ADMINISTRADOR','VENDEDOR'],['DUEÑO','RECEPCIONISTA'],[]]:cambiar('ADMINISTRADOR',b,nombres,422)
    for cliente in ['VENDEDOR','RECEPCIONISTA','DUEÑO']:
        pedir(cliente,'GET','user/cambiarRoles/'+str(b),403);pedir(cliente,'POST','user/guardarRoles/'+str(b),403,data={'csrf_roles':csrf,'roles[]':[str(ids['DUEÑO'])]})
    check(roles(b)=='VENDEDOR','Rechazos mantienen la asignación anterior')
    auditoria=sql("SELECT COUNT(*) FROM auditoria_operaciones WHERE accion='CAMBIO_ROLES' AND entidad='USUARIO' AND id_usuario="+str(a)+" AND id_entidad="+str(b)+" AND detalle LIKE '%Antes: VENDEDOR; Después: RECEPCIONISTA%' AND fecha IS NOT NULL")
    check(int(auditoria)>=1,'Auditoría registra actor, entidad, antes/después y fecha')
    # Un fallo de auditoría revierte cierre de asignaciones y nuevas filas.
    antes=sql('SELECT id_usuario_rol,id_rol,estado,fecha_desde,fecha_hasta FROM usuarios_roles WHERE id_usuario='+str(b)+' ORDER BY id_usuario_rol')
    sql('RENAME TABLE auditoria_operaciones TO auditoria_reserva')
    try:cambiar('ADMINISTRADOR',b,['RECEPCIONISTA'],500)
    finally:sql('RENAME TABLE auditoria_reserva TO auditoria_operaciones')
    check(sql('SELECT id_usuario_rol,id_rol,estado,fecha_desde,fecha_hasta FROM usuarios_roles WHERE id_usuario='+str(b)+' ORDER BY id_usuario_rol')==antes,'Auditoría fallida revierte todas las asignaciones')
    # Restablecer contraseña mantiene token, permisos y cambio obligatorio actuales.
    restablecer=pedir('COMUN','GET','user/restablecerPassword/'+str(b)).text
    pedir('COMUN','POST','user/guardarPassword/'+str(b),303,data={'csrf_password':token(restablecer,'csrf_password'),'contrasena':'Temporalnueva123','confirmacion':'Temporalnueva123'})
    check(sql('SELECT debe_cambiar_password FROM usuarios WHERE id_usuario='+str(b))=='1','Restablecer contraseña conserva su funcionamiento')
    # Un token válido de un usuario normal no autoriza operaciones sobre el principal.
    antes_principal=sql('SELECT * FROM usuarios WHERE id_usuario='+str(principal))
    antes_roles=sql('SELECT * FROM usuarios_roles ORDER BY id_usuario_rol')
    antes_auditoria=sql('SELECT * FROM auditoria_operaciones ORDER BY id_auditoria')
    for actor in ['COMUN','ADMINISTRADOR']:
        pagina=pedir(actor,'GET','user/index').text
        check('/user/restablecerPassword/'+str(principal)+'"' not in pagina and 'Principal protegido' in pagina,'Principal sin acciones sensibles en listado')
        csrf_password=token(pedir(actor,'GET','user/restablecerPassword/'+str(b)).text,'csrf_password')
        pedir(actor,'GET','user/restablecerPassword/'+str(principal),403)
        pedir(actor,'POST','user/guardarPassword/'+str(principal),403,data={'csrf_password':csrf_password,'contrasena':'NoCambiar12345','confirmacion':'NoCambiar12345'})
        for accion in ['baja','reactivar']:pedir(actor,'POST','user/'+accion+'/'+str(principal),403)
    check(sql('SELECT * FROM usuarios WHERE id_usuario='+str(principal))==antes_principal,'Hash, flags y todos los datos del principal idénticos tras rechazos')
    check(sql('SELECT * FROM usuarios_roles ORDER BY id_usuario_rol')==antes_roles and sql('SELECT * FROM auditoria_operaciones ORDER BY id_auditoria')==antes_auditoria,'Rechazos no cambian roles ni generan auditoría falsa')
    # Admin común y principal conservan baja/reactivación y restablecimiento de terceros.
    for actor,clave in [('COMUN','Temporalcomun123'),('ADMINISTRADOR','Temporalprincipal123')]:
        pedir(actor,'POST','user/baja/'+str(inactivo),303)
        pedir(actor,'POST','user/reactivar/'+str(inactivo),303)
        pagina=pedir(actor,'GET','user/restablecerPassword/'+str(inactivo)).text
        pedir(actor,'POST','user/guardarPassword/'+str(inactivo),303,data={'csrf_password':token(pagina,'csrf_password'),'contrasena':clave,'confirmacion':clave})
        check(sql('SELECT estado,debe_cambiar_password FROM usuarios WHERE id_usuario='+str(inactivo))=='1\t1','Terceros siguen administrables por '+actor)
    pedir('ADMINISTRADOR','POST','user/baja/'+str(inactivo),303)
    # Verifica la misma regla desde el modelo, sin pasar por el controlador.
    port=sql_servidor('SELECT @@port')
    antes_usuarios=sql('SELECT COUNT(*) FROM usuarios')
    codigo="$_ENV=['DB_HOST'=>'127.0.0.1;port="+port+"','DB_NAME'=>'proyectofinaldesarrollo','DB_USER'=>'root','DB_PASS'=>'']; define('CORE_PATH','app/core/'); require 'app/core/Autoloader.php'; try { \\app\\models\\UserModel::crearUsuario('No','Guardar','modelo-invalido@test.invalid','pruebaaislada123',["+str(ids['ADMINISTRADOR'])+","+str(ids['VENDEDOR'])+"]); echo 'ERROR'; } catch (DomainException $e) { echo 'RECHAZADO'; }"
    r=subprocess.run(['/opt/lampp/bin/php','-r',codigo],cwd=ROOT,capture_output=True,text=True)
    check(r.returncode==0 and r.stdout=='RECHAZADO' and sql('SELECT COUNT(*) FROM usuarios')==antes_usuarios,'Modelo rechaza combinación inválida y revierte alta')
    # Aísla el caso heredado sin principal para ejercitar el último administrador.
    instalacion=(ROOT/'database/instalacion_profesor.sql').read_text().replace('proyectofinaldesarrollo','ultimo_roles').replace("UPDATE usuarios SET es_principal=1 WHERE email='admin@admin.com';",'')
    sql_servidor(instalacion)
    port=sql_servidor('SELECT @@port')
    for nombre in ['DUEÑO','VENDEDOR','RECEPCIONISTA']:
        codigo="$_ENV=['DB_HOST'=>'127.0.0.1;port="+port+"','DB_NAME'=>'ultimo_roles','DB_USER'=>'root','DB_PASS'=>'']; define('CORE_PATH','app/core/'); require 'app/core/Autoloader.php'; try { \\app\\models\\UserModel::cambiarRoles('1',['"+str(ids[nombre])+"'],'1'); echo 'ERROR'; } catch (DomainException $e) { echo $e->getCode(); }"
        r=subprocess.run(['/opt/lampp/bin/php','-r',codigo],cwd=ROOT,capture_output=True,text=True)
        check(r.returncode==0 and r.stdout=='409','Último administrador no se convierte en '+nombre)
    if os.environ.get('USUARIOS_VISUAL')=='1':
        from usuarios_roles_visual import revisar
        revisar(base,trabajo,{'principal':principal,'comun':a,'admin':c,'vendedor':b,'recepcion':validos['recepcion'],'mixto':validos['mixto'],'dueno':validos['dueno'],'inactivo':inactivo},check)
    print('Permisos/creación/cambio/auditoría/último administrador: OK',flush=True)

if __name__=='__main__':ejecutar()
