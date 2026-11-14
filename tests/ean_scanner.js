// Pruebas del lector en un navegador real, con cámara simulada y sin backend.
async function probarEscanerEan() {
    const comprobaciones = [];
    const lector = window.EanScanner;
    const modal = document.getElementById('escanerEan');
    const boton = document.querySelector('[data-escanear-ean]');
    const campo = document.getElementById('codigo_barras');
    const formulario = campo.form;
    const pistas = [];
    let envios = 0;
    let entradas = 0;
    let detectados = [];
    let solicitud;
    let solicitudes = 0;
    const seguro = window.isSecureContext;
    const detectorOriginal = window.BarcodeDetector;
    const dispositivos = navigator.mediaDevices;
    const eventos = [];
    const cuadros = [];

    // Registra una condición y muestra el caso que falló.
    function comprobar(condicion, mensaje) {
        if (!condicion) throw new Error(mensaje);
        comprobaciones.push(mensaje);
    }

    // Espera cambios asíncronos de vídeo, Bootstrap o reconocimiento.
    async function esperar(condicion, mensaje, limite = 5000) {
        const inicio = performance.now();
        while (!condicion()) {
            if (performance.now() - inicio > limite) throw new Error('Tiempo agotado: ' + mensaje);
            await new Promise(resolve => setTimeout(resolve, 30));
        }
    }

    // Crea una cámara de canvas que permite comprobar el cierre de sus pistas.
    function camara(codigo = null) {
        const canvas = document.createElement('canvas');
        canvas.width = 540; canvas.height = 320;
        const contexto = canvas.getContext('2d');
        contexto.fillStyle = '#fff'; contexto.fillRect(0, 0, canvas.width, canvas.height);
        if (codigo) dibujarCodigo(contexto, codigo);
        const stream = canvas.captureStream(10);
        pistas.push(...stream.getTracks());
        cuadros.push(canvas);
        return stream;
    }

    // Dibuja un EAN real para verificar ZXing, sin simular el decodificador.
    function dibujarCodigo(contexto, codigo) {
        const l = ['0001101','0011001','0010011','0111101','0100011','0110001','0101111','0111011','0110111','0001011'];
        const g = ['0100111','0110011','0011011','0100001','0011101','0111001','0000101','0010001','0001001','0010111'];
        const paridades = ['LLLLLL','LLGLGG','LLGGLG','LLGGGL','LGLLGG','LGGLLG','LGGGLL','LGLGLG','LGLGGL','LGGLGL'];
        const derecha = digito => l[Number(digito)].replace(/[01]/g, bit => bit === '1' ? '0' : '1');
        let barras = '101';
        if (codigo.length === 13) {
            for (let i = 1; i <= 6; i++) barras += (paridades[Number(codigo[0])][i - 1] === 'L' ? l : g)[Number(codigo[i])];
            barras += '01010';
            for (let i = 7; i < 13; i++) barras += derecha(codigo[i]);
        } else {
            for (let i = 0; i < 4; i++) barras += l[Number(codigo[i])];
            barras += '01010';
            for (let i = 4; i < 8; i++) barras += derecha(codigo[i]);
        }
        barras += '101';
        contexto.fillStyle = '#000';
        [...barras].forEach((bit, i) => { if (bit === '1') contexto.fillRect(40 + i * 4, 40, 4, 220); });
    }

    // Abre el componente y espera que el vídeo esté listo para detectar.
    async function abrir() {
        const mostrado = new Promise(resolve => modal.addEventListener('shown.bs.modal', resolve, {once: true}));
        comprobar(lector.abrir(codigo => eventos.push(codigo)), 'Abre una sesión de escaneo');
        await mostrado;
    }

    // Cierra el modal y comprueba que no queda ninguna cámara encendida.
    async function cancelar() {
        lector.cancelar();
        await esperar(() => !modal.classList.contains('show') && !document.querySelector('.modal-backdrop'), 'modal cerrado');
        comprobar(pistas.every(pista => pista.readyState === 'ended'), 'Cancelar detiene todas las pistas');
    }

    // Simula solo la API nativa; los streams y los modales son reales.
    class DetectorFixture {
        // Expone los dos formatos comerciales que necesita el lector.
        static async getSupportedFormats() { return ['ean_13', 'ean_8']; }
        // Entrega los códigos configurados para este caso de prueba.
        async detect() { return detectados.map(rawValue => ({rawValue})); }
    }
    window.BarcodeDetector = DetectorFixture;
    Object.defineProperty(navigator, 'mediaDevices', {configurable: true, value: {getUserMedia: async opciones => {
        solicitudes++;
        comprobar(opciones.audio === false && opciones.video.facingMode.ideal === 'environment', 'Solicita cámara trasera sin audio');
        return solicitud ? solicitud() : camara();
    }}});
    campo.addEventListener('input', () => entradas++);
    formulario.addEventListener('submit', evento => { evento.preventDefault(); envios++; });
    try {
        comprobar(lector.esEanValido('4006381333931') && lector.esEanValido('96385074') && lector.esEanValido('0000000000017'), 'Acepta EAN-13, EAN-8 y ceros iniciales');
        comprobar(!lector.esEanValido('4006381333932') && !lector.esEanValido(4006381333931) && !lector.esEanValido('<script>'), 'Rechaza checksum incorrecto, números y texto ajeno');
        const antesDeAnimar = solicitudes;
        lector.abrir(codigo => eventos.push(codigo));
        lector.cancelar();
        await esperar(() => !modal.classList.contains('show') && !document.querySelector('.modal-backdrop'), 'cancelación durante animación');
        comprobar(solicitudes === antesDeAnimar, 'Cancelar durante la animación no solicita cámara');
        await abrir();
        await esperar(() => pistas.length > 0, 'cámara iniciada');
        comprobar(!lector.abrir(() => {}), 'Impide abrir dos sesiones simultáneas');
        await cancelar();
        comprobar(eventos.length === 0, 'Cancelar no entrega códigos');

        // Si el permiso llega tarde, el stream no debe quedar encendido.
        let resolver;
        const anteriores = solicitudes;
        solicitud = () => new Promise(resolve => { resolver = resolve; });
        await abrir();
        await esperar(() => solicitudes > anteriores, 'permiso pendiente');
        await cancelar();
        resolver(camara());
        await esperar(() => pistas.every(pista => pista.readyState === 'ended'), 'stream tardío liberado');
        comprobar(true, 'Libera un permiso concedido después de cancelar');

        for (const nombre of ['NotAllowedError', 'NotFoundError', 'NotReadableError']) {
            solicitud = async () => { throw new DOMException('simulado', nombre); };
            await abrir();
            await esperar(() => !modal.querySelector('[data-ean-reintentar]').hidden, nombre);
            comprobar(modal.querySelector('[data-ean-estado]').textContent.length > 40, 'Explica el error ' + nombre);
            await cancelar();
        }
        const mediaSimulada = navigator.mediaDevices;
        Object.defineProperty(navigator, 'mediaDevices', {configurable: true, value: undefined});
        await abrir();
        await esperar(() => modal.querySelector('[data-ean-estado]').textContent.includes('no dispone'), 'navegador sin cámara');
        comprobar(true, 'Explica la falta de API de cámara');
        await cancelar();
        Object.defineProperty(navigator, 'mediaDevices', {configurable: true, value: mediaSimulada});
        Object.defineProperty(window, 'isSecureContext', {configurable: true, value: false});
        const antesHttp = solicitudes;
        await abrir();
        await esperar(() => modal.querySelector('[data-ean-estado]').textContent.includes('HTTPS'), 'aviso HTTPS');
        comprobar(solicitudes === antesHttp, 'HTTP inseguro informa HTTPS sin solicitar cámara');
        await cancelar();
        Object.defineProperty(window, 'isSecureContext', {configurable: true, value: seguro});

        solicitud = null;
        detectados = ['4006381333931'];
        boton.click();
        await esperar(() => campo.value === '4006381333931', 'campo de producto completo');
        comprobar(envios === 0 && pistas.every(pista => pista.readyState === 'ended'), 'Productos completa el campo sin guardar y apaga la cámara');
        await new Promise(resolve => setTimeout(resolve, 400));
        comprobar(entradas === 1 && envios === 0 && !modal.classList.contains('show'), 'La detección doble entrega una sola lectura y no envía el formulario');
        detectados = ['4006381333932'];
        await abrir();
        await new Promise(resolve => setTimeout(resolve, 700));
        comprobar(eventos.length === 0 && modal.classList.contains('show'), 'Un EAN con checksum incorrecto no se entrega');
        await cancelar();

        // Firefox ejercita la alternativa real que también se utiliza sin BarcodeDetector.
        window.BarcodeDetector = undefined;
        for (const codigo of ['4006381333931', '96385074']) {
            solicitud = async () => camara(codigo);
            await abrir();
            await esperar(() => eventos.includes(codigo), 'ZXing reconoce ' + codigo, 10000);
            comprobar(pistas.every(pista => pista.readyState === 'ended'), 'ZXing real reconoce ' + codigo + ' y libera cámara');
            await esperar(() => !document.querySelector('.modal-backdrop'), 'alternativa cerrada');
        }
        window.BarcodeDetector = class extends DetectorFixture {
            // Simula una API nativa anunciada pero incapaz de procesar vídeo.
            async detect() { throw new Error('API nativa no disponible'); }
        };
        solicitud = async () => camara('4006381333931');
        const antesDelFallo = eventos.length;
        await abrir();
        await esperar(() => eventos.length === antesDelFallo + 1, 'alternativa tras fallo nativo');
        comprobar(pistas.every(pista => pista.readyState === 'ended'), 'Un fallo nativo continúa con ZXing y libera la cámara');
        await esperar(() => !document.querySelector('.modal-backdrop'), 'modal cerrado tras alternativa');
        window.BarcodeDetector = DetectorFixture;
        detectados = [];
        solicitud = null;
        await abrir();
        await esperar(() => pistas.some(pista => pista.readyState === 'live'), 'cámara antes de salir');
        window.dispatchEvent(new Event('pagehide'));
        await esperar(() => pistas.every(pista => pista.readyState === 'ended'), 'cierre al salir');
        comprobar(true, 'Cambiar de página libera la cámara');
        await esperar(() => !document.querySelector('.modal-backdrop'), 'página cerrada');
        await abrir();
        await esperar(() => pistas.some(pista => pista.readyState === 'live'), 'cámara antes de segundo plano');
        Object.defineProperty(document, 'hidden', {configurable: true, value: true});
        document.dispatchEvent(new Event('visibilitychange'));
        await esperar(() => pistas.every(pista => pista.readyState === 'ended'), 'cierre al ocultar');
        delete document.hidden;
        comprobar(true, 'Pasar a segundo plano libera la cámara');
        return {resultado: 'OK', comprobaciones: comprobaciones.length, detalles: comprobaciones};
    } finally {
        lector.cancelar();
        delete document.hidden;
        Object.defineProperty(window, 'isSecureContext', {configurable: true, value: seguro});
        Object.defineProperty(navigator, 'mediaDevices', {configurable: true, value: dispositivos});
        window.BarcodeDetector = detectorOriginal;
    }
}
