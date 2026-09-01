<?php

namespace App\Services\Integrations;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Generic OAuth2 authorization-code flow (INTG-001). Provider apps are pure
 * configuration — `services.connectors.{key}` supplies client id/secret and
 * endpoints from env — so registering a new OAuth vendor is config, not code.
 * Tokens land in the Integration model's encrypted credentials vault.
 */
class OAuthFlow
{
    /**
     * @return array{client_id: string, client_secret: string, authorize_url: string, token_url: string, scopes: string}|null
     */
    public function config(string $key): ?array
    {
        $config = config("services.connectors.{$key}");
        if (! is_array($config)) {
            return null;
        }

        foreach (['client_id', 'client_secret', 'authorize_url', 'token_url'] as $required) {
            if (empty($config[$required])) {
                return null;
            }
        }

        return [
            'client_id' => (string) $config['client_id'],
            'client_secret' => (string) $config['client_secret'],
            'authorize_url' => (string) $config['authorize_url'],
            'token_url' => (string) $config['token_url'],
            'scopes' => (string) ($config['scopes'] ?? ''),
        ];
    }

    public function isConfigured(string $key): bool
    {
        return $this->config($key) !== null;
    }

    public function authorizeUrl(string $key, string $state): string
    {
        $config = $this->config($key) ?? throw new RuntimeException("OAuth connector {$key} is not configured.");

        return $config['authorize_url'].'?'.http_build_query(array_filter([
            'client_id' => $config['client_id'],
            'redirect_uri' => route('integrations.oauth.callback', $key),
            'response_type' => 'code',
            'scope' => $config['scopes'] !== '' ? $config['scopes'] : null,
            'state' => $state,
        ]));
    }

    /**
     * Exchange the authorization code for tokens.
     *
     * @return array{access_token: string, refresh_token: ?string, expires_at: ?string}
     */
    public function exchange(string $key, string $code): array
    {
        $config = $this->config($key) ?? throw new RuntimeException("OAuth connector {$key} is not configured.");

        $response = Http::asForm()->post($config['token_url'], [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'redirect_uri' => route('integrations.oauth.callback', $key),
        ]);

        $token = (string) $response->json('access_token', '');
        if (! $response->successful() || $token === '') {
            throw new RuntimeException('Token exchange failed: HTTP '.$response->status());
        }

        $expiresIn = $response->json('expires_in');

        return [
            'access_token' => $token,
            'refresh_token' => $response->json('refresh_token'),
            'expires_at' => is_numeric($expiresIn) ? now()->addSeconds((int) $expiresIn)->toIso8601String() : null,
        ];
    }
}
