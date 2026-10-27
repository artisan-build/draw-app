export class BoardSceneController {
    constructor({
        applyScene,
        debounceMs = 650,
        fetchScene,
        onStatus,
        saveScene,
        scheduler = window,
    }) {
        this.applyScene = applyScene;
        this.debounceMs = debounceMs;
        this.fetchScene = fetchScene;
        this.onStatus = onStatus;
        this.saveScene = saveScene;
        this.scheduler = scheduler;
        this.revision = -1;
        this.epoch = 0;
        this.timer = null;
        this.pending = null;
        this.failed = null;
        this.inFlight = false;
        this.deferredRevision = null;
    }

    async load() {
        this.onStatus({ state: 'loading' });

        try {
            return this.applyRemote(await this.fetchScene());
        } catch {
            this.onStatus({
                message: 'The board could not be loaded.',
                state: 'error',
            });

            return false;
        }
    }

    localChange(scene) {
        this.failed = null;
        this.pending = {
            baseRevision: this.revision,
            epoch: this.epoch,
            scene,
        };
        this.onStatus({ state: 'pending' });
        this.clearTimer();
        this.schedulePending();
    }

    async receiveRevision(revision) {
        if (revision <= this.revision) {
            return false;
        }

        if (this.inFlight) {
            this.deferredRevision = Math.max(
                this.deferredRevision ?? revision,
                revision,
            );

            return true;
        }

        this.cancelLocalWork();

        try {
            return this.applyRemote(await this.fetchScene());
        } catch {
            this.onStatus({
                message: 'The latest board revision could not be loaded.',
                retry: () => void this.receiveRevision(revision),
                state: 'error',
            });

            return false;
        }
    }

    async retry() {
        const retryable = this.pending ?? this.failed;

        if (!retryable) {
            return false;
        }

        if (retryable.baseRevision !== this.revision) {
            this.pending = null;
            this.failed = null;
            return this.load();
        }

        this.pending = retryable;
        this.failed = null;
        this.clearTimer();

        return this.flush();
    }

    destroy() {
        this.clearTimer();
        this.pending = null;
        this.failed = null;
        this.deferredRevision = null;
        this.epoch += 1;
    }

    async flush() {
        this.clearTimer();

        if (this.inFlight) {
            return false;
        }

        const request = this.pending;
        this.pending = null;

        if (!request || request.epoch !== this.epoch) {
            return false;
        }

        this.onStatus({ state: 'saving' });
        this.inFlight = true;

        try {
            const payload = await this.saveScene({
                ...request.scene,
                revision: request.baseRevision,
            });
            this.inFlight = false;

            if (request.epoch !== this.epoch) {
                this.schedulePending();
                return false;
            }

            if (payload.revision > this.revision) {
                this.revision = payload.revision;
            }

            const deferredRevision = this.takeDeferredRevision();

            if (deferredRevision !== null && deferredRevision > this.revision) {
                return this.receiveRevision(deferredRevision);
            }

            if (this.pending?.epoch === request.epoch) {
                this.pending.baseRevision = this.revision;
                this.schedulePending();
            } else {
                this.onStatus({ state: 'saved' });
            }

            return true;
        } catch (error) {
            this.inFlight = false;

            if (request.epoch !== this.epoch) {
                this.schedulePending();
                return false;
            }

            if (error?.status === 409) {
                this.cancelLocalWork();
                await this.load();

                return false;
            }

            this.failed = request;
            this.onStatus({
                message: 'The board was not saved.',
                retry: () => void this.retry(),
                state: 'error',
            });

            return false;
        }
    }

    applyRemote(payload) {
        if (payload.revision <= this.revision) {
            return false;
        }

        this.cancelLocalWork();
        this.revision = payload.revision;
        this.applyScene(payload.scene, payload.revision);
        this.onStatus({ state: 'saved' });

        return true;
    }

    cancelLocalWork() {
        this.clearTimer();
        this.pending = null;
        this.failed = null;
        this.deferredRevision = null;
        this.epoch += 1;
    }

    clearTimer() {
        if (this.timer !== null) {
            this.scheduler.clearTimeout(this.timer);
            this.timer = null;
        }
    }

    schedulePending() {
        if (this.pending && this.timer === null) {
            this.timer = this.scheduler.setTimeout(
                () => void this.flush(),
                this.debounceMs,
            );
        }
    }

    takeDeferredRevision() {
        const revision = this.deferredRevision;
        this.deferredRevision = null;

        return revision;
    }
}
