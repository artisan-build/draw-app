<div>
    <section id="new-board" data-testid="board-create-form" style="margin-bottom: 2rem; scroll-margin-top: 7rem;">
        <flux:heading size="xl">Boards</flux:heading>

        <form wire:submit="create" style="display: flex; align-items: end; gap: 0.75rem; margin-top: 1rem;">
            <div style="flex: 1;">
                <flux:input wire:model="title" label="Board title" maxlength="120" />
            </div>
            <flux:button type="submit" variant="primary">Create board</flux:button>
        </form>
    </section>

    <section data-testid="boards-list" style="display: grid; gap: 1rem;">
        @foreach ($boards as $board)
            <article wire:key="board-{{ $board->id }}" data-testid="board-list-item" style="border: 1px solid #e4e4e7; border-radius: 0.75rem; background: #ffffff; padding: 1rem;">
                <a href="{{ route('boards.show', $board) }}" wire:navigate style="color: inherit; font-weight: 600; text-decoration: none;">
                    {{ $board->title }}
                </a>
            </article>
        @endforeach
    </section>
</div>
