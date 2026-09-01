<?php

namespace App\Services\Seo;

use App\Models\Keyword;
use App\Models\SeoLocation;
use App\Seo\MspKeywordLibrary;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * Keyword inventory operations (KSEO-001..015): the curated MSP research
 * library seeder, clustering by primary topic token, keyword→page mapping,
 * and content-gap (keywords with no mapped page).
 */
class KeywordService
{
    /** Generic terms stripped before choosing a cluster token. */
    private const STOPWORDS = ['the', 'a', 'an', 'for', 'of', 'in', 'to', 'and', 'best', 'top', 'msp', 'it', 'services', 'service', 'company', 'near', 'me'];

    /**
     * Seed the curated MSP keyword research library (KSEO-001..012): typed,
     * intent-classified research candidates. Untracked on arrival — the team
     * reviews and enables tracking per keyword, so the daily sweep never blasts
     * an unreviewed list. Volume/difficulty stay null until a data provider
     * fills them; nothing is invented. Geo variants multiply the buying-stage
     * phrases across the tenant's own locations (Phase-2 geo pipeline).
     *
     * @return array{created: int, skipped: int}
     */
    public function seedMspLibrary(bool $withGeoVariants = false): array
    {
        $existing = Keyword::pluck('phrase')->map(fn ($p) => Str::lower((string) $p))->flip()->all();
        $created = $skipped = 0;

        $cities = $withGeoVariants
            ? SeoLocation::where('is_active', true)->get()
                ->map(fn (SeoLocation $l) => trim((string) $l->city) !== ''
                    ? ['city' => trim((string) $l->city), 'market' => trim((string) $l->city).(trim((string) $l->region) !== '' ? ', '.trim((string) $l->region) : '')]
                    : null)
                ->filter()->unique('city')->values()
            : collect();

        foreach (MspKeywordLibrary::entries() as $entry) {
            $phrase = Str::lower($entry['phrase']);
            if (isset($existing[$phrase])) {
                $skipped++;
            } else {
                Keyword::create(['phrase' => $phrase, 'type' => $entry['type'], 'intent' => $entry['intent'], 'is_tracked' => false]);
                $existing[$phrase] = true;
                $created++;
            }

            if (! in_array($entry['type'], MspKeywordLibrary::GEO_TYPES, true)) {
                continue;
            }

            foreach ($cities as $market) {
                $geoPhrase = Str::lower($entry['phrase'].' '.$market['city']);
                if (isset($existing[$geoPhrase])) {
                    $skipped++;

                    continue;
                }
                Keyword::create([
                    'phrase' => $geoPhrase,
                    'type' => $entry['type'],
                    'intent' => $entry['intent'],
                    'location' => $market['market'],
                    'is_tracked' => false,
                ]);
                $existing[$geoPhrase] = true;
                $created++;
            }
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * Assign each tracked keyword a cluster derived from its primary topic token.
     */
    public function recluster(): int
    {
        $keywords = Keyword::all();

        foreach ($keywords as $keyword) {
            $keyword->update(['cluster' => $this->primaryToken($keyword->phrase)]);
        }

        return $keywords->count();
    }

    /**
     * Keywords with no mapped page — the content gap (KSEO-015).
     *
     * @return Collection<int, Keyword>
     */
    public function contentGap(): Collection
    {
        return Keyword::whereNull('mapped_url')->orderBy('phrase')->get();
    }

    public function primaryToken(string $phrase): string
    {
        $tokens = preg_split('/\s+/', mb_strtolower(trim($phrase))) ?: [];
        $tokens = array_values(array_filter(
            $tokens,
            fn (string $t) => ! in_array($t, self::STOPWORDS, true) && mb_strlen($t) > 2,
        ));

        return $tokens[0] ?? 'general';
    }
}
