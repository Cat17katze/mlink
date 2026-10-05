<?php
declare(strict_types=1);

$platforms = [
    'spotify'      => 'Spotify',
    'deezer'       => 'Deezer',
    'youtube'      => 'YouTube',
    'youtubeMusic' => 'YouTube Music',
    'appleMusic'   => 'Apple Music',
    'tidal'        => 'Tidal',
    'amazonMusic'  => 'Amazon Music',
    'soundcloud'   => 'SoundCloud',
    'bandcamp'     => 'Bandcamp',
];

function cleanTrackingParameters(string $url): string {
    $parsed = parse_url($url);
    if ($parsed === false || !isset($parsed['host'])) {
        return $url;
    }

    $trackingKeys = [
        'si', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 
        'utm_content', 'fbclid', 'igshid', '_ga', 'gclid', 'ref', 'source'
    ];

    if (isset($parsed['query'])) {
        parse_str($parsed['query'], $queryParams);
        foreach ($trackingKeys as $key) {
            unset($queryParams[$key]);
        }
        $parsed['query'] = http_build_query($queryParams);
    }

    $cleanUrl = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');
    if (isset($parsed['port'])) {
        $cleanUrl .= ':' . $parsed['port'];
    }
    if (isset($parsed['path'])) {
        $cleanUrl .= $parsed['path'];
    }
    if (!empty($parsed['query'])) {
        $cleanUrl .= '?' . $parsed['query'];
    }
    return $cleanUrl;
}

function formatReleaseDate(?string $rawDate): ?string {
    if (!$rawDate) {
        return null;
    }
    try {
        $dt = new DateTimeImmutable($rawDate);
        return $dt->format('d.m.Y');
    } catch (Exception $e) {
        return $rawDate;
    }
}

/**
 * Führt einen cURL-GET-Request mit Standardkonfiguration durch.
 */
