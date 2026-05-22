import { useState } from 'react';
import { Link } from '@inertiajs/react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import axios, { type AxiosError } from 'axios';
import { AuthLayout } from '@/Layouts/AuthLayout';
import { TextField } from '@/Components/forms/TextField';
import { SubmitButton } from '@/Components/forms/SubmitButton';
import { FormError } from '@/Components/forms/FormError';
import { api } from '@/lib/axios';

const schema = z.object({
    email: z.string().min(1, 'Informe seu e-mail').email('E-mail inválido'),
});

type ForgotForm = z.infer<typeof schema>;

interface ApiError {
    message?: string;
}

export default function ForgotPassword() {
    const [successMessage, setSuccessMessage] = useState<string | null>(null);
    const [generalError, setGeneralError] = useState<string | null>(null);

    const {
        register,
        handleSubmit,
        formState: { errors, isSubmitting },
        reset,
    } = useForm<ForgotForm>({
        resolver: zodResolver(schema),
        defaultValues: { email: '' },
    });

    const onSubmit = handleSubmit(async (values) => {
        setGeneralError(null);
        setSuccessMessage(null);
        try {
            const { data } = await api.post<{ message: string }>('/auth/forgot-password', values);
            setSuccessMessage(data.message);
            reset();
        } catch (error) {
            if (axios.isAxiosError(error)) {
                const data = (error as AxiosError<ApiError>).response?.data;
                setGeneralError(data?.message ?? 'Não foi possível enviar o link. Tente novamente.');
            } else {
                setGeneralError('Erro inesperado. Tente novamente.');
            }
        }
    });

    return (
        <AuthLayout
            title="Recuperar senha"
            subtitle="Vamos enviar um link de redefinição para o seu e-mail."
            footer={
                <p>
                    Lembrou a senha?{' '}
                    <Link href="/auth/login" className="text-sky-600 hover:underline">
                        Voltar ao login
                    </Link>
                </p>
            }
        >
            <form className="space-y-4" onSubmit={onSubmit} noValidate>
                <FormError message={generalError} />

                {successMessage ? (
                    <div
                        role="status"
                        className="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700"
                    >
                        {successMessage}
                    </div>
                ) : null}

                <TextField
                    label="E-mail"
                    type="email"
                    autoComplete="email"
                    autoFocus
                    error={errors.email?.message}
                    {...register('email')}
                />

                <SubmitButton loading={isSubmitting}>Enviar link</SubmitButton>
            </form>
        </AuthLayout>
    );
}
