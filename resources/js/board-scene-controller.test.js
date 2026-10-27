import { describe, expect, it, vi } from 'vitest';

import { BoardSceneController } from './board-scene-controller.js';

const payload = (revision, id = `element-${revision}`) => ({
    revision,
    scene: {
        appState: {},
        elements: id ? [{ id }] : [],
        files: {},
    },
});

function makeController(overrides = {}) {
    const applyScene = vi.fn();
    const fetchScene = vi.fn().mockResolvedValue(payload(1));
    const onStatus = vi.fn();
    const saveScene = vi.fn().mockResolvedValue(payload(2));
    const controller = new BoardSceneController({
        applyScene,
        debounceMs: 25,
        fetchScene,
        onStatus,
        saveScene,
        scheduler: globalThis,
        ...overrides,
    });

    return { applyScene, controller, fetchScene, onStatus, saveScene };
}

describe('BoardSceneController', () => {
    it('applies an initial scene without echoing it as a save', async () => {
        const { applyScene, controller, saveScene } = makeController();

        await controller.load();

        expect(applyScene).toHaveBeenCalledWith(payload(1).scene, 1);
        expect(saveScene).not.toHaveBeenCalled();
    });

    it('ignores same and older signals and cancels a stale pending save for a newer scene', async () => {
        vi.useFakeTimers();
        const fetchScene = vi
            .fn()
            .mockResolvedValueOnce(payload(3))
            .mockResolvedValueOnce(payload(4, null));
        const { applyScene, controller, saveScene } = makeController({
            fetchScene,
        });

        await controller.load();
        controller.localChange(payload(3, 'local').scene);

        expect(await controller.receiveRevision(3)).toBe(false);
        expect(fetchScene).toHaveBeenCalledTimes(1);

        expect(await controller.receiveRevision(4)).toBe(true);
        await vi.runAllTimersAsync();

        expect(applyScene).toHaveBeenLastCalledWith(payload(4, null).scene, 4);
        expect(saveScene).not.toHaveBeenCalled();
        vi.useRealTimers();
    });

    it('surfaces a failed save and retries the same revision', async () => {
        vi.useFakeTimers();
        const saveScene = vi
            .fn()
            .mockRejectedValueOnce(new Error('offline'))
            .mockResolvedValueOnce(payload(2));
        const { controller, onStatus } = makeController({ saveScene });

        await controller.load();
        controller.localChange(payload(1, 'local').scene);
        await vi.runAllTimersAsync();

        expect(onStatus).toHaveBeenLastCalledWith(
            expect.objectContaining({ state: 'error' }),
        );
        expect(await controller.retry()).toBe(true);
        expect(saveScene).toHaveBeenNthCalledWith(
            2,
            expect.objectContaining({ revision: 1 }),
        );
        expect(onStatus).toHaveBeenLastCalledWith({ state: 'saved' });
        vi.useRealTimers();
    });

    it('refetches instead of retrying a revision conflict', async () => {
        vi.useFakeTimers();
        const conflict = Object.assign(new Error('conflict'), { status: 409 });
        const fetchScene = vi
            .fn()
            .mockResolvedValueOnce(payload(1))
            .mockResolvedValueOnce(payload(2, null));
        const saveScene = vi.fn().mockRejectedValue(conflict);
        const { applyScene, controller } = makeController({
            fetchScene,
            saveScene,
        });

        await controller.load();
        controller.localChange(payload(1, 'stale').scene);
        await vi.runAllTimersAsync();

        expect(applyScene).toHaveBeenLastCalledWith(payload(2, null).scene, 2);
        expect(saveScene).toHaveBeenCalledTimes(1);
        vi.useRealTimers();
    });

    it('serializes saves and rebases a queued snapshot onto the saved revision', async () => {
        vi.useFakeTimers();
        let finishFirstSave;
        const saveScene = vi
            .fn()
            .mockImplementationOnce(
                () =>
                    new Promise((resolve) => {
                        finishFirstSave = () => resolve(payload(2));
                    }),
            )
            .mockResolvedValueOnce(payload(3));
        const { controller } = makeController({ saveScene });

        await controller.load();
        controller.localChange(payload(1, 'first').scene);
        await vi.advanceTimersByTimeAsync(25);
        controller.localChange(payload(1, 'second').scene);
        await vi.advanceTimersByTimeAsync(25);

        expect(saveScene).toHaveBeenCalledTimes(1);
        finishFirstSave();
        await Promise.resolve();
        await vi.advanceTimersByTimeAsync(25);

        expect(saveScene).toHaveBeenCalledTimes(2);
        expect(saveScene).toHaveBeenLastCalledWith(
            expect.objectContaining({ revision: 2 }),
        );
        vi.useRealTimers();
    });

    it('keeps a queued edit when its own broadcast arrives before the save response', async () => {
        vi.useFakeTimers();
        let finishFirstSave;
        const saveScene = vi
            .fn()
            .mockImplementationOnce(
                () =>
                    new Promise((resolve) => {
                        finishFirstSave = () => resolve(payload(2, 'first'));
                    }),
            )
            .mockResolvedValueOnce(payload(3, 'second'));
        const { controller, fetchScene } = makeController({ saveScene });

        await controller.load();
        controller.localChange(payload(1, 'first').scene);
        await vi.advanceTimersByTimeAsync(25);
        controller.localChange(payload(1, 'second').scene);
        await vi.advanceTimersByTimeAsync(25);

        expect(await controller.receiveRevision(2)).toBe(true);
        expect(fetchScene).toHaveBeenCalledTimes(1);

        finishFirstSave();
        await vi.runAllTimersAsync();

        expect(saveScene).toHaveBeenCalledTimes(2);
        expect(saveScene).toHaveBeenLastCalledWith(
            expect.objectContaining({
                elements: [{ id: 'second' }],
                revision: 2,
            }),
        );
        expect(fetchScene).toHaveBeenCalledTimes(1);
        vi.useRealTimers();
    });

    it('retries the newer complete pending snapshot after an in-flight save fails', async () => {
        vi.useFakeTimers();
        const firstSave = Promise.withResolvers();
        const saveScene = vi
            .fn()
            .mockReturnValueOnce(firstSave.promise)
            .mockResolvedValueOnce(payload(2, 'second'));
        const { controller, onStatus } = makeController({ saveScene });

        await controller.load();
        controller.localChange(payload(1, 'first').scene);
        await vi.advanceTimersByTimeAsync(25);
        controller.localChange(payload(1, 'second').scene);
        await vi.advanceTimersByTimeAsync(25);

        firstSave.reject(new Error('offline'));
        await Promise.resolve();
        await Promise.resolve();

        expect(onStatus).toHaveBeenLastCalledWith(
            expect.objectContaining({ state: 'error' }),
        );
        expect(await controller.retry()).toBe(true);
        expect(saveScene).toHaveBeenCalledTimes(2);
        expect(saveScene).toHaveBeenLastCalledWith(
            expect.objectContaining({
                elements: [{ id: 'second' }],
                revision: 1,
            }),
        );
        expect(onStatus).toHaveBeenLastCalledWith({ state: 'saved' });
        vi.useRealTimers();
    });
});
