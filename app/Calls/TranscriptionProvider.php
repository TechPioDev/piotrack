<?php

namespace App\Calls;

/**
 * CALL-004: the speech-to-text seam. The fixture driver ships and is tested;
 * a live driver (Whisper API, Deepgram, AssemblyAI, …) is credentials plus a
 * class implementing this contract — the same pattern as SMS and ads.
 */
interface TranscriptionProvider
{
    /**
     * Transcribe the recording at the given URL to plain text.
     */
    public function transcribe(string $recordingUrl): string;

    public function name(): string;
}
