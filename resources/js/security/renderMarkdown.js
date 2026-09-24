import DOMPurify from 'dompurify';
import { marked, Renderer } from 'marked';

const escapeHtml = (value) => value
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');

const renderer = new Renderer();

renderer.html = ({ text }) => escapeHtml(text);

export function renderMarkdown(markdown) {
    const html = marked.parse(String(markdown ?? ''), {
        async: false,
        gfm: true,
        renderer,
    });

    return DOMPurify.sanitize(html, {
        USE_PROFILES: { html: true },
        FORBID_TAGS: ['form', 'iframe', 'object', 'embed', 'style', 'template'],
        FORBID_ATTR: ['style'],
    });
}
