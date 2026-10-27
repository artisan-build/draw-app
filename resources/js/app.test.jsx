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
});
