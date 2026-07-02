@extends('admin.layout')
@section('title', $category->exists ? 'Modifier catégorie' : 'Nouvelle catégorie')
@section('heading', $category->exists ? 'Modifier : ' . $category->name_fr : 'Nouvelle catégorie')

@section('content')
<form action="{{ $category->exists ? route('admin.categories.update', $category) : route('admin.categories.store') }}"
      method="post" class="mx-auto max-w-2xl card p-6">
    @csrf
    @if ($category->exists) @method('PUT') @endif
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="label">Nom (FR) *</label>
            <input name="name_fr" value="{{ old('name_fr', $category->name_fr) }}" required class="input">
        </div>
        <div>
            <label class="label">Nom (AR)</label>
            <input name="name_ar" value="{{ old('name_ar', $category->name_ar) }}" dir="rtl" class="input">
        </div>
        <div>
            <label class="label">Icône (emoji)</label>
            <input name="icon" value="{{ old('icon', $category->icon) }}" class="input" placeholder="✏️">
        </div>
        <div>
            <label class="label">Couleur</label>
            <input name="color" type="color" value="{{ old('color', $category->color ?? '#2563eb') }}" class="input h-11">
        </div>
        <div>
            <label class="label">Catégorie parente</label>
            <select name="parent_id" class="input">
                <option value="">— (principale)</option>
                @foreach ($categories as $c)
                    <option value="{{ $c->id }}" @selected(old('parent_id', $category->parent_id)==$c->id)>{{ $c->name_fr }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label">Ordre</label>
            <input name="sort_order" type="number" value="{{ old('sort_order', $category->sort_order ?? 0) }}" class="input">
        </div>
        <div class="sm:col-span-2">
            <label class="label">Description (FR)</label>
            <textarea name="description_fr" rows="2" class="input">{{ old('description_fr', $category->description_fr) }}</textarea>
        </div>
        <div class="sm:col-span-2 flex gap-4 text-sm">
            <label class="flex items-center gap-2"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category->is_active)) class="rounded"> Active</label>
            <label class="flex items-center gap-2"><input type="hidden" name="is_featured" value="0"><input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $category->is_featured)) class="rounded"> En vedette</label>
        </div>
    </div>
    <div class="mt-6 flex gap-2">
        <button class="btn-primary">💾 Enregistrer</button>
        <a href="{{ route('admin.categories.index') }}" class="btn-ghost">Annuler</a>
    </div>
</form>
@endsection
