/*! @rekhu/seshat-widget 0.15.0 · https://rekhu.com · generated from src/index.js, do not edit */
(() => {
// @rekhu/seshat-widget: the file check on your own site. A print file and its specs go to your back end (a Rekhu server
// package, protocol 1 in PROTOCOL.md); the person sees the findings and a proof of each page, asks for the fixes, sees
// the fixed file checked again, and downloads it. How it runs is yours to set: the specs or a recipe, the fixes made at
// once, on a click or as chosen, with or without the proof, the explanation and the download.

const PROTOCOL = 1;

const TEXTS = {
    en: {
        scan1: 'Reading the pages', scan2: 'Measuring size, trim and bleed', scan3: 'Checking colours and total ink', scan4: 'Looking at pictures and fonts', scan5: 'Deciding what we can fix', wait: 'This takes a few seconds',
        title: 'Check your print file', drop: 'Drop your print file here', choose: 'or choose a file', kinds: 'PDF, EPS, ZIP or a picture',
        stepUpload: 'Upload', stepCheck: 'Check', stepFix: 'Fix', stepDone: 'Done', sending: 'Sending', checking: 'Checking your file against the specs…',
        fixing: 'Fixing and checking again…', ok: 'Your file is ready to print', fixable: 'A few things to fix: we can do that for you', ask: 'Some points need your choice',
        refuse: 'This file can’t be printed as it is', verified: 'Fixed and checked again', verifiedOk: 'Fixed and checked again: ready to print',
        verifiedLeft: 'Fixed and checked again: some points still need your choice', groupFix: 'We fix this', groupAsk: 'Your choice', groupRefuse: 'Can’t print', groupNote: 'Notes',
        groupDone: 'Fixed', page: 'page', pages: 'pages', fix: 'Fix it for me', fixSome: 'Fix the selected', download: 'Download print-ready file', another: 'Check another file',
        again: 'Try again', trim: 'trim', bleed: 'bleed', safe: 'safe zone', of: 'of', prev: 'Previous page', next: 'Next page', show: 'Show page',
        signedOut: 'Your session has ended. Reload the page and sign in again.', failed: 'The file could not be checked right now; try again in a moment.', mm: 'mm', bleedOf: 'bleed',
    },
    nl: {
        scan1: 'De pagina’s lezen', scan2: 'Formaat, snijlijn en afloop meten', scan3: 'Kleuren en inkt controleren', scan4: 'Afbeeldingen en lettertypes bekijken', scan5: 'Bepalen wat we kunnen oplossen', wait: 'Dit duurt een paar seconden',
        title: 'Controleer je drukbestand', drop: 'Sleep je drukbestand hierheen', choose: 'of kies een bestand', kinds: 'PDF, EPS, ZIP of een afbeelding',
        stepUpload: 'Uploaden', stepCheck: 'Controle', stepFix: 'Oplossen', stepDone: 'Klaar', sending: 'Versturen', checking: 'Je bestand wordt gecontroleerd tegen de specificaties…',
        fixing: 'Oplossen en opnieuw controleren…', ok: 'Je bestand is klaar om te drukken', fixable: 'Een paar punten op te lossen: dat doen wij voor je', ask: 'Een paar punten vragen je keuze',
        refuse: 'Dit bestand kan zo niet gedrukt worden', verified: 'Opgelost en opnieuw gecontroleerd', verifiedOk: 'Opgelost en opnieuw gecontroleerd: klaar om te drukken',
        verifiedLeft: 'Opgelost en opnieuw gecontroleerd: een paar punten vragen nog je keuze', groupFix: 'Lossen wij op', groupAsk: 'Jouw keuze', groupRefuse: 'Niet drukbaar', groupNote: 'Opmerkingen',
        groupDone: 'Opgelost', page: 'pagina', pages: 'pagina’s', fix: 'Los het voor me op', fixSome: 'Los de gekozen punten op', download: 'Download drukklaar bestand', another: 'Controleer een ander bestand',
        again: 'Opnieuw proberen', trim: 'snijlijn', bleed: 'afloop', safe: 'veilige zone', of: 'van', prev: 'Vorige pagina', next: 'Volgende pagina', show: 'Toon pagina',
        signedOut: 'Je sessie is verlopen. Herlaad de pagina en log opnieuw in.', failed: 'Het bestand kon nu niet gecontroleerd worden; probeer het zo opnieuw.', mm: 'mm', bleedOf: 'afloop',
    },
    de: {
        scan1: 'Seiten lesen', scan2: 'Format, Endformat und Beschnitt messen', scan3: 'Farben und Farbauftrag prüfen', scan4: 'Bilder und Schriften ansehen', scan5: 'Entscheiden, was wir beheben', wait: 'Das dauert nur ein paar Sekunden',
        title: 'Druckdatei prüfen', drop: 'Druckdatei hier ablegen', choose: 'oder Datei auswählen', kinds: 'PDF, EPS, ZIP oder ein Bild',
        stepUpload: 'Hochladen', stepCheck: 'Prüfen', stepFix: 'Beheben', stepDone: 'Fertig', sending: 'Senden', checking: 'Ihre Datei wird gegen die Vorgaben geprüft…',
        fixing: 'Beheben und erneut prüfen…', ok: 'Ihre Datei ist druckfertig', fixable: 'Einiges zu beheben: das übernehmen wir', ask: 'Einige Punkte brauchen Ihre Entscheidung',
        refuse: 'Diese Datei kann so nicht gedruckt werden', verified: 'Behoben und erneut geprüft', verifiedOk: 'Behoben und erneut geprüft: druckfertig',
        verifiedLeft: 'Behoben und erneut geprüft: einige Punkte brauchen noch Ihre Entscheidung', groupFix: 'Beheben wir', groupAsk: 'Ihre Wahl', groupRefuse: 'Nicht druckbar', groupNote: 'Hinweise',
        groupDone: 'Behoben', page: 'Seite', pages: 'Seiten', fix: 'Für mich beheben', fixSome: 'Auswahl beheben', download: 'Druckfertige Datei laden', another: 'Andere Datei prüfen',
        again: 'Erneut versuchen', trim: 'Endformat', bleed: 'Beschnitt', safe: 'Sicherheitsabstand', of: 'von', prev: 'Vorige Seite', next: 'Nächste Seite', show: 'Seite zeigen',
        signedOut: 'Ihre Sitzung ist abgelaufen. Laden Sie die Seite neu und melden Sie sich an.', failed: 'Die Datei konnte gerade nicht geprüft werden; bitte gleich erneut versuchen.', mm: 'mm', bleedOf: 'Beschnitt',
    },
    fr: {
        scan1: 'Lecture des pages', scan2: 'Mesure du format, de la coupe et du fond perdu', scan3: 'Contrôle des couleurs et de l’encre', scan4: 'Examen des images et des polices', scan5: 'Choix de ce que nous corrigeons', wait: 'Cela prend quelques secondes',
        title: 'Vérifiez votre fichier d’impression', drop: 'Déposez votre fichier ici', choose: 'ou choisissez un fichier', kinds: 'PDF, EPS, ZIP ou une image',
        stepUpload: 'Envoi', stepCheck: 'Contrôle', stepFix: 'Correction', stepDone: 'Terminé', sending: 'Envoi', checking: 'Contrôle de votre fichier selon les spécifications…',
        fixing: 'Correction et nouveau contrôle…', ok: 'Votre fichier est prêt à imprimer', fixable: 'Quelques points à corriger : nous nous en chargeons', ask: 'Quelques points attendent votre choix',
        refuse: 'Ce fichier ne peut pas être imprimé tel quel', verified: 'Corrigé et contrôlé à nouveau', verifiedOk: 'Corrigé et contrôlé à nouveau : prêt à imprimer',
        verifiedLeft: 'Corrigé et contrôlé à nouveau : quelques points attendent encore votre choix', groupFix: 'Nous corrigeons', groupAsk: 'Votre choix', groupRefuse: 'Non imprimable', groupNote: 'Remarques',
        groupDone: 'Corrigé', page: 'page', pages: 'pages', fix: 'Corrigez pour moi', fixSome: 'Corriger la sélection', download: 'Télécharger le fichier prêt', another: 'Vérifier un autre fichier',
        again: 'Réessayer', trim: 'coupe', bleed: 'fond perdu', safe: 'zone de sécurité', of: 'sur', prev: 'Page précédente', next: 'Page suivante', show: 'Voir la page',
        signedOut: 'Votre session a expiré. Rechargez la page et reconnectez-vous.', failed: 'Le fichier n’a pas pu être contrôlé ; réessayez dans un instant.', mm: 'mm', bleedOf: 'fond perdu',
    },
    ar: {
        scan1: 'قراءة الصفحات', scan2: 'قياس المقاس والقص والنزف', scan3: 'فحص الألوان والحبر', scan4: 'فحص الصور والخطوط', scan5: 'تحديد ما يمكننا إصلاحه', wait: 'يستغرق ذلك بضع ثوانٍ',
        title: 'افحص ملف الطباعة', drop: 'أفلت ملف الطباعة هنا', choose: 'أو اختر ملفًا', kinds: 'PDF أو EPS أو ZIP أو صورة',
        stepUpload: 'الرفع', stepCheck: 'الفحص', stepFix: 'الإصلاح', stepDone: 'تم', sending: 'جارٍ الإرسال', checking: 'جارٍ فحص ملفك وفق المواصفات…',
        fixing: 'جارٍ الإصلاح وإعادة الفحص…', ok: 'ملفك جاهز للطباعة', fixable: 'بعض الأمور تحتاج إصلاحًا: سنقوم بذلك عنك', ask: 'بعض النقاط تحتاج اختيارك',
        refuse: 'لا يمكن طباعة هذا الملف كما هو', verified: 'تم الإصلاح وأُعيد الفحص', verifiedOk: 'تم الإصلاح وأُعيد الفحص: جاهز للطباعة',
        verifiedLeft: 'تم الإصلاح وأُعيد الفحص: بعض النقاط ما زالت تحتاج اختيارك', groupFix: 'سنصلحه', groupAsk: 'اختيارك', groupRefuse: 'غير قابل للطباعة', groupNote: 'ملاحظات',
        groupDone: 'تم إصلاحه', page: 'صفحة', pages: 'صفحات', fix: 'أصلحه لي', fixSome: 'أصلح المحدد', download: 'نزّل الملف الجاهز للطباعة', another: 'افحص ملفًا آخر',
        again: 'حاول مجددًا', trim: 'القص', bleed: 'النزف', safe: 'المنطقة الآمنة', of: 'من', prev: 'الصفحة السابقة', next: 'الصفحة التالية', show: 'اعرض الصفحة',
        signedOut: 'انتهت جلستك. أعد تحميل الصفحة وسجّل الدخول مجددًا.', failed: 'تعذّر فحص الملف الآن؛ حاول بعد قليل.', mm: 'مم', bleedOf: 'نزف',
    },
};

/**
 * The connection without an interface: protocol 1 with the user's own sign-in. options: endpoint, headers (an object or
 * an async function), credentials ('same-origin'), csrf (sent as X-CSRF-TOKEN; a page's meta csrf-token by default).
 */
function createClient(options = {}) {
    const base = String(options.endpoint || '').replace(/\/+$/, '');
    if (!base) throw new Error('Give the endpoint of your back end: createClient({ endpoint: "/rekhu/seshat" }).');
    const credentials = options.credentials || 'same-origin';

    /** The headers of every call: the protocol, the CSRF token and the app's own. */
    async function headers(extra = {}) {
        const own = typeof options.headers === 'function' ? await options.headers() : options.headers || {};
        const meta = typeof document !== 'undefined' ? document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') : null;
        const csrf = options.csrf ?? meta;
        return { accept: 'application/json', 'x-rekhu-protocol': String(PROTOCOL), ...(csrf ? { 'x-csrf-token': csrf } : {}), ...own, ...extra };
    }

    /** An error answer as an Error with its status and type, its message for the person. */
    const failure = (status, body) => Object.assign(new Error(body?.error?.message || `The server answered ${status}.`), { status, type: body?.error?.type || 'error' });

    async function json(response) {
        const body = await response.json().catch(() => ({}));
        if (!response.ok) throw failure(response.status, body);
        return body;
    }

    return {
        endpoint: base,

        /** POST /check: the file and its specs, with the upload's progress (0 to 1) to onProgress. */
        async check(file, { specs = {}, recipe = null, locale = null, explain = true, onProgress = null } = {}) {
            const form = new FormData();
            form.append('file', file, file.name || 'file.pdf');
            form.append('specs', JSON.stringify(specs || {}));
            if (recipe) form.append('recipe', recipe);
            if (locale) form.append('locale', locale);
            if (!explain) form.append('explain', '0');
            const h = await headers();
            if (typeof XMLHttpRequest === 'undefined' || !onProgress) return json(await fetch(`${base}/check`, { method: 'POST', headers: h, body: form, credentials }));
            return new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', `${base}/check`);
                xhr.withCredentials = credentials === 'include';
                for (const [k, v] of Object.entries(h)) xhr.setRequestHeader(k, v);
                xhr.upload.onprogress = (e) => e.lengthComputable && onProgress(e.loaded / e.total);
                xhr.upload.onload = () => onProgress(1);
                xhr.onerror = () => reject(Object.assign(new Error('The file could not be sent; check the connection and try again.'), { status: 0, type: 'connection_error' }));
                xhr.onload = () => {
                    let body = {};
                    try { body = JSON.parse(xhr.responseText || '{}'); } catch { /* not JSON */ }
                    if (xhr.status >= 200 && xhr.status < 300) resolve(body); else reject(failure(xhr.status, body));
                };
                xhr.send(form);
            });
        },

        /** POST /fix: all fixes Seshat decided on, or the named ones; the answer carries the check of the fixed file. */
        async fix(fileId, { specs = {}, fixes = null, recipe = null, locale = null } = {}) {
            const body = { file_id: fileId, specs, ...(Array.isArray(fixes) ? { fixes } : {}), ...(recipe ? { recipe } : {}), ...(locale ? { locale } : {}) };
            return json(await fetch(`${base}/fix`, { method: 'POST', headers: await headers({ 'content-type': 'application/json' }), body: JSON.stringify(body), credentials }));
        },

        /** GET /preview: a page as a PNG Blob (with the user's sign-in, so it works with tokens too). */
        async preview(fileId, page = 1) {
            const response = await fetch(`${base}/preview?file=${encodeURIComponent(fileId)}&page=${page}`, { headers: await headers({ accept: 'image/png' }), credentials });
            if (!response.ok) throw failure(response.status, await response.json().catch(() => ({})));
            return response.blob();
        },

        /** GET /files/{id}: the file as a Blob with its name. */
        async download(fileId) {
            const response = await fetch(`${base}/files/${encodeURIComponent(fileId)}`, { headers: await headers({ accept: '*/*' }), credentials });
            if (!response.ok) throw failure(response.status, await response.json().catch(() => ({})));
            const name = /filename\*=UTF-8''([^;]+)/i.exec(response.headers.get('content-disposition') || '')?.[1] || /filename="([^"]+)"/i.exec(response.headers.get('content-disposition') || '')?.[1];
            return { blob: await response.blob(), name: name ? decodeURIComponent(name) : 'print-file.pdf' };
        },
    };
}

