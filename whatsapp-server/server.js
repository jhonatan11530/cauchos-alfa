/**
 * Microservicio open source de WhatsApp basado en OpenWA (@open-wa/wa-automate).
 * Expone endpoints HTTP que consume el panel administrativo de Laravel:
 *
 *   GET  /status            -> estado de la sesion + QR (dataURL) si no hay sesion
 *   POST /send-message      -> { to, message }                       texto
 *   POST /send-file         -> multipart: to, message?, file         archivo adjunto
 *   POST /send-catalog      -> multipart/JSON: to, message, file|url PDF del catalogo
 *   POST /logout            -> cierra la sesion
 *
 * Uso:  npm install && npm start   (dentro de whatsapp-server/)
 */
const express = require('express');
const multer = require('multer');
const path = require('path');
const fs = require('fs');
const wa = require('@open-wa/wa-automate');
// En la linea 4.76 el QR se emite por el event emitter interno del paquete,
// no por el callback de create().
const { ev } = require('@open-wa/wa-automate/dist/controllers/events');

const PORT = process.env.PORT || 3010;

// OpenWA/puppeteer pueden lanzar errores no capturados al refrescar el QR
// ("Execution context was destroyed", etc.). Si no se capturan, el proceso
// muere y el dashboard se queda sin QR. Se registran y se reintenta la sesion.
process.on('uncaughtException', (err) => {
    console.error('Excepcion no capturada (proceso mantenido vivo):', err.message);
    if (!sessionReady && !client) setTimeout(startSession, 5000);
});
process.on('unhandledRejection', (reason) => {
    console.error('Promesa rechazada no capturada (proceso mantenido vivo):', reason);
});
const TMP_DIR = path.join(__dirname, 'tmp');
if (!fs.existsSync(TMP_DIR)) fs.mkdirSync(TMP_DIR, { recursive: true });

const upload = multer({ dest: TMP_DIR, limits: { fileSize: 50 * 1024 * 1024 } });
const app = express();
app.use(express.json({ limit: '50mb' }));
app.use(express.urlencoded({ extended: true, limit: '50mb' }));

// Permite peticiones del panel de Laravel (otro puerto/host en desarrollo)
app.use((req, res, next) => {
    res.header('Access-Control-Allow-Origin', '*');
    res.header('Access-Control-Allow-Headers', 'Content-Type, Accept');
    res.header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
    if (req.method === 'OPTIONS') return res.sendStatus(200);
    next();
});

let client = null;          // sesion activa de OpenWA
let qrDataUrl = null;       // ultimo QR generado
let sessionReady = false;
let sessionError = null;    // ultimo error de inicializacion (para /status)
let sessionStarting = false; // true mientras OpenWA esta lanzando el navegador

function normalizeNumber(to) {
    // Acepta 04141234567, +58 414-1234567, 584141234567, etc.
    let digits = String(to || '').replace(/\D/g, '');
    if (digits.startsWith('0')) digits = '58' + digits.slice(1);       // Venezuela
    else if (digits.length === 10) digits = '58' + digits;             // fallback 10 digitos
    return digits + '@c.us';
}

async function sendMessage(to, message) {
    if (!sessionReady || !client) throw new Error('La sesion de WhatsApp no esta lista. Escanea el QR.');
    return client.sendText(normalizeNumber(to), message || '');
}

const MIME_MAP = {
    '.pdf': 'application/pdf', '.png': 'image/png', '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg',
    '.gif': 'image/gif', '.webp': 'image/webp',
};

// Solo se permiten imagenes y PDF.
function validateAllowedFile(fileName) {
    const ext = path.extname(fileName || '').toLowerCase();
    if (!MIME_MAP[ext]) {
        throw new Error('Tipo de archivo no permitido. Solo se envian imagenes (png, jpg, jpeg, gif, webp) y PDF.');
    }
    return MIME_MAP[ext];
}

async function sendFile(to, message, filePath, fileName) {
    if (!sessionReady || !client) throw new Error('La sesion de WhatsApp no esta lista. Escanea el QR.');
    // Solo imagenes y PDF; cualquier otro tipo se rechaza.
    const mime = validateAllowedFile(fileName) || validateAllowedFile(filePath);
    // OpenWA sendFile NO acepta rutas absolutas de Windows: requiere un DataURL
    // (data:...;base64) o una ruta relativa con ./ . Convertimos el archivo a base64.
    const dataUrl = 'data:' + mime + ';base64,' + fs.readFileSync(filePath).toString('base64');
    const result = await client.sendFile(normalizeNumber(to), dataUrl, fileName, message || '');
    // OpenWA puede devolver false sin lanzar excepcion cuando el envio falla.
    if (result === false) {
        throw new Error('WhatsApp no acepto el archivo (posible formato o tamano invalido).');
    }
    return result;
}

// ---------------- Endpoints HTTP ----------------

app.get('/status', (req, res) => {
    res.json({
        ready: sessionReady,
        starting: sessionStarting,
        qr: sessionReady ? null : qrDataUrl,
        error: sessionError,
    });
});

app.post('/send-message', async (req, res) => {
    try {
        const { to, message } = req.body || {};
        if (!to) return res.status(422).json({ ok: false, error: 'El campo "to" es obligatorio.' });
        await sendMessage(to, message);
        res.json({ ok: true });
    } catch (e) {
        res.status(500).json({ ok: false, error: e.message });
    }
});

