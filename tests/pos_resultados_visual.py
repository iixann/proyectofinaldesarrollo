"""Mide el POS real con búsqueda simulada; no carga .env ni toca bases de datos."""
from pathlib import Path
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
import subprocess,threading,tempfile,time,json,base64,sys,os
sys.dont_write_bytecode=True
from ean_scanner_visual import Bidi
from estabilizacion_visual import preparar_perfil
ORIGINAL=Path(__file__).resolve().parents[1]
ROOT=Path(os.environ.get('POS_RESULTADOS_RAIZ',ORIGINAL))
RESOLUCIONES=[(1366,768),(1920,1080),(1600,900),(1440,900),(1280,720),(1024,768)]
# Sirve vistas reales y dependencias locales de prueba sin acceder al backend.
class Servidor(BaseHTTPRequestHandler):
    def log_message(self,*_):pass
    def do_GET(self):
        ruta=self.path.split('?')[0]
        if ruta=='/pos':
            contenido=subprocess.check_output(['/opt/lampp/bin/php',str(ORIGINAL/'tests/pos_resultados_vista.php')],cwd=ROOT)
            for externo,local in [(b'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css',b'/bootstrap.css'),(b'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js',b'/bootstrap.js'),(b'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css',b'/iconos.css')]:contenido=contenido.replace(externo,local)
            tipo='text/html'
        elif ruta=='/iconos.css':contenido=b'';tipo='text/css'
        elif ruta in ['/bootstrap.css','/bootstrap.js']:
            contenido=Path('/tmp/ean-bootstrap.min.css' if ruta.endswith('css') else '/tmp/ean-bootstrap.bundle.min.js').read_bytes();tipo='text/css' if ruta.endswith('css') else 'text/javascript'
        else:
            archivo=(ROOT/'public'/ruta.removeprefix('/proyectofinaldesarrollo/')).resolve()
            if not archivo.is_relative_to(ROOT/'public') or not archivo.is_file() or archivo.suffix not in ['.css','.js']:self.send_error(404);return
            contenido=archivo.read_bytes();tipo='text/css' if archivo.suffix=='.css' else 'text/javascript'
        self.send_response(200);self.send_header('Content-Type',tipo);self.end_headers();self.wfile.write(contenido)
