// Un solo lector de cámara; los módulos reciben el EAN y deciden cómo usarlo.
(() => {
    'use strict';
    const elemento = document.getElementById('escanerEan');
    if (!elemento || !window.bootstrap) return;
    const video = elemento.querySelector('video');
    const estado = elemento.querySelector('[data-ean-estado]');
    const reintentar = elemento.querySelector('[data-ean-reintentar]');
    const modal = bootstrap.Modal.getOrCreateInstance(elemento);
    let cargaAlternativa;
    let secuencia = 0;
    let abierto = false;
    let cancelado = false;
    let leyendo = false;
    let stream = null;
    let temporizador;
    let alLeer;
    let resultado = null;
    let origen;

    // Valida longitud y dígito de control sin perder los ceros iniciales.
    function esEanValido(codigo) {
        if (typeof codigo !== 'string' || !/^(?:\d{8}|\d{13})$/.test(codigo)) return false;
        let suma = 0;
        for (let i = codigo.length - 2, peso = 3; i >= 0; i--, peso = 4 - peso) {
            suma += Number(codigo[i]) * peso;
        }
        return (10 - suma % 10) % 10 === Number(codigo[codigo.length - 1]);
    }

    // Detiene todas las pistas y anula cualquier lectura anterior pendiente.
    function detenerCamara() {
        ++secuencia;
        leyendo = false;
        clearTimeout(temporizador);
        if (stream) stream.getTracks().forEach(pista => pista.stop());
        stream = null;
        video.pause();
        video.srcObject = null;
    }

    // Traduce errores del navegador a indicaciones para continuar manualmente.
    function mensajeError(error) {
        if (['NotAllowedError', 'PermissionDeniedError', 'SecurityError'].includes(error.name)) {
            return 'No se permitió usar la cámara. Revisá el permiso del navegador o ingresá el EAN manualmente.';
        }
        if (['NotFoundError', 'DevicesNotFoundError', 'OverconstrainedError'].includes(error.name)) {
            return 'No se encontró una cámara disponible. Podés ingresar el EAN manualmente.';
        }
        if (['NotReadableError', 'TrackStartError', 'AbortError'].includes(error.name)) {
            return 'No se pudo iniciar la cámara. Cerrá otras aplicaciones que puedan estar usándola.';
        }
        return error.message || 'No se pudo usar la cámara. Podés ingresar el EAN manualmente.';
    }

    // Muestra el error y libera la cámara aunque haya fallado el reconocimiento.
    function fallar(error) {
        detenerCamara();
        video.hidden = true;
        estado.textContent = mensajeError(error);
        estado.className = 'alert alert-warning mb-0';
        reintentar.hidden = false;
    }

    // Carga ZXing local solo cuando el navegador necesita la alternativa.
    function cargarAlternativa() {
        if (window.ZXing) return Promise.resolve(window.ZXing);
        if (!cargaAlternativa) {
            cargaAlternativa = new Promise((resolve, reject) => {
                const script = document.createElement('script');
                const limite = setTimeout(() => terminar(new Error('No se pudo cargar el lector. Reintentá o ingresá el EAN manualmente.')), 12000);
                // Resuelve una única carga y permite reintentar si falla el archivo local.
                function terminar(error) {
                    clearTimeout(limite);
                    script.onload = script.onerror = null;
                    if (error) { script.remove(); reject(error); }
                    else resolve(window.ZXing);
                }
                script.src = elemento.dataset.alternativa;
                script.onload = () => terminar(window.ZXing ? null : new Error('El lector alternativo no está disponible.'));
                script.onerror = () => terminar(new Error('No se pudo cargar el lector. Reintentá o ingresá el EAN manualmente.'));
                document.head.appendChild(script);
            }).catch(error => { cargaAlternativa = null; throw error; });
        }
        return cargaAlternativa;
    }

    // Reconoce EAN con el navegador si soporta ambos formatos comerciales.
    async function crearDetector() {
        if (window.BarcodeDetector) {
            try {
                const formatos = await window.BarcodeDetector.getSupportedFormats();
                if (['ean_13', 'ean_8'].every(formato => formatos.includes(formato))) {
                    const detector = new window.BarcodeDetector({formats: ['ean_13', 'ean_8']});
                    return {nativo: true, detectar: async () => (await detector.detect(video)).map(item => item.rawValue)};
                }
            } catch (error) { /* Si la API falla, continúa con la alternativa local. */ }
        }
        return crearDetectorAlternativo();
    }

    // ZXing lee fotogramas; este componente sigue siendo dueño del stream.
    async function crearDetectorAlternativo() {
        const zxing = await cargarAlternativa();
        const lector = new zxing.MultiFormatReader();
        lector.setHints(new Map([[zxing.DecodeHintType.POSSIBLE_FORMATS, [zxing.BarcodeFormat.EAN_13, zxing.BarcodeFormat.EAN_8]]]));
        const canvas = document.createElement('canvas');
        const contexto = canvas.getContext('2d', {willReadFrequently: true});
        if (!contexto) throw new Error('Este navegador no permite preparar la imagen de la cámara.');
        return {nativo: false, detectar: () => {
            const escala = Math.min(1, 1280 / video.videoWidth);
            canvas.width = Math.round(video.videoWidth * escala);
            canvas.height = Math.round(video.videoHeight * escala);
            contexto.drawImage(video, 0, 0, canvas.width, canvas.height);
            try {
                const imagen = new zxing.HTMLCanvasElementLuminanceSource(canvas);
                return [lector.decodeWithState(new zxing.BinaryBitmap(new zxing.HybridBinarizer(imagen))).getText()];
            } catch (error) {
                // Un fotograma sin código todavía no es un error de cámara.
                if (error instanceof zxing.NotFoundException || error instanceof zxing.ChecksumException || error instanceof zxing.FormatException) return [];
                throw error;
            } finally { lector.reset(); }
        }};
    }

    // Confirma dos fotogramas iguales y entrega una sola lectura por apertura.
    async function leerFotograma(detector, turno, anterior = null) {
        if (!leyendo || turno !== secuencia) return;
        try {
            let codigo = null;
            if (video.readyState >= 2 && video.videoWidth > 0) {
                const codigos = await detector.detectar();
                codigo = codigos.find(esEanValido) || null;
            }
            // Ignora respuestas que llegaron después de cancelar o cambiar de pantalla.
            if (!leyendo || turno !== secuencia) return;
            if (codigo && codigo === anterior) {
                resultado = codigo;
                detenerCamara();
                modal.hide();
                return;
            }
            temporizador = setTimeout(() => leerFotograma(detector, turno, codigo), 180);
        } catch (error) {
            if (!leyendo || turno !== secuencia) return;
            // Si falla la API nativa, intenta ZXing sin volver a pedir la cámara.
            if (detector.nativo) {
                try {
                    const alternativa = await crearDetectorAlternativo();
                    if (leyendo && turno === secuencia) leerFotograma(alternativa, turno);
                } catch (fallo) { if (turno === secuencia) fallar(fallo); }
            } else fallar(error);
        }
    }

    // Abre la cámara trasera y rechaza HTTP inseguro sin afectar el formulario.
    async function iniciarCamara() {
        if (cancelado) return;
        detenerCamara();
        const turno = secuencia;
        reintentar.hidden = true;
        video.hidden = true;
        estado.className = 'alert alert-info mb-0';
        estado.textContent = 'Preparando cámara…';
        try {
            if (!window.isSecureContext) throw new Error('El navegador necesita una conexión segura HTTPS para utilizar la cámara. Podés ingresar el EAN manualmente.');
            if (!navigator.mediaDevices?.getUserMedia) throw new Error('Este navegador no dispone de acceso a la cámara. Podés ingresar el EAN manualmente.');
            const detector = await crearDetector();
            if (!abierto || turno !== secuencia) return;
            estado.textContent = 'Permití el acceso a la cámara para continuar.';
            const recibido = await navigator.mediaDevices.getUserMedia({audio: false, video: {facingMode: {ideal: 'environment'}, width: {ideal: 1280}, height: {ideal: 720}}});
            // Si se canceló mientras esperaba el permiso, libera el stream tardío.
            if (!abierto || turno !== secuencia) { recibido.getTracks().forEach(pista => pista.stop()); return; }
            stream = recibido;
            video.srcObject = stream;
            video.hidden = false;
            await video.play();
            if (!abierto || turno !== secuencia) return;
            estado.textContent = 'Apuntá al código completo, con buena luz y sin reflejos. Mantené el celular quieto.';
            leyendo = true;
            leerFotograma(detector, turno);
        } catch (error) { if (abierto && turno === secuencia) fallar(error); }
    }

    // Abre una única sesión y guarda quién recibirá el EAN detectado.
    function abrir(callback) {
        if (abierto || document.querySelector('.modal.show') || typeof callback !== 'function') return false;
        abierto = true;
        cancelado = false;
        alLeer = callback;
        resultado = null;
        origen = document.activeElement;
        modal.show();
        return true;
    }

    // Cancela sin entregar códigos y descarta cualquier permiso pendiente.
    function cancelar() {
        cancelado = true;
        resultado = null;
        alLeer = null;
        detenerCamara();
        modal.hide();
    }

    // Si se cancela durante la animación, no inicia una cámara al terminarla.
    elemento.addEventListener('shown.bs.modal', () => {
        if (cancelado) modal.hide();
        else iniciarCamara();
    });
    elemento.querySelectorAll('[data-bs-dismiss="modal"]').forEach(boton => boton.addEventListener('click', cancelar));
    elemento.addEventListener('keydown', evento => { if (evento.key === 'Escape') cancelar(); });
    elemento.addEventListener('hide.bs.modal', detenerCamara);
    // Entrega el resultado después de cerrar para evitar modales superpuestos.
    elemento.addEventListener('hidden.bs.modal', () => {
        const codigo = resultado;
        const callback = alLeer;
        resultado = alLeer = null;
        abierto = false;
        if (origen?.isConnected && !origen.disabled) origen.focus();
        if (codigo && callback) callback(codigo);
    });
    reintentar.addEventListener('click', iniciarCamara);
    window.addEventListener('pagehide', cancelar);
    document.addEventListener('visibilitychange', () => { if (document.hidden && abierto) cancelar(); });
    window.EanScanner = {abrir, cancelar, esEanValido};
})();
