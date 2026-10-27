import '@excalidraw/excalidraw/index.css';

import { Excalidraw } from '@excalidraw/excalidraw';
import { createElement } from 'react';
import { createRoot } from 'react-dom/client';

export function mountExcalidraw(element, props = {}) {
    const root = createRoot(element);

    root.render(createElement(Excalidraw, props));

    return () => root.unmount();
}
