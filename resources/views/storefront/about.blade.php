@extends('layouts.app')
@section('title', __('shop.about'))

@section('content')
<div class="container-x py-12">
    <div class="mx-auto max-w-3xl">
        <h1 class="font-display text-3xl font-bold">{{ __('shop.about') }}</h1>
        <p class="mt-4 text-ink-700 leading-relaxed">
            {{ app()->getLocale()==='ar'
                ? 'سعيدي للقرطاسية متجر متخصص في اللوازم المدرسية والمكتبية والمعلوماتية. نوفر منتجات ذات جودة عالية مع التوصيل لكل ولايات الوطن والدفع عند الاستلام.'
                : "Saidi Papetrie est votre boutique spécialisée en fournitures scolaires, bureautiques et informatiques. Nous proposons des produits de qualité avec livraison dans les 58 wilayas et paiement à la livraison." }}
        </p>
        <div class="mt-8 grid gap-4 sm:grid-cols-3">
            @foreach ([['🚚','Livraison 58 wilayas','Noest, Yalidine & livraison propre'],['💵','Paiement à la livraison','Aucune carte requise'],['✅','Qualité garantie','Produits sélectionnés']] as $b)
                <div class="card p-5 text-center">
                    <div class="text-3xl">{{ $b[0] }}</div>
                    <h3 class="mt-2 font-semibold">{{ $b[1] }}</h3>
                    <p class="mt-1 text-sm text-slate-500">{{ $b[2] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
