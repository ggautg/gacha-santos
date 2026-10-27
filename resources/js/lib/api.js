export class ApiError extends Error {
    constructor(message, errors = {}, status = 0) {
        super(message);
        this.name = 'ApiError';
        this.errors = errors;
        this.status = status;
    }

    firstError() {
        const first = Object.values(this.errors ?? {})[0];

        return Array.isArray(first) ? first[0] : this.message;
    }
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

async function request(url, { method = 'POST', data = null } = {}) {
    let response;

    try {
        response = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: data === null ? undefined : JSON.stringify(data),
        });
    } catch {
        throw new ApiError('No se pudo conectar con el santuario. Revisa tu conexión.');
    }

    const payload = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new ApiError(
            payload.message || 'El cielo guardó silencio. Inténtalo de nuevo.',
            payload.errors ?? {},
            response.status,
        );
    }

    return payload;
}

export function post(url, data = {}) {
    return request(url, { method: 'POST', data });
}

export function get(url) {
    return request(url, { method: 'GET' });
}
