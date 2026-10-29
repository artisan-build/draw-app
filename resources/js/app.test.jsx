import { describe, expect, it, vi } from 'vitest';

const createdRoots = vi.hoisted(() => []);

vi.mock('react-dom/client', () => ({
    createRoot: vi.fn(() => {
        const root = {
            render: vi.fn(),
            unmount: vi.fn(),
        };
        createdRoots.push(root);

        return root;
    }),
}));

vi.mock('./components/board-canvas.jsx', () => ({
    BoardCanvas: () => null,
}));

describe('board canvas Livewire lifecycle', () => {
    it('unmounts the current React root before navigation and mounts the replacement', async () => {
        document.body.innerHTML = '<main data-board-canvas data-can-edit="false"></main>';

        await import('./app.jsx');
        document.dispatchEvent(new Event('DOMContentLoaded'));

        expect(createdRoots).toHaveLength(1);
        expect(createdRoots[0].render).toHaveBeenCalledOnce();

        document.dispatchEvent(new Event('livewire:navigating'));

        expect(createdRoots[0].unmount).toHaveBeenCalledOnce();

        document.body.innerHTML = '<main data-board-canvas data-can-edit="false"></main>';
        document.dispatchEvent(new Event('livewire:navigated'));

        expect(createdRoots).toHaveLength(2);
        expect(createdRoots[1].render).toHaveBeenCalledOnce();
    });

    it('copies prompts on request and new share links on arrival', async () => {
        const writeText = vi.fn().mockResolvedValue(undefined);
        Object.defineProperty(navigator, 'clipboard', {
            configurable: true,
            value: { writeText },
        });
        document.body.innerHTML = `
            <textarea id="agent-prompt">Test-created prompt</textarea>
            <button type="button" data-copy-target="agent-prompt" data-copy-status="prompt-status" data-copy-success="Prompt copied">Copy</button>
            <span id="prompt-status"></span>
            <input id="share-link" value="https://example.test/test-created-share">
            <button type="button" data-copy-target="share-link" data-copy-status="share-status" data-copy-success="Link copied" data-copy-on-load>Copy link</button>
            <span id="share-status">Link ready to copy</span>
        `;

        document.dispatchEvent(new Event('livewire:navigated'));
        await vi.waitFor(() => {
            expect(writeText).toHaveBeenCalledWith(
                'https://example.test/test-created-share',
            );
            expect(document.getElementById('share-status').textContent).toBe(
                'Link copied',
            );
        });

        document.querySelector('[data-copy-target="agent-prompt"]').click();
        await vi.waitFor(() => {
            expect(writeText).toHaveBeenCalledWith('Test-created prompt');
            expect(document.getElementById('prompt-status').textContent).toBe(
                'Prompt copied',
            );
        });
    });
});
