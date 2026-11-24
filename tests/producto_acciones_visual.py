"""Verifica dropdowns Bootstrap por rol y su recorte en tablas; nunca usa una base real."""
from pathlib import Path
from http.server import BaseHTTPRequestHandler,ThreadingHTTPServer
import subprocess,threading,tempfile,time,json,base64,sys,os,shutil
from urllib.parse import unquote
sys.dont_write_bytecode=True
from ean_scanner_visual import Bidi
from estabilizacion_visual import preparar_perfil
ORIGINAL=Path(__file__).resolve().parents[1]
ROOT=Path(os.environ.get('PRODUCTO_ACCIONES_RAIZ',ORIGINAL))
RESOLUCIONES=[(1920,1080),(1366,768),(1280,720),(1024,768),(390,844)]
# Sirve únicamente la vista ficticia y los recursos originales de presentación.
class Servidor(BaseHTTPRequestHandler):
    def log_message(self,*_):pass
    def do_GET(self):
        ruta=self.path.split('?')[0]
        if ruta.startswith('/productos/'):
            contenido=subprocess.check_output(['/opt/lampp/bin/php',str(ORIGINAL/'tests/producto_acciones_vista.php'),unquote(ruta.split('/')[-1])],cwd=ROOT)
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
# Mide también hit-testing para detectar menús visualmente cortados por overflow.
def ejecutar():
    trabajo=Path(os.environ.get('PRODUCTO_ACCIONES_SALIDA','/tmp/producto-acciones-original'));trabajo.mkdir(parents=True,exist_ok=True)
    perfil=Path(tempfile.mkdtemp(prefix='perfil-',dir=trabajo));preparar_perfil(perfil)
    servidor=ThreadingHTTPServer(('127.0.0.1',18791),Servidor);threading.Thread(target=servidor.serve_forever,daemon=True).start()
    log=(trabajo/'firefox.log').open('w');firefox=subprocess.Popen(['/snap/firefox/current/usr/lib/firefox/firefox','--headless','--no-remote','--profile',str(perfil),'--remote-debugging-port','9223','about:blank'],stdout=log,stderr=log)
    checks=[];medidas=[]
    # Detiene la prueba ante cualquier permiso visual o recorte incorrecto.
    def check(ok,nombre):
        if not ok:raise AssertionError(nombre)
        checks.append(nombre)
    try:
        for _ in range(180):
            try:c=Bidi();break
            except OSError:time.sleep(.25)
        else:raise RuntimeError('No inició Firefox')
        c.comando('session.new',{'capabilities':{}});contexto=c.comando('browsingContext.getTree',{})['contexts'][0]['context']
        def ev(s):return c.evaluar(contexto,s)
        def esperar(s):
            for _ in range(100):
                if ev(s):return
                time.sleep(.05)
            raise AssertionError(s)
        def captura(n):(trabajo/(n+'.png')).write_bytes(base64.b64decode(c.comando('browsingContext.captureScreenshot',{'context':contexto,'origin':'viewport'})['data']))
        for rol in ['ADMINISTRADOR','DUEÑO','RECEPCIONISTA','VENDEDOR']:
            c.comando('browsingContext.navigate',{'context':contexto,'url':'http://127.0.0.1:18791/productos/'+rol,'wait':'complete'})
            check(ev("!document.body.innerText.includes('Warning:') && document.title.includes('productos')"),rol+' vista válida')
            for w,h in RESOLUCIONES:
                c.comando('browsingContext.setViewport',{'context':contexto,'viewport':{'width':w,'height':h},'devicePixelRatio':1})
                ev("(()=>{scrollTo({top:0,behavior:'instant'});return true})()")
                check(ev('document.documentElement.scrollWidth<=innerWidth+1'),rol+' '+str(w)+' sin overflow general')
                check(ev("[...document.querySelectorAll('tbody .badge')].every(e=>e.getBoundingClientRect().height<35)"),rol+' estados compactos')
                if rol=='VENDEDOR':
                    check(ev("!document.querySelector('[data-producto-acciones]') && ![...document.querySelectorAll('th')].some(e=>e.textContent==='Acciones') && document.querySelector('thead tr').children.length===10"),'Vendedor sin menú ni columna vacía')
                    if w in [1366,390]:captura(rol+'-'+str(w))
                    continue
                check(ev("document.querySelectorAll('[data-producto-acciones]').length===9"),rol+' botón único por fila')
                if w==1366:captura(rol+'-cerrado')
                for i in [0,4,8]:
                    ev("(()=>{const b=document.querySelectorAll('[data-producto-acciones]')["+str(i)+"];b.scrollIntoView({block:'center',inline:'end',behavior:'instant'});b.click();return true})()")
                    esperar("document.querySelector('.dropdown-menu.show')!==null")
                    time.sleep(.1)
                    m=ev("""(()=>{const m=document.querySelector('.dropdown-menu.show'),r=m.getBoundingClientRect(),op=[...m.querySelectorAll('.dropdown-item')];return {x:r.x,y:r.y,right:r.right,bottom:r.bottom,w:innerWidth,h:innerHeight,acciones:op.map(e=>e.textContent.trim()),posicion:getComputedStyle(m).position,visible:op.every(e=>{const r=e.getBoundingClientRect();return e.contains(document.elementFromPoint(r.x+r.width/2,r.y+r.height/2))}),editar:op[0].getAttribute('href'),anchoColumna:m.closest('td').getBoundingClientRect().width}})()""")
                    check(m['x']>=0 and m['y']>=0 and m['right']<=w+1 and m['bottom']<=h+1 and m['visible'],f'{rol} {w} fila {i}: menú entero y accesible {m}')
                    esperado=['Editar','Auditoría','Movimientos']+(['Reactivar'] if i==8 else ['Ajustar stock','Registrar decomiso','Dar de baja'])
                    check(m['acciones']==esperado,f'{rol} fila {i}: exactamente las acciones existentes')
                    check(m['editar'].endswith('/producto/editar/'+str(i+1)),'Editar conserva su ruta')
                    check(m['anchoColumna']<160,'Columna de acciones compacta')
                    medidas.append({'rol':rol,'ancho':w,'fila':i,**m})
                    if i==4 and w in [1366,390]:captura(rol+'-abierto-'+str(w))
                    if i==0 and w==1366:
                        ev("(()=>{window.bajaLista=false;document.getElementById('confirmarBaja').addEventListener('shown.bs.modal',()=>{window.bajaLista=true},{once:true});document.querySelector('.dropdown-menu.show form[data-confirmar-baja] button').click();return true})()")
                        esperar("window.bajaLista===true")
                        check(ev("document.querySelector('[data-pregunta-baja]').textContent.includes('Vino Malbec 1')"),'Baja conserva confirmación con producto correcto')
                        ev("(()=>{document.querySelector('#confirmarBaja [data-bs-dismiss=modal]').click();return true})()")
                        esperar("!document.querySelector('.modal-backdrop')")
                        check(ev("document.querySelectorAll('tbody tr').length===9"),'Cancelar no realiza baja')
                    ev("(()=>{document.querySelectorAll('[data-producto-acciones]').forEach(b=>bootstrap.Dropdown.getInstance(b)?.hide());return true})()")
        check(ev('(()=>{new Function('+json.dumps((ROOT/'public/js/producto-acciones.js').read_text())+');return true})()'),'Sintaxis del JS en Firefox correcta')
        (trabajo/'resultado.json').write_text(json.dumps({'comprobaciones':len(checks),'checks':checks,'medidas':medidas},ensure_ascii=False,indent=2));print(json.dumps({'resultado':'OK','comprobaciones':len(checks),'capturas':str(trabajo)}),flush=True)
    finally:
        firefox.terminate();firefox.wait(timeout=20);servidor.shutdown();log.close();shutil.rmtree(perfil)
if __name__=='__main__':ejecutar()
