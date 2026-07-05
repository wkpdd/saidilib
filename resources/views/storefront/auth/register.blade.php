@extends('layouts.app')
@section('title', __('shop.register'))

@section('content')
<section class="container-x max-w-md py-12">
    <div class="card p-6 sm:p-8">
        <h1 class="font-display text-2xl font-bold">{{ __('shop.register') }}</h1>
        <p class="mt-1 text-sm text-slate-500">Suivez vos commandes et votre solde.</p>

        @if ($errors->any())
            <div class="mt-4 rounded-xl bg-red-50 p-3 text-sm text-red-700">
                <ul class="list-disc ps-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <form action="{{ route('account.register.post') }}" method="post" class="mt-6">
            @csrf
            <label class="label">{{ __('shop.full_name') }}</label>
            <input name="name" value="{{ old('name') }}" required autofocus class="input mb-4">

            <label class="label">{{ __('shop.email') }}</label>
            <input name="email" type="email" value="{{ old('email') }}" required class="input mb-4">

            <label class="label">{{ __('shop.phone') }}</label>
            <input name="phone" value="{{ old('phone') }}" required class="input mb-4">

            <label class="label">{{ __('shop.wilaya') }}</label>
            <select name="wilaya_id" class="input mb-4">
                <option value="">—</option>
                @foreach ($wilayas as $w)
                    <option value="{{ $w->id }}" @selected(old('wilaya_id')==$w->id)>{{ $w->code }} · {{ $w->name_fr ?? $w->name }}</option>
                @endforeach
            </select>

            <label class="label">{{ __('shop.password') }}</label>
            <input name="password" type="password" required class="input mb-4">

            <label class="label">{{ __('shop.confirm_password') }}</label>
            <input name="password_confirmation" type="password" required class="input mb-6">

            <button class="btn-primary w-full">{{ __('shop.register') }}</button>
        </form>

        <p class="mt-4 text-center text-sm text-slate-500">
            {{ __('shop.have_account') }}
            <a href="{{ route('account.login') }}" class="font-semibold text-brand-700 hover:underline">{{ __('shop.login') }}</a>
        </p>
    </div>
</section>
@endsection
