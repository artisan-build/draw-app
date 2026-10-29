<section
    data-testid="board-editor-shell"
    class="board-editor"
    x-data="{ fullScreen: false, toggleFullScreen() { this.fullScreen = ! this.fullScreen; this.$nextTick(() => window.dispatchEvent(new Event('resize'))); } }"
    x-bind:class="{ 'board-editor--full-screen': fullScreen }"
    x-on:keydown.escape.window="if (fullScreen && ! document.querySelector('dialog[open]')) toggleFullScreen()"
>
    <header class="board-editor-header" data-testid="board-editor-header">
        <flux:button href="{{ route('boards.index') }}" wire:navigate variant="ghost" x-show="! fullScreen">Back</flux:button>
        <flux:heading size="xl" class="board-editor-title">{{ $board->title }}</flux:heading>

        <div class="board-editor-actions">
            @if ($canEdit)
                <flux:modal.trigger name="board-agent">
                    <flux:button icon="sparkles" data-testid="board-agent-button">Agent</flux:button>
                </flux:modal.trigger>
            @endif

            @if ($canShare)
                <flux:modal.trigger name="board-share">
                    <flux:button icon="share" data-testid="board-share-button">Share</flux:button>
                </flux:modal.trigger>
            @endif

            <flux:button
                icon="arrows-pointing-out"
                x-show="! fullScreen"
                x-on:click="toggleFullScreen()"
                data-testid="board-full-screen-button"
            >Full screen</flux:button>
            <flux:button
                variant="primary"
                icon="arrows-pointing-in"
                x-show="fullScreen"
                x-cloak
                x-on:click="toggleFullScreen()"
                data-testid="board-exit-full-screen-button"
            >Exit full screen</flux:button>
        </div>
    </header>

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
        class="board-canvas"
    ></div>

    @if ($canEdit)
        <flux:modal name="board-agent" class="w-full md:w-[40rem]">
            <section class="board-modal-body" data-testid="board-agent-prompt">
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
        </flux:modal>
    @endif

    @if ($canShare)
        {{-- A share posts and comes back here; reopen the modal so the new link is the first thing seen. --}}
        @if ($shareCreated)
            <div x-init="$nextTick(() => $flux.modal('board-share').show())"></div>
        @endif

        <flux:modal name="board-share" class="w-full md:w-[40rem]">
            <section class="board-modal-body" data-testid="board-share-create">
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
        </flux:modal>
    @endif
</section>
