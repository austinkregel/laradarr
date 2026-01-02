<?php
declare(strict_types=1);

namespace App\Services;

use App\Contracts\QBittorrentServiceContract;
use App\Exceptions\Integration\ApiException;
use App\Services\DTOs\QBittorrent\TorrentDTO;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class QBittorrentService extends BaseApiClient implements QBittorrentServiceContract
{
    private ?string $sid = null;

    protected function serviceKey(): string
    {
        return 'qbittorrent';
    }

    protected function expectsJson(): bool
    {
        return false; // qBittorrent uses form-encoded requests for some endpoints
    }

    /**
     * Authenticate with qBittorrent and store session ID.
     */
    public function login(): bool
    {
        $username = (string) config('services.qbittorrent.username', 'admin');
        $password = (string) config('services.qbittorrent.password', 'adminadmin');

        try {
            // qBittorrent login uses form-encoded POST
            $response = Http::baseUrl(rtrim($this->baseUrl(), '/'))
                ->asForm()
                ->post('/api/v2/auth/login', [
                    'username' => $username,
                    'password' => $password,
                ]);

            // qBittorrent returns "Ok." on success, "Fails." on failure
            $body = $response->body();
            if ($body === 'Ok.') {
                // Extract SID from Set-Cookie header
                $setCookie = $response->header('Set-Cookie');
                if ($setCookie && preg_match('/SID=([^;]+)/', $setCookie, $matches)) {
                    $this->sid = $matches[1];
                    return true;
                }
                // Try cookies array
                $cookies = $response->cookies();
                foreach ($cookies as $cookie) {
                    if ($cookie->getName() === 'SID') {
                        $this->sid = $cookie->getValue();
                        return true;
                    }
                }
            }

            Log::error('qbittorrent.login.failed', ['response' => $body]);
            return false;
        } catch (\Exception $e) {
            Log::error('qbittorrent.login.exception', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Ensure we're authenticated before making requests.
     */
    private function ensureAuthenticated(): void
    {
        if ($this->sid === null) {
            $this->login();
        }
    }

    protected function defaultHeaders(): array
    {
        $headers = parent::defaultHeaders();
        if ($this->sid !== null) {
            $headers['Cookie'] = 'SID=' . $this->sid;
        }
        return $headers;
    }

    /**
     * Helper to build full URL (needed for error messages).
     */
    protected function fullUrl(string $baseUrl, string $path): string
    {
        return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    }

    /**
     * Override request method to handle form-encoded bodies for qBittorrent.
     */
    protected function request(
        string $method,
        string $path,
        array $query = [],
        array $body = [],
        array $headers = [],
    ): \Illuminate\Http\Client\Response {
        $this->ensureAuthenticated();
        $this->enforceRateLimit();

        $baseUrl = $this->baseUrl();
        if ($baseUrl === '') {
            throw new ApiException(
                service: $this->serviceKey(),
                method: $method,
                url: $path,
                status: null,
                responseBody: null,
                message: 'Missing base URL configuration',
            );
        }

        $start = hrtime(true);
        $attempt = 0;

        try {
            $client = Http::baseUrl(rtrim($baseUrl, '/'))
                ->timeout($this->timeoutSeconds())
                ->withHeaders(array_merge($this->defaultHeaders(), $headers))
                ->withQueryParameters(array_merge($this->defaultQuery(), $query));

            // qBittorrent uses form-encoded for POST requests
            if ($method === 'POST' && !empty($body)) {
                $client = $client->asForm();
            } else {
                $client = $client->acceptJson();
            }

            $response = $client->send($method, ltrim($path, '/'), $body === [] ? [] : $body);

            // Check for errors (403 might mean we need to re-authenticate)
            if ($response->status() === 403 && $attempt === 0) {
                $this->sid = null;
                $this->ensureAuthenticated();
                $attempt++;
                // Retry once
                return $this->request($method, $path, $query, $body, $headers);
            }

            $this->throwIfError($response, $method);
            $this->logSuccess($method, $response, $start, $attempt);

            return $response;
        } catch (ApiException $e) {
            // If we get a 403, try to re-authenticate
            if ($e->status === 403 && $attempt === 0) {
                $this->sid = null;
                $this->ensureAuthenticated();
                $attempt++;
                // Retry once
                return $this->request($method, $path, $query, $body, $headers);
            }
            $this->logFailure($method, $path, $start, $attempt, $e);
            throw $e;
        } catch (\Throwable $e) {
            $this->logFailure($method, $path, $start, $attempt, $e);
            throw new ApiException(
                service: $this->serviceKey(),
                method: $method,
                url: $this->fullUrl($baseUrl, $path),
                status: null,
                responseBody: null,
                message: $e->getMessage(),
                previous: $e,
            );
        }
    }

    /**
     * Get all active torrents.
     *
     * @return Collection<int, TorrentDTO>
     */
    public function getTorrents(array $filters = []): Collection
    {
        $this->ensureAuthenticated();

        $query = [];
        if (!empty($filters)) {
            $query['filter'] = $filters['filter'] ?? 'all';
            if (isset($filters['category'])) {
                $query['category'] = $filters['category'];
            }
            if (isset($filters['tag'])) {
                $query['tag'] = $filters['tag'];
            }
        }

        try {
            // qBittorrent API returns JSON for torrent list
            $response = $this->request('GET', '/api/v2/torrents/info', query: $query);
            $json = $response->json();
            
            if (!is_array($json)) {
                return collect();
            }
            
            return collect($json)
                ->filter(fn ($row) => is_array($row) && isset($row['hash']))
                ->map(fn (array $row) => TorrentDTO::fromArray($row))
                ->values();
        } catch (\Exception $e) {
            Log::error('qbittorrent.get_torrents.exception', ['error' => $e->getMessage()]);
            return collect();
        }
    }

    /**
     * Re-announce a torrent (nudge it to reconnect to trackers).
     */
    public function reannounce(string $hash): bool
    {
        $this->ensureAuthenticated();

        try {
            $response = $this->request('POST', '/api/v2/torrents/reannounce', body: [
                'hashes' => $hash,
            ]);

            return $response->body() === 'Ok.';
        } catch (\Exception $e) {
            Log::error('qbittorrent.reannounce.exception', [
                'hash' => $hash,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Resume a paused torrent.
     */
    public function resume(string $hash): bool
    {
        $this->ensureAuthenticated();

        try {
            $response = $this->request('POST', '/api/v2/torrents/resume', body: [
                'hashes' => $hash,
            ]);

            return $response->body() === 'Ok.';
        } catch (\Exception $e) {
            Log::error('qbittorrent.resume.exception', [
                'hash' => $hash,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Force resume a torrent (resume even if queue is full).
     */
    public function forceResume(string $hash): bool
    {
        $this->ensureAuthenticated();

        try {
            $response = $this->request('POST', '/api/v2/torrents/resume', body: [
                'hashes' => $hash,
            ]);

            // Also try to set priority to maximum
            $this->request('POST', '/api/v2/torrents/increasePrio', body: [
                'hashes' => $hash,
            ]);

            return $response->body() === 'Ok.';
        } catch (\Exception $e) {
            Log::error('qbittorrent.force_resume.exception', [
                'hash' => $hash,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get torrent files for a specific torrent.
     *
     * @return array<int, array{name: string, size: int, progress: float, priority: int, is_seed: bool, piece_range: array{0: int, 1: int}}>
     */
    public function getTorrentFiles(string $hash): array
    {
        $this->ensureAuthenticated();

        try {
            $response = $this->request('GET', '/api/v2/torrents/files', query: ['hash' => $hash]);
            $json = $response->json();

            return is_array($json) ? $json : [];
        } catch (\Exception $e) {
            Log::error('qbittorrent.get_torrent_files.exception', [
                'hash' => $hash,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Get torrent properties including content path.
     */
    public function getTorrentProperties(string $hash): ?array
    {
        $this->ensureAuthenticated();

        try {
            $response = $this->request('GET', '/api/v2/torrents/properties', query: ['hash' => $hash]);
            $json = $response->json();

            return is_array($json) ? $json : null;
        } catch (\Exception $e) {
            Log::error('qbittorrent.get_torrent_properties.exception', [
                'hash' => $hash,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Delete a torrent (and optionally its files).
     *
     * @param bool $deleteFiles If true, also delete the downloaded files
     */
    public function deleteTorrent(string $hash, bool $deleteFiles = false): bool
    {
        $this->ensureAuthenticated();

        try {
            $endpoint = $deleteFiles ? '/api/v2/torrents/delete' : '/api/v2/torrents/delete';
            $response = $this->request('POST', $endpoint, body: [
                'hashes' => $hash,
                'deleteFiles' => $deleteFiles ? 'true' : 'false',
            ]);

            return $response->body() === 'Ok.';
        } catch (\Exception $e) {
            Log::error('qbittorrent.delete_torrent.exception', [
                'hash' => $hash,
                'delete_files' => $deleteFiles,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}

