import { render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { BoardCanvas } from './board-canvas.jsx';
import { findRequestedFrame } from '../frame.js';

let excalidrawProps;
let excalidrawInstance = 0;
const excalidrawApi = {
    addFiles: vi.fn(),
    scrollToContent: vi.fn(),
    updateScene: vi.fn(),
};
const channel = { listen: vi.fn().mockReturnThis() };
const echo = {
    channel: vi.fn(() => channel),
    leave: vi.fn(),
    private: vi.fn(() => channel),
    socketId: vi.fn(() => 'socket-123'),
};

vi.mock('@excalidraw/excalidraw', async () => {
    const { useState } = await import('react');

    return {
        Excalidraw: (props) => {
            const [instance] = useState(() => ++excalidrawInstance);

            excalidrawProps = props;
            props.excalidrawAPI(excalidrawApi);

            return (
                <div
                    data-instance={instance}
                    data-testid="fake-excalidraw"
                />
            );
        },
    };
});

vi.mock('../echo.js', () => ({ getEcho: () => echo }));

const scene = {
    appState: { viewBackgroundColor: '#fff' },
    elements: [],
    files: {},
};

const props = {
    channelKind: 'public',
    channelName: 'link-shares.opaque',
    frame: null,
    reverb: { host: '127.0.0.1', key: 'local', port: 8080, scheme: 'http' },
    saveUrl: null,
    sceneUrl: '/scene',
};

beforeEach(() => {
    excalidrawProps = null;
    excalidrawInstance = 0;
    vi.clearAllMocks();
    fetch.mockResolvedValue({
        json: () => Promise.resolve({ revision: 1, scene }),
        ok: true,
    });
});

describe('BoardCanvas', () => {
    it('mounts signed-link viewers in Excalidraw view mode', async () => {
        render(<BoardCanvas {...props} canEdit={false} />);

        await screen.findByTestId('fake-excalidraw');

        expect(excalidrawProps.viewModeEnabled).toBe(true);
        expect(excalidrawProps.zenModeEnabled).toBe(true);
        expect(excalidrawProps.UIOptions).toBeUndefined();
        expect(echo.channel).toHaveBeenCalledWith('link-shares.opaque');
    });

    it('keeps editors editable without disabling the standard Excalidraw UI', async () => {
        render(
            <BoardCanvas
                {...props}
                canEdit
                channelKind="private"
                channelName="boards.board-id"
                saveUrl="/save"
            />,
        );

        await screen.findByTestId('fake-excalidraw');

        expect(excalidrawProps.viewModeEnabled).toBe(false);
        expect(excalidrawProps.UIOptions).toBeUndefined();
        expect(echo.private).toHaveBeenCalledWith('boards.board-id');
        await waitFor(() => expect(fetch).toHaveBeenCalledWith('/scene', expect.anything()));
    });

    it('fits the requested frame on initial load and after a newer signal', async () => {
        const initialFrame = { id: 'initial', name: 'Agenda', type: 'frame' };
        const refreshedFrame = {
            id: 'refreshed',
            name: 'Agenda',
            type: 'frame',
        };
        fetch
            .mockResolvedValueOnce({
                json: () =>
                    Promise.resolve({
                        revision: 1,
                        scene: { ...scene, elements: [initialFrame] },
                    }),
                ok: true,
            })
            .mockResolvedValueOnce({
                json: () =>
                    Promise.resolve({
                        revision: 2,
                        scene: { ...scene, elements: [refreshedFrame] },
                    }),
                ok: true,
            });

        render(<BoardCanvas {...props} canEdit={false} frame="agenda" />);
        await screen.findByTestId('fake-excalidraw');
        await waitFor(() =>
            expect(excalidrawApi.scrollToContent).toHaveBeenCalledWith(
                initialFrame,
                expect.objectContaining({ fitToViewport: true }),
            ),
        );

        const signal = channel.listen.mock.calls.at(-1)[1];
        signal({ revision: 2 });

        await waitFor(() =>
            expect(excalidrawApi.scrollToContent).toHaveBeenCalledWith(
                refreshedFrame,
                expect.objectContaining({ fitToViewport: true }),
            ),
        );
    });

    it('remounts Excalidraw so remote snapshots replace removed app state and files', async () => {
        const initialScene = {
            appState: {
                gridModeEnabled: true,
                viewBackgroundColor: '#ffeeee',
            },
            elements: [{ id: 'initial', type: 'rectangle' }],
            files: { old: { id: 'old', mimeType: 'image/png' } },
        };
        const replacementScene = {
            appState: {},
            elements: [],
            files: {},
        };
        fetch
            .mockResolvedValueOnce({
                json: () =>
                    Promise.resolve({ revision: 1, scene: initialScene }),
                ok: true,
            })
            .mockResolvedValueOnce({
                json: () =>
                    Promise.resolve({ revision: 2, scene: replacementScene }),
                ok: true,
            });

        render(<BoardCanvas {...props} canEdit={false} />);
        const initialCanvas = await screen.findByTestId('fake-excalidraw');
        const initialInstance = initialCanvas.dataset.instance;

        expect(excalidrawProps.initialData.appState).toEqual(
            expect.objectContaining(initialScene.appState),
        );
        expect(excalidrawProps.initialData.files).toEqual(initialScene.files);

        const signal = channel.listen.mock.calls.at(-1)[1];
        signal({ revision: 2 });

        await waitFor(() =>
            expect(screen.getByTestId('fake-excalidraw').dataset.instance).not.toBe(
                initialInstance,
            ),
        );
        expect(excalidrawProps.initialData.appState).not.toHaveProperty(
            'gridModeEnabled',
        );
        expect(excalidrawProps.initialData.appState).not.toHaveProperty(
            'viewBackgroundColor',
        );
        expect(excalidrawProps.initialData.files).toEqual({});
        expect(excalidrawApi.updateScene).not.toHaveBeenCalled();
        expect(excalidrawApi.addFiles).not.toHaveBeenCalled();
    });

    it('does not echo initial or refreshed scenes back through the save route', async () => {
        const refreshedScene = {
            ...scene,
            elements: [{ id: 'remote', type: 'rectangle' }],
        };
        fetch
            .mockResolvedValueOnce({
                json: () => Promise.resolve({ revision: 1, scene }),
                ok: true,
            })
            .mockResolvedValueOnce({
                json: () =>
                    Promise.resolve({ revision: 2, scene: refreshedScene }),
                ok: true,
            });

        render(
            <BoardCanvas
                {...props}
                canEdit
                channelKind="private"
                channelName="boards.board-id"
                saveUrl="/save"
            />,
        );
        await screen.findByTestId('fake-excalidraw');
        await new Promise((resolve) => setTimeout(resolve, 10));
        excalidrawProps.onChange(scene.elements, scene.appState, scene.files);
        await new Promise((resolve) => setTimeout(resolve, 675));

        expect(fetch).toHaveBeenCalledTimes(1);

        const signal = channel.listen.mock.calls.at(-1)[1];
        const initialInstance = screen.getByTestId('fake-excalidraw').dataset.instance;
        signal({ revision: 2 });
        await waitFor(() =>
            expect(screen.getByTestId('fake-excalidraw').dataset.instance).not.toBe(
                initialInstance,
            ),
        );
        await new Promise((resolve) => setTimeout(resolve, 10));
        excalidrawProps.onChange(
            refreshedScene.elements,
            refreshedScene.appState,
            refreshedScene.files,
        );
        await new Promise((resolve) => setTimeout(resolve, 675));

        expect(fetch).toHaveBeenCalledTimes(2);

        excalidrawProps.onChange(
            [{ id: 'local', type: 'rectangle' }],
            refreshedScene.appState,
            refreshedScene.files,
        );
        await new Promise((resolve) => setTimeout(resolve, 675));

        expect(fetch).toHaveBeenCalledTimes(3);
        expect(fetch).toHaveBeenLastCalledWith(
            '/save',
            expect.objectContaining({
                headers: expect.objectContaining({
                    'Content-Type': 'application/json',
                    'X-Socket-ID': 'socket-123',
                }),
                method: 'PUT',
            }),
        );
    });
});

describe('findRequestedFrame', () => {
    const elements = [
        { id: 'shape', type: 'rectangle' },
        { id: 'first', name: 'Opening', type: 'frame' },
        { id: 'deleted', isDeleted: true, name: 'Old', type: 'frame' },
        { id: 'second', name: 'Results', type: 'frame' },
    ];

    it('selects live frames by one-based index', () => {
        expect(findRequestedFrame(elements, '2')?.id).toBe('second');
        expect(findRequestedFrame(elements, '0')).toBeNull();
    });

    it('selects live frames by case-insensitive name', () => {
        expect(findRequestedFrame(elements, 'rEsUlTs')?.id).toBe('second');
        expect(findRequestedFrame(elements, 'Old')).toBeNull();
    });
});
