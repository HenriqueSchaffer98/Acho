import { Head, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

interface TenantProps {
    id: string;
    slug: string;
    name: string;
}

interface SharedProps {
    tenant: TenantProps | null;
    [key: string]: unknown;
}

interface AuthLayoutProps {
    title: string;
    subtitle?: string;
    children: ReactNode;
    footer?: ReactNode;
}

export function AuthLayout({ title, subtitle, children, footer }: AuthLayoutProps) {
    const { props } = usePage<SharedProps>();
    const tenantName = props.tenant?.name ?? 'Acho';

    return (
        <>
            <Head title={title} />

            <div className="flex min-h-screen flex-col bg-gradient-to-b from-slate-50 to-slate-100">
                <header className="px-6 py-5">
                    <h1 className="text-xl font-semibold text-slate-800">{tenantName}</h1>
                </header>

                <main className="flex flex-1 items-center justify-center px-4 pb-12">
                    <div className="w-full max-w-md rounded-2xl bg-white p-8 shadow-sm ring-1 ring-slate-200">
                        <div className="mb-6">
                            <h2 className="text-2xl font-semibold text-slate-900">{title}</h2>
                            {subtitle ? <p className="mt-1 text-sm text-slate-500">{subtitle}</p> : null}
                        </div>

                        {children}

                        {footer ? (
                            <div className="mt-6 border-t border-slate-200 pt-4 text-center text-sm text-slate-500">
                                {footer}
                            </div>
                        ) : null}
                    </div>
                </main>

                <footer className="px-6 py-4 text-center text-xs text-slate-400">
                    © {new Date().getFullYear()} {tenantName}. Powered by Acho.
                </footer>
            </div>
        </>
    );
}
