import { describe, expect, it } from 'vitest';

import { renderMarkdown } from './renderMarkdown';

describe('renderMarkdown', () => {
    it('renders supported Markdown', () => {
        const html = renderMarkdown('# Heading\n\n**Safe** [link](https://example.com)');

        expect(html).toContain('<h1>Heading</h1>');
        expect(html).toContain('<strong>Safe</strong>');
        expect(html).toContain('href="https://example.com"');
    });

    it.each([
        '<script>alert(1)</script>',
        '<img src=x onerror=alert(1)>',
        '<svg><script>alert(1)</script></svg>',
        '[unsafe](javascript:alert(1))',
        '[encoded](&#x6a;avascript:alert(1))',
        '<a href="javascript:alert(1)" onclick="alert(1)">unsafe</a>',
    ])('neutralizes unsafe markup: %s', (payload) => {
        const html = renderMarkdown(payload);
        const document = new DOMParser().parseFromString(html, 'text/html');

        expect(document.querySelector('script, svg, iframe, object, embed')).toBeNull();
        expect(document.querySelector('[onerror], [onclick], [style]')).toBeNull();
        expect(document.querySelector('[href^="javascript:"]')).toBeNull();
        expect(document.querySelector('[src^="javascript:"]')).toBeNull();
    });
});
