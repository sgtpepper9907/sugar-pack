<?php

namespace SugarPack\Http;

use GuzzleHttp\Client;
use Phpfastcache\CacheManager;
use Psr\Http\Message\ResponseInterface;
use stdClass;

class SugarCient
{
    /**
     * Underlying HTTP Client
     *
     * @var \GuzzleHttp\Client
     */
    private $client = null;

    /**
     * @var \Phpfastcache\Core\Pool\ExtendedCacheItemPoolInterface
     */
    private $cache = null;

    private $instance;
    private $user;
    private $password;
    private $platform;

    private const TOKEN_CACHE_KEY = 'sugarcrm_token';

    public function __construct(
        string $instance,
        string $user,
        string $password,
        ?string $platform = 'base'
    ) {
        $this->client = new Client([
            'base_uri' => $instance,
            'http_errors' => false
        ]);

        $this->instance = $instance;
        $this->user = $user;
        $this->password = $password;
        $this->platform = $platform ?? 'base';
        $this->cache = CacheManager::getInstance('files');
    }

    public function getAccessToken(): string
    {
        $token = $this->readFromCache();

        if (!is_null($token)) {
            return $token;
        }

        [$token, $ttl] = $this->generateNewAccessToken();
        $this->writeTokenToCache($token, $ttl);

        return $token;
    }

    public function getInstalledPackage(string $name): ?stdClass
    {
        $response = $this->client->get('/rest/v11/Administration/packages/installed', [
            'headers' => [
                'oauth-token' => $this->getAccessToken()
            ]
        ]);

        $this->handleResponseErrors($response);

        $jsonResponse = @json_decode($response->getBody());

        foreach ($jsonResponse->packages as $package) {
            if ($package->name == $name) {
                return $package;
            }
        }

        return null;
    }

    public function uninstallPackageById(string $id): void
    {
        $response = $this->client->get("/rest/v11/Administration/packages/{$id}/uninstall", [
            'headers' => [
                'oauth-token' => $this->getAccessToken()
            ]
        ]);

        $this->handleResponseErrors($response);
    }

    public function deleteStagedPackage(string $name): void
    {
        $stagedPackage = $this->getStagedPackage($name);

        if (is_null($stagedPackage)) {
            return;
        }

        $response = $this->client->delete("/rest/v11/Administration/packages/{$stagedPackage->unFile}", [
            'headers' => [
                'oauth-token' => $this->getAccessToken()
            ]
        ]);

        $this->handleResponseErrors($response);
    }

    public function getStagedPackage(string $name): ?stdClass
    {
        $response = $this->client->get('/rest/v11/Administration/packages/staged', [
            'headers' => [
                'oauth-token' => $this->getAccessToken()
            ]
        ]);

        $this->handleResponseErrors($response);
        $jsonResponse = @json_decode($response->getBody());

        foreach ($jsonResponse->packages as $package) {
            if ($package->name == $name) {
                return $package;
            }
        }

        return null;
    }

    public function uploadPackage($zipResource, callable $progressCallback = null): string
    {
        $response = $this->client->post('/rest/v11/Administration/packages', [
            'headers' => [
                'oauth-token' => $this->getAccessToken()
            ],
            'multipart' => [
                [
                    'name' => 'upgrade_zip',
                    'contents' => $zipResource
                ]
            ],
            'progress' => $progressCallback ?? function () {
            }
        ]);

        $this->handleResponseErrors($response);
        $jsonResponse = @json_decode($response->getBody());

        return $jsonResponse->file_install;
    }

    public function installPackage(string $id) : void
    {
        $response = $this->client->get("/rest/v11/Administration/packages/$id/install", [
            'headers' => [
                'oauth-token' => $this->getAccessToken()
            ]
        ]);

        $this->handleResponseErrors($response);
    }

    private function readFromCache(): ?string
    {
        $item = $this->cache->getItem($this->cacheKey());

        return $item->get();
    }

    private function generateNewAccessToken(): array
    {
        $response = $this->client->post('/rest/v11/oauth2/token', [
            'json' => [
                'grant_type' => 'password',
                'client_id' => 'sugar',
                'client_secret' => '',
                'username' => $this->user,
                'password' => $this->password,
                'platform' => $this->platform
            ]
        ]);

        $this->handleResponseErrors($response);

        $jsonResponse = @json_decode($response->getBody());
        
        return [$jsonResponse->access_token, $jsonResponse->expires_in];
    }

    private function writeTokenToCache(string $token, int $ttl): void
    {
        $item = $this->cache->getItem($this->cacheKey())
                            ->set($token)
                            ->expiresAfter($ttl);

        $this->cache->save($item);
    }

    private function handleResponseErrors(ResponseInterface $response): void
    {
        $jsonResponse = @json_decode($response->getBody());

        if ($response->getStatusCode() < 300) {
            return;
        }

        $message =  $jsonResponse->error_description
                    ?? $jsonResponse->error_message
                    ?? $response->getReasonPhrase();

        throw new \Exception("Sugar response error: {$message}");
    }

    private function cacheKey() : string
    {
        return self::TOKEN_CACHE_KEY . ':' . parse_url($this->instance, PHP_URL_HOST);
    }
}
