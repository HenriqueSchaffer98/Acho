import { useState } from 'react';
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
        email: z.string().min(1, 'Informe seu e-mail').email('E-mail inválido'),
        password: z
            .string()
            .min(8, 'A senha precisa ter pelo menos 8 caracteres')
            .regex(/[A-Za-z]/, 'A senha precisa ter pelo menos uma letra')
            .regex(/\d/, 'A senha precisa ter pelo menos um número'),
        password_confirmation: z.string().min(1, 'Confirme a senha'),
        terms: z.literal(true, { message: 'Você precisa aceitar os termos.' }),
    })
    .refine((data) => data.password === data.password_confirmation, {
        path: ['password_confirmation'],
        message: 'As senhas não conferem',
    });

type RegisterForm = z.infer<typeof schema>;

interface ApiError {
    message?: string;
    errors?: Record<string, string[]>;
}

interface RegisterResponse {
    access_token: string;
}

export default function Register() {
    const [generalError, setGeneralError] = useState<string | null>(null);

    const {
        register,
        handleSubmit,
        formState: { errors, isSubmitting },
        setError,
    } = useForm<RegisterForm>({
        resolver: zodResolver(schema),
        defaultValues: {
            name: '',
            email: '',
            password: '',
            password_confirmation: '',
            terms: false as unknown as true,
        },
    });

    const onSubmit = handleSubmit(async (values) => {
        setGeneralError(null);
        try {
            const { data } = await api.post<RegisterResponse>('/auth/register', values);
            setAccessToken(data.access_token);
            router.visit('/');
        } catch (error) {
            if (axios.isAxiosError(error)) {
                const data = (error as AxiosError<ApiError>).response?.data;
                if (data?.errors) {
                    Object.entries(data.errors).forEach(([field, messages]) => {
                        setError(field as keyof RegisterForm, { message: messages[0] });
                    });
                } else {
                    setGeneralError(data?.message ?? 'Não foi possível concluir o cadastro.');
                }
            } else {
                setGeneralError('Erro inesperado. Tente novamente.');
            }
        }
    });

    return (
        <AuthLayout
            title="Criar conta"
            subtitle="Cadastre-se gratuitamente para agendar visitas e acompanhar imóveis."
            footer={
                <p>
                    Já tem uma conta?{' '}
                    <Link href="/auth/login" className="text-sky-600 hover:underline">
                        Entrar
                    </Link>
                </p>
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
                    label="E-mail"
                    type="email"
                    autoComplete="email"
                    error={errors.email?.message}
                    {...register('email')}
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

                <label className="flex items-start gap-2 text-sm text-slate-700">
                    <input
                        type="checkbox"
                        className="mt-0.5 h-4 w-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500"
                        {...register('terms')}
                    />
                    <span>
                        Concordo com os{' '}
                        <Link href="/termos" className="text-sky-600 hover:underline">
                            Termos de Uso
                        </Link>{' '}
                        e a{' '}
                        <Link href="/privacidade" className="text-sky-600 hover:underline">
                            Política de Privacidade
                        </Link>
                        .
                    </span>
                </label>
                {errors.terms ? <p className="text-xs text-red-600">{errors.terms.message}</p> : null}

                <SubmitButton loading={isSubmitting}>Criar conta</SubmitButton>
            </form>
        </AuthLayout>
    );
}
