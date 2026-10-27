export function findRequestedFrame(elements, requestedFrame) {
    if (!requestedFrame) {
        return null;
    }

    const frames = elements.filter(
        (element) => element.type === 'frame' && !element.isDeleted,
    );

    if (/^[1-9]\d*$/.test(requestedFrame)) {
        return frames[Number(requestedFrame) - 1] ?? null;
    }

    const requestedName = requestedFrame.toLocaleLowerCase();

    return (
        frames.find(
            (frame) => frame.name?.toLocaleLowerCase() === requestedName,
        ) ?? null
    );
}
