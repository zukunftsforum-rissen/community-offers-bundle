import { whoami, openDoor, requestAccess } from './api.js';

const APP_CONFIG = window.CO_APP_CONFIG ?? {
    loginPath: '/login',
    logoutPath: '/_contao/logout',
    logoutRedirectPath: '/app',
};

function buildLogoutUrl() {
    const logoutPath = APP_CONFIG.logoutPath || '/_contao/logout';
    const redirectPath = APP_CONFIG.logoutRedirectPath || '/app';

    return logoutPath + '?redirect=' + encodeURIComponent(redirectPath);
}

const AREAS = [
    { slug: 'workshop', title: 'Werkstatt' },
    { slug: 'sharing', title: 'Ausleihen' },
    { slug: 'depot', title: 'Depot' },
    { slug: 'swap-house', title: 'Tauschhaus' },
];

function memberDisplayName(me) {
    const fn = me?.member?.firstname ?? '';
    const ln = me?.member?.lastname ?? '';
    const full = `${fn} ${ln}`.trim();

    if (full) return full;
    if (me?.member?.email) return me.member.email;
    if (me?.member?.id) return `#${me.member.id}`;

    return 'Mitglied';
}

function formatCountdown(seconds) {
    const sec = Math.max(0, Math.floor(Number(seconds || 0)));
    const m = Math.floor(sec / 60);
    const s = sec % 60;

    return `${m}:${String(s).padStart(2, '0')}`;
}

function startCooldownOnButton(btn, seconds, textFn, onDone) {
    let remaining = Number.isFinite(seconds) && seconds > 0 ? Math.floor(seconds) : 0;

    if (!btn.dataset.originalText) {
        btn.dataset.originalText = btn.textContent;
    }

    btn.disabled = true;
    btn.classList.add('cooldown');

    const tick = () => {
        if (remaining <= 0) {
            btn.classList.remove('cooldown');
            btn.disabled = false;

            if (typeof onDone === 'function') {
                onDone();
            } else {
                btn.textContent = btn.dataset.originalText;
            }

            btn.classList.add('pulse-ready');
            setTimeout(() => btn.classList.remove('pulse-ready'), 1800);

            return;
        }

        btn.textContent = textFn(remaining);
        remaining -= 1;
        setTimeout(tick, 1000);
    };

    tick();
}

function getRequestState(me, slug) {
    const r = me?.requests?.[slug];

    if (!r) {
        return null;
    }

    if (typeof r === 'string') {
        return { state: r, retryAfterSeconds: 0 };
    }

    return {
        state: r.state ?? null,
        retryAfterSeconds: Number(r.retryAfterSeconds ?? 0),
    };
}

