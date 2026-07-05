<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Administration — {{ \App\Models\Setting::get('store_name', 'Saidi Papetrie') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="grid min-h-screen place-items-center bg-gradient-to-br from-brand-700 to-brand-900 p-4">
    <div class="w-full max-w-sm">
        <div class="mb-6 text-center">
            <div class="mx-auto w-fit rounded-2xl bg-white p-3 shadow-soft">
                <img src="{{ asset('logov2.jpeg') }}" alt="{{ \App\Models\Setting::get('store_name', 'Saidi Papetrie') }}" class="h-20 w-auto">
            </div>
            <p class="mt-3 text-sm text-brand-100">Espace administrateur</p>
        </div>
        <form action="{{ route('admin.login.post') }}" method="post" class="card p-6">
            @csrf
            @if ($errors->any())
                <div class="mb-4 rounded-xl bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>
            @endif
            <label class="label">Email</label>
            <input name="email" type="email" value="{{ old('email') }}" required autofocus class="input mb-4">
            <label class="label">Mot de passe</label>
            <input name="password" type="password" required class="input mb-4">
            <label class="mb-4 flex items-center gap-2 text-sm text-ink-700">
                <input type="checkbox" name="remember" class="rounded border-slate-300"> Se souvenir de moi
            </label>
            <button class="btn-primary w-full">Se connecter</button>
        </form>
        <p class="mt-4 text-center text-xs text-brand-100">admin@saidi-papetrie.dz / password</p>
    </div>
</body>
</html>
