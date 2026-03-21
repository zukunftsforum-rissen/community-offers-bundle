export async function whoami() {
    const response = await fetch('/api/door/whoami', {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
    });

    if (!response.ok) {
        return { authenticated: false, areas: [] };
    }

    const data = await response.json().catch(() => ({}));

    if (!data || typeof data !== 'object') {
        return { authenticated: false, areas: [] };
    }

    data.authenticated = !!data.authenticated;
    data.areas = Array.isArray(data.areas) ? data.areas : [];

    return data;
}

export async function requestAccess(slug) {
    const response = await fetch('/api/door/request/' + encodeURIComponent(slug), {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
    });

    const body = await response.json().catch(() => ({}));

    return {
        status: response.status,
        success: !!body.success,
        message: body.message,
        retryAfterSeconds: body.retryAfterSeconds,
    };
}

export async function openDoor(slug) {
    const response = await fetch('/api/door/open/' + encodeURIComponent(slug), {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
    });

    const body = await response.json().catch(() => ({}));

    return {
        status: response.status,
        success: !!body.success,
        message: body.message,
        retryAfterSeconds: body.retryAfterSeconds,
        door: body.door,
        jobId: body.jobId,
        accepted: body.accepted,
        mode: body.mode,
        jobStatus: body.status,
        expiresAt: body.expiresAt,
        correlationId: body.correlationId,
    };
}