async function initApp() {
    const statusEl = document.getElementById('status');
    const actionsEl = document.getElementById('actions');
    const loginHintEl = document.getElementById('loginHint');
    const logoutBtn = document.getElementById('logout');
    const loginLinkEl = document.getElementById('loginLink');
    if (loginLinkEl) {
        loginLinkEl.href = APP_CONFIG.loginPath || '/login';
    }

    if (!statusEl || !actionsEl || !loginHintEl) {
        console.error('App DOM missing: status/actions/loginHint not found');
        return;
    }

    logoutBtn?.addEventListener('click', () => {
        location.href = buildLogoutUrl();
    });

    async function refreshAndRender() {
        statusEl.textContent = 'Prüfe Login …';

        try {
            const me = await whoami();

            if (!me.authenticated) {
                statusEl.textContent = 'Bitte einloggen 🔐';
                actionsEl.classList.add('hidden');
                loginHintEl.classList.remove('hidden');
                return;
            }

            statusEl.innerHTML = `<span class="status">Hallo <strong>${memberDisplayName(me)} 👋</strong></span>`;

            actionsEl.classList.remove('hidden');
            loginHintEl.classList.add('hidden');

            renderAreas(me);
        } catch (e) {
            console.error(e);
            statusEl.textContent = 'Fehler bei Login-Prüfung ❌';
        }
    }

    function renderAreas(me) {
        const granted = Array.isArray(me?.areas) ? me.areas : [];

        actionsEl.innerHTML = '';

        for (const { slug, title } of AREAS) {
            const isGranted = granted.includes(slug);
            const req = getRequestState(me, slug);

            const doorBtn = document.createElement('button');
            doorBtn.type = 'button';
            doorBtn.className = 'btn';

            if (isGranted) {
                doorBtn.dataset.door = slug;
                doorBtn.textContent = `${title} öffnen 🔓`;

                doorBtn.addEventListener('click', async () => {
                    doorBtn.disabled = true;
                    statusEl.textContent = `Öffne ${title} …`;

                    try {
                        const result = await openDoor(slug);
                        if (result.status === 429) {
                            startCooldownOnButton(
                                doorBtn,
                                Number(result.retryAfterSeconds || 5),
                                (sec) => `Bitte kurz warten (${formatCountdown(sec)})`,
                                () => { doorBtn.textContent = `${title} öffnen 🔓`; }
                            );
                            statusEl.textContent = 'Bitte kurz warten ⏳';
                            return;
                        }

                        if (result.status === 202) {
                            statusEl.textContent = 'Job angenommen ⏳ Gerät hat den Auftrag noch nicht abgeholt.';
                        } else if (result.success) {
                            statusEl.textContent = 'Tür geöffnet ✅';
                        } else if (result.status === 403) {
                            statusEl.textContent = 'Kein Zugriff 🔐';
                        } else {
                            statusEl.textContent = result.message ? `Fehler ❌ ${result.message}` : 'Fehler ❌';
                        }

                    } catch (e) {
                        console.error(e);
                        statusEl.textContent = 'Netzwerkfehler ❌';
                    } finally {
                        if (!doorBtn.classList.contains('cooldown')) {
                            doorBtn.disabled = false;
                        }
                    }
                });

                actionsEl.appendChild(doorBtn);
                continue;
            }

            doorBtn.textContent = `${title} (kein Zugang)`;
            doorBtn.disabled = true;

            if (req?.state === 'pending_confirmed') {
                doorBtn.textContent = `${title}: wartet auf Freigabe ⏳`;
            } else if (req?.state === 'pending_unconfirmed') {
                doorBtn.textContent = `${title}: E-Mail bestätigen 📩`;
            }

            doorBtn.classList.add('has-request');
            actionsEl.appendChild(doorBtn);

            const requestLink = document.createElement('a');
            requestLink.href = '#';
            requestLink.className = 'request-link';
            requestLink.textContent = 'Zugang beantragen';

            if (req?.state === 'pending_confirmed') {
                requestLink.classList.add('sent');
                requestLink.textContent = 'Wartet auf Freigabe ⏳';
                requestLink.addEventListener('click', (e) => e.preventDefault());
                actionsEl.appendChild(requestLink);
                continue;
            }

            if (req?.state === 'pending_unconfirmed' && req.retryAfterSeconds > 0) {
                requestLink.classList.add('cooldown');
                requestLink.textContent = `Keine E-Mail erhalten? Neuer Versuch in ${formatCountdown(req.retryAfterSeconds)} möglich.`;
                requestLink.addEventListener('click', (e) => e.preventDefault());
                actionsEl.appendChild(requestLink);

                setTimeout(() => {
                    requestLink.classList.remove('cooldown');
                    requestLink.classList.add('ready');
                    requestLink.textContent = 'Keine E-Mail erhalten? Erneut senden ➜';
                }, req.retryAfterSeconds * 1000);
            }

            requestLink.addEventListener('click', async (e) => {
                e.preventDefault();

                if (requestLink.classList.contains('cooldown')) {
                    return;
                }

                requestLink.classList.remove('ready');
                requestLink.textContent = 'Sende …';

                try {
                    const res = await requestAccess(slug);

                    if (res.status === 429) {
                        const secs = Number(res.retryAfterSeconds || 60);
                        requestLink.classList.add('cooldown');
                        requestLink.textContent = `Neuer Versuch in ${formatCountdown(secs)} möglich.`;

                        setTimeout(() => {
                            requestLink.classList.remove('cooldown');
                            requestLink.classList.add('ready');
                            requestLink.textContent = 'Erneut senden ➜';
                        }, secs * 1000);

                        statusEl.textContent = 'Bitte kurz warten ⏳';
                        return;
                    }

                    if (res.success) {
                        requestLink.classList.add('sent');
                        requestLink.textContent = 'Mail gesendet ✅';
                        statusEl.textContent = 'Mail gesendet ✅';
                        await refreshAndRender();
                        return;
                    }

                    requestLink.textContent = 'Zugang beantragen';
                    statusEl.textContent = res.message ? `Hinweis: ${res.message}` : 'Konnte nicht senden ❌';
                } catch (err) {
                    console.error(err);
                    requestLink.textContent = 'Zugang beantragen';
                    statusEl.textContent = 'Netzwerkfehler ❌';
                }
            });

            actionsEl.appendChild(requestLink);
        }

        const hr = document.createElement('hr');
        actionsEl.appendChild(hr);

        const logoutButton = document.createElement('button');
        logoutButton.id = 'logout';
        logoutButton.className = 'btn secondary';
        logoutButton.type = 'button';
        logoutButton.textContent = 'Logout';
        logoutButton.addEventListener('click', () => {
            location.href = buildLogoutUrl();
        });

        actionsEl.appendChild(logoutButton);
    }

    await refreshAndRender();
}

void initApp();
