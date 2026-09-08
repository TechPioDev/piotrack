<?php

namespace App\Seo;

use App\Seo\Contracts\AiSearchProvider;
use App\Seo\Contracts\LinkDataProvider;
use App\Seo\Contracts\RankProvider;
use App\Seo\Contracts\SearchConsoleProvider;
use App\Seo\Contracts\WebVitalsProvider;
use App\Seo\Providers\FixtureAiSearchProvider;
use App\Seo\Providers\FixtureLinkDataProvider;
use App\Seo\Providers\FixtureRankProvider;
use App\Seo\Providers\FixtureSearchConsoleProvider;
use App\Seo\Providers\FixtureWebVitalsProvider;
use App\Seo\Providers\GeminiAiSearchProvider;
use App\Seo\Providers\OpenAiSearchProvider;
use App\Seo\Providers\SerpApiRankProvider;
use InvalidArgumentException;

/**
 * Resolves the active rank / AI-search drivers from config (SEO_RANK_PROVIDER /
 * SEO_AI_PROVIDER). Bound so that type-hinting RankProvider / AiSearchProvider
 * yields the configured driver (mirrors PaymentProviderManager).
 */
class SeoProviderManager
{
    public function rank(?string $name = null): RankProvider
    {
        $name ??= (string) config('seo.rank_provider', 'fixture');

        return match ($name) {
            'fixture' => new FixtureRankProvider,
            'serpapi' => new SerpApiRankProvider,
            default => throw new InvalidArgumentException("Unknown rank provider [{$name}]."),
        };
    }

    /** LINK-001..003: the link-index driver (fixture default). */
    public function links(?string $name = null): LinkDataProvider
    {
        $name ??= (string) config('seo.link_provider', 'fixture');

        return match ($name) {
            'fixture' => new FixtureLinkDataProvider,
            default => throw new InvalidArgumentException("Unknown link data provider [{$name}]."),
        };
    }

    /** TSEO-023: the Search Console driver (fixture default). */
    public function searchConsole(?string $name = null): SearchConsoleProvider
    {
        $name ??= (string) config('seo.search_console_provider', 'fixture');

        return match ($name) {
            'fixture' => new FixtureSearchConsoleProvider,
            default => throw new InvalidArgumentException("Unknown Search Console provider [{$name}]."),
        };
    }

    /** TSEO-019: the CWV field-data driver (fixture default). */
    public function vitals(?string $name = null): WebVitalsProvider
    {
        $name ??= (string) config('seo.vitals_provider', 'fixture');

        return match ($name) {
            'fixture' => new FixtureWebVitalsProvider,
            default => throw new InvalidArgumentException("Unknown web-vitals provider [{$name}]."),
        };
    }

    public function ai(?string $name = null): AiSearchProvider
    {
        $name ??= (string) config('seo.ai_provider', 'fixture');

        return match ($name) {
            'fixture' => new FixtureAiSearchProvider,
            'openai' => new OpenAiSearchProvider(new AnswerAnalyzer),
            'gemini' => new GeminiAiSearchProvider(new AnswerAnalyzer),
            default => throw new InvalidArgumentException("Unknown AI search provider [{$name}]."),
        };
    }

    /**
     * Engine label -> the driver that can actually ask that engine (AIVM).
     * ChatGPT and Gemini have wired APIs and go live the moment a key exists
     * (env or the platform AI console's); every other engine label runs on the
     * configured default so its numbers stay clearly simulated.
     */
    public function aiFor(string $engine): AiSearchProvider
    {
        return match ($this->aiProviderNameFor($engine)) {
            'openai' => new OpenAiSearchProvider(new AnswerAnalyzer),
            'gemini' => new GeminiAiSearchProvider(new AnswerAnalyzer),
            default => $this->ai(),
        };
    }

    /** Which driver name backs an engine label, recorded on every check. */
    public function aiProviderNameFor(string $engine): string
    {
        if ($engine === 'chatgpt' && OpenAiSearchProvider::key() !== '') {
            return 'openai';
        }
        if ($engine === 'gemini' && GeminiAiSearchProvider::key() !== '') {
            return 'gemini';
        }

        return $this->aiProviderName();
    }

    /**
     * live|simulated per engine, so the UI can label numbers honestly.
     *
     * @param  list<string>  $engines
     * @return array<string, string>
     */
    public function engineStatuses(array $engines): array
    {
        $statuses = [];
        foreach ($engines as $engine) {
            $statuses[$engine] = $this->aiProviderNameFor($engine) === 'fixture' ? 'simulated' : 'live';
        }

        return $statuses;
    }

    /** The configured rank driver's name, recorded against every position. */
    public function rankProviderName(): string
    {
        return (string) config('seo.rank_provider', 'fixture');
    }

    /**
     * Whether positions come from a real SERP lookup.
     *
     * The fixture driver derives a position from a hash of the inputs, which is
     * useful for exercising the pipeline and useless as a ranking. Mirrors
     * AiProviderManager::isLive() so the UI can say so plainly rather than
     * presenting a hash as a search result.
     */
    public function isRankLive(): bool
    {
        return $this->rankProviderName() !== 'fixture';
    }

    /** The configured AI-search driver's name, recorded against every check. */
    public function aiProviderName(): string
    {
        return (string) config('seo.ai_provider', 'fixture');
    }

    /**
     * Same question for the AI-search driver behind AI visibility, and with a
     * sharper edge: the fixture driver invents competitor domains and citations,
     * which must never be read as findings about a real market.
     */
    public function isAiLive(): bool
    {
        return $this->aiProviderName() !== 'fixture';
    }
}
