<?php
require_once __DIR__ . '/vendor/autoload.php';

ob_implicit_flush(true);
while (ob_get_level()) ob_end_clean();

use Geotab\Client;
use Geotab\Models\Security\FileSessionProvider;
use Geotab\Services\FeedObserver;
use Geotab\Models\Errors\HTTP503ResponseException;

// ── Backpressure flag ────────────────────────────────────────
// SIGUSR1 = suspend, SIGUSR2 = resume
// These are set from the signal handlers and checked inside the
// onData callback, which runs inside the Reactor fiber context.
$suspended = false;

pcntl_signal(SIGUSR1, function() use (&$suspended) {
    $suspended = true;
    fwrite(STDERR, "[FeedObserver] Suspended via SIGUSR1\n");
});

pcntl_signal(SIGUSR2, function() use (&$suspended) {
    $suspended = false;
    fwrite(STDERR, "[FeedObserver] Resumed via SIGUSR2\n");
});

echo "Starting" . PHP_EOL;
$db = getenv('GEOTAB_DATABASE') ?? throw new RuntimeException('GEOTAB_DATABASE envar is not set');
Client::create($db, function(Client $sdk) use (&$suspended) {
    $sdk->setSessionProvider(new FileSessionProvider('/usr/src/app/sessions'));
    $sdk->authenticate();

    echo "\nStarting LogRecord feed stream. Press Ctrl+C to stop.\n";

    $observer = new FeedObserver($sdk, 'LogRecord', fromVersion: '0');

    try {
        $observer->start(function(array $records) use ($sdk, &$suspended) {

            // Backpressure hold — spin inside the Reactor using client->wait()
            // so the fiber stays alive and the event loop keeps ticking.
            // SIGUSR2 will clear $suspended and let us fall through.
            while ($suspended) {
                pcntl_signal_dispatch();   // process any pending signals
                $sdk->wait(0.5);           // yields to Reactor, non-blocking sleep
            }

            foreach ($records as $record) {
                echo json_encode($record) . PHP_EOL;
            }

        }, resultsLimit: 1000);

    } catch (HTTP503ResponseException $e) {
        echo PHP_EOL . "Feed terminated: Geotab service unavailable ({$e->url})." . PHP_EOL;
        echo "Last known version: {$observer->fromVersion}" . PHP_EOL;
        echo "Restart the observer with this version after a backoff period." . PHP_EOL;
    }

    echo PHP_EOL . "Feed observer stopped gracefully." . PHP_EOL;
});

echo "Done" . PHP_EOL;