<section data-testid="board-editor-shell">
    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
        <flux:button href="{{ route('boards.index') }}" wire:navigate variant="ghost">Back</flux:button>
        <flux:heading size="xl">{{ $board->title }}</flux:heading>
    </div>

    <div
        data-testid="editor-mount"
        data-board-id="{{ $board->id }}"
        data-scene-url="{{ route('boards.scene.show', $board) }}"
        data-can-edit="{{ $canEdit ? 'true' : 'false' }}"
        style="display: grid; min-height: 32rem; place-items: center; border: 1px dashed #a1a1aa; border-radius: 0.75rem; background: #ffffff; color: #71717a;"
    >
        <span>Editor mount</span>
    </div>

    @if ($canShare)
        <section data-testid="board-share-create" style="display: grid; gap: 1rem; margin-top: 2rem;">
            <flux:heading size="lg">Share board</flux:heading>
            <p>People shown here are team members who have opened this app.</p>

            <form method="POST" action="{{ route('board-shares.store', $board) }}" style="display: flex; align-items: end; gap: 0.75rem;">
                @csrf
                <input type="hidden" name="kind" value="user">
                <label>
                    Person
                    <select name="recipient_user_id" required>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    Access
                    <select name="access">
                        <option value="view">View</option>
                        <option value="edit">Edit</option>
                    </select>
                </label>
                <flux:button type="submit" variant="primary">Share with person</flux:button>
            </form>

            <form method="POST" action="{{ route('board-shares.store', $board) }}" style="display: flex; align-items: end; gap: 0.75rem;">
                @csrf
                <input type="hidden" name="kind" value="team">
                <label>
                    Access
                    <select name="access">
                        <option value="view">View</option>
                        <option value="edit">Edit</option>
                    </select>
                </label>
                <flux:button type="submit">Share with team</flux:button>
            </form>

            <form method="POST" action="{{ route('board-shares.store', $board) }}" style="display: flex; align-items: end; gap: 0.75rem;">
                @csrf
                <input type="hidden" name="kind" value="link">
                <flux:input name="expires_in_minutes" type="number" min="1" max="525600" label="Expiry in minutes (optional)" />
                <flux:button type="submit">Create view-only link</flux:button>
            </form>
        </section>
    @endif
</section>
