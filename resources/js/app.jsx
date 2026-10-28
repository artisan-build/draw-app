import '@excalidraw/excalidraw/index.css';
import '../css/board-canvas.css';
import '../css/app.css';

import { createElement } from 'react';
import { createRoot } from 'react-dom/client';

import { BoardCanvas } from './components/board-canvas.jsx';

const roots = new Map();

function mountBoardCanvas(element) {
    if (roots.has(element)) {
        return;
    }

    const root = createRoot(element);
    const canEdit = element.dataset.canEdit === 'true';

    root.render(
        createElement(BoardCanvas, {
            canEdit,
            channelKind: element.dataset.channelKind,
            channelName: element.dataset.channelName,
            frame: new URLSearchParams(window.location.search).get('frame'),
            reverb: {
                host: element.dataset.reverbHost,
                key: element.dataset.reverbKey,
                port: Number(element.dataset.reverbPort),
                scheme: element.dataset.reverbScheme,
            },
            saveUrl: canEdit ? element.dataset.saveUrl : null,
            sceneUrl: element.dataset.sceneUrl,
        }),
    );
    roots.set(element, root);
}

function mountBoardCanvases() {
    document
        .querySelectorAll('[data-board-canvas]')
        .forEach((element) => mountBoardCanvas(element));
}

function unmountBoardCanvases() {
    roots.forEach((root) => root.unmount());
    roots.clear();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mountBoardCanvases, {
        once: true,
    });
} else {
    mountBoardCanvases();
}

document.addEventListener('livewire:navigated', mountBoardCanvases);
document.addEventListener('livewire:navigating', unmountBoardCanvases);
