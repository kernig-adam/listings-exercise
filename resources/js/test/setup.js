import { afterEach } from 'vitest';
import { enableAutoUnmount } from '@vue/test-utils';

// Mounted with attachTo: document.body, so unmount between tests or axe sees leftovers.
enableAutoUnmount(afterEach);
