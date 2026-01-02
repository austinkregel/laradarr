<?php
declare(strict_types=1);

namespace App\Services;

use App\Contracts\MagnetParserContract;

class MagnetParser implements MagnetParserContract
{
    /**
     * Parse a magnet URL and extract available metadata.
     *
     * @return array{title: string|null, size: int|null, infoHash: string|null, trackers: string[]}
     */
    public static function parse(string $magnetUrl): array
    {
        if (!str_starts_with($magnetUrl, 'magnet:')) {
            return [
                'title' => null,
                'size' => null,
                'infoHash' => null,
                'trackers' => [],
            ];
        }

        try {
            $url = parse_url($magnetUrl);
            if (!isset($url['query'])) {
                return [
                    'title' => null,
                    'size' => null,
                    'infoHash' => null,
                    'trackers' => [],
                ];
            }

            parse_str($url['query'], $params);

            // Extract display name (title)
            $title = null;
            if (isset($params['dn'])) {
                $title = urldecode($params['dn']);
            }

            // Extract file size (exact length in bytes)
            $size = null;
            if (isset($params['xl'])) {
                $size = (int) $params['xl'];
            }

            // Extract info hash from exact topic
            $infoHash = null;
            if (isset($params['xt'])) {
                // xt format: urn:btih:HASH or urn:sha1:HASH
                if (preg_match('/urn:(?:btih|sha1):([a-fA-F0-9]{40})/', $params['xt'], $matches)) {
                    $infoHash = strtolower($matches[1]);
                }
            }

            // Extract trackers
            $trackers = [];
            if (isset($params['tr'])) {
                $trackerList = is_array($params['tr']) ? $params['tr'] : [$params['tr']];
                foreach ($trackerList as $tracker) {
                    $decoded = urldecode($tracker);
                    if (!empty($decoded)) {
                        $trackers[] = $decoded;
                    }
                }
            }

            return [
                'title' => $title,
                'size' => $size,
                'infoHash' => $infoHash,
                'trackers' => $trackers,
            ];
        } catch (\Exception $e) {
            // If parsing fails, return empty data
            return [
                'title' => null,
                'size' => null,
                'infoHash' => null,
                'trackers' => [],
            ];
        }
    }
}

