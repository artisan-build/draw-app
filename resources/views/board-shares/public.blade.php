<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => $board->title])
    </head>
    <body style="margin: 0;">
        <main
            data-testid="public-viewer-mount"
            data-board-id="{{ $board->id }}"
            data-scene-url="{{ $sceneUrl }}"
            data-can-edit="false"
            style="width: 100vw; height: 100vh;"
        ></main>
    </body>
</html>
