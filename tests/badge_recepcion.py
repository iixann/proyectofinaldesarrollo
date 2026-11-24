"""Comprueba el badge real con compras y recepciones exclusivamente en MariaDB temporal."""
from pathlib import Path
import os,re,json,sys,subprocess,time,base64,shutil
sys.dont_write_bytecode=True
# Reutiliza el servidor aislado y sus clientes autenticados de la regresión de roles.
def probar(pedir,sql,token,check,base,trabajo):
    # Asegura un badge de Reposición independiente durante todo el recorrido.
    sql('UPDATE productos SET stock_minimo=20 WHERE id_producto=1')
    propietario=sql("SELECT id_usuario FROM usuarios WHERE email='dueno@test.invalid'")
    sql("INSERT INTO compras(id_proveedor,id_usuario,numero_comprobante,estado,estado_recepcion,total) VALUES(1,"+propietario+",'INACTIVA-BADGE',0,'PENDIENTE',0)")
    etapas=[]
    # Lee solamente el enlace solicitado, no otros números presentes en la página.
    def enlace(pagina,modulo):
        m=re.search(r'<a\b[^>]*href="/proyectofinaldesarrollo/'+modulo+r'/index"[^>]*>(.*?)</a>',pagina,re.S)
        return m.group(1) if m else None
    # Compara SQL, listado operativo y badges de los cuatro roles tras cada navegación.
    def estado(esperado):
        real=int(sql("SELECT COUNT(*) FROM compras WHERE estado=1 AND estado_recepcion='PENDIENTE'"))
        check(real==esperado,'Cantidad pendiente real = '+str(esperado))
        listado=pedir('RECEPCIONISTA','GET','recepcion/index').text
        check(len(re.findall(r'href="/proyectofinaldesarrollo/recepcion/compra/\d+"',listado))==esperado,'Badge coincide con las filas pendientes')
        repos=int(sql('SELECT COUNT(*) FROM productos WHERE estado=1 AND stock_actual<=stock_minimo'))
        for rol in ['ADMINISTRADOR','DUEÑO','RECEPCIONISTA','VENDEDOR']:
            pagina=pedir(rol,'GET','producto/index').text;r=enlace(pagina,'recepcion');p=enlace(pagina,'reposicion')
            if rol=='VENDEDOR':
                check(r is None and p is None,'Vendedor sin enlaces ni badges operativos');continue
            check(r is not None,'Recepción visible para '+rol)
            numero=re.search(r'<span\b[^>]*>(\d+)</span>',r)
            check((int(numero[1]) if numero else 0)==esperado and (numero is not None)==(esperado>0),rol+' badge '+str(esperado)+' u oculto en cero')
            check(('compras pendientes de recepción' in r)==(esperado>0),'Etiqueta accesible del contador')
            rp=re.search(r'<span\b[^>]*>(\d+)</span>',p or '')
            check((int(rp[1]) if rp else 0)==repos,'Reposición conserva su cantidad independiente')
            if numero:check('class="badge rounded-pill text-bg-danger"' in r,'Mismo estilo Bootstrap que Reposición')
            if rol=='RECEPCIONISTA':check(enlace(pagina,'compra') is None,'Recepcionista sigue sin Compras')
        etapas.append({'pendientes':esperado,'reposicion':repos})
    estado(0)
    csrf=token(pedir('DUEÑO','GET','compra/crear').text);ids=[]
    for i in [1,2]:
        antes=sql('SELECT stock_actual FROM productos WHERE id_producto=1');mov=sql('SELECT COUNT(*) FROM movimientos_stock')
        pedir('DUEÑO','POST','compra/guardar',303,data={'csrf_compra':csrf,'id_proveedor':'1','numero_comprobante':'BADGE-'+str(i),'renglones[0][id_producto]':'1','renglones[0][cantidad]':'2','renglones[0][precio_unitario]':'50.00'})
        cid=sql("SELECT id_compra FROM compras WHERE numero_comprobante='BADGE-"+str(i)+"'");ids.append(cid)
        check(sql('SELECT stock_actual FROM productos WHERE id_producto=1')==antes and sql('SELECT COUNT(*) FROM movimientos_stock')==mov,'Compra pendiente no mueve stock ni genera movimientos')
        estado(i)
    if os.environ.get('BADGE_RECEPCION_VISUAL')=='1':visual(base,trabajo,check)
    for cid,decision,esperado in [(ids[0],'ACEPTADA',1),(ids[1],'RECHAZADA',0)]:
        pagina=pedir('RECEPCIONISTA','GET','recepcion/compra/'+cid).text
        antes=int(sql('SELECT stock_actual FROM productos WHERE id_producto=1'));mov=int(sql('SELECT COUNT(*) FROM movimientos_stock'))
        pedir('RECEPCIONISTA','POST','recepcion/guardarCompra/'+cid,303,data={'csrf_recepcion':token(pagina,'csrf_recepcion'),'cantidades[1]':'2','decision':decision,'motivo_rechazo':'Rotura' if decision=='RECHAZADA' else ''})
        check(int(sql('SELECT stock_actual FROM productos WHERE id_producto=1'))==antes+(2 if decision=='ACEPTADA' else 0),'Stock cambia únicamente en aceptación')
        check(int(sql('SELECT COUNT(*) FROM movimientos_stock'))==mov+(1 if decision=='ACEPTADA' else 0),'Rechazar no crea entrada de stock')
        estado(esperado)
    check([e['pendientes'] for e in etapas]==[0,1,2,1,0],'Secuencia exacta 0 → 1 → 2 → 1 → 0')
    check(all(e['reposicion']==1 for e in etapas),'Reposición permanece en 1, independiente de Recepción')
    (trabajo/'badge-etapas.json').write_text(json.dumps(etapas,indent=2))
    return estado