/** The findings that are left for the person: a question, a refusal, or a fix not made yet. */
const open = (findings) => findings.filter((f) => ['fix', 'ask', 'refuse'].includes(f.decision));

/**
 * The flow of one file, without an interface: check, fix, verify, done. options: the client's, and specs, recipe,
 * locale, explain (true), fix ('button': on a click, 'choose': the person picks, 'auto': at once, false: check only).
 * Events through on(type, fn): state, progress, checked, fixed, done ({ file_id, name, fixed, findings }), error.
 */
function createFlow(options = {}) {
    const client = options.client || createClient(options);
    const listeners = {};
    let config = { fix: 'button', explain: true, specs: {}, recipe: null, locale: null, ...options };
    let state = { step: 'idle', progress: 0, file: null, check: null, fixed: null, chosen: [], error: null };
    let run = 0;

    const emit = (type, detail) => (listeners[type] || []).forEach((fn) => fn(detail));
    const set = (change) => { state = { ...state, ...change }; emit('state', state); };

    /** A failure: the state says so and listeners hear it; a sign-in that ended is told apart. */
    function failed(error, mine) {
        if (mine !== run) return;
        set({ step: 'error', error: { message: error.message, status: error.status ?? 0, type: error.type ?? 'error' } });
        emit('error', state.error);
    }

    /** The flow is over: nothing left to fix, or the fixed file checked again. */
    function finish(fileId, name, fixed, findings) {
        set({ step: 'done' });
        emit('done', { file_id: fileId, name, fixed, findings });
    }

    const flow = {
        client,
        get state() { return state; },
        get config() { return config; },
        on(type, fn) { (listeners[type] ||= []).push(fn); return () => { listeners[type] = listeners[type].filter((f) => f !== fn); }; },
        configure(change) { config = { ...config, ...change }; emit('state', state); return flow; },

        /** The findings to show: of the fixed file once it is fixed, else of the check. */
        findings() { return (state.fixed?.check || state.check)?.findings || []; },

        /** One file: sent with its progress, checked; then fixed at once when the workflow says auto. */
        async check(file) {
            const mine = ++run;
            set({ step: 'uploading', progress: 0, file: { name: file.name, size: file.size, blob: file }, check: null, fixed: null, chosen: [], error: null });
            try {
                const check = await client.check(file, {
                    specs: config.specs, recipe: config.recipe, locale: config.locale, explain: config.explain,
                    onProgress: (share) => { if (mine === run) { set({ progress: share, step: share >= 1 ? 'checking' : 'uploading' }); emit('progress', share); } },
                });
                if (mine !== run) return null;
                const fixable = check.findings.filter((f) => f.decision === 'fix' && f.fix);
                set({ step: 'checked', check, progress: 1, chosen: [...new Set(fixable.map((f) => f.fix))] });
                emit('checked', check);
                if (!open(check.findings).length || config.fix === false && !check.findings.some((f) => ['ask', 'refuse'].includes(f.decision))) finish(check.file_id, check.name, false, check.findings);
                else if (config.fix === 'auto' && fixable.length) await flow.fix();
                return check;
            } catch (error) { failed(error, mine); return null; }
        },

        /** Picks or drops one fix, for the workflow where the person chooses. */
        toggle(fix) {
            const chosen = state.chosen.includes(fix) ? state.chosen.filter((f) => f !== fix) : [...state.chosen, fix];
            set({ chosen });
        },

        /** The fixes made (all, or the chosen ones), and the fixed file checked again: the verification. */
        async fix() {
            const mine = run;
            if (!state.check) return null;
            set({ step: 'fixing', error: null });
            try {
                const fixes = config.fix === 'choose' ? state.chosen : null;
                const fixed = await client.fix(state.check.file_id, { specs: config.specs, recipe: config.recipe, fixes, locale: config.locale });
                if (mine !== run) return null;
                set({ step: 'fixed', fixed });
                emit('fixed', fixed);
                const left = fixed.check ? fixed.check.findings : state.check.findings.filter((f) => f.decision !== 'fix');
                if (!left.some((f) => ['ask', 'refuse'].includes(f.decision))) finish(fixed.file_id, fixed.name || state.check.name, true, left);
                return fixed;
            } catch (error) { failed(error, mine); return null; }
        },

        /** The same file once more, after a failure. */
        retry() { return state.file?.blob ? flow.check(state.file.blob) : flow.reset(); },

        /** Back to the start; an answer still on its way is ignored. */
        reset() { run++; set({ step: 'idle', progress: 0, file: null, check: null, fixed: null, chosen: [], error: null }); return flow; },
    };
    return flow;
}

