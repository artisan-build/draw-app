<section data-testid="board-editor-shell">
    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
        <flux:button href="{{ route('boards.index') }}" wire:navigate variant="ghost">Back</flux:button>
        <flux:heading size="xl">{{ $board->title }}</flux:heading>
    </div>

    <div
        data-testid="editor-mount"
        data-board-id="{{ $board->id }}"
        data-scene-url="{{ route('boards.scene.show', $board) }}"
        style="display: grid; min-height: 32rem; place-items: center; border: 1px dashed #a1a1aa; border-radius: 0.75rem; background: #ffffff; color: #71717a;"
    >
        <span>Editor mount</span>
    </div>
</section>
