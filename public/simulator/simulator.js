/* Community Offers Door Simulator v4 */
(function () {
    const config = window.CO_SIMULATOR_CONFIG || {};
    const POLL_URL = config.pollUrl || '/door-simulator/poll';
    const CONFIRM_URL = config.confirmUrl || '/door-simulator/confirm';
    const POLL_INTERVAL_MS = Number(config.pollIntervalMs || 2000);

    const elConnectionState = document.getElementById('sim-connection-state');
    const elLastPoll = document.getElementById('sim-last-poll');
    const elLastJob = document.getElementById('sim-last-job');
    const elLastStatus = document.getElementById('sim-last-status');
    const elLog = document.getElementById('sim-log');
    const elClearLog = document.getElementById('sim-clear-log');

    let pollTimer = null;
    let isPolling = false;
    let soundEnabled = false;
    const runningAreas = new Set();

    console.log('CO simulator v4 loaded');

    function nowTime() {
        const d = new Date();
        return d.toLocaleTimeString('de-DE');
    }

    function log(message) {
        if (!elLog) {
            return;
        }

        const entry = document.createElement('div');
        entry.className = 'co-sim-log__entry';
        entry.innerHTML = '<span class="co-sim-log__time">' + escapeHtml(nowTime()) + '</span>' + escapeHtml(message);

        elLog.prepend(entry);

        while (elLog.children.length > 80) {
            elLog.removeChild(elLog.lastChild);
        }
    }

    function escapeHtml(value) {
        return String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function setConnectionState(text) {
        if (elConnectionState) {
            elConnectionState.textContent = text;
        }
    }

    function setLastPoll(text) {
        if (elLastPoll) {
            elLastPoll.textContent = text;
        }
    }

    function setLastJob(text) {
        if (elLastJob) {
            elLastJob.textContent = text;
        }
    }

    function setLastStatus(text) {
        if (elLastStatus) {
            elLastStatus.textContent = text;
        }
    }

    function sleep(ms) {
        return new Promise(function (resolve) {
            window.setTimeout(resolve, ms);
        });
    }

    function nextFrame() {
        return new Promise(function (resolve) {
            window.requestAnimationFrame(resolve);
        });
    }

    function playUnlockSound() {
        if (!soundEnabled) {
            return;
        }

        const buzzer = document.getElementById('sound-door-buzzer');

        if (!buzzer) {
            return;
        }

        buzzer.currentTime = 0;
        buzzer.play().catch((error) => {
            log('Buzzer konnte nicht abgespielt werden: ' + error);
        });
    }

    function playDoorClickSound() {
        if (!soundEnabled) {
            return;
        }

        const click = document.getElementById('sound-door-click');

        if (!click) {
            return;
        }

        click.currentTime = 0;
        click.play().catch((error) => {
            log('Klick konnte nicht abgespielt werden: ' + error);
        });
    }

    function getDoorByArea(area) {
        return document.querySelector('.co-door[data-area="' + CSS.escape(area) + '"]');
    }

    function getDoorStateEl(door) {
        return door ? door.querySelector('.co-door__state') : null;
    }

    function getPersonForDoor(door) {
        if (!door) {
            return null;
        }

        const shedKey = door.getAttribute('data-shed-key');
        return document.querySelector('.co-person[data-person-for="' + CSS.escape(shedKey) + '"]');
    }

    function setDoorState(door, text) {
        const stateEl = getDoorStateEl(door);
        if (stateEl) {
            stateEl.textContent = text;
        }
    }

    function getDoorIndex(door) {
        const allDoors = Array.from(door.parentElement.querySelectorAll('.co-door'));
        return allDoors.indexOf(door);
    }

    function computePersonWaitingX(door) {
        return getDoorIndex(door) === 0 ? 72 : 188;
    }

    function computePersonPushX(door) {
        return getDoorIndex(door) === 0 ? 92 : 208;
    }

    function computePersonEnterX(door) {
        return getDoorIndex(door) === 0 ? 128 : 244;
    }

    function showPersonAtDoor(person, door) {
        if (!person) {
            return;
        }

        person.classList.remove('is-hidden', 'is-entering', 'is-pushing', 'is-waiting');
        person.classList.add('is-visible', 'is-waiting');
        person.style.transform = 'translateX(' + computePersonWaitingX(door) + 'px)';
    }

    function hidePerson(person) {
        if (!person) {
            return;
        }

        person.classList.remove('is-visible', 'is-waiting', 'is-pushing', 'is-entering');
        person.classList.add('is-hidden');
        person.style.transform = 'translateX(0)';
    }

    async function animateDoor(area, label) {
        const door = getDoorByArea(area);

        if (!door) {
            log('Keine Tür für Area "' + label + '" gefunden.');
            return;
        }

        const person = getPersonForDoor(door);

        if (door.getAttribute('data-busy') === '1') {
            return;
        }

        door.setAttribute('data-busy', '1');
        runningAreas.add(area);

        setLastJob(label);
        setLastStatus('Person wartet vor Tür');
        setDoorState(door, 'Person steht vor Tür…');

        showPersonAtDoor(person, door);
        await nextFrame();
        await sleep(160);

        setDoorState(door, 'Summer…');
        setLastStatus('Summer läuft');
        door.classList.add('is-unlocking');
        playUnlockSound();

        await sleep(105);

        setDoorState(door, 'Klack…');
        setLastStatus('Tür entriegelt');
        playDoorClickSound();

        await sleep(45);

        door.classList.add('is-open');
        setDoorState(door, 'Tür wird aufgedrückt…');
        setLastStatus('Person drückt Tür auf');

        if (person) {
            person.classList.remove('is-waiting');
            person.classList.add('is-pushing');
            person.style.transform = 'translateX(' + computePersonPushX(door) + 'px)';
        }

        await sleep(240);

        if (person) {
            person.classList.remove('is-pushing');
            person.classList.add('is-entering');
            person.style.transform = 'translateX(' + computePersonEnterX(door) + 'px)';
        }

        setDoorState(door, 'Betreten…');
        setLastStatus('Person tritt ein');

        await sleep(620);

        door.classList.remove('is-unlocking', 'is-open');
        setDoorState(door, 'Schließt…');
        setLastStatus('Tür schließt');

        await sleep(240);

        hidePerson(person);
        setDoorState(door, 'Bereit');
        setLastStatus('Bereit');

        door.setAttribute('data-busy', '0');
        runningAreas.delete(area);
    }

    async function confirmJob(jobId, nonce, correlationId, label) {
        const response = await fetch(CONFIRM_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                jobId: jobId,
                nonce: nonce,
                ok: true,
                correlationId: correlationId,
                meta: {
                    source: 'door-simulator'
                }
            })
        });

        if (!response.ok) {
            throw new Error('HTTP ' + response.status);
        }

        log('Confirm für "' + label + '" gesendet.');
        setLastStatus('Bestätigt');
    }

    function normalizeJobs(payload) {
        if (!payload) {
            return [];
        }

        if (Array.isArray(payload.jobs)) {
            return payload.jobs;
        }

        if (payload.job && typeof payload.job === 'object') {
            return [payload.job];
        }

        return [];
    }

    function getJobArea(job) {
        return job.area || job.areaKey || job.doorKey || null;
    }

    function getJobNonce(job) {
        return job.nonce || null;
    }

    function getJobId(job) {
        return job.jobId || job.id || null;
    }

    function getJobCorrelationId(job) {
        return job.correlationId || null;
    }

    async function handleJob(job) {
        const area = getJobArea(job);
        const jobId = getJobId(job);
        const nonce = getJobNonce(job);
        const correlationId = getJobCorrelationId(job);

        if (!area) {
            log('Job ohne Area empfangen.');
            return;
        }

        const door = getDoorByArea(area);
        const label = door ? (door.getAttribute('data-label') || area) : area;

        if (runningAreas.has(area)) {
            log('Area "' + label + '" läuft bereits.');
            return;
        }

        log('Job empfangen für "' + label + '".');
        setLastJob(label);
        setLastStatus('Job empfangen');

        await animateDoor(area, label);

        if (!jobId || !nonce) {
            log('Confirm übersprungen: jobId oder nonce fehlt.');
            setLastStatus('Confirm unvollständig');
            return;
        }

        await confirmJob(jobId, nonce, correlationId, label);
    }

    async function pollDevice() {
        if (isPolling) {
            return;
        }

        isPolling = true;

        try {
            setConnectionState('Polling…');

            const response = await fetch(POLL_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'same-origin',
                body: JSON.stringify({})
            });

            setLastPoll(nowTime());

            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }

            const payload = await response.json();
            const jobs = normalizeJobs(payload);

            setConnectionState('Verbunden');

            for (const job of jobs) {
                await handleJob(job);
            }
        } catch (error) {
            setConnectionState('Fehler');
            setLastStatus('Polling fehlgeschlagen');
            log('Polling fehlgeschlagen: ' + error);
        } finally {
            isPolling = false;
        }
    }

    function startPolling() {
        if (pollTimer) {
            window.clearInterval(pollTimer);
        }

        pollDevice();
        pollTimer = window.setInterval(pollDevice, POLL_INTERVAL_MS);
        log('Pi-Simulator gestartet.');
    }

    function stopPolling() {
        if (pollTimer) {
            window.clearInterval(pollTimer);
            pollTimer = null;
        }

        setConnectionState('Gestoppt');
        log('Pi-Simulator gestoppt.');
    }

    function unlockAudio() {
        const buzzer = document.getElementById('sound-door-buzzer');
        const click = document.getElementById('sound-door-click');

        [buzzer, click].forEach((audio) => {
            if (!audio) {
                return;
            }

            const p = audio.play();

            if (p) {
                p.then(() => {
                    audio.pause();
                    audio.currentTime = 0;
                }).catch(() => {});
            }
        });

        soundEnabled = true;

        const button = document.getElementById('enable-sound');
        if (button) {
            button.textContent = 'Ton aktiviert';
            button.disabled = true;
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (elClearLog) {
            elClearLog.addEventListener('click', function () {
                elLog.innerHTML = '';
            });
        }

        const button = document.getElementById('enable-sound');
        if (button) {
            button.addEventListener('click', unlockAudio);
        }

        document.body.addEventListener('click', unlockAudio, { once: true });

        startPolling();

        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                stopPolling();
            } else {
                startPolling();
            }
        });

        window.addEventListener('beforeunload', function () {
            stopPolling();
        });
    });
})();
