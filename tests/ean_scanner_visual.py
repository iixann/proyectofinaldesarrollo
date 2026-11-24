"""Verifica las vistas originales con Firefox, datos ficticios y cámara simulada."""
from pathlib import Path
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
import base64
import json
import os
import socket
import struct
import sys
import subprocess
import tempfile
import threading
import time
import urllib.parse

ROOT = Path(__file__).resolve().parents[1]

# Sirve únicamente vistas ficticias y recursos públicos del proyecto original.
class Vistas(BaseHTTPRequestHandler):
    # Evita llenar la salida con cada recurso solicitado por el navegador.
    def log_message(self, *_args):
        pass

    # Renderiza los PHP originales sin cargar credenciales ni base real.
    def do_GET(self):
        ruta = urllib.parse.urlparse(self.path).path
        if ruta.startswith('/fixture/'):
            modulo = ruta.split('/')[-1]
            if modulo not in ['producto', 'compra', 'directa', 'recepcion', 'stock', 'reposicion']:
                self.send_error(404)
                return
            proceso = subprocess.run(['/opt/lampp/bin/php', 'tests/ean_scanner_vistas.php', modulo], cwd=ROOT, capture_output=True, check=True)
            if proceso.stderr:
                raise RuntimeError(proceso.stderr.decode())
            contenido = proceso.stdout.replace(b'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css', b'/bootstrap.css').replace(b'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js', b'/bootstrap.js')
            contenido = contenido.replace(b'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css', b'/iconos.css')
            tipo = 'text/html; charset=utf-8'
        elif ruta in ['/bootstrap.css', '/bootstrap.js']:
            archivo = Path('/tmp/ean-bootstrap.min.css' if ruta.endswith('css') else '/tmp/ean-bootstrap.bundle.min.js')
            contenido = archivo.read_bytes()
            tipo = 'text/css' if ruta.endswith('css') else 'text/javascript'
        elif ruta == '/iconos.css':
            contenido, tipo = b'', 'text/css'
        else:
            archivo = (ROOT / 'public' / ruta.removeprefix('/proyectofinaldesarrollo/').removeprefix('public/')).resolve()
            if not archivo.is_relative_to(ROOT / 'public') or archivo.suffix not in ['.js', '.css'] or not archivo.is_file():
                self.send_error(404)
                return
            contenido = archivo.read_bytes()
            tipo = 'text/css' if archivo.suffix == '.css' else 'text/javascript'
        self.send_response(200)
        self.send_header('Content-Type', tipo)
        self.send_header('Content-Length', str(len(contenido)))
        self.end_headers()
        self.wfile.write(contenido)

# Cliente mínimo del protocolo oficial WebDriver BiDi, sin dependencias Python.
class Bidi:
    # Abre una conexión WebSocket local contra el Firefox de prueba.
    def __init__(self):
        # Un perfil nuevo puede tardar en arrancar en equipos con poca RAM.
        self.socket = socket.create_connection(('127.0.0.1', 9223), timeout=120)
        clave = base64.b64encode(os.urandom(16)).decode()
        self.socket.sendall(f'GET /session HTTP/1.1\r\nHost: 127.0.0.1:9223\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Key: {clave}\r\nSec-WebSocket-Version: 13\r\n\r\n'.encode())
        cabecera = b''
        while not cabecera.endswith(b'\r\n\r\n'):
            cabecera += self.socket.recv(1)
        if b'101 Switching Protocols' not in cabecera:
            raise RuntimeError(cabecera.decode())
        self.contador = 0

    # Lee la cantidad exacta de bytes del siguiente mensaje.
    def leer(self, cantidad):
        datos = b''
        while len(datos) < cantidad:
            fragmento = self.socket.recv(cantidad - len(datos))
            if not fragmento:
                raise RuntimeError('Firefox cerró la conexión')
            datos += fragmento
        return datos

    # Envía un comando y espera su respuesta, ignorando eventos ajenos.
    def comando(self, metodo, parametros):
        self.contador += 1
        mensaje = json.dumps({'id': self.contador, 'method': metodo, 'params': parametros}).encode()
        longitud = len(mensaje)
        cabecera = bytes([0x81, 0x80 | longitud]) if longitud < 126 else (b'\x81\xfe' + struct.pack('!H', longitud) if longitud < 65536 else b'\x81\xff' + struct.pack('!Q', longitud))
        mascara = os.urandom(4)
        self.socket.sendall(cabecera + mascara + bytes(valor ^ mascara[i % 4] for i, valor in enumerate(mensaje)))
        while True:
            inicio = self.leer(2)
            longitud = inicio[1] & 127
            if longitud == 126:
                longitud = struct.unpack('!H', self.leer(2))[0]
            elif longitud == 127:
                longitud = struct.unpack('!Q', self.leer(8))[0]
            datos = self.leer(longitud)
            respuesta = json.loads(datos)
            if respuesta.get('id') == self.contador:
                if respuesta.get('type') == 'error':
                    raise RuntimeError(respuesta)
                return respuesta['result']

    # Evalúa una comprobación dentro de la página y devuelve su JSON.
    def evaluar(self, contexto, expresion):
        respuesta = self.comando('script.evaluate', {'expression': '(async () => JSON.stringify(await (' + expresion + ')))()', 'target': {'context': contexto}, 'awaitPromise': True})
        if respuesta.get('type') == 'exception':
            raise RuntimeError(respuesta)
        return json.loads(respuesta['result']['value'])