const STYLE = `
:host { --c: #6A1FD0; --c-spark: #A55CFF; --c-ink: #fff; --rekhu-text: #140E11; --rekhu-muted: #6E6266; --rekhu-bg: transparent; --rekhu-surface: #fff;
  --rekhu-soft: #F5F1F2; --rekhu-border: #E8E1E4; --rekhu-ok: #0F7B55; --rekhu-warn: #B45309; --rekhu-bad: #BE123C; --rekhu-radius: 6px;
  --rekhu-font: 'Syne', 'Helvetica Neue', Arial, sans-serif; --rekhu-mono: 'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, monospace;
  display: block; font-family: var(--rekhu-font); font-size: 14px; line-height: 1.5; color: var(--rekhu-text); container-type: inline-size; -webkit-font-smoothing: antialiased; }
:host([theme=dark]) { --c: #A55CFF; --c-ink: #140E11; --rekhu-text: #F5F1F2; --rekhu-muted: #8F8388; --rekhu-surface: #1F171B; --rekhu-soft: #2E2428; --rekhu-border: #3A3034; --rekhu-ok: #34D399; --rekhu-warn: #FBBF24; --rekhu-bad: #FB7185; }
@media (prefers-color-scheme: dark) { :host([theme=auto]) { --c: #A55CFF; --c-ink: #140E11; --rekhu-text: #F5F1F2; --rekhu-muted: #8F8388; --rekhu-surface: #1F171B; --rekhu-soft: #2E2428; --rekhu-border: #3A3034; --rekhu-ok: #34D399; --rekhu-warn: #FBBF24; --rekhu-bad: #FB7185; } }
* { box-sizing: border-box; }
.box { position: relative; background: var(--rekhu-bg); }
header { display: flex; flex-wrap: wrap; align-items: baseline; justify-content: space-between; gap: 6px 16px; padding-bottom: 12px; border-bottom: 1px solid var(--rekhu-border); }
h3, ::slotted([slot=title]) { margin: 0; font-size: 1.05rem; font-weight: 700; letter-spacing: -.01em; }
.specs { display: flex; flex-wrap: wrap; gap: 4px 14px; font-family: var(--rekhu-mono); font-size: 11.5px; color: var(--rekhu-muted); text-transform: uppercase; letter-spacing: .06em; }
.spec + .spec::before { content: "/"; margin-inline-end: 14px; color: var(--rekhu-border); }
.track { display: flex; align-items: center; gap: 10px; margin: 14px 0 18px; padding: 0; list-style: none; font-family: var(--rekhu-mono); font-size: 11px; text-transform: uppercase; letter-spacing: .08em; color: var(--rekhu-muted); }
.track li { display: flex; align-items: center; gap: 8px; white-space: nowrap; transition: color .3s; }
.track li i { font-style: normal; color: var(--rekhu-muted); opacity: .55; transition: color .3s, opacity .3s; }
.track li.past i, .track li.now i { opacity: 1; }
.track li + li::before { content: ""; width: 28px; height: 1px; background: var(--rekhu-border); }
.track li.past { color: var(--rekhu-text); }
.track li.past i { color: var(--c); }
.track li.now { color: var(--c); font-weight: 600; }
.track li.now i { color: var(--c); }
.track li.now::after { content: ""; width: 6px; height: 6px; border-radius: 50%; background: var(--c-spark); animation: blink 1.4s ease-in-out infinite; }
@keyframes blink { 50% { opacity: .25; } }
.drop { position: relative; display: grid; justify-items: center; gap: 6px; text-align: center; padding: 40px 16px; border: 1px dashed var(--rekhu-border); border-radius: var(--rekhu-radius); cursor: pointer;
  background: var(--rekhu-surface); transition: border-color .2s, background .2s; }
.drop:hover, :host([dragging]) .drop { border-color: var(--c); border-style: solid; background: color-mix(in srgb, var(--c) 4%, var(--rekhu-surface)); }
.drop .art { display: grid; place-items: center; width: 40px; height: 40px; border-radius: 50%; color: var(--c); border: 1px solid color-mix(in srgb, var(--c) 35%, transparent); transition: transform .3s cubic-bezier(.2,.8,.2,1); }
.drop .art svg { width: 18px; height: 18px; }
.drop:hover .art, :host([dragging]) .art { transform: translateY(-3px); background: var(--c); color: var(--c-ink); border-color: var(--c); }
.drop strong { margin-top: 6px; font-size: .98rem; font-weight: 700; }
.drop span { font-size: 13px; color: var(--rekhu-muted); }
.drop u { color: var(--c); font-weight: 600; text-decoration: underline; text-underline-offset: 3px; text-decoration-thickness: 1px; }
.drop .kinds { margin-top: 4px; font-family: var(--rekhu-mono); font-size: 10.5px; letter-spacing: .08em; text-transform: uppercase; }
input[type=file] { display: none; }
:focus-visible { outline: 2px solid var(--c); outline-offset: 2px; }
.work { display: grid; gap: 14px; justify-items: center; padding: 22px 4px 10px; text-align: center; }
.file { display: grid; grid-template-columns: auto 1fr; gap: 0 12px; align-items: center; width: min(100%, 420px); text-align: start; }
.file .type { display: grid; place-items: center; width: 34px; height: 42px; border-radius: 3px; font-family: var(--rekhu-mono); font-size: 9.5px; font-weight: 700; color: var(--c); border: 1px solid color-mix(in srgb, var(--c) 40%, transparent); position: relative; }
.file .type::before { content: ""; position: absolute; top: -1px; right: -1px; width: 9px; height: 9px; background: linear-gradient(225deg, var(--rekhu-surface) 50%, color-mix(in srgb, var(--c) 40%, transparent) 50% calc(50% + 1px), transparent calc(50% + 1px)); }
.file .meta strong { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 14px; }
.file .meta span { font-family: var(--rekhu-mono); font-size: 11px; color: var(--rekhu-muted); }
.bar { position: relative; height: 2px; margin-top: 8px; background: var(--rekhu-border); overflow: hidden; }
.bar i { position: absolute; inset: 0 auto 0 0; width: var(--p, 0%); background: var(--c); transition: width .25s; }
.bar.wait i, .work .scanning .bar i { width: 30%; animation: run 1.1s ease-in-out infinite; }
@keyframes run { from { transform: translateX(-100%); } to { transform: translateX(340%); } }
.scan { position: relative; width: 104px; aspect-ratio: 148 / 210; border: 1px solid var(--rekhu-border); background: var(--rekhu-surface); overflow: hidden; }
.scan .ln { position: absolute; inset-inline-start: 10px; height: 4px; background: var(--rekhu-soft); }
.scan .ln:nth-child(1) { top: 12px; width: 55%; height: 8px; } .scan .ln:nth-child(2) { top: 26px; width: 70%; } .scan .ln:nth-child(3) { top: 34px; width: 45%; }
.scan .ln:nth-child(4) { top: 48px; height: 48px; inset-inline-end: 10px; } .scan .ln:nth-child(5) { top: 104px; width: 65%; } .scan .ln:nth-child(6) { top: 112px; width: 38%; }
.scan .beam { position: absolute; inset-inline: 0; top: 0; height: 1px; background: var(--c-spark); box-shadow: 0 0 12px 2px color-mix(in srgb, var(--c-spark) 60%, transparent); animation: beam 1.6s cubic-bezier(.45,0,.55,1) infinite alternate; }
@keyframes beam { from { transform: translateY(0); } to { transform: translateY(146px); } }
.says { position: relative; height: 1.5em; width: 100%; font-family: var(--rekhu-mono); font-size: 11.5px; letter-spacing: .06em; text-transform: uppercase; color: var(--c); }
.says span { position: absolute; inset: 0; opacity: 0; animation: say 9s infinite; }
.says span:nth-child(2) { animation-delay: 1.8s; } .says span:nth-child(3) { animation-delay: 3.6s; } .says span:nth-child(4) { animation-delay: 5.4s; } .says span:nth-child(5) { animation-delay: 7.2s; }
@keyframes say { 0% { opacity: 0; transform: translateY(5px); } 4%, 18% { opacity: 1; transform: none; } 22%, 100% { opacity: 0; transform: translateY(-5px); } }
.work small { font-size: 12px; color: var(--rekhu-muted); }
.result { display: grid; gap: 24px; }
@container (min-width: 640px) { .result { grid-template-columns: minmax(0, 4fr) minmax(0, 7fr); align-items: start; } figure { position: sticky; top: 12px; } }
figure { margin: 0; }
.sheet { position: relative; display: grid; place-items: center; min-height: 220px; padding: 16px; background: var(--rekhu-soft); border-radius: var(--rekhu-radius); overflow: hidden; }
.sheet img { display: block; max-width: 100%; max-height: 460px; box-shadow: 0 1px 2px rgb(20 14 17 / .08), 0 12px 28px -14px rgb(20 14 17 / .35); animation: land .5s cubic-bezier(.2,.8,.2,1) both; }
@keyframes land { from { opacity: 0; transform: translateY(8px); } }
.sheet.busy::after { content: ""; position: absolute; inset-inline: 0; top: 0; height: 1px; background: var(--c-spark); box-shadow: 0 0 14px 3px color-mix(in srgb, var(--c-spark) 50%, transparent); animation: sweep 1.6s cubic-bezier(.45,0,.55,1) infinite alternate; }
@keyframes sweep { to { top: 100%; } }
figcaption { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; margin-top: 8px; font-family: var(--rekhu-mono); font-size: 10.5px; letter-spacing: .05em; text-transform: uppercase; color: var(--rekhu-muted); }
.legend { display: flex; gap: 12px; }
.legend i { display: inline-block; width: 12px; height: 1.5px; margin-inline-end: 5px; vertical-align: middle; }
.pager { display: inline-flex; align-items: center; gap: 2px; }
.pager button { width: 24px; height: 24px; padding: 0; justify-content: center; }
.pager span { padding: 0 6px; color: var(--rekhu-text); }
.verdict { display: flex; gap: 10px; align-items: flex-start; padding-bottom: 14px; border-bottom: 1px solid var(--rekhu-border); }
.verdict .ic { display: grid; place-items: center; width: 22px; height: 22px; border-radius: 50%; flex: none; margin-top: 1px; color: #fff; }
.verdict .ic svg { width: 13px; height: 13px; }
.verdict .ic path { stroke-dasharray: 40; stroke-dashoffset: 40; animation: draw .5s .1s cubic-bezier(.6,0,.2,1) forwards; }
@keyframes draw { to { stroke-dashoffset: 0; } }
.verdict b { display: block; font-size: 1rem; font-weight: 700; letter-spacing: -.01em; line-height: 1.35; }
.verdict small { display: block; margin-top: 2px; font-family: var(--rekhu-mono); font-size: 11px; color: var(--rekhu-muted); }
.verdict.ok .ic { background: var(--rekhu-ok); } .verdict.warn .ic { background: var(--rekhu-warn); } .verdict.bad .ic { background: var(--rekhu-bad); }
.counts { display: flex; flex-wrap: wrap; gap: 4px 16px; margin-top: 10px; font-family: var(--rekhu-mono); font-size: 11px; letter-spacing: .04em; color: var(--rekhu-muted); }
.counts span { display: inline-flex; align-items: center; gap: 6px; }
.counts span::before { content: ""; width: 6px; height: 6px; background: var(--k); }
.summary { margin: 12px 0 0; font-size: 14px; line-height: 1.6; }
.group { margin-top: 18px; }
.group h4 { display: flex; align-items: center; gap: 8px; margin: 0 0 4px; font-family: var(--rekhu-mono); font-size: 10.5px; font-weight: 600; text-transform: uppercase; letter-spacing: .1em; color: var(--rekhu-muted); }
.group h4::before { content: ""; width: 6px; height: 6px; background: var(--k); }
.group h4 b { font-weight: 600; color: var(--rekhu-text); }
ul { list-style: none; margin: 0; padding: 0; }
li.finding { --k: var(--rekhu-muted); display: grid; grid-template-columns: auto 1fr auto; gap: 2px 10px; align-items: baseline; padding: 9px 2px; border-bottom: 1px solid var(--rekhu-border); font-size: 14px; line-height: 1.45; transition: background .15s, padding .15s; }
li.finding.link { cursor: pointer; }
li.finding.link:hover { background: color-mix(in srgb, var(--k) 5%, transparent); padding-inline: 8px; }
li.finding .k { display: inline-grid; place-items: center; width: 16px; height: 16px; color: var(--k); transform: translateY(3px); }
li.finding .k svg { width: 14px; height: 14px; }
li.finding .text { grid-column: 2; }
li.finding .question { grid-column: 2; font-size: 13px; font-weight: 600; }
li.finding .pg { grid-column: 3; font-family: var(--rekhu-mono); font-size: 10.5px; letter-spacing: .04em; text-transform: uppercase; color: var(--rekhu-muted); white-space: nowrap; }
li.finding input { appearance: none; width: 15px; height: 15px; margin: 0; border-radius: 3px; border: 1.5px solid var(--rekhu-border); display: grid; place-items: center; cursor: pointer; transform: translateY(2px); transition: background .15s, border-color .15s; }
li.finding input:hover { border-color: var(--c); }
li.finding input:checked { border-color: var(--c); background: var(--c); }
li.finding input:checked::after { content: ""; width: 7px; height: 4px; border: 1.5px solid var(--c-ink); border-top: 0; border-inline-end: 0; transform: rotate(-45deg) translate(0.5px, -1px); }
li.fix { --k: var(--c); } li.ask { --k: var(--rekhu-warn); } li.refuse { --k: var(--rekhu-bad); } li.note { --k: var(--rekhu-muted); } li.done { --k: var(--rekhu-ok); }
li.done .text { color: var(--rekhu-muted); text-decoration: line-through; text-decoration-color: color-mix(in srgb, var(--rekhu-muted) 60%, transparent); }
.enter li.finding, .enter .group h4, .enter .counts, .enter .summary, .enter .actions { animation: rise .4s cubic-bezier(.2,.8,.2,1) both; animation-delay: calc(var(--i, 0) * 40ms + 80ms); }
.enter .verdict, .enter .drop, .enter .work, .enter .error { animation: rise .35s cubic-bezier(.2,.8,.2,1) both; }
@keyframes rise { from { opacity: 0; transform: translateY(6px); } }
.actions { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-top: 18px; }
button { font: inherit; font-size: 13px; font-weight: 600; line-height: 1; border-radius: 999px; padding: 8px 14px; cursor: pointer;
  border: 1px solid var(--rekhu-border); background: transparent; color: var(--rekhu-text); display: inline-flex; align-items: center; gap: 6px; transition: background .15s, border-color .15s, color .15s, transform .1s; }
button:hover { border-color: var(--rekhu-text); }
button:active { transform: translateY(1px); }
button svg { width: 14px; height: 14px; }
button.primary { border-color: var(--c); background: var(--c); color: var(--c-ink); }
button.primary:hover { background: color-mix(in srgb, var(--c) 86%, #000); border-color: color-mix(in srgb, var(--c) 86%, #000); }
button.link { border-color: transparent; padding-inline: 6px; color: var(--rekhu-muted); }
button.link:hover { color: var(--rekhu-text); border-color: transparent; }
button:disabled { opacity: .6; cursor: progress; }
button .spinner { width: 12px; height: 12px; border-radius: 50%; border: 1.5px solid color-mix(in srgb, var(--c-ink) 35%, transparent); border-top-color: var(--c-ink); animation: turn .7s linear infinite; }
@keyframes turn { to { transform: rotate(360deg); } }
.error { display: grid; gap: 12px; justify-items: start; padding: 14px 0; border-top: 2px solid var(--rekhu-bad); }
.error p { margin: 0; font-weight: 600; color: var(--rekhu-bad); }
.error .actions { margin: 0; }
.burst { position: absolute; inset: 0; pointer-events: none; overflow: hidden; }
.burst i { position: absolute; top: 25%; left: 60%; width: 5px; height: 5px; background: var(--k); opacity: 0; animation: fly 1s cubic-bezier(.2,.8,.2,1) forwards; animation-delay: calc(var(--d) * 1ms); }
@keyframes fly { 0% { opacity: 1; transform: translate(0, 0) rotate(0); } 100% { opacity: 0; transform: translate(var(--x), var(--y)) rotate(var(--r)); } }
@media (prefers-reduced-motion: reduce) { *, *::before, *::after { animation-duration: .01ms !important; animation-iteration-count: 1 !important; transition-duration: .01ms !important; } }
`;

