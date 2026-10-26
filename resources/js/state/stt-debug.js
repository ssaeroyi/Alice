import { speechDebugSnapshot, stopPronounceCheck } from '../components/speech.js';

/**
 * Admin STT debug pane.
 *
 * Mounts when the server marks the page with data-debug-actor="admin"
 * (i.e. an administrator is previewing via "View As", the same gate as the
 * READER TIMER DEBUG panel), OR the admin "Say-it debug" toggle on the Status
 * page has stamped data-stt-debug="1" for this session (persistent, no URL
 * param needed) — AND at least one pronunciation-check widget is present. A
 * normal student session never builds it. A dev-only escape hatch
 * (?stt-diag=1 on localhost/127.0.0.1, or when the app is in debug mode)
 * enables the same pane so speech behaviour can be verified without an admin
 * session.
 *
 * It renders the speech module's REAL state (speechDebugSnapshot − never a
 * reimplementation) so the admin can watch recognition work or fail in the
 * browser: model readiness, secure context, a live listening-seconds
 * ticker, the live recognized transcript, the last raw error / verdict, and
 * a rolling event timeline (start → result → error → end → verdict).
 * "STOP NOW" runs the same tap-to-stop code path as the mic button.
 *
 * Mirrors the visual register of the READER TIMER DEBUG panel (dark ink,
 * amber accents, Quicksand) but docks top-left, just under the sticky
 * VIEWING-AS banner, so it clears the student's bottom tab bar on
 * non-immersive pages.
 */
