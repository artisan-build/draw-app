<section data-testid="shared-boards-list" style="display: grid; gap: 1rem;">
    <flux:heading size="xl">Shared with me</flux:heading>

    @foreach ($boards as $board)
        <article wire:key="shared-board-{{ $board->id }}" data-testid="shared-board-list-item" style="border: 1px solid #e4e4e7; border-radius: 0.75rem; background: #ffffff; padding: 1rem;">
            <a href="{{ route('boards.show', $board) }}" wire:navigate style="color: inherit; font-weight: 600; text-decoration: none;">
                {{ $board->title }}
            </a>
        </article>
    @endforeach
</section>
