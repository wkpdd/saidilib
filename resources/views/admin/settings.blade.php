@extends('admin.layout')
@section('title', 'Paramètres')
@section('heading', 'Paramètres de la boutique')

@section('content')
@php $v = fn($k, $d = '') => old($k, optional($settings[$k] ?? null)->value ?? $d); @endphp
<form action="{{ route('admin.settings.update') }}" method="post" enctype="multipart/form-data" class="grid gap-6 lg:grid-cols-2">
    @csrf @method('PATCH')

    <div class="card p-6">
        <h2 class="mb-4 font-semibold">Général</h2>
        <label class="label">Nom de la boutique</label>
        <input name="store_name" value="{{ $v('store_name') }}" class="input mb-4">
        <label class="label">Slogan (FR)</label>
        <input name="tagline_fr" value="{{ $v('tagline_fr') }}" class="input mb-4">
        <label class="label">Slogan (AR)</label>
        <input name="tagline_ar" value="{{ $v('tagline_ar') }}" dir="rtl" class="input mb-4">
        <label class="label">Devise</label>
        <input name="currency" value="{{ $v('currency', 'DA') }}" class="input mb-4">
        <label class="label">Logo</label>
        <input type="file" name="logo" accept="image/*" class="input">
    </div>

    <div class="card p-6">
        <h2 class="mb-4 font-semibold">Bandeau d'annonce</h2>
        <label class="label">Annonce (FR)</label>
        <textarea name="announcement_fr" rows="2" class="input mb-4">{{ $v('announcement_fr') }}</textarea>
        <label class="label">Annonce (AR)</label>
        <textarea name="announcement_ar" rows="2" dir="rtl" class="input">{{ $v('announcement_ar') }}</textarea>
    </div>

    <div class="card p-6">
        <h2 class="mb-4 font-semibold">Contact</h2>
        <label class="label">Téléphone</label>
        <input name="phone" value="{{ $v('phone') }}" class="input mb-4">
        <label class="label">Email</label>
        <input name="email" value="{{ $v('email') }}" class="input mb-4">
        <label class="label">Adresse (FR)</label>
        <input name="address_fr" value="{{ $v('address_fr') }}" class="input mb-4">
        <label class="label">Adresse (AR)</label>
        <input name="address_ar" value="{{ $v('address_ar') }}" dir="rtl" class="input mb-4">
        <label class="label">Horaires</label>
        <input name="hours" value="{{ $v('hours') }}" class="input">
    </div>

    <div class="card p-6">
        <h2 class="mb-4 font-semibold">Réseaux sociaux & SEO</h2>
        <label class="label">Facebook</label>
        <input name="facebook" value="{{ $v('facebook') }}" class="input mb-4">
        <label class="label">Instagram</label>
        <input name="instagram" value="{{ $v('instagram') }}" class="input mb-4">
        <label class="label">TikTok</label>
        <input name="tiktok" value="{{ $v('tiktok') }}" class="input mb-4">
        <label class="label">Titre SEO</label>
        <input name="meta_title" value="{{ $v('meta_title') }}" class="input mb-4">
        <label class="label">Description SEO</label>
        <textarea name="meta_description" rows="2" class="input">{{ $v('meta_description') }}</textarea>
    </div>

    <div class="lg:col-span-2">
        <button class="btn-primary">💾 Enregistrer les paramètres</button>
    </div>
</form>
@endsection
