<?php

namespace App\Services\Chat;

use App\Models\ChatMessage;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Files visitors send in the chat. Raster images are shown in the conversation
 * itself - to the visitor who sent them and to the team - because that is what
 * anyone expects of a chat. Everything else is only ever downloaded: the files
 * come from anonymous people on the internet.
 */
class ChatAttachments
{
    /** Types a browser can show inline without running anything. SVG is deliberately absent. */
    public const INLINE_IMAGES = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];

    /** @return array{path: string, name: string, size: int, mime: string|null}|null */
    public function of(ChatMessage $message): ?array
    {
        $attachment = $message->meta['attachment'] ?? null;
        if (! is_array($attachment) || ! is_string($attachment['path'] ?? null)) {
            return null;
        }

        return [
            'path' => $attachment['path'],
            'name' => (string) ($attachment['name'] ?? 'attachment'),
            'size' => (int) ($attachment['size'] ?? 0),
            'mime' => isset($attachment['mime']) ? (string) $attachment['mime'] : null,
        ];
    }

    public function isImage(ChatMessage $message): bool
    {
        return in_array($this->of($message)['mime'] ?? null, self::INLINE_IMAGES, true);
    }

    /**
     * The file itself: an image shown inline when asked for, anything else as
     * a download. The type sent is the one the server detected from the bytes
     * at upload, never the name, and the browser is told not to guess.
     */
    public function respond(ChatMessage $message, bool $inline): StreamedResponse
    {
        $attachment = $this->of($message);
        abort_if($attachment === null || ! Storage::disk('local')->exists($attachment['path']), 404);

        $headers = ['X-Content-Type-Options' => 'nosniff'];

        if ($inline && $this->isImage($message)) {
            return Storage::disk('local')->response($attachment['path'], $attachment['name'], [
                ...$headers,
                'Content-Type' => (string) $attachment['mime'],
                'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox",
                'Cache-Control' => 'private, max-age=86400',
            ]);
        }

        return Storage::disk('local')->download($attachment['path'], $attachment['name'], $headers);
    }
}
