<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body style="margin: 0; background: #f8fafc; color: #18181b; font-family: ui-sans-serif, system-ui, sans-serif;">
        <header style="border-bottom: 1px solid #e4e4e7; background: #ffffff;">
            <nav style="display: flex; align-items: center; justify-content: space-between; max-width: 72rem; margin: 0 auto; padding: 1rem 1.5rem;">
                <a href="{{ route('boards.index') }}" wire:navigate style="color: inherit; font-size: 1.125rem; font-weight: 700; text-decoration: none;">
                    Draw
                </a>
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <a href="{{ route('boards.index') }}" wire:navigate>Boards</a>
                    <a href="{{ route('boards.shared') }}" wire:navigate>Shared with me</a>
                    <a href="{{ route('board-shares.index') }}" wire:navigate>My shares</a>
                    <span style="color: #71717a; font-size: 0.875rem;">{{ auth()->user()?->name }}</span>
                </div>
            </nav>
        </header>

        <main style="max-width: 72rem; margin: 0 auto; padding: 2rem 1.5rem;">
            {{ $slot }}
        </main>

        <footer data-testid="about-excalidraw" style="max-width: 72rem; margin: 0 auto; padding: 0 1.5rem 2rem; color: #71717a; font-size: 0.875rem;">
            Powered by <a href="https://excalidraw.com" rel="noreferrer" target="_blank">Excalidraw</a>.
            For richer collaboration and AI tools, visit <a href="https://plus.excalidraw.com" rel="noreferrer" target="_blank">Excalidraw+</a>.
            Excalidraw is used under its MIT license.
        </footer>

        @fluxScripts
    </body>
</html>
