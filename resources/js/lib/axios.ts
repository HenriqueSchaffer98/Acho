import axios, { AxiosError, type AxiosInstance, type AxiosRequestConfig } from 'axios';

/**
 * Authenticated HTTP client (ADR-014).
 *
 * - Stores `access_token` ONLY in memory (never in localStorage, per AC-12).
 * - Sends the `refresh_token` cookie automatically (`withCredentials`).
 * - On 401, attempts `/auth/refresh` exactly once; if it succeeds, retries
 *   the original request. Concurrent 401s share the same refresh attempt
 *   (single-flight queue).
 */

type AccessTokenListener = (token: string | null) => void;

let accessToken: string | null = null;
const listeners = new Set<AccessTokenListener>();

export function getAccessToken(): string | null {
    return accessToken;
}

export function setAccessToken(token: string | null): void {
    accessToken = token;
    listeners.forEach((listener) => listener(token));
}

export function subscribeAccessToken(listener: AccessTokenListener): () => void {
    listeners.add(listener);
    return () => listeners.delete(listener);
}

let refreshPromise: Promise<string | null> | null = null;

async function refreshAccessToken(): Promise<string | null> {
    if (refreshPromise) {
        return refreshPromise;
    }

    refreshPromise = (async () => {
        try {
            const response = await axios.post<{ access_token: string }>('/auth/refresh', {}, { withCredentials: true });
            const newToken = response.data.access_token;
            setAccessToken(newToken);
            return newToken;
        } catch {
            setAccessToken(null);
            return null;
        } finally {
            refreshPromise = null;
        }
    })();

    return refreshPromise;
}

export const api: AxiosInstance = axios.create({
    baseURL: '/',
    withCredentials: true,
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

api.interceptors.request.use((config) => {
    if (accessToken !== null) {
        config.headers.Authorization = `Bearer ${accessToken}`;
    }
    return config;
});

api.interceptors.response.use(
    (response) => response,
    async (error: AxiosError) => {
        const original = error.config as (AxiosRequestConfig & { _retry?: boolean }) | undefined;

        if (
            error.response?.status !== 401 ||
            !original ||
            original._retry === true ||
            original.url === '/auth/refresh' ||
            original.url === '/auth/login'
        ) {
            return Promise.reject(error);
        }

        original._retry = true;

        const newToken = await refreshAccessToken();

        if (newToken === null) {
            return Promise.reject(error);
        }

        original.headers = original.headers ?? {};
        (original.headers as Record<string, string>).Authorization = `Bearer ${newToken}`;

        return api.request(original);
    },
);

export function clearAccessToken(): void {
    setAccessToken(null);
}
