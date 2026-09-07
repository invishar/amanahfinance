import { Head, router, usePage } from '@inertiajs/react';

export default function Dashboard() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Dashboard" />

            <main className="mx-auto flex min-h-screen max-w-xl flex-col justify-center gap-3 px-6">
                <h1 className="text-2xl font-semibold">Halo, {auth.user.name}</h1>
                <p className="text-sm text-neutral-600">
                    Family aktif:{' '}
                    <strong>
                        {auth.family
                            ? `${auth.family.name} (${auth.family.role})`
                            : 'belum tergabung family manapun'}
                    </strong>
                </p>
                <p className="text-sm text-neutral-600">
                    Sesi ini dipegang cookie, bukan token di localStorage.
                </p>

                <button
                    type="button"
                    onClick={() => router.post('/logout')}
                    className="w-fit rounded border border-neutral-300 px-4 py-2 text-sm font-medium"
                >
                    Keluar
                </button>
            </main>
        </>
    );
}
