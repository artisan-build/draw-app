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

    @if ($canEdit)
        <section class="board-support-card" data-testid="board-agent-prompt">
            <div>
                <flux:heading size="lg">Work on this board with an agent</flux:heading>
                <p>Copy this prompt into your agent chat.</p>
            </div>
            <textarea id="board-agent-prompt" class="board-copy-field board-copy-field--prompt" readonly>{{ $agentPrompt }}</textarea>
            <div class="board-copy-actions">
                <button
                    class="board-copy-button"
                    type="button"
                    data-copy-target="board-agent-prompt"
                    data-copy-status="board-agent-prompt-status"
                    data-copy-success="Prompt copied"
                >Copy prompt</button>
                <span id="board-agent-prompt-status" class="board-copy-status" aria-live="polite"></span>
            </div>
        </section>
    @endif

    @if ($canShare)
        <section class="board-support-card" data-testid="board-share-create">
            <flux:heading size="lg">Share board</flux:heading>
            <p>People shown here are team members who have opened this app.</p>

            @if ($shareCreated)
                <div class="board-share-result" data-testid="board-share-result">
                    @if ($newShareUrl)
                        <label for="new-board-share-url">View-only link</label>
                        <div class="board-copy-row">
                            <input id="new-board-share-url" class="board-copy-field" type="text" value="{{ $newShareUrl }}" readonly>
                            <button
                                class="board-copy-button"
                                type="button"
                                data-copy-target="new-board-share-url"
                                data-copy-status="new-board-share-status"
                                data-copy-success="Link copied"
                                data-copy-on-load
                            >Copy link</button>
                        </div>
                        <span id="new-board-share-status" class="board-copy-status" aria-live="polite">Link ready to copy</span>
                    @else
                        <p>Share created.</p>
                    @endif
                </div>
            @endif

            <form class="board-share-form" method="POST" action="{{ route('board-shares.store', $board) }}">
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

            <form class="board-share-form" method="POST" action="{{ route('board-shares.store', $board) }}">
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

            <form class="board-share-form" method="POST" action="{{ route('board-shares.store', $board) }}">
                @csrf
                <input type="hidden" name="kind" value="link">
                <flux:input name="expires_in_minutes" type="number" min="1" max="525600" label="Expiry in minutes (optional)" />
                <flux:button type="submit">Create view-only link</flux:button>
            </form>
        </section>
    @endif
</section>
