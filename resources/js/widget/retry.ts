/**
 * Retry policy for the chat widget's API calls.
 *
 * The widget treats a failed request as terminal: the visitor is told chat is
 * unavailable and the conversation stops there. That is the right response to a
 * paused widget or a blocked origin, and far too harsh for a dropped connection
 * or a server that happened to be restarting — one blip would end a sales
 * conversation mid-question with no way back.
 *
 * So a request is tried once more when the failure looks momentary, and not when
 * it does not. A 4xx is a deterministic answer — validation, gone, throttled —
 * and repeating it only makes the visitor wait longer for the same outcome.
 *
 * Extracted from the widget entry point so it can be tested on its own: importing
 * that module would mount the widget.
 */

const RETRY_PAUSE_MS = 700;

const pause = (ms: number) => new Promise((resolve) => setTimeout(resolve, ms));

/**
 * @param run   performs the request; called again if the first attempt looks
 *              momentarily broken
 * @param delay overridable so tests need not wait in real time
 */
export async function fetchWithRetry(run: () => Promise<Response>, delay: number = RETRY_PAUSE_MS): Promise<Response> {
    let response: Response;

    try {
        response = await run();
    } catch {
        // Never reached the server: DNS, a dropped connection, a sleeping laptop.
        await pause(delay);

        return run();
    }

    if (response.status >= 500) {
        // Reached it, and it was in no state to answer.
        await pause(delay);

        return run();
    }

    return response;
}
