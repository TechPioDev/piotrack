/**
 * Copy text to the clipboard, including on plain HTTP.
 *
 * `navigator.clipboard` only exists in a secure context (HTTPS or localhost).
 * Plenty of installations run over http:// on a private network, where it is
 * simply undefined — so calling it there throws and the copy silently fails.
 * This falls back to the old execCommand path, which still works everywhere.
 *
 * @returns whether the text reached the clipboard, so the caller can tell the
 *          user the truth rather than always claiming success.
 */
export async function copyText(text: string): Promise<boolean> {
    if (typeof navigator !== 'undefined' && navigator.clipboard?.writeText) {
        try {
            await navigator.clipboard.writeText(text);
            return true;
        } catch {
            // Permission denied or blocked — fall through to the legacy path.
        }
    }

    try {
        const area = document.createElement('textarea');
        area.value = text;
        // Keep it out of view and out of the tab order, but still selectable.
        area.setAttribute('readonly', '');
        area.style.position = 'fixed';
        area.style.top = '-1000px';
        area.style.opacity = '0';
        document.body.appendChild(area);

        area.select();
        area.setSelectionRange(0, text.length);
        const copied = document.execCommand('copy');

        document.body.removeChild(area);
        return copied;
    } catch {
        return false;
    }
}
