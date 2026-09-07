import { Head, useForm } from '@inertiajs/react';

export default function Login() {
    // useForm memakai fetch/XHR Inertia, yang otomatis mengirim header
    // X-XSRF-TOKEN dari cookie XSRF-TOKEN -- tidak perlu @csrf manual.
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    function submit(event) {
        event.preventDefault();
        post('/login', { onFinish: () => setData('password', '') });
    }

    return (
        <>
            <Head title="Masuk" />

            <main className="mx-auto flex min-h-screen max-w-sm flex-col justify-center gap-6 px-6">
                <h1 className="text-xl font-semibold">Masuk</h1>

                <form onSubmit={submit} className="flex flex-col gap-4">
                    <label className="flex flex-col gap-1">
                        <span className="text-sm font-medium">Email</span>
                        <input
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            autoComplete="username"
                            autoFocus
                            className="rounded border border-neutral-300 px-3 py-2 text-sm"
                        />
                        {errors.email && <span className="text-sm text-red-600">{errors.email}</span>}
                    </label>

                    <label className="flex flex-col gap-1">
                        <span className="text-sm font-medium">Kata sandi</span>
                        <input
                            type="password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            autoComplete="current-password"
                            className="rounded border border-neutral-300 px-3 py-2 text-sm"
                        />
                        {errors.password && (
                            <span className="text-sm text-red-600">{errors.password}</span>
                        )}
                    </label>

                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={data.remember}
                            onChange={(e) => setData('remember', e.target.checked)}
                        />
                        Ingat saya
                    </label>

                    <button
                        type="submit"
                        disabled={processing}
                        className="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                    >
                        {processing ? 'Memproses…' : 'Masuk'}
                    </button>
                </form>
            </main>
        </>
    );
}
