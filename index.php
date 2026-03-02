<?php
require_once __DIR__ . '/vendor/autoload.php';

use Geotab\Client;
use Geotab\Core\Reactor;
use Geotab\Models\EntityResponse;
use Geotab\Models\Security\FileSessionProvider;
use Geotab\Services\FeedObserver;
use Geotab\Models\Errors\HTTP503ResponseException;

echo "Starting" . PHP_EOL;

Client::create('demo_tsvl_las', function(Client $sdk) {
    $sdk->setSessionProvider(new FileSessionProvider('/usr/src/app/sessions'));
    $sdk->authenticate();

    echo "Dispatching concurrent requests...\n";
    $start = microtime(true);

    // Scatter/Gather: fire three requests concurrently
    $data = Reactor::getInstance()->waitAll([
        'users'   => fn() => $sdk->call("Get", ["typeName" => "User",   "resultsLimit" => 5]),
        'devices' => fn() => $sdk->call("Get", ["typeName" => "Device", "resultsLimit" => 5]),
        'groups'  => fn() => $sdk->call("Get", ["typeName" => "Group",  "resultsLimit" => 5])
    ]);

    echo "All requests finished in " . round(microtime(true) - $start, 3) . "s\n";

    foreach ($data as $key => $result) {
        if ($result instanceof \Throwable) {
            echo "Error in {$key}: " . $result->getMessage() . PHP_EOL;
            continue;
        }
        echo "Count for {$key}: " . $result->count . PHP_EOL;
    }

    // Stream LogRecords to console via FeedObserver
    // SIGINT (Ctrl+C) or SIGTERM (Docker) will set Reactor::stopping,
    // which causes the observer loop to exit cleanly after the current request completes.
    echo "\nStarting LogRecord feed stream. Press Ctrl+C to stop.\n";

    $observer = new FeedObserver($sdk, 'LogRecord', fromVersion:'0');

    try {
        $observer->start(function(array $records) {
               
         foreach ($records as $record) {
                echo sprintf(
                    "[LogRecord] Device: %s | DateTime: %s | Speed: %s\n",
                    $record['device']['id']   ?? 'N/A',
                    $record['dateTime']       ?? 'N/A',
                    $record['speed']          ?? 'N/A'
                );
            } 
        },resultsLimit:1000);
    } catch (HTTP503ResponseException $e) {
        echo "\nFeed terminated: Geotab service unavailable ({$e->url}).\n";
        echo "Last known version: {$observer->fromVersion}\n";
        echo "Restart the observer with this version after a backoff period.\n";
    }

    echo "\nFeed observer stopped gracefully.\n";
});

echo "Done" . PHP_EOL;