<?php

/*
 * This file is a part of the DiscordPHP-Http project.
 *
 * Copyright (c) 2021-present David Cole <david.cole1340@gmail.com>
 *
 * This file is subject to the MIT license that is bundled
 * with this source code in the LICENSE file.
 */

namespace Discord\Http;

use Composer\InstalledVersions;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use SplQueue;

/**
 * Represents a rate-limit bucket.
 *
 * @author David Cole <david.cole1340@gmail.com>
 */
class Bucket
{
    /**
     * Request queue.
     *
     * @var SplQueue
     */
    protected $queue;

    /**
     * Bucket name.
     *
     * @var string
     */
    protected $name;

    /**
     * ReactPHP event loop.
     *
     * @var LoopInterface
     */
    protected $loop;

    /**
     * HTTP logger.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Callback for when a request is ready.
     *
     * @var callable
     */
    protected $runRequest;

    /**
     * Whether we are checking the queue.
     *
     * @var bool
     */
    protected $checkerRunning = false;

    /**
     * Number of requests allowed before reset.
     *
     * @var int
     */
    protected $requestLimit;

    /**
     * Number of remaining requests before reset.
     *
     * @var int
     */
    protected $requestRemaining;

    /**
     * Timer to reset the bucket.
     *
     * @var TimerInterface
     */
    protected $resetTimer;

    /**
     * Whether react/promise v3 is used, if false, using v2
     */
    protected $promiseV3 = true;

    /**
     * Bucket constructor.
     *
     * @param string   $name
     * @param callable $runRequest
     */
    public function __construct(string $name, LoopInterface $loop, LoggerInterface $logger, callable $runRequest)
    {
        $this->queue = new SplQueue;
        $this->name = $name;
        $this->loop = $loop;
        $this->logger = $logger;
        $this->runRequest = $runRequest;

        $this->promiseV3 = str_starts_with(InstalledVersions::getVersion('react/promise'), '3.');
    }

    /**
     * Enqueue a request.
     *
     * @param Request $request
     */
    public function enqueue(Request $request)
    {
        $this->queue->enqueue($request);
        $this->logger->debug($this.' queued '.$request);
        $this->checkQueue();
    }

    /**
     * Checks for requests in the bucket.
     */
    public function checkQueue()
    {
        // We are already checking the queue.
        if ($this->checkerRunning) {
            return;
        }

        $this->checkerRunning = true;
        $this->__checkQueue();
    }

    protected function __checkQueue()
    {
        // Check for rate-limits
        if ($this->requestRemaining < 1 && ! is_null($this->requestRemaining)) {
            $interval = 0;
            if ($this->resetTimer) {
                $interval = $this->resetTimer->getInterval() ?? 0;
            }
            $this->logger->info($this.' expecting rate limit, timer interval '.($interval * 1000).' ms');
            $this->checkerRunning = false;

            return;
        }

        // Queue is empty, job done.
        if ($this->queue->isEmpty()) {
            $this->checkerRunning = false;

            return;
        }

        /** @var Request */
        $request = $this->queue->dequeue();

        // Promises v3 changed `->then` to behave as `->done` and removed `->then`. We still need the behaviour of `->done` in projects using v2
        ($this->runRequest)($request)->{$this->promiseV3 ? 'then' : 'done'}(function (ResponseInterface $response) {
            $resetAfter = (float) $response->getHeaderLine('X-Ratelimit-Reset-After');
            $limit = $response->getHeaderLine('X-Ratelimit-Limit');
            $remaining = $response->getHeaderLine('X-Ratelimit-Remaining');

            if ($resetAfter) {
                $resetAfter = (float) $resetAfter;

                if ($this->resetTimer) {
                    $this->loop->cancelTimer($this->resetTimer);
                }

                $this->resetTimer = $this->loop->addTimer($resetAfter, function () {
                    // Reset requests remaining and check queue
                    $this->requestRemaining = $this->requestLimit;
                    $this->resetTimer = null;
                    $this->checkQueue();
                });
            }

            // Check if rate-limit headers are present and store
            if (is_numeric($limit)) {
                $this->requestLimit = (int) $limit;
            }

            if (is_numeric($remaining)) {
                $this->requestRemaining = (int) $remaining;
            }

            // Check for more requests
            $this->__checkQueue();
        }, function ($rateLimit) use ($request) {
            if ($rateLimit instanceof RateLimit) {
                $this->queue->enqueue($request);

                // Bucket-specific rate-limit
                // Re-queue the request and wait the retry after time
                if (! $rateLimit->isGlobal()) {
                    $this->loop->addTimer($rateLimit->getRetryAfter(), fn () => $this->__checkQueue());
                }
                // Stop the queue checker for a global rate-limit.
                // Will be restarted when global rate-limit finished.
                else {
                    $this->checkerRunning = false;

                    $this->logger->debug($this.' stopping queue checker');
                }
            } else {
                $this->__checkQueue();
            }
        });
    }

    /**
     * Converts a bucket to a user-readable string.
     *
     * @return string
     */
    public function __toString()
    {
        return 'BUCKET '.$this->name;
    }
}