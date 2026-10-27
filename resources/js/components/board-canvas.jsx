import { Excalidraw } from '@excalidraw/excalidraw';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

import { BoardSceneController } from '../board-scene-controller.js';
import { getEcho } from '../echo.js';
import { findRequestedFrame } from '../frame.js';

const safeAppState = (appState) => ({
    gridModeEnabled: appState.gridModeEnabled,
    gridSize: appState.gridSize,
    gridStep: appState.gridStep,
    viewBackgroundColor: appState.viewBackgroundColor,
});

const sceneFingerprint = (scene) =>
    JSON.stringify({
        appState: safeAppState(scene.appState),
        elements: scene.elements,
        files: scene.files,
    });

async function jsonRequest(url, options = {}) {
    const response = await fetch(url, {
        credentials: 'same-origin',
        ...options,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            ...options.headers,
        },
    });

    if (!response.ok) {
        const error = new Error(`Board request failed with ${response.status}.`);
        error.status = response.status;
        throw error;
    }

    return response.json();
}

function csrfToken() {
    return (
        document.querySelector('meta[name="csrf-token"]')?.content ?? ''
    );
}

export function BoardCanvas({
    canEdit,
    channelKind,
    channelName,
    frame,
    reverb,
    saveUrl,
    sceneUrl,
}) {
    const [scene, setScene] = useState(null);
    const [sceneRevision, setSceneRevision] = useState(null);
    const [status, setStatus] = useState({ state: 'loading' });
    const apiRef = useRef(null);
    const applyingRemote = useRef(false);
    const controllerRef = useRef(null);
    const remoteFingerprint = useRef(null);
    const sceneRef = useRef(null);

    const updateStatus = useCallback((nextStatus) => {
        setStatus((currentStatus) =>
            currentStatus.state === nextStatus.state &&
            currentStatus.message === nextStatus.message &&
            currentStatus.retry === nextStatus.retry
                ? currentStatus
                : nextStatus,
        );
    }, []);

    const fitFrame = useCallback(
        (nextScene) => {
            if (canEdit || !apiRef.current) {
                return;
            }

            const selectedFrame = findRequestedFrame(
                nextScene.elements,
                frame,
            );

            if (selectedFrame) {
                requestAnimationFrame(() =>
                    apiRef.current?.scrollToContent(selectedFrame, {
                        animate: false,
                        fitToViewport: true,
                    }),
                );
            }
        },
        [canEdit, frame],
    );

    const applyScene = useCallback(
        (nextScene, revision) => {
            applyingRemote.current = true;
            remoteFingerprint.current = sceneFingerprint(nextScene);
            apiRef.current = null;
            sceneRef.current = nextScene;
            setScene(nextScene);
            setSceneRevision(revision);

            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    applyingRemote.current = false;
                });
            });
        },
        [],
    );

    const handleExcalidrawApi = useCallback(
        (api) => {
            apiRef.current = api;

            if (sceneRef.current) {
                fitFrame(sceneRef.current);
            }
        },
        [fitFrame],
    );

    const handleChange = useCallback(
        (elements, appState, files) => {
            const nextScene = {
                appState: safeAppState(appState),
                elements: [...elements],
                files,
            };

            if (!canEdit || applyingRemote.current) {
                return;
            }

            if (sceneFingerprint(nextScene) === remoteFingerprint.current) {
                remoteFingerprint.current = null;
                return;
            }

            controllerRef.current?.localChange(nextScene);
        },
        [canEdit],
    );

    const initialData = useMemo(
        () =>
            scene
                ? {
                      appState: {
                          ...scene.appState,
                          viewModeEnabled: !canEdit,
                          zenModeEnabled: !canEdit,
                      },
                      elements: scene.elements,
                      files: scene.files,
                  }
                : null,
        [canEdit, scene],
    );

    useEffect(() => {
        const controller = new BoardSceneController({
            applyScene,
            fetchScene: () => jsonRequest(sceneUrl),
            onStatus: updateStatus,
            saveScene: (payload) => {
                const socketId = getEcho(reverb).socketId();

                return jsonRequest(saveUrl, {
                    body: JSON.stringify(payload),
                    headers: {
                        'X-CSRF-TOKEN': csrfToken(),
                        ...(socketId ? { 'X-Socket-ID': socketId } : {}),
                    },
                    method: 'PUT',
                });
            },
        });
        controllerRef.current = controller;
        void controller.load();

        return () => {
            controller.destroy();
            controllerRef.current = null;
        };
    }, [applyScene, reverb, saveUrl, sceneUrl, updateStatus]);

    useEffect(() => {
        const controller = controllerRef.current;

        if (!controller) {
            return undefined;
        }

        const echo = getEcho(reverb);
        const channel =
            channelKind === 'private'
                ? echo.private(channelName)
                : echo.channel(channelName);

        channel.listen('.board.updated', ({ revision }) => {
            void controller.receiveRevision(revision);
        });

        return () => echo.leave(channelName);
    }, [channelKind, channelName, reverb]);

    if (!scene) {
        return (
            <div className="board-canvas-message" data-testid="board-loading">
                {status.state === 'error'
                    ? status.message
                    : 'Loading board…'}
            </div>
        );
    }

    return (
        <div className="board-canvas" data-testid="board-canvas">
            <Excalidraw
                key={sceneRevision}
                excalidrawAPI={handleExcalidrawApi}
                initialData={initialData}
                onChange={handleChange}
                viewModeEnabled={!canEdit}
                zenModeEnabled={!canEdit}
            />

            {canEdit && (
                <div
                    aria-live="polite"
                    className={`board-save-status board-save-status--${status.state}`}
                    data-testid="board-save-status"
                >
                    {status.state === 'pending' && 'Unsaved changes'}
                    {status.state === 'saving' && 'Saving…'}
                    {status.state === 'saved' && 'Saved'}
                    {status.state === 'error' && (
                        <>
                            <span>{status.message}</span>{' '}
                            {status.retry && (
                                <button type="button" onClick={status.retry}>
                                    Retry
                                </button>
                            )}
                        </>
                    )}
                </div>
            )}
        </div>
    );
}
