"""Revisa Usuarios por actor/objetivo y formulario de roles; usa el servidor temporal."""
from pathlib import Path
import subprocess,tempfile,time,json,base64,urllib.request,shutil,os
from ean_scanner_visual import Bidi
from estabilizacion_visual import preparar_perfil
# Navega con cuentas reales de prueba y nunca entra a la instalación comercial.
def revisar(base,trabajo,ids,check):
    for nombre,url in [('css','https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css'),('js','https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js')]:
        archivo=Path('/tmp/usuarios-bootstrap.'+nombre)
        if not archivo.exists():
            with urllib.request.urlopen(url,timeout=30) as r:archivo.write_bytes(r.read())
    perfil=trabajo/'firefox';perfil.mkdir();preparar_perfil(perfil);log=(trabajo/'firefox.log').open('w')
    firefox=subprocess.Popen(['/snap/firefox/current/usr/lib/firefox/firefox','--headless','--no-remote','--profile',str(perfil),'--remote-debugging-port','9223','about:blank'],stdout=log,stderr=log)
    medidas=[];capturas=trabajo/'visual';capturas.mkdir()
    try:
        for _ in range(180):
            try:c=Bidi();break
            except OSError:time.sleep(.25)
        else:raise RuntimeError('No inició Firefox')
        c.comando('session.new',{'capabilities':{}});ctx=c.comando('browsingContext.getTree',{})['contexts'][0]['context']
        # Evalúa expresiones en la página real.
        def ev(s):return c.evaluar(ctx,s)
        # Espera eventos/estado visible, sin asumir tiempos de la animación.
        def esperar(s):
            for _ in range(150):
                if ev(s):return
                time.sleep(.05)
            raise AssertionError(s)
        # Navega exclusivamente dentro del servidor aislado.
        def ir(ruta):c.comando('browsingContext.navigate',{'context':ctx,'url':base+ruta,'wait':'complete'})
        # Conserva evidencia fuera del repositorio.
        def capturar(nombre):(capturas/(nombre+'.png')).write_bytes(base64.b64decode(c.comando('browsingContext.captureScreenshot',{'context':ctx,'origin':'viewport'})['data']))
        for actor,correo,clave in [('principal','admin@admin.com','admin1234'),('comun','admin-a@test.invalid','pruebaaislada123')]:
            ir('session/logout');ir('session/login')
            ev("(()=>{document.querySelector('[name=correo]').value="+json.dumps(correo)+";document.querySelector('[name=contrasena]').value="+json.dumps(clave)+";document.querySelector('form').requestSubmit();return true})()")
            esperar("!location.pathname.endsWith('/session/login')")
            # Evita repetir la tabla si solo se revalidan los formularios.
            for w,h in ([] if os.environ.get('USUARIOS_SOLO_FORMULARIOS')=='1' else [(1366,768),(1024,768),(390,844)]):
                c.comando('browsingContext.setViewport',{'context':ctx,'viewport':{'width':w,'height':h},'devicePixelRatio':1});ir('user/index')
                check(ev('document.documentElement.scrollWidth<=innerWidth+1'),'Usuarios sin overflow general '+actor+' '+str(w))
                check(ev("document.querySelectorAll('[data-usuario-acciones]').length===(document.querySelectorAll('tbody tr').length-1)"),'Un botón Acciones por usuario administrable')
                check(ev("[...document.querySelectorAll('tbody tr')].every(f=>f.getBoundingClientRect().height<100)"),'Filas compactas con correo/nombre legibles '+str(w))
                if w==1366:capturar(actor+'-cerrado')
                for objetivo in ['principal','admin','vendedor','recepcion','mixto','dueno','inactivo']:
                    uid=ids[objetivo]
                    if objetivo=='principal':
                        check(ev("(()=>{const f=[...document.querySelectorAll('tbody tr')].find(f=>f.cells[0].textContent.trim()==="+json.dumps(str(uid))+");return !f.querySelector('[data-usuario-acciones]')&&f.cells[6].textContent.includes('Principal protegido')})()"),'Principal sin dropdown ni acciones '+actor+' '+str(w))
                        continue
                    ev("(()=>{const f=[...document.querySelectorAll('tbody tr')].find(f=>f.cells[0].textContent.trim()==="+json.dumps(str(uid))+");const b=f.querySelector('[data-usuario-acciones]');b.scrollIntoView({block:'center',inline:'end',behavior:'instant'});b.click();return true})()")
                    esperar("document.querySelector('.dropdown-menu.show')!==null");time.sleep(.1)
                    m=ev("""(()=>{const menu=document.querySelector('.dropdown-menu.show'),r=menu.getBoundingClientRect(),op=[...menu.querySelectorAll('.dropdown-item')],f=menu.closest('tr');return {left:r.left,right:r.right,top:r.top,bottom:r.bottom,ancho:f.cells[6].getBoundingClientRect().width,opciones:op.map(o=>o.textContent.trim()),roles:!!menu.querySelector('a[href*="/cambiarRoles/"]'),rojo:!!menu.querySelector('button.text-danger'),visible:op.filter(e=>!e.classList.contains('disabled')).every(e=>{const r=e.getBoundingClientRect();return e.contains(document.elementFromPoint(r.x+r.width/2,r.y+r.height/2))})}})()""")
                    protegido=objetivo=='principal' or (actor=='comun' and objetivo=='admin')
                    check(m['roles']==(not protegido),'Cambiar roles según actor/objetivo '+actor+' '+objetivo)
                    check(m['visible'] and m['left']>=0 and m['right']<=w+1 and m['top']>=0 and m['bottom']<=h+1,'Menú completo sin recorte '+actor+' '+objetivo+' '+str(w))
                    check(m['ancho']<160,'Columna compacta de acciones')
                    check('Editar' not in m['opciones'] and 'Restablecer contraseña' in m['opciones'],'Solo acciones existentes más Cambiar roles')
                    check(('Baja protegida' in m['opciones']) if objetivo=='principal' else ('Reactivar' in m['opciones']) if objetivo=='inactivo' else ('Dar de baja' in m['opciones'] and m['rojo']),'Baja/reactivación y principal correctos')
                    medidas.append({'actor':actor,'objetivo':objetivo,'resolucion':[w,h],**m})
                    if objetivo in ['mixto','inactivo'] and w in [1366,390]:capturar(actor+'-'+objetivo+'-'+str(w))
                    # Cancelar conserva filas y el formulario POST existente.
                    if objetivo=='vendedor' and w==1366:
                        ev("(()=>{window.modalListo=false;document.getElementById('confirmarBaja').addEventListener('shown.bs.modal',()=>{window.modalListo=true},{once:true});document.querySelector('.dropdown-menu.show form[data-confirmar-baja] button').click();return true})()")
                        esperar('window.modalListo===true')
                        check(ev("document.querySelector('[data-pregunta-baja]').textContent.includes('vendedor-valido')"),'Baja mantiene confirmación con nombre')
                        ev("(()=>{document.querySelector('#confirmarBaja [data-bs-dismiss=modal]').click();return true})()")
                        esperar("!document.querySelector('.modal-backdrop')")
                    ev("(()=>{document.querySelectorAll('[data-usuario-acciones]').forEach(b=>bootstrap.Dropdown.getInstance(b)?.hide());return true})()")
            if os.environ.get('USUARIOS_SOLO_FORMULARIOS')!='1': print('Visual Usuarios '+actor+': 7 objetivos × 3 resoluciones OK',flush=True)
            # Creación y cambio comparten exactamente las mismas restricciones visuales.
            for ruta in ['user/crear','user/cambiarRoles/'+str(ids['vendedor'])]:
                ir(ruta)
                ev("(()=>{document.querySelectorAll('[data-roles-exclusivos] input').forEach(c=>{c.checked=false;c.disabled=false});return true})()")
                for exclusivo in ['ADMINISTRADOR','DUEÑO']:
                    ev("(()=>{document.querySelector('[data-rol=\""+exclusivo+"\"]').click();return true})()")
                    check(ev("(()=>{const xs=[...document.querySelectorAll('[data-rol]')];return xs.filter(c=>c.checked).length===1&&xs.every(c=>c.checked||c.disabled)})()"),'Rol exclusivo bloquea los demás en '+ruta)
                    ev("(()=>{document.querySelector('[data-rol=\""+exclusivo+"\"]').click();return true})()")
                    check(ev("[...document.querySelectorAll('[data-rol]')].every(c=>!c.disabled)"),'Desmarcar habilita selección nueva')
                ev("(()=>{document.querySelector('[data-rol=VENDEDOR]').click();document.querySelector('[data-rol=RECEPCIONISTA]').click();return true})()")
                check(ev("[...document.querySelectorAll('[data-rol]')].filter(c=>c.checked).length===2"),'Operativos se combinan en '+ruta)
                ev("(()=>{document.querySelector('[data-rol=ADMINISTRADOR]').click();return true})()")
                check(ev("[...document.querySelectorAll('[data-rol]')].filter(c=>c.checked).length===1"),'Elegir administrador desmarca operativos')
                capturar(actor+'-'+ruta.replace('/','_'))
                # El envío vacío se detiene antes de llamar al backend.
                ev("(()=>{document.querySelector('[data-rol=ADMINISTRADOR]').click();const f=document.querySelector('[data-roles-exclusivos]').closest('form');if(f.elements.nombre){f.elements.nombre.value='Prueba';f.elements.apellido.value='Visual';f.elements.correo.value='visual-no-guardar@test.invalid';f.elements.contrasena.value='pruebaaislada123';}f.requestSubmit();return true})()")
                check(ev("!document.querySelector('[data-error-roles]').hidden"),'Frontend impide guardar sin roles en '+ruta)
        codigo=(Path(__file__).resolve().parents[1]/'public/js/usuarios.js').read_text()
        check(ev('(()=>{new Function('+json.dumps(codigo)+');return true})()'),'JavaScript de Usuarios compila en Firefox')
        (trabajo/'usuarios-visual.json').write_text(json.dumps(medidas,ensure_ascii=False,indent=2))
    finally:
        firefox.terminate();firefox.wait(timeout=20);log.close();shutil.rmtree(perfil)
