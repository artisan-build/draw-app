@props(['title' => null])

@push('head')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    @vite('resources/js/app.jsx')
    @fluxAppearance
@endpush

@push('scripts')
    @fluxScripts
@endpush

<x-bfc-layout :title="filled($title) ? $title.' - Draw' : 'Draw'">
    <x-slot:sidebar>
        <div class="draw-sidebar" data-testid="draw-sidebar">
            <nav class="draw-navigation" aria-label="Draw" data-testid="draw-navigation">
                <a href="{{ route('boards.index') }}" wire:navigate @if(request()->routeIs('bfc.dashboard', 'boards.index')) aria-current="page" @endif>
                    Boards
                </a>
                <a href="{{ route('boards.shared') }}" wire:navigate @if(request()->routeIs('boards.shared')) aria-current="page" @endif>
                    Shared with me
                </a>
                <a href="{{ route('board-shares.index') }}" wire:navigate @if(request()->routeIs('board-shares.index')) aria-current="page" @endif>
                    My shares
                </a>
            </nav>
        </div>
    </x-slot:sidebar>

    <div data-testid="draw-app-shell">
        <div class="draw-content" data-testid="draw-content">
            {{ $slot }}

            <footer data-testid="about-excalidraw">
                Powered by <a href="https://excalidraw.com" rel="noreferrer" target="_blank">Excalidraw</a>.
                For richer collaboration and AI tools, visit <a href="https://plus.excalidraw.com" rel="noreferrer" target="_blank">Excalidraw+</a>.
                Excalidraw is used under its MIT license.
            </footer>
        </div>
    </div>
</x-bfc-layout>
