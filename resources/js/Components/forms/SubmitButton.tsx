import type { ButtonHTMLAttributes, ReactNode } from 'react';

interface SubmitButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
    loading?: boolean;
    children: ReactNode;
}

export function SubmitButton({ loading = false, disabled, children, ...rest }: SubmitButtonProps) {
    return (
        <button
            type="submit"
            disabled={loading || disabled}
            className="w-full rounded-lg bg-sky-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-sky-300 disabled:cursor-not-allowed disabled:bg-slate-300"
            {...rest}
        >
            {loading ? 'Aguarde…' : children}
        </button>
    );
}
