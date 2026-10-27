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
                <span style="color: #71717a; font-size: 0.875rem;">{{ auth()->user()?->name }}</span>
            </nav>
        </header>

        <main style="max-width: 72rem; margin: 0 auto; padding: 2rem 1.5rem;">
            {{ $slot }}
        </main>

        @fluxScripts
    </body>
</html>