const ICON = {
    upload: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 16V4M7 9l5-5 5 5"/><path d="M4 15v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/></svg>',
    ok: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>',
    warn: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 6v8M12 18.5v.01"/></svg>',
    bad: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m7 7 10 10M17 7 7 17"/></svg>',
    fix: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L4 17l3 3 5.3-5.3a4 4 0 0 0 5.4-5.4l-2.5 2.5-2.4-.6-.6-2.4z"/></svg>',
    ask: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9.1 9a3 3 0 0 1 5.8 1c0 2-3 2.6-3 4.5M12 18h.01"/></svg>',
    refuse: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="8"/><path d="m6.5 6.5 11 11"/></svg>',
    note: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 11v6M12 7.5h.01"/></svg>',
    done: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>',
    magic: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m4 20 11-11M14 4v3M12.5 5.5h3M19 9v3M17.5 10.5h3M18 3l.5 1.5L20 5l-1.5.5L18 7l-.5-1.5L16 5l1.5-.5z"/></svg>',
    download: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v12M7 11l5 5 5-5M5 20h14"/></svg>',
    again: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12a8 8 0 0 1 14-5.3L20 8M20 4v4h-4M20 12a8 8 0 0 1-14 5.3L4 16M4 20v-4h4"/></svg>',
};

