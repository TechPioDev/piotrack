<?php

namespace App\Calls;

/**
 * Deterministic transcription driver for dev/tests. Its output states its own
 * provenance so fixture text can never masquerade as a real transcript.
 */
class FixtureTranscriptionProvider implements TranscriptionProvider
{
    public function transcribe(string $recordingUrl): string
    {
        return sprintf(
            "[Fixture transcript — no speech-to-text credentials configured]\nRecording: %s\nAgent: Thanks for calling, how can I help?\nCaller: We're looking at switching IT providers this quarter.",
            $recordingUrl,
        );
    }

    public function name(): string
    {
        return 'fixture';
    }
}
