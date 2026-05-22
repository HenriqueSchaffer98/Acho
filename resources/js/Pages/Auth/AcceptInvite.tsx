import { useEffect, useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import axios, { type AxiosError } from 'axios';
import { AuthLayout } from '@/Layouts/AuthLayout';
import { TextField } from '@/Components/forms/TextField';
import { SubmitButton } from '@/Components/forms/SubmitButton';
import { FormError } from '@/Components/forms/FormError';
import { api, setAccessToken } from '@/lib/axios';

const schema = z
    .object({
        name: z.string().min(1, 'Informe seu nome').max(120, 'Nome muito longo'),
        password: z
            .string()
            .min(8, 'A senha precisa ter pelo menos 8 caracteres')
            .regex(/[A-Za-z]/, 'A senha precisa ter pelo menos uma letra')
            .regex(/\d/, 'A senha precisa ter pelo menos um número'),
        password_confirmation: z.string().min(1, 'Confirme a senha'),
    })
    .refine((data) => data.password === data.password_confirmation, {
        path: ['password_confirmation'],
        message: 'As senhas não conferem',
    });

type InviteForm = z.infer<typeof schema>;

interface ApiError {
    message?: string;
}

interface InviteResponse {
    access_token: string;
}

export default function AcceptInvite() {
    const [token, setToken] = useState<string | null>(null);
    const [generalError, setGeneralError] = useState<string | null>(null);

    useEffect(() => {
        const params = new URLSearchParams(window.location.search);
        const queryToken = params.get('token');
        if (queryToken === null || queryToken === '') {
            setGeneralError('Convite inválido ou expirado.');
        } else {
            setToken(queryToken);
        }
    }, []);

    const {
        register,
        handleSubmit,
        formState: { errors, isSubmitting },
    } = useForm<InviteForm>({
        resolver: zodResolver(schema),
        defaultValues: { name: '', password: '', password_confirmation: '' },
    });

    const onSubmit = handleSubmit(async (values) => {
        if (token === null) {
            return;
        }
        setGeneralError(null);
        try {
            const { data } = await api.post<InviteResponse>('/auth/convite/aceitar', {
                token,
                ...values,
            });
            setAccessToken(data.access_token);
            router.visit('/');
        } catch (error) {
            if (axios.isAxiosError(error)) {
                const data = (error as AxiosError<ApiError>).response?.data;
                setGeneralError(data?.message ?? 'Não foi possível aceitar o convite.');
            } else {
                setGeneralError('Erro inesperado. Tente novamente.');
            }
        }
    });

    return (
        <AuthLayout
            title="Aceitar convite"
            subtitle="Configure seu acesso de corretor escolhendo seu nome de exibição e uma senha."
            footer={
                <Link href="/auth/login" className="text-sky-600 hover:underline">
                    Voltar ao login
                </Link>
            }
        >
            <form className="space-y-4" onSubmit={onSubmit} noValidate>
                <FormError message={generalError} />

                <TextField
                    label="Nome completo"
                    type="text"
                    autoComplete="name"
                    autoFocus
                    error={errors.name?.message}
                    {...register('name')}
                />

                <TextField
                    label="Senha"
                    type="password"
                    autoComplete="new-password"
                    error={errors.password?.message}
                    {...register('password')}
                />

                <TextField
                    label="Confirme a senha"
                    type="password"
                    autoComplete="new-password"
                    error={errors.password_confirmation?.message}
                    {...register('password_confirmation')}
                />

                <SubmitButton loading={isSubmitting} disabled={token === null}>
                    Concluir cadastro
                </SubmitButton>
            </form>
        </AuthLayout>
    );
}