const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const size = (bytes) => (bytes >= 1048576 ? `${(bytes / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`);
const Base = typeof HTMLElement === 'undefined' ? class {} : HTMLElement;

/** <rekhu-seshat>: the ready-made file check. Settings through mount() or data-settings; element.flow is the logic underneath. */
class RekhuSeshat extends Base {
    constructor() {
        super();
        this.attachShadow({ mode: 'open' });
        this.settings = {};
        this.flow = null;
        this.page = 1;
        this.images = new Map();
    }

    connectedCallback() {
        if (!this.flow) {
            let fromAttribute = {};
            try { fromAttribute = JSON.parse(this.dataset.settings || '{}'); } catch { /* not JSON */ }
            this.configure({ ...fromAttribute, ...this.settings });
            this.listen();
        }
        this.render();
    }

    /** Settings now or later: endpoint, specs, recipe, fix, proof, explain, download, steps, locale, texts and the rest. */
    configure(settings = {}) {
        this.settings = { proof: true, download: true, steps: true, theme: 'light', ...this.settings, ...settings };
        const locale = this.settings.locale === undefined ? (document.documentElement.lang || navigator.language || 'en') : this.settings.locale;
        const flowSettings = { ...this.settings, locale };
        if (!this.flow) {
            if (!this.settings.endpoint) return this;
            this.flow = createFlow(flowSettings);
            this.flow.on('state', () => this.render());
            const forward = (type, hook) => this.flow.on(type, (detail) => {
                this.dispatchEvent(new CustomEvent(`rekhu:${type}`, { detail, bubbles: true, composed: true }));
                this.settings[hook]?.(detail);
            });
            forward('checked', 'onChecked');
            forward('fixed', 'onFixed');
            forward('done', 'onDone');
            forward('error', 'onError');
            this.flow.on('error', (e) => { if ([401, 419].includes(e.status)) this.dispatchEvent(new CustomEvent('rekhu:unauthorized', { detail: { status: e.status }, bubbles: true, composed: true })); });
            this.flow.on('checked', () => { this.page = 1; this.proof(); });
            this.flow.on('fixed', () => { this.page = 1; this.proof(); });
        } else this.flow.configure(flowSettings);
        const dir = this.settings.dir || (document.documentElement.dir === 'rtl' || this.language() === 'ar' ? 'rtl' : 'ltr');
        this.setAttribute('dir', dir);
        this.render();
        return this;
    }

