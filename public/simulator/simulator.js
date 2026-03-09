(function () {
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
        const runningAreas = new Set();

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

        function playUnlockSound() {
            try {
                const AudioContextClass = window.AudioContext || window.webkitAudioContext;

                if (!AudioContextClass) {
                    return;
                }

                const ctx = new AudioContextClass();
                const oscillator = ctx.createOscillator();
                const gain = ctx.createGain();

                oscillator.type = 'square';
                oscillator.frequency.setValueAtTime(980, ctx.currentTime);
                oscillator.frequency.exponentialRampToValueAtTime(520, ctx.currentTime + 0.07);
                oscillator.frequency.exponentialRampToValueAtTime(360, ctx.currentTime + 0.16);

                gain.gain.setValueAtTime(0.0001, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.08, ctx.currentTime + 0.01);
                gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.2);

                oscillator.connect(gain);
                gain.connect(ctx.destination);

                oscillator.start();
                oscillator.stop(ctx.currentTime + 0.22);
            } catch (error) {
                log('Audio konnte nicht abgespielt werden: ' + error);
            }
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

        function resetDoorAndPerson(door, person) {
            if (door) {
                door.classList.remove('is-unlocking', 'is-open');
                setDoorState(door, 'Bereit');
                door.setAttribute('data-busy', '0');
            }

            if (person) {
                person.classList.remove('is-visible', 'is-walking');
                person.style.transform = '';
            }
        }

        function computePersonTargetX(door) {
            const allDoors = Array.from(door.parentElement.querySelectorAll('.co-door'));
            const index = allDoors.indexOf(door);

            return index === 0 ? 110 : 225;
        }

        async function animateDoor(area, label) {
            const door = getDoorByArea(area);

            if (!door) {
                log('Keine Tür für Area "' + area + '" gefunden.');
                return;
            }

            const person = getPersonForDoor(door);

            if (door.getAttribute('data-busy') === '1') {
                log('Tür "' + label + '" ist bereits in Bewegung.');
                return;
            }

            door.setAttribute('data-busy', '1');
            runningAreas.add(area);

            setLastJob(label);
            setLastStatus('Entriegelung läuft');
            setDoorState(door, 'Entriegelt…');
            door.classList.add('is-unlocking');
            playUnlockSound();
            log('Entriegelung für "' + label + '" gestartet.');

            await sleep(450);

            door.classList.add('is-open');
            setDoorState(door, 'Offen');
            setLastStatus('Tür offen');
            log('Tür "' + label + '" geöffnet.');

            if (person) {
                person.classList.add('is-visible');
                person.style.transform = 'translateX(' + computePersonTargetX(door) + 'px)';
                await sleep(140);
                person.classList.add('is-walking');
            }

            setDoorState(door, 'Betreten…');
            setLastStatus('Männchen tritt ein');
            log('Männchen betritt "' + label + '".');

            await sleep(1200);

            if (person) {
                person.classList.remove('is-walking');
                person.style.opacity = '0';
            }

            setDoorState(door, 'Schließt…');
            setLastStatus('Tür schließt');
            log('Tür "' + label + '" schließt.');

            await sleep(500);

            resetDoorAndPerson(door, person);
            runningAreas.delete(area);
            setLastStatus('Bereit');
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

        function getJobId(job) {
            return job.jobId || job.id || null;
        }

        function getJobNonce(job) {
            return job.nonce || null;
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


        function getJobArea(job) {
            return job.area || job.areaKey || null;
        }

        function getJobId(job) {
            return job.jobId || job.id || null;
        }

        function getJobNonce(job) {
            return job.nonce || null;
        }

        function getJobCorrelationId(job) {
            return job.correlationId || null;
        }

        document.addEventListener('DOMContentLoaded', function () {
            if (elClearLog) {
                elClearLog.addEventListener('click', function () {
                    elLog.innerHTML = '';
                });
            }

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
})();