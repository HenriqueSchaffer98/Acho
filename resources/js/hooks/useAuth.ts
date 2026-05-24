import { useCallback, useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { api, clearAccessToken, setAccessToken } from '@/lib/axios';

export type UserRole = 'admin' | 'corretor' | 'cliente' | 'super_admin';

export interface AuthUser {
    id: string;
    name: string;
    email: string;
    role: UserRole;
}

interface LoginPayload {
    email: string;
    password: string;
}

interface LoginResponse {
    access_token: string;
    user: AuthUser;
}

interface SharedProps {
    auth: {
        user: AuthUser | null;
    };
    [key: string]: unknown;
}

export function useAuth() {
    const { props } = usePage<SharedProps>();
    const sharedUser = props.auth?.user ?? null;

    const [user, setUser] = useState<AuthUser | null>(sharedUser);

    useEffect(() => {
        setUser(sharedUser);
    }, [sharedUser]);

    const login = useCallback(async (payload: LoginPayload): Promise<void> => {
        const { data } = await api.post<LoginResponse>('/auth/login', payload);
        setAccessToken(data.access_token);
        setUser(data.user);
    }, []);

    const logout = useCallback(async (): Promise<void> => {
        try {
            await api.post('/auth/logout');
        } finally {
            clearAccessToken();
            setUser(null);
            router.visit('/auth/login', { replace: true });
        }
    }, []);

    return {
        user,
        isAuthenticated: user !== null,
        login,
        logout,
    };
}
