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

    {{-- Telegram notifications --}}
    <div class="card p-6">
        <h2 class="mb-1 font-semibold">🔔 Notifications Telegram</h2>
        <p class="mb-4 text-xs text-slate-400">Recevez chaque nouvelle commande sur Telegram. Créez un bot via <b>@BotFather</b>, collez son token, puis ajoutez un ou plusieurs chat IDs (un par ligne ou séparés par une virgule).</p>
        <label class="label">Token du bot</label>
        <input name="telegram_bot_token" value="{{ $v('telegram_bot_token') }}" class="input mb-4" placeholder="123456:ABC-DEF...">
        <label class="label">Chat IDs des destinataires</label>
        <textarea name="telegram_chat_ids" rows="3" class="input" placeholder="Ex : 123456789, -1001234567890">{{ $v('telegram_chat_ids') }}</textarea>
        <p class="mt-1 text-xs text-slate-400">Plusieurs destinataires possibles (personnes ou groupes/canaux).</p>
    </div>

    {{-- Social media publishing (real posts) --}}
    <div class="card p-6 lg:col-span-2">
        <h2 class="mb-1 font-semibold">📣 Publication réseaux sociaux (posts réels)</h2>
        <p class="mb-4 text-xs text-slate-400">Permet de publier un produit directement comme <b>post</b> (image + texte). Nécessite des comptes <b>professionnels</b>. Les images doivent être accessibles publiquement (site en ligne).</p>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="label">Facebook — Page ID</label>
                <input name="fb_page_id" value="{{ $v('fb_page_id') }}" class="input mb-3" placeholder="1234567890">
                <label class="label">Facebook — Page Access Token</label>
                <input name="fb_page_token" value="{{ $v('fb_page_token') }}" class="input" placeholder="EAAB...">
            </div>
            <div>
                <label class="label">Instagram — Business Account ID</label>
                <input name="ig_user_id" value="{{ $v('ig_user_id') }}" class="input mb-3" placeholder="17841400000000000">
                <label class="label">Instagram — Access Token <span class="text-slate-400">(sinon celui de la page FB)</span></label>
                <input name="ig_token" value="{{ $v('ig_token') }}" class="input" placeholder="Optionnel">
            </div>
        </div>
        <label class="label mt-3">Telegram — Canal (chat_id ou @canal)</label>
        <input name="telegram_channel_id" value="{{ $v('telegram_channel_id') }}" class="input" placeholder="@saidipapetrie ou -1001234567890">
        <input type="hidden" name="fb_graph_version" value="{{ $v('fb_graph_version', 'v19.0') }}">
        <p class="mt-2 text-xs text-slate-400">La publication utilise l'API Graph (Facebook/Instagram) et le bot Telegram configuré ci-dessus.</p>
    </div>

    {{-- Noest delivery --}}
    <div class="card p-6">
        <h2 class="mb-1 font-semibold">🚚 Livraison Noest (API)</h2>
        <p class="mb-4 text-xs text-slate-400">Identifiants fournis par Noest à la création de votre compte.</p>
        <label class="label">API Token</label>
        <input name="noest_token" value="{{ $v('noest_token') }}" class="input mb-4" placeholder="Bearer token">
        <label class="label">GUID (user_guid)</label>
        <input name="noest_guid" value="{{ $v('noest_guid') }}" class="input mb-4" placeholder="abc123-def456-...">
        <label class="label">Code station (stop desk par défaut)</label>
        <input name="noest_station_code" value="{{ $v('noest_station_code') }}" class="input mb-4" placeholder="Optionnel">
        <label class="flex items-center gap-2 text-sm">
            <input type="hidden" name="noest_enabled" value="0">
            <input type="checkbox" name="noest_enabled" value="1" @checked($v('noest_enabled')==='1') class="rounded"> Activer l'expédition via Noest
        </label>
    </div>

    <div class="lg:col-span-2">
        <button class="btn-primary">💾 Enregistrer les paramètres</button>
    </div>
</form>

{{-- Telegram test (separate form) --}}
<form action="{{ route('admin.settings.telegram.test') }}" method="post" class="lg:col-span-2">
    @csrf
    <button class="btn-ghost">📨 Envoyer un test Telegram</button>
    <span class="ms-2 text-xs text-slate-400">Enregistrez d'abord vos paramètres.</span>
</form>

{{-- Danger zone — reset test data --}}
<div class="mt-8 rounded-2xl border-2 border-red-200 bg-red-50/50 p-6">
    <h2 class="font-display text-lg font-bold text-red-700">⚠️ Zone dangereuse — Réinitialiser les données</h2>
    <p class="mt-1 max-w-2xl text-sm text-red-600/90">
        À utiliser <b>une seule fois</b>, lorsque le client valide le site. Efface toutes les
        <b>commandes, clients, dettes, incidents et le catalogue de démonstration</b> (produits, images).
        <b>Conserve</b> : wilayas &amp; tarifs de livraison, paramètres, pixels et comptes administrateurs.
        Cette action est <b>irréversible</b>.
    </p>

    <form action="{{ route('admin.settings.reset') }}" method="post"
          class="mt-4 grid max-w-xl gap-3"
          onsubmit="return confirm('Dernière confirmation : effacer définitivement toutes les données de test ?')">
        @csrf
        <div>
            <label class="label text-red-700">Tapez <code class="rounded bg-red-100 px-1">REINITIALISER</code> pour confirmer</label>
            <input name="confirm" autocomplete="off" class="input" placeholder="REINITIALISER">
        </div>
        <div>
            <label class="label text-red-700">Votre mot de passe administrateur</label>
            <input name="password" type="password" autocomplete="current-password" class="input">
        </div>
        <div>
            <button class="btn bg-red-600 text-white hover:bg-red-700">🗑️ Effacer les données de test</button>
        </div>
    </form>
</div>
@endsection
