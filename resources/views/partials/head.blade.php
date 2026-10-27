<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Draw') : config('app.name', 'Draw') }}
</title>

<link rel="icon" href="/favicon.svg" type="image/svg+xml">
@vite('resources/js/app.jsx')
@fluxAppearance
