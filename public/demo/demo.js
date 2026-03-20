(() => {
    const steps = [...document.querySelectorAll(".step")];
    const cursor = document.getElementById("cursor");
    const screen = document.getElementById("screen");
    const simStatus = document.getElementById("sim-status");
    const pauseBtn = document.getElementById("pause-btn");
    const repeatBtn = document.getElementById("repeat-btn");
    const enableSoundBtn = document.getElementById("enable-sound");

    const workshopButton = document.getElementById("btn-open-workshop");
    const swapButton = document.getElementById("btn-open-swap");

    const audioIds = [
        "sound-door-buzzer",
        "sound-door-open",
        "sound-door-close",
    ];

    let soundEnabled = false;
    let paused = false;
    let pauseResolver = null;
    let isRunning = false;
    let runToken = 0;

    function setActiveStep(index) {
        steps.forEach((step, i) => step.classList.toggle("active", i === index));
    }

    function pace(ms) {
        const value = Number(
            getComputedStyle(document.documentElement)
                .getPropertyValue("--speed")
                .trim()
        );

        return ms * (Number.isFinite(value) && value > 0 ? value : 1);
    }

    function cursorYOffset() {
        const raw = getComputedStyle(document.documentElement)
            .getPropertyValue("--cursor-y-offset")
            .trim();

        return Number(raw.replace("px", "")) || 0;
    }

    function placeCursor(el) {
        if (!el || !cursor || !screen) {
            return;
        }

        const screenRect = screen.getBoundingClientRect();
        const rect = el.getBoundingClientRect();
        const x = rect.left - screenRect.left + rect.width / 2 - 13;
        const y = rect.top - screenRect.top + rect.height / 2 - 13 + cursorYOffset();

        cursor.style.left = `${x}px`;
        cursor.style.top = `${y}px`;
    }

    function showCursorAt(el) {
        placeCursor(el);
        cursor?.classList.add("show");
    }

    function hideCursor() {
        cursor?.classList.remove("show", "tap");
    }

    function tapCursor() {
        if (!cursor) {
            return;
        }

        cursor.classList.add("tap");
        window.setTimeout(() => cursor.classList.remove("tap"), 120);
    }

    function pulse(el) {
        if (!el) {
            return;
        }

        el.style.filter = "brightness(1.08)";
        el.style.transform = "scale(0.99)";

        window.setTimeout(() => {
            el.style.filter = "";
            el.style.transform = "";
        }, 180);
    }

    function playSound(id) {
        if (!soundEnabled) {
            return;
        }

        const audio = document.getElementById(id);
        if (!(audio instanceof HTMLMediaElement)) {
            return;
        }

        audio.pause();
        audio.currentTime = 0;

        audio.play().catch((err) => {
            console.log("audio blocked", id, err);
        });
    }

    function primeSounds() {
        soundEnabled = true;

        if (enableSoundBtn) {
            enableSoundBtn.textContent = "Ton aktiv";
            enableSoundBtn.disabled = true;
            enableSoundBtn.style.opacity = ".8";
        }

        audioIds.forEach((id) => {
            const audio = document.getElementById(id);

            if (!(audio instanceof HTMLMediaElement)) {
                return;
            }

            audio.play()
                .then(() => {
                    audio.pause();
                    audio.currentTime = 0;
                })
                .catch((err) => {
                    console.log("enable sound failed", id, err);
                });
        });
    }

    function wait(ms, token) {
        return new Promise((resolve) => {
            let remaining = ms;
            let last = null;

            function tick(now) {
                if (token !== runToken) {
                    resolve();
                    return;
                }

                if (last === null) {
                    last = now;
                }

                const diff = now - last;
                last = now;

                if (!paused) {
                    remaining -= diff;
                }

                if (remaining <= 0) {
                    resolve();
                    return;
                }

                requestAnimationFrame(tick);
            }

            requestAnimationFrame(tick);
        });
    }

    function waitIfPaused(token) {
        if (token !== runToken || !paused) {
            return Promise.resolve();
        }

        return new Promise((resolve) => {
            pauseResolver = () => {
                if (token === runToken) {
                    resolve();
                } else {
                    resolve();
                }
            };
        });
    }

    function setPaused(next) {
        paused = next;

        if (pauseBtn) {
            pauseBtn.textContent = paused ? "Weiter" : "Pause";
        }

        if (!paused && pauseResolver) {
            const resolver = pauseResolver;
            pauseResolver = null;
            resolver();
        }
    }

    function getDoor(area) {
        return document.querySelector(`.door[data-area="${area}"]`);
    }

    function getPersonForDoor(door) {
        const shedKey = door?.dataset?.shedKey;
        return shedKey ? document.querySelector(`.person[data-person-for="${shedKey}"]`) : null;
    }

    function setDoorState(door, text) {
        const el = door?.querySelector(".door__state");

        if (el) {
            el.textContent = text;
        }
    }

    function resetDoor(door, person) {
        if (!door) {
            return;
        }

        door.classList.remove("is-unlocking", "is-open");

        if (person) {
            person.classList.remove("is-visible", "is-hidden", "is-waiting", "is-pushing", "is-entering");
            person.style.transform = "";
        }
    }

    function resetPresentationState() {
        paused = false;
        pauseResolver = null;

        if (pauseBtn) {
            pauseBtn.textContent = "Pause";
            pauseBtn.style.display = "inline-block";
        }

        repeatBtn?.classList.remove("show");

        setActiveStep(0);
        hideCursor();

        if (simStatus) {
            simStatus.textContent = "Bereit.";
        }

        if (enableSoundBtn) {
            enableSoundBtn.textContent = soundEnabled ? "Ton aktiv" : "Ton aktivieren";
            enableSoundBtn.disabled = soundEnabled;
            enableSoundBtn.style.opacity = soundEnabled ? ".8" : "";
        }

        [workshopButton, swapButton].forEach((btn) => btn?.classList.remove("opened"));

        document.querySelectorAll(".door").forEach((door) => {
            const area = door.dataset.area;
            const person = getPersonForDoor(door);

            resetDoor(door, person);

            if (area === "depot") setDoorState(door, "Gesperrt");
            if (area === "swap-house") setDoorState(door, "Bereit");
            if (area === "workshop") setDoorState(door, "Bereit");
            if (area === "sharing") setDoorState(door, "Später");
        });
    }

    async function playDoorAnimation(area, token) {
        const door = getDoor(area);

        if (!door || token !== runToken) {
            return;
        }

        const person = getPersonForDoor(door);

        resetDoor(door, person);
        void door.offsetWidth;

        simStatus.textContent = `${door.dataset.label} wird entriegelt …`;
        setDoorState(door, "Entriegelt …");
        door.classList.add("is-unlocking");
        playSound("sound-door-buzzer");

        await wait(pace(240), token);
        await waitIfPaused(token);
        if (token !== runToken) return;

        door.classList.remove("is-unlocking");
        door.classList.add("is-open");
        setDoorState(door, "Öffnet …");
        simStatus.textContent = `${door.dataset.label} öffnet …`;
        playSound("sound-door-open");

        if (person) {
            person.classList.add("is-visible", "is-waiting");
        }

        await wait(pace(200), token);
        await waitIfPaused(token);
        if (token !== runToken) return;

        if (person) {
            person.classList.add("is-pushing");
        }

        await wait(pace(260), token);
        await waitIfPaused(token);
        if (token !== runToken) return;

        if (person) {
            person.classList.remove("is-waiting", "is-pushing");
            person.classList.add("is-entering");
            person.style.transform = area === "swap-house" ? "translateX(66px)" : "translateX(72px)";
        }

        await wait(pace(500), token);
        await waitIfPaused(token);
        if (token !== runToken) return;

        if (person) {
            person.classList.remove("is-visible", "is-entering");
            person.classList.add("is-hidden");
        }

        await wait(pace(220), token);
        await waitIfPaused(token);
        if (token !== runToken) return;

        door.classList.remove("is-open");
        setDoorState(door, "Schließt …");
        simStatus.textContent = `${door.dataset.label} schließt …`;
        playSound("sound-door-close");

        await wait(pace(420), token);
        await waitIfPaused(token);
        if (token !== runToken) return;

        resetDoor(door, person);
        setDoorState(door, "Demo abgeschlossen.");
        simStatus.textContent = `${door.dataset.label} abgeschlossen.`;
    }

    async function clickElement(el, token) {
        if (!el || token !== runToken) {
            return;
        }

        showCursorAt(el);
        await wait(pace(420), token);
        await waitIfPaused(token);
        if (token !== runToken) return;

        placeCursor(el);
        tapCursor();
        pulse(el);

        await wait(pace(220), token);
        await waitIfPaused(token);
    }

    async function runSequence() {
        if (isRunning) {
            return;
        }

        isRunning = true;
        runToken += 1;
        const token = runToken;

        resetPresentationState();

        await clickElement(enableSoundBtn, token);
        if (!soundEnabled) {
            primeSounds();
        }

        await wait(pace(500), token);
        await waitIfPaused(token);
        if (token !== runToken) return;

        await clickElement(document.getElementById("link-request-first"), token);
        setActiveStep(1);

        await wait(pace(750), token);
        await waitIfPaused(token);
        if (token !== runToken) return;

        await clickElement(document.getElementById("btn-submit-request"), token);
        setActiveStep(2);

        await wait(pace(1200), token);
        await waitIfPaused(token);
        if (token !== runToken) return;

        setActiveStep(3);

        await wait(pace(900), token);
        await waitIfPaused(token);
        if (token !== runToken) return;

        await clickElement(document.getElementById("btn-mail-open-app"), token);
        setActiveStep(4);

        await wait(pace(900), token);
        await waitIfPaused(token);
        if (token !== runToken) return;

        await clickElement(document.getElementById("link-open-reset"), token);
        setActiveStep(5);

        await wait(pace(900), token);
        await waitIfPaused(token);
        if (token !== runToken) return;

        await clickElement(document.getElementById("btn-send-reset-mail"), token);
        setActiveStep(6);

        await wait(pace(900), token);
        await waitIfPaused(token);
        if (token !== runToken) return;

        await clickElement(document.getElementById("btn-mail-open-password"), token);
        setActiveStep(7);

        await wait(pace(900), token);
        await waitIfPaused(token);
        if (token !== runToken) return;

        await clickElement(document.getElementById("btn-final-login"), token);
        setActiveStep(8);

        await wait(pace(900), token);
        await waitIfPaused(token);
        if (token !== runToken) return;

        await clickElement(workshopButton, token);
        workshopButton?.classList.add("opened");
        await playDoorAnimation("workshop", token);

        await wait(pace(700), token);
        await waitIfPaused(token);
        if (token !== runToken) return;

        await clickElement(swapButton, token);
        swapButton?.classList.add("opened");
        await playDoorAnimation("swap-house", token);

        await wait(pace(900), token);
        await waitIfPaused(token);
        if (token !== runToken) return;

        hideCursor();
        simStatus.textContent = "Ablauf beendet.";

        if (pauseBtn) {
            pauseBtn.style.display = "none";
        }

        repeatBtn?.classList.add("show");
        isRunning = false;
    }

    function restartSequence() {
        if (isRunning) {
            runToken += 1;
            isRunning = false;
        }

        runSequence();
    }

    pauseBtn?.addEventListener("click", () => {
        if (isRunning) {
            setPaused(!paused);
        }
    });

    repeatBtn?.addEventListener("click", () => {
        restartSequence();
    });

    enableSoundBtn?.addEventListener("click", () => {
        if (!soundEnabled) {
            primeSounds();
        }
    });

    runSequence();
})();