    language() {
        return String(this.settings.locale || document.documentElement.lang || 'en').slice(0, 2).toLowerCase();
    }

    /** A word in the person's language, the app's own first. */
    t(key) {
        return this.settings.texts?.[key] ?? (TEXTS[this.language()] || TEXTS.en)[key] ?? TEXTS.en[key] ?? key;
    }

    /** Checks a file, as a drop or the chooser does: for a page that has the file already. */
    check(file) { return this.flow?.check(file); }

    reset() { this.clearImages(); this.flow?.reset(); return this; }

    destroy() { this.clearImages(); this.remove(); }

    clearImages() { for (const url of this.images.values()) URL.revokeObjectURL(url); this.images.clear(); }

    /** The current file's page as a picture, fetched once with the person's sign-in. */
    async proof() {
        const s = this.flow?.state;
        const fileId = s?.fixed?.file_id || s?.check?.file_id;
        if (!fileId || this.settings.proof === false) return;
        const key = `${fileId}:${this.page}`;
        if (!this.images.has(key)) {
            try { this.images.set(key, URL.createObjectURL(await this.flow.client.preview(fileId, this.page))); } catch { return; }
        }
        this.render();
    }

    /** Clicks, keys, the chooser and drag and drop, once, on the shadow root: the content is drawn again on each change. */
    listen() {
        const root = this.shadowRoot;
        root.addEventListener('click', (e) => {
            if (e.target.type === 'checkbox' && e.target.dataset.fix) { this.flow?.toggle(e.target.dataset.fix); return; }
            const el = e.target.closest('[data-action]');
            if (!el || !this.flow) return;
            const action = el.dataset.action;
            if (action === 'choose') root.querySelector('input[type=file]')?.click();
            if (action === 'fix') this.flow.fix();
            if (action === 'another') this.reset();
            if (action === 'retry') this.flow.retry();
            if (action === 'page') { this.page = Number(el.dataset.page); this.render(); this.proof(); }
            if (action === 'download') this.download();
        });
        root.addEventListener('change', (e) => {
            if (e.target.type === 'file' && e.target.files?.[0]) this.flow?.check(e.target.files[0]);
        });
        root.addEventListener('keydown', (e) => {
            if ((e.key === 'Enter' || e.key === ' ') && e.target.dataset?.action === 'choose') { e.preventDefault(); root.querySelector('input[type=file]')?.click(); }
        });
        this.addEventListener('dragover', (e) => { if (this.flow?.state.step === 'idle') { e.preventDefault(); this.setAttribute('dragging', ''); } });
        this.addEventListener('dragleave', () => this.removeAttribute('dragging'));
        this.addEventListener('drop', (e) => {
            this.removeAttribute('dragging');
            if (this.flow?.state.step !== 'idle' || !e.dataTransfer?.files?.[0]) return;
            e.preventDefault();
            this.flow.check(e.dataTransfer.files[0]);
        });
    }

    /** The fixed file saved by the browser under its own name, with the person's sign-in. */
    async download() {
        const id = this.flow.state.fixed?.file_id || this.flow.state.check?.file_id;
        const { blob, name } = await this.flow.client.download(id);
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = name;
        document.body.appendChild(a);
        a.click();
        setTimeout(() => { URL.revokeObjectURL(a.href); a.remove(); }, 1000);
    }

