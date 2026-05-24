import { forwardRef, type InputHTMLAttributes } from 'react';

interface TextFieldProps extends InputHTMLAttributes<HTMLInputElement> {
    label: string;
    error?: string;
}

export const TextField = forwardRef<HTMLInputElement, TextFieldProps>(
    ({ label, error, id, className, ...inputProps }, ref) => {
        const inputId = id ?? `field-${label.toLowerCase().replace(/\s+/g, '-')}`;

        return (
            <div className="space-y-1">
                <label htmlFor={inputId} className="block text-sm font-medium text-slate-700">
                    {label}
                </label>
                <input
                    ref={ref}
                    id={inputId}
                    className={
                        'w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm transition focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200 ' +
                        (error ? 'border-red-400 focus:border-red-500 focus:ring-red-200 ' : '') +
                        (className ?? '')
                    }
                    aria-invalid={error ? 'true' : 'false'}
                    aria-describedby={error ? `${inputId}-error` : undefined}
                    {...inputProps}
                />
                {error ? (
                    <p id={`${inputId}-error`} className="text-xs text-red-600">
                        {error}
                    </p>
                ) : null}
            </div>
        );
    },
);

TextField.displayName = 'TextField';
