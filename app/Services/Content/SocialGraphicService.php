<?php

namespace App\Services\Content;

use App\Models\BrandProfile;
use App\Models\SocialPost;

/**
 * SOC-009: template-based social graphics generated from REAL brand assets —
 * the post's own text on a card in the brand palette. Generated collateral
 * from recorded facts (the P22 style-guide precedent); bespoke creative
 * stays designer work (BRAND-019).
 */
class SocialGraphicService
{
    /**
     * A 1200×630 SVG card for the post.
     */
    public function svg(SocialPost $post): string
    {
        $brand = BrandProfile::first();
        $palette = $brand->palette ?? [];
        $primary = $this->firstColor($palette) ?? '#0f172a';
        $accent = $this->secondColor($palette) ?? '#38bdf8';
        $name = $brand?->legal_name ?: 'Your MSP';

        $lines = $this->wrap((string) $post->body, 34, 6);
        $text = '';
        $y = 260;
        foreach ($lines as $line) {
            $text .= '<text x="80" y="'.$y.'" font-family="Arial, sans-serif" font-size="44" font-weight="600" fill="#ffffff">'.e($line).'</text>';
            $y += 62;
        }

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="630" viewBox="0 0 1200 630">
  <rect width="1200" height="630" fill="{$primary}"/>
  <rect x="0" y="0" width="1200" height="12" fill="{$accent}"/>
  <text x="80" y="120" font-family="Arial, sans-serif" font-size="30" font-weight="700" fill="{$accent}" letter-spacing="4">{$this->esc($name)}</text>
  {$text}
  <rect x="80" y="540" width="64" height="6" fill="{$accent}"/>
  <text x="80" y="590" font-family="Arial, sans-serif" font-size="22" fill="#ffffffb0">{$this->esc(ucfirst((string) $post->channel))} · {$this->esc($name)}</text>
</svg>
SVG;
    }

    /**
     * @param  array<string, string>  $palette
     */
    private function firstColor(array $palette): ?string
    {
        foreach ($palette as $value) {
            if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $value)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $palette
     */
    private function secondColor(array $palette): ?string
    {
        $seen = 0;
        foreach ($palette as $value) {
            if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $value)) {
                $seen++;
                if ($seen === 2) {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function wrap(string $text, int $width, int $maxLines): array
    {
        $words = preg_split('/\s+/', trim($text)) ?: [];
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current.' '.$word;
            if (mb_strlen($candidate) > $width && $current !== '') {
                $lines[] = $current;
                $current = $word;
                if (count($lines) === $maxLines - 1) {
                    break;
                }
            } else {
                $current = $candidate;
            }
        }
        if ($current !== '' && count($lines) < $maxLines) {
            $lines[] = mb_strlen($current) > $width ? mb_substr($current, 0, $width - 1).'…' : $current;
        }

        return $lines;
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1);
    }
}
