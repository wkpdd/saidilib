@extends('layouts.app')
@section('title', __('shop.new_password'))

@section('content')
<section class="container-x max-w-md py-12">
    <div class="card p-6 sm:p-8">
        <h1 class="font-display text-2xl font-bold">{{ __('shop.new_password') }}</h1>

        @if ($errors->any())
            <div class="mt-4 rounded-xl bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

        <form action="{{ route('account.password.update') }}" method="post" class="mt-6">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <label class="label">{{ __('shop.email') }}</label>
            <input name="email" type="email" value="{{ old('email', $email) }}" required class="input mb-4">
            <label class="label">{{ __('shop.password') }}</label>
            <input name="password" type="password" required autofocus class="input mb-4">
            <label class="label">{{ __('shop.confirm_password') }}</label>
            <input name="password_confirmation" type="password" required class="input mb-4">
            <button class="btn-primary w-full">{{ __('shop.save_password') }}</button>
        </form>
    </div>
</section>
@endsection
