<section data-testid="my-shares-list" style="display: grid; gap: 1rem;">
    <flux:heading size="xl">My shares</flux:heading>

    @foreach ($shares as $share)
        <article wire:key="share-{{ $share->id }}" data-testid="my-share-list-item" style="border: 1px solid #e4e4e7; border-radius: 0.75rem; background: #ffffff; padding: 1rem;">
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 1rem;">
                <div>
                    <strong>{{ $share->board->title }}</strong>
                    <span>{{ ucfirst($share->kind->value) }}</span>
                    <span data-testid="share-access-label">
                        {{ $share->kind->value === 'link' ? 'View only' : ucfirst($share->access->value) }}
                    </span>
                    @if ($share->recipient !== null)
                        <span>{{ $share->recipient->name }} ({{ $share->recipient->email }})</span>
                    @endif
                    @if ($share->kind->value === 'link')
                        <a href="{{ $share->signedUrl() }}">Open signed link</a>
                    @endif
                </div>
                <form method="POST" action="{{ route('board-shares.destroy', $share) }}">
                    @csrf
                    @method('DELETE')
                    <flux:button type="submit" variant="danger">Delete share</flux:button>
                </form>
            </div>
        </article>
    @endforeach
</section>