# Conserva capturas y métricas fuera del proyecto; el perfil se elimina al terminar.
def ejecutar():
    trabajo=Path(os.environ.get('POS_RESULTADOS_SALIDA','/tmp/pos-resultados-final'));trabajo.mkdir(parents=True,exist_ok=True)
    perfil=Path(tempfile.mkdtemp(prefix='perfil-',dir=trabajo));preparar_perfil(perfil)
    servidor=ThreadingHTTPServer(('127.0.0.1',18791),Servidor);threading.Thread(target=servidor.serve_forever,daemon=True).start()
    log=(trabajo/'firefox.log').open('w');firefox=subprocess.Popen(['/snap/firefox/current/usr/lib/firefox/firefox','--headless','--no-remote','--profile',str(perfil),'--remote-debugging-port','9223','about:blank'],stdout=log,stderr=log)
    checks=[];medidas=[]
    # Cada condición expresa un resultado visible, no una copia del código.
    def comprobar(ok,nombre):
        if not ok:raise AssertionError(nombre)
        checks.append(nombre)
    try:
        for _ in range(180):
            try:c=Bidi();break
            except OSError:time.sleep(.25)
        else:raise RuntimeError('No inició Firefox')
        c.comando('session.new',{'capabilities':{}});contexto=c.comando('browsingContext.getTree',{})['contexts'][0]['context']
        def ev(s):return c.evaluar(contexto,s)
        def ir():c.comando('browsingContext.navigate',{'context':contexto,'url':'http://127.0.0.1:18791/pos','wait':'complete'})
        def esperar(s):
            for _ in range(100):
                if ev(s):return
                time.sleep(.05)
            raise AssertionError('Espera: '+s)
        def capturar(nombre):(trabajo/(nombre+'.png')).write_bytes(base64.b64decode(c.comando('browsingContext.captureScreenshot',{'context':contexto})['data']))
        ir();esperar("document.getElementById('terminalPos') && typeof CarritoTemporal!=='undefined'")
        productos=[{'id_producto':i+1,'codigo_barras':str(i+1).zfill(13),'nombre_producto':'Producto de prueba '+str(i+1),'precio_unitario':'25.00','stock_disponible':100,'cantidad':1} for i in range(25)]
        geometria="""(()=>{const t=document.getElementById('tablaCarrito'),r=t.getBoundingClientRect(),s=document.querySelector('.pos-total-venta').getBoundingClientRect(),b=document.getElementById('abrirCobro').getBoundingClientRect();return {alto:t.clientHeight,scroll:t.scrollHeight,filas:document.querySelectorAll('#lineasCarrito tr').length,completas:[...document.querySelectorAll('#lineasCarrito tr')].filter(f=>{const x=f.getBoundingClientRect();return x.top>=r.top&&x.bottom<=r.bottom}).length,resultados:getComputedStyle(document.getElementById('resultadosBusqueda')).display,totalVisible:s.bottom<=innerHeight&&s.top>=r.bottom-1,cobrarVisible:b.bottom<=innerHeight,overflow:document.documentElement.scrollHeight>innerHeight+1}})()"""
        for n in [0,1,2,5,15,25]:
            ev("(()=>{const t=document.getElementById('terminalPos');CarritoTemporal.crear(t.dataset.usuario,t.dataset.sesion,t.dataset.contexto).guardar("+json.dumps(productos[:n])+",null);return true})()")
            ir();esperar('document.querySelectorAll("#lineasCarrito tr").length==='+str(n))
            for w,h in RESOLUCIONES:
                c.comando('browsingContext.setViewport',{'context':contexto,'viewport':{'width':w,'height':h},'devicePixelRatio':1});m=ev(geometria);m.update(ancho=w,altoPantalla=h,cantidad=n);medidas.append(m);(trabajo/'medidas.json').write_text(json.dumps(medidas,indent=2))
                comprobar(m['resultados']=='none',f'{w} / {n}: resultados no ocupan espacio')
                comprobar(m['totalVisible'] and m['cobrarVisible'] and not m['overflow'],f'{w} / {n}: resumen visible y sin scroll general')
                if n in [1,2]:comprobar(m['completas']==n,f'{w}: {n} filas completas')
                if n>=5:comprobar(m['completas']>=3,f'{w}: varias filas completas')
                if n>=15 and w==1366:comprobar(m['scroll']>m['alto'],f'{w}: scroll interno con {n} filas')
                if w==1366 and n in [2,5,25]:capturar('carrito-'+str(n))
        # Responde búsquedas solo en el navegador: nunca confirma una venta real.
        c.comando('browsingContext.setViewport',{'context':contexto,'viewport':{'width':1366,'height':768},'devicePixelRatio':1})
        ev("(()=>{sessionStorage.clear();return true})()");ir()
        for i in range(2):
            producto={'id_producto':i+1,'nombre_producto':'Producto '+str(i+1),'codigo_barras':str(i+1).zfill(13),'precio_venta':'25.00','stock_actual':100,'categoria':'Prueba'}
            ev("(()=>{window.fetch=async()=>new Response(JSON.stringify({ok:true,productos:["+json.dumps(producto)+"]}),{headers:{'content-type':'application/json'}});return true})()")
            ev("(()=>{document.dispatchEvent(new KeyboardEvent('keydown',{key:'F3',bubbles:true,cancelable:true}));document.getElementById('nombre').value='Producto';document.getElementById('buscarNombre').requestSubmit();return true})()")
            esperar("document.querySelector('#productosEncontrados button')!==null")
            comprobar(ev("getComputedStyle(document.getElementById('resultadosBusqueda')).display!=='none'"),'Resultados aparecen en nueva búsqueda')
            capturar('buscando-'+str(i));ev("(()=>{document.querySelector('#productosEncontrados button').click();return true})()")
            comprobar(ev("getComputedStyle(document.getElementById('resultadosBusqueda')).display==='none' && document.getElementById('nombre').value==='' && document.activeElement.id==='codigo'"),'Agregar oculta resultados, limpia nombre y enfoca EAN')
            comprobar(ev("document.getElementById('cantidadArticulos').textContent.includes('"+str(i+1)+"')"),'Producto agregado y total actualizado')
        comprobar(ev(geometria)['completas']==2,'Dos productos distintos visibles completos después de buscar')
        # Compila los scripts propios en el motor real del navegador.
        for archivo in (ROOT/'public/js').glob('*.js'):
            comprobar(ev('(()=>{new Function('+json.dumps(archivo.read_text())+');return true})()'),'Sintaxis JavaScript '+archivo.name)
        capturar('despues-de-agregar')
        # La fila seleccionada y el borrador siguen funcionando después de volver.
        ir();comprobar(ev("document.querySelectorAll('#lineasCarrito tr').length===2"),'Carrito restaurado tras navegar')
        ev("(()=>{document.querySelector('#lineasCarrito tr td').click();document.activeElement.dispatchEvent(new KeyboardEvent('keydown',{key:'Delete',bubbles:true,cancelable:true}));return true})()")
        comprobar(ev("document.querySelectorAll('#lineasCarrito tr').length===1"),'Supr elimina fila seleccionada restaurada')
        (trabajo/'resultado.json').write_text(json.dumps({'checks':len(checks),'medidas':medidas,'comprobaciones':checks},ensure_ascii=False,indent=2));print(json.dumps({'resultado':'OK','comprobaciones':len(checks),'directorio':str(trabajo)}),flush=True)
    finally:
        firefox.terminate();firefox.wait(timeout=20);servidor.shutdown();log.close()
        import shutil
        shutil.rmtree(perfil)
if __name__=='__main__':ejecutar()
