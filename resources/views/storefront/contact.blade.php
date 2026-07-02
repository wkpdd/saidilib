@extends('layouts.app')
@section('title', __('shop.contact'))

@section('content')
@php use App\Models\Setting; @endphp
<div class="container-x py-12">
    <div class="mx-auto max-w-2xl text-center">
        <h1 class="font-display text-3xl font-bold">{{ __('shop.contact') }}</h1>
        <p class="mt-2 text-slate-500">{{ Setting::get('hours') }}</p>
    </div>
    <div class="mx-auto mt-8 grid max-w-2xl gap-4 sm:grid-cols-2">
        <div class="card p-6">
            <h3 class="font-semibold">📞 {{ __('shop.phone') }}</h3>
            <p class="mt-1 text-ink-700">{{ Setting::get('phone') }}</p>
        </div>
        <div class="card p-6">
            <h3 class="font-semibold">✉️ Email</h3>
            <p class="mt-1 text-ink-700">{{ Setting::get('email') }}</p>
        </div>
        <div class="card p-6 sm:col-span-2">
            <h3 class="font-semibold">📍 {{ app()->getLocale()==='ar' ? Setting::get('address_ar') : Setting::get('address_fr') }}</h3>
            <div class="mt-3 flex gap-2">
                @if (Setting::get('facebook'))<a href="{{ Setting::get('facebook') }}" class="btn-ghost">Facebook</a>@endif
                @if (Setting::get('instagram'))<a href="{{ Setting::get('instagram') }}" class="btn-ghost">Instagram</a>@endif
            </div>
        </div>
    </div>
</div>
@endsection
