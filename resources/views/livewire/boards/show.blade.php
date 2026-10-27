<section data-testid="board-editor-shell">
    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
        <flux:button href="{{ route('boards.index') }}" wire:navigate variant="ghost">Back</flux:button>
        <flux:heading size="xl">{{ $board->title }}</flux:heading>
    </div>

    <div
        data-testid="editor-mount"
        data-board-canvas
        data-board-id="{{ $board->id }}"
        data-scene-url="{{ route('boards.scene.show', $board) }}"
        data-save-url="{{ $canEdit ? route('boards.scene.update', $board) : '' }}"
        data-can-edit="{{ $canEdit ? 'true' : 'false' }}"
        data-channel-kind="private"
        data-channel-name="boards.{{ $board->id }}"
        data-reverb-key="{{ config('broadcasting.connections.reverb.key') }}"
        data-reverb-host="{{ config('broadcasting.connections.reverb.options.host') }}"
        data-reverb-port="{{ config('broadcasting.connections.reverb.options.port') }}"
        data-reverb-scheme="{{ config('broadcasting.connections.reverb.options.scheme') }}"
        style="height: 70vh; border: 1px solid #d4d4d8; border-radius: 0.75rem;"
    ></div>

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