app.post('/send-file', upload.single('file'), async (req, res) => {
    let filePath = req.file ? req.file.path : null;
    try {
        const { to, message } = req.body || {};
        if (!to) return res.status(422).json({ ok: false, error: 'El campo "to" es obligatorio.' });
        if (!filePath) return res.status(422).json({ ok: false, error: 'Debes adjuntar un archivo.' });
        await sendFile(to, message, filePath, req.file.originalname);
        res.json({ ok: true });
    } catch (e) {
        res.status(500).json({ ok: false, error: e.message });
    } finally {
        if (filePath) fs.unlink(filePath, () => { });
    }
});

// Enviar un archivo ya generado por Laravel (ej. PDF del catalogo) a un numero.
app.post('/send-catalog', upload.none(), async (req, res) => {
    try {
        const { to, message, path: serverPath } = req.body || {};
        if (!to || !serverPath) {
            return res.status(422).json({ ok: false, error: 'Campos requeridos: to, path.' });
        }
        if (!fs.existsSync(serverPath)) {
            return res.status(404).json({ ok: false, error: 'El archivo no existe en el servidor.' });
        }
        await sendFile(to, message, serverPath, path.basename(serverPath));
        res.json({ ok: true });
    } catch (e) {
        res.status(500).json({ ok: false, error: e.message });
    }
});

app.post('/logout', async (req, res) => {
    try {
        if (client) { await client.logout(); await client.kill(); }
    } catch (_) { /* la sesion ya pudo estar cerrada */ }
    client = null; sessionReady = false; qrDataUrl = null;
    res.json({ ok: true });
});

// Regenera el QR / reintentar la sesion sin reiniciar el proceso Node.
app.post('/restart', async (req, res) => {
    try {
        if (client) { await client.kill(); }
    } catch (_) { /* la sesion ya pudo estar cerrada */ }
    client = null; sessionReady = false; qrDataUrl = null; sessionError = null;
    startSession();
    res.json({ ok: true, message: 'Reiniciando sesion; el QR aparecera en unos segundos.' });
});

app.listen(PORT, () => console.log(`Servidor WhatsApp (OpenWA) escuchando en http://localhost:${PORT}`));

// ---------------- Sesion OpenWA ----------------

// Busca un Chromium portable en la misma carpeta de server.js (raiz o subcarpetas)
// Ej: whatsapp-server\chrome.exe | whatsapp-server\chrome-win\chrome.exe
function findPortableChromium() {
    const exeNames = ['chrome.exe', 'chromium.exe', 'msedge.exe', 'headless_shell.exe'];
    const roots = [__dirname];
    for (const root of roots) {
        for (const name of exeNames) {
            const direct = path.join(root, name);
            if (fs.existsSync(direct)) return direct;
        }
    }
    // Busqueda en subcarpetas (1 nivel de profundidad para no ser lento)
    try {
        for (const dir of fs.readdirSync(__dirname, { withFileTypes: true })) {
            if (!dir.isDirectory() || dir.name === 'node_modules' || dir.name.startsWith('_IGNORE')) continue;
            for (const name of exeNames) {
                const nested = path.join(__dirname, dir.name, name);
                if (fs.existsSync(nested)) return nested;
            }
            const chromeWin = path.join(__dirname, dir.name, 'chrome-win', 'chrome.exe');
            if (fs.existsSync(chromeWin)) return chromeWin;
            const chromeWin64 = path.join(__dirname, dir.name, 'chrome-win64', 'chrome.exe');
            if (fs.existsSync(chromeWin64)) return chromeWin64;
        }
    } catch (_) { /* sin permisos de lectura, se usa fallback */ }
    return null;
}

// Chromium portable ubicado junto a server.js (whatsapp-server\chrome-win\chrome.exe)
const PORTABLE_CHROMIUM = path.join(__dirname, 'chrome-win', 'chrome.exe');

const candidates = [
    PORTABLE_CHROMIUM,
    findPortableChromium(),
    process.env.CHROME_PATH,
    'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
    'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
    'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
    'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
].filter(Boolean);
const browserPath = candidates.find((p) => fs.existsSync(p));
if (browserPath) console.log('Usando navegador:', browserPath);
else console.log('No se encontro Chromium portable ni Chrome/Edge; OpenWA descargara Chromium.');

// Suscripcion al QR emitido internamente (evento qr.<sessionId>).
// En la linea 4.76 el callback de create() no se invoca; el QR viaja por
// el event emitter global del paquete.
ev.on('qr.**', (data) => {
    if (!data || typeof data !== 'string') return;
    // getQrPng() devuelve base64 puro; otros eventos pueden traer dataURL
    qrDataUrl = data.startsWith('data:') ? data : 'data:image/png;base64,' + data;
    sessionError = null;
    console.log('QR listo para escanear (disponible en /status).');
});

// Arranca (o reinicia) la sesion de WhatsApp. Puede llamarse varias veces
// para regenerar el QR sin matar el proceso.
function startSession() {
    if (sessionStarting) return; // evita dos lanzamientos en paralelo
    sessionStarting = true;
    sessionError = null;
    wa.create({
        sessionId: 'cauchos-alfa',
        multiDevice: true,
        authTimeout: 60,
        blockCrashLogs: true,
        disableSpintax: true,
        ...(browserPath ? { executablePath: browserPath, useChrome: true } : {}),
        viewport: { width: 1280, height: 720 },
    }).then((c) => {
        client = c;
        sessionReady = true;
        sessionStarting = false;
        qrDataUrl = null;
        sessionError = null;
        console.log('Sesion de WhatsApp lista.');
    }).catch((e) => {
        sessionStarting = false;
        sessionError = e.message;
        console.error('Error iniciando OpenWA:', e.message);
        // Reintento automatico: si el navegador fallo, el QR nunca apareceria
        // en el dashboard y el usuario no tendria forma de recuperarlo.
        setTimeout(startSession, 10000);
    });
}

startSession();