export default function initSttDebug() {
    const body = document.body;

    const params = new URLSearchParams(window.location.search);
    const isLocal = ['localhost', '127.0.0.1', '::1'].includes(window.location.hostname);
    const devHook = params.get('stt-diag') === '1'
        && (isLocal || document.body.dataset.debug === '1');

    // Persistent admin toggle (see "Say-it debug" on the Status page) stamps
    // data-stt-debug="1" on student say-it pages for the session. Plus the
    // dev-only ?stt-diag=1 hook, plus the admin View-As actor.
    const sttDev = body.dataset.sttDebug === '1';
    if (body.dataset.debugActor !== 'admin' && !devHook && !sttDev) {
        return;
    }

    if (!document.querySelector('[data-pronounce-check]')) {
        return;
    }

    if (document.querySelector('[data-stt-debug]')) {
        return;
    }

    window.__speechDebug = speechDebugSnapshot;

    const panel = document.createElement('div');
    panel.setAttribute('data-stt-debug', '');
    panel.style.cssText = 'position:fixed;top:56px;left:8px;z-index:80;padding:10px 12px;'
        + 'background:#2D231E;color:#FBBF24;border:1px solid #FBBF24;border-radius:6px;'
        + 'font-family:"Quicksand",sans-serif;font-size:11px;line-height:1.5;'
        + 'box-shadow:0 1px 3px rgba(0,0,0,0.35);max-width:320px;';
    panel.innerHTML = ''
        + '<div style="font-size:9px;font-weight:700;letter-spacing:0.02em;color:#fff;margin-bottom:4px;">STT DEBUG</div>'
        + '<div data-s="secure">SECURE - HOST -</div>'
        + '<div data-s="engine">ENGINE -</div>'
        + '<div data-s="state">STATE IDLE</div>'
        + '<div data-s="target">TARGET -</div>'
        + '<div data-s="conf">WORDS -</div>'
        + '<div data-s="heard" style="white-space:pre-wrap;word-break:break-word;">HEARD</div>'
        + '<div data-s="speech">SPEECH -</div>'
        + '<div data-s="source" style="color:#fff;">SOURCE -</div>'
        + '<div data-s="last">LAST -</div>'
        + '<div data-s="widgets">WIDGETS -</div>'
        + '<div data-s="log" style="margin-top:4px;border-top:1px solid rgba(251,191,36,0.35);padding-top:4px;'
        + 'font-family:monospace;font-size:10px;line-height:1.45;max-height:132px;overflow-y:auto;"></div>'
        + '<div style="display:flex;gap:6px;margin-top:6px;">'
        + '<button type="button" data-b="stop" style="flex:1;padding:5px;background:#FBBF24;color:#2D231E;border:0;'
        + 'border-radius:4px;font:inherit;font-weight:700;cursor:pointer;">STOP NOW</button>'
        + '<button type="button" data-b="clear" style="flex:1;padding:5px;background:transparent;color:#FBBF24;'
        + 'border:1px solid #FBBF24;border-radius:4px;font:inherit;font-weight:700;cursor:pointer;">CLEAR LOG</button>'
        + '</div>';
    document.body.appendChild(panel);

    const el = {
        secure: panel.querySelector('[data-s="secure"]'),
        engine: panel.querySelector('[data-s="engine"]'),
        state: panel.querySelector('[data-s="state"]'),
        target: panel.querySelector('[data-s="target"]'),
        conf: panel.querySelector('[data-s="conf"]'),
        heard: panel.querySelector('[data-s="heard"]'),
        speech: panel.querySelector('[data-s="speech"]'),
        source: panel.querySelector('[data-s="source"]'),
        last: panel.querySelector('[data-s="last"]'),
        widgets: panel.querySelector('[data-s="widgets"]'),
        log: panel.querySelector('[data-s="log"]'),
        stop: panel.querySelector('[data-b="stop"]'),
        clear: panel.querySelector('[data-b="clear"]'),
    };

    let logSince = 0;
    let lastLogKey = '';

    const renderLog = (entries) => {
        const shown = entries.filter((entry) => entry.t >= logSince).slice(-12);
        const key = shown.map((entry) => `${entry.t}.${entry.kind}.${entry.detail}`).join('|');
        if (key === lastLogKey) {
            return;
        }
        lastLogKey = key;

        if (shown.length === 0) {
            el.log.textContent = '';
            return;
        }

        el.log.innerHTML = '';
        for (const entry of shown) {
            const line = document.createElement('div');
            const time = new Date(entry.t);
            line.textContent = `${String(time.getHours()).padStart(2, '0')}:`
                + `${String(time.getMinutes()).padStart(2, '0')}:`
                + `${String(time.getSeconds()).padStart(2, '0')}.`
                + `${String(time.getMilliseconds()).padStart(3, '0')} ${entry.kind} ${entry.detail}`;
            el.log.appendChild(line);
        }
    };

    const repaint = () => {
        const s = window.__speechDebug();

        el.secure.textContent = 'SECURE ' + (s.secure ? 'yes' : 'no')
            + ' HOST ' + (s.host || '-');
        el.engine.textContent = 'ENGINE OFFLINE' + (s.offline ? ' · ' + s.offline : '');
        el.state.textContent = 'STATE ' + (s.listening
            ? `LISTEN ${s.elapsed.toFixed(1)}s`
            : 'IDLE');
        el.target.textContent = 'TARGET ' + (s.target || '-');
        el.conf.textContent = 'WORDS ' + (s.finalWords && s.finalWords.length
            ? s.finalWords.map((w) => `${w.word} ${(w.conf ?? 0).toFixed(2)}`).join(' · ')
            : '-');
        el.heard.textContent = 'HEARD ' + (s.transcript || s.final || (s.listening ? '(listening…)' : '-'));
        el.speech.textContent = 'SPEECH ' + Math.round(s.speechMs || 0) + 'ms'
            + ' · ratio ' + (s.speechRatio || 0).toFixed(2)
            + ' · peak ' + (s.speechPeak || 0).toFixed(4)
            + ' · floor ' + (s.ambientRms || 0).toFixed(4)
            + ' · lvl ' + (s.meterLevel || 0);
        el.source.textContent = 'SOURCE ' + (s.source || '-');
        el.last.textContent = 'LAST ' + (s.error
            ? `ERR ${s.error}`
            : (s.verdict
                ? `VERDICT ${s.verdict.label} (heard: "${s.verdict.heard || '—'}")`
                : (s.finalSeen ? 'final received' : (s.listens > 0 ? 'no result yet' : '-'))));
        el.widgets.textContent = `WIDGETS ${s.bound}/${s.widgets} bound`;
        renderLog(s.log);
    };

    el.stop.addEventListener('click', () => {
        stopPronounceCheck();
        setTimeout(repaint, 60);
    });

    el.clear.addEventListener('click', () => {
        logSince = Date.now();
        lastLogKey = '';
        renderLog(window.__speechDebug().log);
    });

    repaint();
    const interval = setInterval(repaint, 200);

    window.addEventListener('pagehide', () => clearInterval(interval), { once: true });
}