    /** The specs as chips: 148 × 210 mm, 3 mm bleed, 2 pages. */
    specChips() {
        const s = this.flow?.state.check?.specs || this.settings.specs || {};
        const chips = [s.width_mm && s.height_mm ? `${s.width_mm} × ${s.height_mm} ${this.t('mm')}` : '', s.bleed_mm ? `${s.bleed_mm} ${this.t('mm')} ${this.t('bleedOf')}` : '', s.pages ? `${s.pages} ${s.pages > 1 ? this.t('pages') : this.t('page')}` : '', this.settings.recipe || ''];
        return chips.filter(Boolean).map((c) => `<span class="spec" part="spec">${esc(c)}</span>`).join('');
    }

    /** The steps of this workflow on a track, the done ones ticked and the current one ringed. */
    steps(step) {
        if (this.settings.steps === false) return '';
        const list = [['upload', this.t('stepUpload')], ['check', this.t('stepCheck')], ...(this.settings.fix === false ? [] : [['fix', this.t('stepFix')]]), ['done', this.t('stepDone')]];
        const at = { idle: 0, uploading: 0, checking: 1, checked: 1, error: 1, fixing: 2, fixed: 2, done: list.length - 1 }[step] ?? 0;
        const items = list.map(([key, label], i) => {
            const past = i < at || (step === 'done' && i === at);
            return `<li class="${past ? 'past' : i === at ? 'now' : ''}" part="step step-${key}" ${i === at ? 'aria-current="step"' : ''}><i>${past ? '✓' : String(i + 1).padStart(2, '0')}</i>${esc(label)}</li>`;
        }).join('');
        return `<ol class="track" part="steps">${items}</ol>`;
    }

    /** One finding: its kind, the sentence (the explanation's when there is one), its question, its page; a box to pick a fix. */
    finding(f, explained, choosing, i) {
        const said = explained.find((e) => e.code === f.code);
        const kind = ['fix', 'ask', 'refuse'].includes(f.decision) ? f.decision : 'note';
        const pages = Array.isArray(f.pages) && f.pages.length ? f.pages : [];
        const label = pages.length ? `${pages.length > 1 ? this.t('pages') : this.t('page')} ${pages.slice(0, 4).join(', ')}${pages.length > 4 ? '…' : ''}` : '';
        const lead = choosing && f.decision === 'fix' && f.fix ? `<input type="checkbox" data-fix="${esc(f.fix)}" ${this.flow.state.chosen.includes(f.fix) ? 'checked' : ''} aria-label="${esc(this.t('fix'))}">` : `<span class="k">${ICON[kind]}</span>`;
        const link = pages.length && this.settings.proof !== false ? ` link" data-action="page" data-page="${pages[0]}" title="${esc(this.t('show'))} ${pages[0]}` : '';
        return `<li class="finding ${kind}${link}" part="finding finding-${kind}" style="--i:${i}">${lead}<span class="text">${esc(said?.text || f.about || f.code)}${f.file ? ` · ${esc(f.file)}` : ''}</span>${said?.question ? `<span class="question">${esc(said.question)}</span>` : ''}${label ? `<span class="pg">${esc(label)}</span>` : ''}</li>`;
    }

    /** The findings in groups: fixed first after a fix, then we fix, your choice, can't print, notes. */
    groups(findings, explained, applied) {
        const choosing = this.settings.fix === 'choose' && !this.flow.state.fixed;
        const solved = (a) => (this.flow.state.check?.findings || []).find((f) => f.fix === a.fix);
        const colour = { done: 'var(--rekhu-ok)', fix: 'var(--c)', ask: 'var(--rekhu-warn)', refuse: 'var(--rekhu-bad)', note: '#94a3b8' };
        const order = [['done', this.t('groupDone')], ['refuse', this.t('groupRefuse')], ['fix', this.t('groupFix')], ['ask', this.t('groupAsk')], ['note', this.t('groupNote')]];
        let i = 0;
        return order.map(([key, label]) => {
            const items = key === 'done'
                ? applied.map((a) => `<li class="finding done" part="finding finding-done" style="--i:${i++}"><span class="k">${ICON.done}</span><span class="text">${esc(explained.find((e) => e.code === solved(a)?.code)?.text || solved(a)?.about || a.about || a.fix)}</span></li>`)
                : findings.filter((f) => (['fix', 'ask', 'refuse'].includes(f.decision) ? f.decision : 'note') === key).map((f) => this.finding(f, explained, choosing, i++));
            return items.length ? `<section class="group" part="group group-${key}" style="--k:${colour[key]}"><h4 style="--i:${i}">${esc(label)} <b>${items.length}</b></h4><ul>${items.join('')}</ul></section>` : '';
        }).join('');
    }

    /** The headline of the result and its line below: ok, fixable, ask or refuse, and after the fix the verification. */
    verdict(findings, fixed) {
        if (findings.some((f) => f.decision === 'refuse')) return ['bad', this.t('refuse')];
        if (fixed) return findings.some((f) => ['ask', 'fix'].includes(f.decision)) ? ['warn', this.t('verifiedLeft')] : ['ok', this.t('verifiedOk')];
        if (findings.some((f) => f.decision === 'ask')) return ['warn', this.t('ask')];
        if (findings.some((f) => f.decision === 'fix')) return ['warn', this.t('fixable')];
        return ['ok', this.t('ok')];
    }

    /** A short burst of colour when a file is ready to print. */
    burst() {
        const colours = ['var(--c)', 'var(--c-spark)', 'var(--rekhu-ok)', 'var(--rekhu-text)'];
        return `<div class="burst" aria-hidden="true">${Array.from({ length: 22 }, (_, n) => `<i style="--k:${colours[n % 4]};--x:${Math.round(Math.cos(n) * (120 + (n % 5) * 40))}px;--y:${Math.round(Math.sin(n) * 120 + 60)}px;--r:${n * 47}deg;--d:${n * 12}"></i>`).join('')}</div>`;
    }

    /** While a file goes up: its name, size and the bar; patched in place as it moves, so nothing jumps. */
    uploading(s) {
        const pct = Math.round((s.progress || 0) * 100);
        const kind = String(s.file?.name || '').split('.').pop().slice(0, 4).toUpperCase() || 'PDF';
        const checking = s.step === 'checking';
        const says = checking ? `<div class="says" aria-live="polite">${['scan1', 'scan2', 'scan3', 'scan4', 'scan5'].map((k) => `<span>${esc(this.t(k))}</span>`).join('')}</div>` : '';
        return `<div class="work" part="progress">${checking ? '<div class="scan"><span class="ln"></span><span class="ln"></span><span class="ln"></span><span class="ln"></span><span class="ln"></span><span class="ln"></span><span class="beam"></span></div>' : ''}
            <div class="file"><span class="type">${esc(kind)}</span><div class="meta"><strong>${esc(s.file?.name)}</strong><span data-pct>${checking ? esc(this.t('checking')) : `${esc(this.t('sending'))} · ${pct}%${s.file?.size ? ` · ${size(s.file.size)}` : ''}`}</span>
            <div class="bar"><i style="--p:${checking ? 100 : pct}%"></i></div></div></div>${says}${checking ? `<small>${esc(this.t('wait'))}</small>` : ''}</div>`;
    }

