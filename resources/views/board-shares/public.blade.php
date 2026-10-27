<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => $board->title])
    </head>
    <body style="margin: 0;">
        <main
            data-testid="public-viewer-mount"
            data-board-canvas
            data-scene-url="{{ $sceneUrl }}"
            data-can-edit="false"
            data-channel-kind="public"
            data-channel-name="link-shares.{{ $share->id }}"
            data-reverb-key="{{ config('broadcasting.connections.reverb.key') }}"
            data-reverb-host="{{ config('broadcasting.connections.reverb.options.host') }}"
            data-reverb-port="{{ config('broadcasting.connections.reverb.options.port') }}"
            data-reverb-scheme="{{ config('broadcasting.connections.reverb.options.scheme') }}"
            style="width: 100vw; height: 100vh;"
        ></main>
    </body>
</html>