# Ejecuta seis vistas a tres anchos y conserva capturas para revisión visual.
def ejecutar():
    servidor = ThreadingHTTPServer(('127.0.0.1', 8766), Vistas)
    threading.Thread(target=servidor.serve_forever, daemon=True).start()
    # El perfil y las capturas de prueba quedan fuera del código del proyecto.
    with tempfile.TemporaryDirectory(prefix='ean-firefox-', dir='/tmp') as perfil:
        log = open('/tmp/ean-firefox.log', 'w')
        binario = '/snap/firefox/current/usr/lib/firefox/firefox' if Path('/snap/firefox/current/usr/lib/firefox/firefox').is_file() else 'firefox'
        firefox = subprocess.Popen([binario, '--headless', '--no-remote', '--profile', perfil, '--remote-debugging-port', '9223'], stdout=log, stderr=log)
        try:
            cliente = None
            for _ in range(160):
                try:
                    cliente = Bidi()
                    break
                except (OSError, RuntimeError):
                    time.sleep(.25)
            if cliente is None:
                raise RuntimeError(Path('/tmp/ean-firefox.log').read_text())
            cliente.comando('session.new', {'capabilities': {}})
            contexto = cliente.comando('browsingContext.create', {'type': 'tab'})['context']
            resultados = []
            modulos = []
            for ancho in ([] if '--solo-lector' in sys.argv else [360, 390, 412]):
                cliente.comando('browsingContext.setViewport', {'context': contexto, 'viewport': {'width': ancho, 'height': 844}, 'devicePixelRatio': 1})
                for modulo in ['producto', 'compra', 'directa', 'recepcion', 'stock', 'reposicion']:
                    cliente.comando('browsingContext.navigate', {'context': contexto, 'url': 'http://127.0.0.1:8766/fixture/' + modulo, 'wait': 'complete'})
                    time.sleep(.3)
                    datos = cliente.evaluar(contexto, '''({ancho: innerWidth, contenido: document.documentElement.scrollWidth, botones: [...document.querySelectorAll('[data-escanear-ean]')].map(b => ({ancho: b.getBoundingClientRect().width, alto: b.getBoundingClientRect().height, minimo: getComputedStyle(b).minHeight})), lector: !!window.EanScanner})''')
                    assert datos['contenido'] <= ancho, (modulo, datos)
                    assert datos['lector'] and datos['botones'] and all(b['alto'] >= 43.9 for b in datos['botones']), (modulo, datos)
                    cliente.evaluar(contexto, "(() => { if (!document.querySelector('[data-ean-resumen]')) document.querySelector('[data-escanear-ean]').scrollIntoView({block: 'center', behavior: 'instant'}); return true; })()")
                    captura = cliente.comando('browsingContext.captureScreenshot', {'context': contexto})
                    Path(f'/tmp/ean-{modulo}-{ancho}.png').write_bytes(base64.b64decode(captura['data']))
                    resultados.append({'modulo': modulo, **datos})
                    if ancho == 390 and modulo != 'producto':
                        adaptadores = (ROOT / 'tests/ean_modulos.js').read_text()
                        modulos.append(cliente.evaluar(contexto, '(async () => {' + adaptadores + '\nreturn await probarModuloEan(' + json.dumps(modulo) + ');})()'))
            # Revisa también el modal y sus errores a los tres anchos móviles.
            for ancho in [360, 390, 412]:
                cliente.comando('browsingContext.setViewport', {'context': contexto, 'viewport': {'width': ancho, 'height': 844}, 'devicePixelRatio': 1})
                cliente.comando('browsingContext.navigate', {'context': contexto, 'url': 'http://127.0.0.1:8766/fixture/producto', 'wait': 'complete'})
                datos = cliente.evaluar(contexto, '''(async () => {
                    window.BarcodeDetector = class { static async getSupportedFormats() { return ['ean_13', 'ean_8']; } };
                    Object.defineProperty(navigator, 'mediaDevices', {configurable: true, value: {getUserMedia: async () => { throw new DOMException('Prueba', 'NotAllowedError'); }}});
                    EanScanner.abrir(() => {});
                    await new Promise(resolve => setTimeout(resolve, 750));
                    const modal = document.getElementById('escanerEan');
                    return {ancho: modal.scrollWidth, mensaje: modal.querySelector('[data-ean-estado]').textContent};
                })()''')
                assert datos['ancho'] <= ancho and 'permiso' in datos['mensaje'], datos
                captura = cliente.comando('browsingContext.captureScreenshot', {'context': contexto})
                Path(f'/tmp/ean-modal-{ancho}.png').write_bytes(base64.b64decode(captura['data']))
            cliente.comando('browsingContext.navigate', {'context': contexto, 'url': 'http://127.0.0.1:8766/fixture/producto', 'wait': 'complete'})
            codigo = (ROOT / 'tests/ean_scanner.js').read_text()
            pruebas = cliente.evaluar(contexto, '(async () => {' + codigo + '\nreturn await probarEscanerEan();})()')
            Path('/tmp/ean-scanner-lector.json' if '--solo-lector' in sys.argv else '/tmp/ean-scanner-resultados.json').write_text(json.dumps({'vistas': resultados, 'modulos': modulos, 'lector': pruebas}, ensure_ascii=False, indent=2))
            print(json.dumps({'vistas': len(resultados), 'modulos': modulos, 'lector': pruebas}, ensure_ascii=False))
            cliente.comando('session.end', {})
        finally:
            firefox.terminate()
            firefox.wait(timeout=15)
            servidor.shutdown()
            log.close()

if __name__ == '__main__':
    ejecutar()
