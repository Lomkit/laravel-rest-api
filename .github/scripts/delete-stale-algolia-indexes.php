<?php

$app = (string) getenv('ALGOLIA_APP_ID');
$key = (string) getenv('ALGOLIA_SECRET');

$call = static function (string $method, string $path) use ($app, $key): array {
    $context = stream_context_create(['http' => [
        'method' => $method,
        'header' => "X-Algolia-Application-Id: {$app}\r\nX-Algolia-API-Key: {$key}\r\n",
        'ignore_errors' => true,
        'timeout' => 20,
    ]]);
    $body = file_get_contents("https://{$app}.algolia.net{$path}", false, $context);

    return is_string($body) && is_array($decoded = json_decode($body, true)) ? $decoded : [];
};

$cutoff = time() - 3600;

foreach ($call('GET', '/1/indexes')['items'] ?? [] as $index) {
    $name = is_string($index['name'] ?? null) ? $index['name'] : '';
    $updated = strtotime(is_string($index['updatedAt'] ?? null) ? $index['updatedAt'] : 'now');

    if (str_starts_with($name, 'lrapi_live_') && $updated !== false && $updated < $cutoff) {
        $call('DELETE', '/1/indexes/'.rawurlencode($name));
        echo "Deleted {$name}\n";
    }
}
