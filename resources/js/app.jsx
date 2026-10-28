import '@excalidraw/excalidraw/index.css';
import '../css/board-canvas.css';
import '../css/app.css';

import { createElement } from 'react';
import { createRoot } from 'react-dom/client';

import { BoardCanvas } from './components/board-canvas.jsx';

const roots = new Map();
const autoCopied = new WeakSet();

function copyValue(button) {
    const target = document.getElementById(button.dataset.copyTarget);

    if (target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement) {
        target.focus();
        target.select();

        return target.value;
    }

    return target?.textContent ?? '';
}

function updateCopyStatus(button, message) {
    const status = document.getElementById(button.dataset.copyStatus);

    if (status) {
        status.textContent = message;
    }
}

async function copyFrom(button) {
    const value = copyValue(button);

    try {
        if (!navigator.clipboard?.writeText) {
            throw new Error('Clipboard API unavailable');
        }

        await navigator.clipboard.writeText(value);
        updateCopyStatus(button, button.dataset.copySuccess ?? 'Copied');
    } catch {
        updateCopyStatus(button, 'Select the text and copy it');
    }
}

function mountClipboardControls() {
    document.querySelectorAll('[data-copy-on-load]').forEach((button) => {
        if (autoCopied.has(button)) {
            return;
        }

        autoCopied.add(button);
        void copyFrom(button);
    });
}

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

    mountClipboardControls();
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
document.addEventListener('click', (event) => {
    if (!(event.target instanceof Element)) {
        return;
    }

    const button = event.target.closest('[data-copy-target]');
    if (button instanceof HTMLButtonElement) {
        void copyFrom(button);
    }
});