# Mide el badge real en navbar expandida y colapsada para cada rol.
def visual(base,trabajo,check):
    from ean_scanner_visual import Bidi
    from estabilizacion_visual import preparar_perfil
    perfil=trabajo/'firefox-badge';perfil.mkdir();preparar_perfil(perfil);log=(trabajo/'firefox-badge.log').open('w')
    firefox=subprocess.Popen(['/snap/firefox/current/usr/lib/firefox/firefox','--headless','--no-remote','--profile',str(perfil),'--remote-debugging-port','9223','about:blank'],stdout=log,stderr=log)
    medidas=[]
    try:
        for _ in range(180):
            try:c=Bidi();break
            except OSError:time.sleep(.25)
        else:raise RuntimeError('No inició Firefox')
        c.comando('session.new',{'capabilities':{}});ctx=c.comando('browsingContext.getTree',{})['contexts'][0]['context']
        def ev(s):return c.evaluar(ctx,s)
        def esperar(s):
            for _ in range(100):
                if ev(s):return
                time.sleep(.05)
            raise AssertionError(s)
        def ir(ruta):c.comando('browsingContext.navigate',{'context':ctx,'url':base+ruta,'wait':'complete'})
        for rol,correo,clave in [('ADMINISTRADOR','admin@admin.com','admin1234'),('DUEÑO','dueno@test.invalid','pruebaaislada123'),('RECEPCIONISTA','recepcion@test.invalid','pruebaaislada123'),('VENDEDOR','vendedor@test.invalid','pruebaaislada123')]:
            ir('session/logout');ir('session/login')
            ev("(()=>{document.querySelector('[name=correo]').value="+json.dumps(correo)+";document.querySelector('[name=contrasena]').value="+json.dumps(clave)+";document.querySelector('form').requestSubmit();return true})()")
            esperar("!location.pathname.endsWith('/session/login')")
            for w,h in [(1920,1080),(1366,768),(1280,720),(1024,768),(390,844)]:
                c.comando('browsingContext.setViewport',{'context':ctx,'viewport':{'width':w,'height':h},'devicePixelRatio':1});ir('producto/index')
                if w<1200:
                    ev("(()=>{bootstrap.Collapse.getOrCreateInstance(document.getElementById('navegacionPrincipal'),{toggle:false}).show();return true})()")
                    esperar("document.getElementById('navegacionPrincipal').classList.contains('show')");time.sleep(.35)
                m=ev("""(()=>{const a=document.querySelector('nav a[href$="/recepcion/index"]'),b=a?.querySelector('.badge'),p=document.querySelector('nav a[href$="/reposicion/index"] .badge'),r=b?.getBoundingClientRect();return {page:document.documentElement.scrollWidth,width:innerWidth,recepcion:!!a,numero:b?.textContent,repo:p?.textContent,alto:r?.height,ancho:r?.width,visible:!!r&&r.height>0&&r.top>=0&&r.bottom<=innerHeight,compras:!!document.querySelector('nav a[href$="/compra/index"]')}})()""")
                check(m['page']<=w+1,'Navbar sin overflow '+rol+' '+str(w))
                if rol=='VENDEDOR':check(not m['recepcion'],'Sin Recepción para vendedor')
                else:check(m['numero']=='2' and m['repo']=='1' and m['visible'] and m['alto']<32 and m['ancho']<36,'Badges legibles e independientes '+rol+' '+str(w))
                if rol=='RECEPCIONISTA':check(not m['compras'],'Navbar móvil/escritorio sin Compras para recepción')
                medidas.append({'rol':rol,'resolucion':[w,h],**m})
                if w in [1366,390]:(trabajo/('badge-'+rol+'-'+str(w)+'.png')).write_bytes(base64.b64decode(c.comando('browsingContext.captureScreenshot',{'context':ctx,'origin':'viewport'})['data']))
        (trabajo/'badge-visual.json').write_text(json.dumps(medidas,ensure_ascii=False,indent=2))
    finally:
        firefox.terminate();firefox.wait(timeout=20);log.close();shutil.rmtree(perfil)
if __name__=='__main__':
    os.environ['BADGE_RECEPCION']='1'
    os.environ.setdefault('COMPRA_PRUEBA_RESULTADO','/tmp/badge-recepcion-original.json')
    from compra_recepcionista import ejecutar
    ejecutar()
