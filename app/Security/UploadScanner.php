<?php

namespace App\Security;

use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * First-party upload content scanning (SEC-003): refuses files whose CONTENT
 * contradicts what they claim to be, before anything touches storage. This is
 * heuristic scanning — EICAR, executable headers, script payloads in
 * media/documents, magic-byte verification — not an AV engine; a ClamAV-class
 * scanner slots in behind the same seam when the deployment provides one.
 */
class UploadScanner
{
    /** The standard AV test string (its first 30 bytes, per the EICAR spec). */
    private const EICAR = 'X5O!P%@AP[4\\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*';

    /** Leading magic bytes each extension must actually carry. */
    private const MAGIC = [
        'pdf' => ['%PDF'],
        'png' => ["\x89PNG"],
        'jpg' => ["\xFF\xD8\xFF"],
        'jpeg' => ["\xFF\xD8\xFF"],
        'gif' => ['GIF87a', 'GIF89a'],
        'webp' => ['RIFF'],
        'docx' => ["PK\x03\x04"],
        'xlsx' => ["PK\x03\x04"],
        'pptx' => ["PK\x03\x04"],
        'zip' => ["PK\x03\x04"],
    ];

    /** Extensions whose files legitimately contain arbitrary text. */
    private const TEXTUAL = ['csv', 'txt', 'doc', 'xls', 'ppt'];

    /**
     * @throws ValidationException when the file must not be stored
     */
    public function scan(UploadedFile $file): void
    {
        $this->assertContentSafe($this->head($file), strtolower((string) $file->getClientOriginalExtension()));
    }

    /**
     * The content checks themselves, separated from file I/O so the EICAR
     * branch stays testable: a host AV (Windows Defender included) blocks the
     * EICAR file from ever existing on disk, which is the one payload an
     * end-to-end fixture therefore cannot carry.
     *
     * @throws ValidationException
     */
    public function assertContentSafe(string $head, string $extension): void
    {
        if (str_contains($head, self::EICAR)) {
            $this->refuse('The file matches the EICAR malware test signature.');
        }

        // Executable headers, whatever the file claims to be.
        if (str_starts_with($head, 'MZ') || str_starts_with($head, "\x7FELF")) {
            $this->refuse('Executable files cannot be uploaded.');
        }

        // The claimed type must match the bytes actually in the file.
        if (isset(self::MAGIC[$extension])) {
            $matches = false;
            foreach (self::MAGIC[$extension] as $magic) {
                if (str_starts_with($head, $magic)) {
                    $matches = true;
                    break;
                }
            }
            if (! $matches) {
                $this->refuse('The file content does not match its claimed type.');
            }
        }

        // Script payloads hiding in files that should never contain script.
        // SVG is markup by nature but inline <script> in an uploaded SVG is
        // almost always hostile, so it gets the same treatment.
        if (! in_array($extension, self::TEXTUAL, true)) {
            foreach (['<?php', '<%', '<script'] as $needle) {
                if (stripos($head, $needle) !== false) {
                    $this->refuse('The file contains embedded script content.');
                }
            }
        }
    }

    /**
     * The first 64KB of the upload. Fails CLOSED: a file whose content cannot
     * be read cannot be scanned, so it is refused. Windows tmpfile() share
     * semantics block reopening a Testing\File by path, so the fake's own open
     * handle is used there — real uploads are ordinary tmp files.
     */
    private function head(UploadedFile $file): string
    {
        $content = @file_get_contents((string) $file->getRealPath(), length: 65536);

        if ($content === false && $file instanceof File && is_resource($file->tempFile)) {
            rewind($file->tempFile);
            $content = stream_get_contents($file->tempFile, 65536);
            rewind($file->tempFile);
        }

        if ($content === false) {
            $this->refuse('The file content could not be read for scanning.');
        }

        return $content;
    }

    private function refuse(string $reason): never
    {
        throw ValidationException::withMessages(['file' => __('Upload refused: :reason', ['reason' => $reason])]);
    }
}
