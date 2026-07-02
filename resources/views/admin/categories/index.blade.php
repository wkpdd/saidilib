@extends('admin.layout')
@section('title', 'Catégories')
@section('heading', 'Catégories')

@section('content')
<div class="mb-4 flex justify-end">
    <a href="{{ route('admin.categories.create') }}" class="btn-primary">+ Nouvelle catégorie</a>
</div>
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    @forelse ($categories as $cat)
        <div class="card p-5" style="border-top: 3px solid {{ $cat->color }}">
            <div class="flex items-start justify-between">
                <span class="grid h-12 w-12 place-items-center rounded-xl text-2xl" style="background: {{ $cat->color }}1f">{{ $cat->icon }}</span>
                <span class="badge {{ $cat->is_active ? 'bg-green-50 text-green-700' : 'bg-slate-100 text-slate-500' }}">{{ $cat->is_active ? 'Active' : 'Masquée' }}</span>
            </div>
            <h3 class="mt-3 font-semibold">{{ $cat->name_fr }}</h3>
            <p class="text-sm text-slate-400" dir="rtl">{{ $cat->name_ar }}</p>
            <p class="mt-1 text-xs text-slate-400">{{ $cat->products_count }} produits</p>
            <div class="mt-4 flex gap-2">
                <a href="{{ route('admin.categories.edit', $cat) }}" class="btn-ghost flex-1 text-sm">Modifier</a>
                <form action="{{ route('admin.categories.destroy', $cat) }}" method="post" onsubmit="return confirm('Supprimer ?')">
                    @csrf @method('DELETE')
                    <button class="btn-ghost text-sm text-red-600">Suppr.</button>
                </form>
            </div>
        </div>
    @empty
        <p class="text-slate-400">Aucune catégorie.</p>
    @endforelse
</div>
@endsection