    /** The whole interface for the current state; entrance motion only when the step changes. */
    render() {
        if (!this.shadowRoot) return;
        if (!this.flow) { this.shadowRoot.innerHTML = ''; return; }
        const s = this.flow.state;
        const moved = s.step !== this.drawnStep || s.fixed !== this.drawnFixed;
        if (!moved && s.step === 'uploading') {
            const pct = Math.round((s.progress || 0) * 100);
            const bar = this.shadowRoot.querySelector('.bar i');
            const label = this.shadowRoot.querySelector('[data-pct]');
            if (bar) bar.style.setProperty('--p', `${pct}%`);
            if (label) label.textContent = `${this.t('sending')} · ${pct}%${s.file?.size ? ` · ${size(s.file.size)}` : ''}`;
            return;
        }
        this.drawnStep = s.step;
        this.drawnFixed = s.fixed;
        this.setAttribute('theme', this.settings.theme || 'light');
        const accept = this.settings.accept || '.pdf,.eps,.ai,.zip,.jpg,.jpeg,.png,.tif,.tiff';
        let body = '';
        if (s.step === 'idle') {
            body = `<div class="drop" part="dropzone" role="button" tabindex="0" data-action="choose"><span class="art">${ICON.upload}</span><strong>${esc(this.t('drop'))}</strong><span><u>${esc(this.t('choose'))}</u></span>
                <span class="kinds">PDF · EPS · ZIP · JPG · PNG · TIFF</span></div><input type="file" accept="${esc(accept)}">`;
        } else if (s.step === 'uploading' || s.step === 'checking') {
            body = this.uploading(s);
        } else if (s.step === 'error') {
            const message = [401, 419].includes(s.error?.status) ? this.t('signedOut') : s.error?.message || this.t('failed');
            body = `<div class="error" part="error" role="alert"><p>${esc(message)}</p><div class="actions"><button type="button" data-action="retry" part="button">${ICON.again}${esc(this.t('again'))}</button><button type="button" data-action="another" part="button">${esc(this.t('another'))}</button></div></div>`;
        } else {
            const fixed = s.fixed && s.fixed.check ? s.fixed : null;
            const findings = this.flow.findings();
            const [tone, headline] = this.verdict(findings, !!fixed);
            const explained = s.check?.explanation?.findings || [];
            const pages = Number((fixed?.check?.pages) || s.check?.pages || 1);
            const fileId = fixed?.file_id || s.check?.file_id;
            const image = this.images.get(`${fileId}:${this.page}`);
            const fixing = s.step === 'fixing';
            const proof = this.settings.proof === false ? '' : `<figure part="proof"><div class="sheet ${fixing ? 'busy' : ''}">${image ? `<img src="${image}" alt="${esc(this.t('show'))} ${this.page}">` : '<div class="scan"><span class="ln"></span><span class="ln"></span><span class="ln"></span><span class="ln"></span><span class="ln"></span><span class="ln"></span><span class="beam"></span></div>'}</div>
                <figcaption><span class="legend"><span><i style="background:#16a34a"></i>${esc(this.t('trim'))}</span><span><i style="background:#2563eb"></i>${esc(this.t('bleed'))}</span><span><i style="background:#f97316"></i>${esc(this.t('safe'))}</span></span>
                ${pages > 1 ? `<span class="pager"><button type="button" data-action="page" data-page="${Math.max(1, this.page - 1)}" ${this.page <= 1 ? 'disabled' : ''} aria-label="${esc(this.t('prev'))}">‹</button><span>${this.page} ${esc(this.t('of'))} ${pages}</span><button type="button" data-action="page" data-page="${Math.min(pages, this.page + 1)}" ${this.page >= pages ? 'disabled' : ''} aria-label="${esc(this.t('next'))}">›</button></span>` : ''}</figcaption></figure>`;
            const canFix = this.settings.fix !== false && !s.fixed && findings.some((f) => f.decision === 'fix' && f.fix);
            const fixLabel = this.settings.fix === 'choose' ? `${this.t('fixSome')} (${s.chosen.length})` : this.t('fix');
            const tally = [['fix', 'var(--c)', this.t('groupFix')], ['ask', 'var(--rekhu-warn)', this.t('groupAsk')], ['refuse', 'var(--rekhu-bad)', this.t('groupRefuse')]]
                .map(([d, k, l]) => [findings.filter((f) => f.decision === d).length, k, l]).filter(([n]) => n).map(([n, k, l]) => `<span style="--k:${k}">${n} · ${esc(l)}</span>`).join('');
            const sub = fixed ? `${fixed.applied?.length || 0} · ${this.t('groupDone')}` : s.check?.name || '';
            body = `<div class="result">${proof}<section>
                <div class="verdict ${tone}" part="status status-${tone}" role="status"><span class="ic">${ICON[tone]}</span><div><b>${esc(headline)}</b><small>${esc(sub)}</small></div></div>
                ${tally ? `<div class="counts">${tally}</div>` : ''}
                ${!fixed && s.check?.explanation?.summary ? `<p class="summary" part="explanation">${esc(s.check.explanation.summary)}</p>` : ''}
                ${this.groups(findings, explained, fixed?.applied || [])}
                <div class="actions" part="actions">
                    ${canFix || fixing ? `<button type="button" class="primary" part="button button-primary" data-action="fix" ${fixing || (this.settings.fix === 'choose' && !s.chosen.length) ? 'disabled' : ''}>${fixing ? `<span class="spinner"></span>${esc(this.t('fixing'))}` : `${ICON.magic}${esc(fixLabel)}`}</button>` : ''}
                    ${fixed && this.settings.download !== false ? `<button type="button" class="primary" part="button button-primary" data-action="download">${ICON.download}${esc(this.t('download'))}</button>` : ''}
                    <button type="button" class="link" part="button button-link" data-action="another">${esc(this.t('another'))}</button>
                </div></section></div>`;
        }
        const celebrate = moved && s.step === 'done' && !this.flow.findings().some((f) => ['ask', 'refuse', 'fix'].includes(f.decision));
        this.shadowRoot.innerHTML = `<style>${STYLE}</style><div class="box ${moved ? 'enter' : ''}" part="box"><header part="header"><slot name="title"><h3>${esc(this.settings.title || this.t('title'))}</h3></slot><div class="specs">${this.specChips()}</div></header>${this.steps(s.step)}${body}${celebrate ? this.burst() : ''}</div>`;
    }
}

/** <rekhu-seshat> registered once. */
function define(name = 'rekhu-seshat') {
    if (typeof customElements !== 'undefined' && !customElements.get(name)) customElements.define(name, class extends RekhuSeshat {});
}

/**
 * The file check put on the page: into target (an element or a selector), with the settings. Answers the element, with
 * configure(), check(file), reset() and destroy(); null during server rendering.
 */
function mount(target, settings = {}) {
    if (typeof document === 'undefined') return null;
    define();
    const host = typeof target === 'string' ? document.querySelector(target) : target;
    if (!host) throw new Error(`No element for the file check: ${target}`);
    const el = host.localName === 'rekhu-seshat' ? host : host.appendChild(document.createElement('rekhu-seshat'));
    el.configure(settings);
    return el;
}

define();
window.RekhuSeshat = { mount, define, createClient, createFlow, TEXTS, PROTOCOL };
})();
