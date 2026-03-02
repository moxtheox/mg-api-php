<?php
declare(strict_types=1);

namespace Geotab\Services;

use Fiber;
use Geotab\Client;
use Geotab\Core\Reactor;
use Geotab\Models\FeedResponse;
use RuntimeException;

class FeedObserver {
    private ?string $currentVersion;
    private bool $paused = false;   // internal pause state

    public function __construct(
        private Client $client,
        private string $type,
        private ?string $modelClass = null,
        ?string $fromVersion = null
    ) {
        $this->currentVersion = $fromVersion;
    }

    public ?string $fromVersion {
        get => $this->currentVersion;
    }

    /**
     * Suspend polling at the next cycle boundary.
     * Safe to call from a signal handler — sets a flag only.
     */
    public function suspend(): void {
        $this->paused = true;
        fwrite(STDERR, "[FeedObserver] Suspended.\n");
    }

    /**
     * Resume polling.
     * Safe to call from a signal handler.
     */
    public function resume(): void {
        $this->paused = false;
        fwrite(STDERR, "[FeedObserver] Resumed.\n");
    }

    public function start(
        callable $onData,
        ?array $search = null,
        ?int $resultsLimit = 50000
    ): void {
        $parentFiber = Fiber::getCurrent()
            ?? throw new RuntimeException("start() must be called within a Fiber.");

        $reactor = Reactor::getInstance();

        $reactor->register(function() use ($reactor, $parentFiber, $onData, $search, $resultsLimit) {
            while (!$reactor->stopping) {

                // ── Pause gate ───────────────────────────────────────────
                // Yield to the Reactor in 0.5s increments while suspended.
                // This keeps the event loop alive and signal handlers firing
                // without consuming any API quota.
                while ($this->paused && !$reactor->stopping) {
                    pcntl_signal_dispatch();
                    $this->client->wait(0.5);
                }

                if ($reactor->stopping) break;

                $params = [
                    'typeName'     => $this->type,
                    'resultsLimit' => $resultsLimit,
                ];

                if ($this->currentVersion !== null) {
                    $params['fromVersion'] = $this->currentVersion;
                }

                if ($search !== null) {
                    $params['search'] = $search;
                }

                $start = microtime(true);

                $res = $this->client->call(
                    method: 'GetFeed',
                    params: $params,
                    modelClass: $this->modelClass,
                    responseClass: FeedResponse::class
                );

                $this->currentVersion = $res->toVersion;

                if ($res->count > 0) {
                    $onData($res->data);
                }

                $elapsed = microtime(true) - $start;

                if ($res->count === $resultsLimit) {
                    if ($elapsed < 1.0) {
                        $this->client->wait(1.0 - $elapsed);
                    }
                } elseif ($res->count < $resultsLimit / 2) {
                    $this->client->wait(30.0);
                } else {
                    $this->client->wait(15.0);
                }
            }

            if ($parentFiber->isSuspended()) {
                $parentFiber->resume();
            }
        });

        Fiber::suspend();
    }
}