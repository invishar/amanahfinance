import { Head, Link, usePage } from '@inertiajs/react';

export default function Welcome() {
    const { appName, auth } = usePage().props;

    return (
        <>
            <Head title="Beranda" />

            <main className="mx-auto flex min-h-screen max-w-xl flex-col justify-center gap-4 px-6">
                <h1 className="text-2xl font-semibold">{appName}</h1>
                <p className="text-sm text-neutral-600">
                    Halaman ini dirender server lewat Inertia dan dipasang di klien oleh React.
                </p>

                {auth.user ? (
                    <Link
                        href="/dashboard"
                        className="w-fit rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white"
                    >
                        Buka dashboard
                    </Link>
                ) : (
                    <Link
                        href="/login"
                        className="w-fit rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white"
                    >
                        Masuk
                    </Link>
                )}
            </main>
        </>
    );
}
