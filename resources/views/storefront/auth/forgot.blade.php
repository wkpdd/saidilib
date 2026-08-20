@extends('layouts.app')
@section('title', __('shop.forgot_password'))

@section('content')
<section class="container-x max-w-md py-12">
    <div class="card p-6 sm:p-8">
        <h1 class="font-display text-2xl font-bold">{{ __('shop.forgot_password') }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ __('shop.forgot_password_hint') }}</p>

        @if ($errors->any())
            <div class="mt-4 rounded-xl bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

        <form action="{{ route('account.password.email') }}" method="post" class="mt-6">
            @csrf
            <label class="label">{{ __('shop.email') }}</label>
            <input name="email" type="email" value="{{ old('email') }}" required autofocus class="input mb-4">
            <button class="btn-primary w-full">{{ __('shop.send_reset_link') }}</button>
        </form>

        <p class="mt-4 text-center text-sm text-slate-500">
            <a href="{{ route('account.login') }}" class="font-semibold text-brand-700 hover:underline">← {{ __('shop.login') }}</a>
        </p>
    </div>
</section>
@endsection