function curlFetch(string $url): array {
    $ch = curl_init();
    $options = [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
        CURLOPT_FOLLOWLOCATION => true,
    ];

    $debianCaBundle = '/etc/ssl/certs/ca-certificates.crt';
    if (file_exists($debianCaBundle)) {
        $options[CURLOPT_CAINFO] = $debianCaBundle;
    }

    curl_setopt_array($ch, $options);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErrno = curl_errno($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    return [$response, $httpCode, $curlErrno, $curlError];
}

/**
 * Sucht Songs via Deezer Search API und wählt das wahrscheinlichste Original.
 */
function searchMusicQuery(string $query): ?array {
    $searchUrl = 'https://api.deezer.com/search?' . http_build_query([
        'q'     => $query,
        'limit' => 15,
    ]);

    [$response, $httpCode, $errno, $err] = curlFetch($searchUrl);
    if ($errno !== 0 || $httpCode !== 200 || !$response) {
        return null;
    }

    $json = json_decode($response, true);
    if (!isset($json['data']) || !is_array($json['data']) || count($json['data']) === 0) {
        return null;
    }

    $items = $json['data'];
    $totalFound = count($items);

    // Score-Berechnung: Originale bevorzugen, Remixe/Tributes abwerten
    $bestItem = null;
    $bestScore = -999.0;
    $queryLower = mb_strtolower($query);

    foreach ($items as $item) {
        $title = $item['title'] ?? '';
        $artist = $item['artist']['name'] ?? '';
        $combined = mb_strtolower("{$artist} {$title}");

        similar_text($queryLower, $combined, $similarity);
        $score = $similarity;

        $titleLower = mb_strtolower($title);
        // Abwertungen für nicht-originale Versionen
        if (str_contains($titleLower, 'karaoke') || str_contains($titleLower, 'tribute')) {
            $score -= 40;
        } elseif (str_contains($titleLower, 'remix') || str_contains($titleLower, 'remaster')) {
            $score -= 15;
        } elseif (str_contains($titleLower, 'live')) {
            $score -= 10;
        }

        if ($score > $bestScore) {
            $bestScore = $score;
            $bestItem = $item;
        }
    }

    return [
        'item'        => $bestItem ?? $items[0],
        'totalFound'  => $totalFound,
        'hasMultiple' => $totalFound > 1,
    ];
}

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$isJsonRequest = stripos($contentType, 'application/json') !== false;

$input = '';

if ($isJsonRequest) {
    $rawInput = file_get_contents('php://input');
    $requestData = json_decode($rawInput, true);
    $input = isset($requestData['url']) ? trim((string)$requestData['url']) : '';
} elseif (isset($_POST['url'])) {
    $input = trim((string)$_POST['url']);
}

$resultData = null;
$errorMessage = null;
$httpStatusCode = 200;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($input === '') {
        $httpStatusCode = 400;
        $errorMessage = 'Bitte einen Titel, Interpreten oder Streaming-Link eingeben.';
    } else {
        $targetUrl = '';
        $hasMultipleResults = false;
        $totalFoundResults = 1;
        $isTextSearch = !filter_var($input, FILTER_VALIDATE_URL);

        if ($isTextSearch) {
            // Textsuche über Deezer
            $searchResult = searchMusicQuery($input);
            if (!$searchResult || !isset($searchResult['item']['link'])) {
                $httpStatusCode = 404;
                $errorMessage = "Keine passenden Titel für '{$input}' gefunden.";
            } else {
                $targetUrl = $searchResult['item']['link'];
                $hasMultipleResults = $searchResult['hasMultiple'];
                $totalFoundResults = $searchResult['totalFound'];
            }
        } else {
            // Direkte URL-Eingabe
            $targetUrl = cleanTrackingParameters($input);
        }

        if (!$errorMessage) {
            $apiUrl = 'https://api.songlink.co/v1-alpha.1/links?' . http_build_query([
                'url'         => $targetUrl,
                'userCountry' => 'DE',
            ]);

            [$response, $httpCode, $curlErrno, $curlError] = curlFetch($apiUrl);

            if ($curlErrno !== 0) {
                $httpStatusCode = 500;
                $errorMessage = "cURL-Fehler [{$curlErrno}]: {$curlError}";
            } elseif ($httpCode === 404) {
                $httpStatusCode = 404;
                $errorMessage = 'Titel/Album konnte auf den Streaming-Plattformen nicht zugeordnet werden.';
            } elseif ($httpCode !== 200 || !$response) {
                $httpStatusCode = 502;
                $errorMessage = 'Fehler beim Abruf von Songlink (HTTP ' . $httpCode . ').';
            } else {
                $data = json_decode($response, true);
                if (!is_array($data) || !isset($data['entityUniqueId'], $data['entitiesByUniqueId'])) {
                    $httpStatusCode = 502;
                    $errorMessage = 'Unerwartetes Datenformat von Songlink empfangen.';
                } else {
                    $mainEntity = $data['entitiesByUniqueId'][$data['entityUniqueId']] ?? [];
                    $title = $mainEntity['title'] ?? 'Unbekannter Titel';
                    $artist = $mainEntity['artistName'] ?? 'Unbekannter Interpret';
                    $thumbnail = $mainEntity['thumbnailUrl'] ?? null;
                    $releaseDate = formatReleaseDate($mainEntity['releaseDate'] ?? null);

                    $foundLinks = [];
                    $linksByPlatform = $data['linksByPlatform'] ?? [];

                    foreach ($platforms as $key => $name) {
                        if (isset($linksByPlatform[$key]['url'])) {
                            $foundLinks[] = [
                                'platform' => $name,
                                'url'      => cleanTrackingParameters($linksByPlatform[$key]['url'])
                            ];
                        }
                    }

                    $qobuzQuery = rawurlencode($artist . ' ' . $title);
                    $foundLinks[] = [
                        'platform' => 'Qobuz (Suche)',
                        'url'      => "https://www.qobuz.com/de-de/search?q={$qobuzQuery}"
                    ];

                    $resultData = [
                        'artist'           => $artist,
                        'title'            => $title,
                        'releaseDate'      => $releaseDate,
                        'cleanedUrl'       => $targetUrl,
                        'thumbnail'        => $thumbnail,
                        'pageUrl'          => $data['pageUrl'] ?? null,
                        'links'            => $foundLinks,
                        'isTextSearch'     => $isTextSearch,
                        'multipleResults'  => $hasMultipleResults,
                        'totalResults'     => $totalFoundResults,
                    ];
                }
            }
        }
    }

    if ($isJsonRequest) {
        http_response_code($httpStatusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($errorMessage ? ['error' => $errorMessage] : $resultData);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Music API Test</title>
</head>
<body>

<h1>Music Resolver API – Testumgebung</h1>

<form method="POST" action="">
    <p>
        <label for="url">Streaming-URL oder Titel/Interpret:</label><br>
        <input type="text" id="url" name="url" required style="width: 450px;" placeholder="z. B. Never Gonna Give You Up oder Link" value="<?= htmlspecialchars($input) ?>">
        <button type="submit">Testen</button>
    </p>
</form>

<?php if ($errorMessage): ?>
    <p><strong>Fehler (HTTP <?= $httpStatusCode ?>):</strong> <?= htmlspecialchars($errorMessage) ?></p>
<?php endif; ?>

<?php if ($resultData !== null): ?>
    <?php if (!empty($resultData['multipleResults'])): ?>
        <p><em>Hinweis: Es wurden <?= (int)$resultData['totalResults'] ?> Treffer gefunden. Das wahrscheinlichste Original bzw. der beste Treffer wird angezeigt.</em></p>
    <?php endif; ?>
    <h2>Generiertes JSON:</h2>
    <pre><?= htmlspecialchars(json_encode($resultData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></pre>
<?php endif; ?>

</body>
</html>