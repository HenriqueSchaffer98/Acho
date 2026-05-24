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
import { useAuth } from '@/hooks/useAuth';

const schema = z.object({
    email: z.string().min(1, 'Informe seu e-mail').email('E-mail inválido'),
    password: z.string().min(1, 'Informe sua senha'),
});

type LoginForm = z.infer<typeof schema>;

interface ApiError {
    message?: string;
}

export default function Login() {
    const { login } = useAuth();
    const [generalError, setGeneralError] = useState<string | null>(null);

    const {
        register,
        handleSubmit,
        formState: { errors, isSubmitting },
    } = useForm<LoginForm>({
        resolver: zodResolver(schema),
        defaultValues: { email: '', password: '' },
    });

    const onSubmit = handleSubmit(async (values) => {
        setGeneralError(null);
        try {
            await login(values);
            router.visit('/');
        } catch (error) {
            if (axios.isAxiosError(error)) {
                const data = (error as AxiosError<ApiError>).response?.data;
                setGeneralError(data?.message ?? 'Não foi possível entrar. Tente novamente.');
            } else {
                setGeneralError('Erro inesperado. Tente novamente.');
            }
        }
    });

    return (
        <AuthLayout
            title="Entrar"
            subtitle="Acesse sua conta para gerenciar o catálogo, agendamentos e contatos."
            footer={
                <p>
                    Ainda não tem conta?{' '}
                    <Link href="/auth/cadastro" className="text-sky-600 hover:underline">
                        Cadastre-se
                    </Link>
                </p>
            }
        >
            <form className="space-y-4" onSubmit={onSubmit} noValidate>
                <FormError message={generalError} />

                <TextField
                    label="E-mail"
                    type="email"
                    autoComplete="email"
                    autoFocus
                    error={errors.email?.message}
                    {...register('email')}
                />

                <TextField
                    label="Senha"
                    type="password"
                    autoComplete="current-password"
                    error={errors.password?.message}
                    {...register('password')}
                />

                <div className="text-right text-sm">
                    <Link href="/auth/esqueci-senha" className="text-sky-600 hover:underline">
                        Esqueci minha senha
                    </Link>
                </div>

                <SubmitButton loading={isSubmitting}>Entrar</SubmitButton>
            </form>
        </AuthLayout>
    );
}
