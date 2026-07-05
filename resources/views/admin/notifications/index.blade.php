@extends('admin.layout')
@section('title', 'Notifications')
@section('heading', 'Notifications')

@section('content')
<div class="card overflow-hidden">
    @forelse ($notifications as $n)
        <a href="{{ $n->url ?: '#' }}"
           class="flex items-start gap-3 border-b border-slate-100 px-5 py-4 last:border-0 hover:bg-slate-50 {{ $n->read_at ? '' : 'bg-brand-50/40' }}">
            <span class="text-2xl">{{ $n->icon }}</span>
            <div class="flex-1">
                <p class="font-medium">{{ $n->title }}</p>
                @if ($n->body)<p class="text-sm text-slate-500">{{ $n->body }}</p>@endif
            </div>
            <span class="text-xs text-slate-400">{{ $n->created_at->diffForHumans() }}</span>
        </a>
    @empty
        <div class="px-5 py-12 text-center text-slate-400">Aucune notification.</div>
    @endforelse
</div>
<div class="mt-4">{{ $notifications->links() }}</div>
@endsection
