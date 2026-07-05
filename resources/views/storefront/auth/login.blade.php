@extends('layouts.app')
@section('title', __('shop.login'))

@section('content')
<section class="container-x max-w-md py-12">
    <div class="card p-6 sm:p-8">
        <h1 class="font-display text-2xl font-bold">{{ __('shop.login') }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ __('shop.account') }} — Saidi Papetrie</p>

        @if ($errors->any())
            <div class="mt-4 rounded-xl bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

        <form action="{{ route('account.login.post') }}" method="post" class="mt-6">
            @csrf
            <label class="label">{{ __('shop.email') }}</label>
            <input name="email" type="email" value="{{ old('email') }}" required autofocus class="input mb-4">
            <label class="label">{{ __('shop.password') }}</label>
            <input name="password" type="password" required class="input mb-4">
            <label class="mb-4 flex items-center gap-2 text-sm text-ink-700">
                <input type="checkbox" name="remember" class="rounded border-slate-300"> {{ __('shop.remember_me') }}
            </label>
            <button class="btn-primary w-full">{{ __('shop.sign_in') }}</button>
        </form>

        <p class="mt-4 text-center text-sm text-slate-500">
            {{ __('shop.no_account') }}
            <a href="{{ route('account.register') }}" class="font-semibold text-brand-700 hover:underline">{{ __('shop.register') }}</a>
        </p>
    </div>
</section>
@endsection
