<?php

declare(strict_types=1);

namespace Distantmagic\GraphqlSwoolePromiseAdapter;

use Distantmagic\SwooleFuture\PromiseState;
use Distantmagic\SwooleFuture\SwooleFuture;
use Distantmagic\SwooleFuture\SwooleFutureResult;
use GraphQL\Executor\Promise\Promise;
use GraphQL\Executor\Promise\PromiseAdapter;
use LogicException;
use Throwable;

use function Swoole\Coroutine\batch;

readonly class SwoolePromiseAdapter implements PromiseAdapter
{
    public GraphQLResolverPromiseAdapterRegistry $resolverPromiseAdapterRegistry;
    private float $timeout;

    public function __construct()
    {
        $this->resolverPromiseAdapterRegistry = new GraphQLResolverPromiseAdapterRegistry();
        $this->timeout = defined('DM_BATCH_PROMISE_TIMEOUT')
            ? (float) DM_BATCH_PROMISE_TIMEOUT
            : 0.3;
    }

    public function all(iterable $promisesOrValues): Promise
    {
        $batch = [];

        foreach (new SwoolePromiseBatchIterator($promisesOrValues) as $callback) {
            $batch[] = $callback;
        }

        $results = batch($batch, $this->timeout);

        return $this->createFulfilled($results);
    }

    public function convertThenable(mixed $thenable): Promise
    {
        switch (true) {
            case $thenable instanceof SwooleFuture:
            case $thenable instanceof SwooleFutureResult:
                return $this->wrap($thenable);
        }

        if (is_object($thenable) && $this->resolverPromiseAdapterRegistry->canConvert($thenable)) {
            return $this->wrap($this->resolverPromiseAdapterRegistry->convertThenable($thenable));
        }

        throw new LogicException(sprintf(
            'Thenable is not supported: %s',
            is_object($thenable) ? $thenable::class : (string) $thenable
        ));
    }

    public function create(callable $resolver): Promise
    {
        throw new LogicException('Not yet implemented');
    }

    public function createFulfilled($value = null): Promise
    {
        return $this->wrap(new SwooleFutureResult(PromiseState::Fulfilled, $value));
    }

    public function createRejected(Throwable $reason): Promise
    {
        return $this->wrap(new SwooleFutureResult(PromiseState::Rejected, $reason));
    }

    public function isThenable($value): bool
    {
        if ($this->resolverPromiseAdapterRegistry->canConvert($value)) {
            return true;
        }

        switch (true) {
            case $value instanceof SwooleFuture:
            case $value instanceof SwooleFutureResult:
                return true;
        }

        return false;
    }

    public function then(Promise $promise, ?callable $onFulfilled = null, ?callable $onRejected = null): Promise
    {
        $adoptedPromise = $promise->adoptedPromise;

        if ($adoptedPromise instanceof SwooleFuture) {
            return $this->thenFuture($promise, $adoptedPromise, $onFulfilled, $onRejected);
        }

        if ($adoptedPromise instanceof SwooleFutureResult) {
            return $this->thenResolved($promise, $adoptedPromise, $onFulfilled, $onRejected);
        }

        throw new LogicException('Unsupported thenable value');
    }

    private function future(callable $executor, mixed $value): Promise
    {
        $futureResolver = new SwooleFuture($executor);

        return $this->wrap($futureResolver->resolve($value));
    }

    private function thenFuture(
        Promise $promise,
        SwooleFuture $adoptedPromise,
        ?callable $onFulfilled = null,
        ?callable $onRejected = null,
    ): Promise {
        if (!is_callable($onFulfilled) && !is_callable($onRejected)) {
            return $promise;
        }

        return $this->wrap($adoptedPromise->then(
            is_callable($onFulfilled) ? new SwooleFuture($onFulfilled) : null,
            is_callable($onRejected) ? new SwooleFuture($onRejected) : null,
        ));
    }

    private function thenResolved(
        Promise $promise,
        SwooleFutureResult $adoptedPromise,
        ?callable $onFulfilled = null,
        ?callable $onRejected = null,
    ): Promise {
        if (!$adoptedPromise->state->isSettled()) {
            throw new LogicException('Thenable should always be settled before chaining');
        }

        if (is_callable($onFulfilled) && PromiseState::Fulfilled === $adoptedPromise->state) {
            return $this->future($onFulfilled, $adoptedPromise->result);
        }

        if (is_callable($onRejected) && PromiseState::Rejected === $adoptedPromise->state) {
            return $this->future($onRejected, $adoptedPromise->result);
        }

        return $promise;
    }

    private function wrap(SwooleFuture|SwooleFutureResult $swoolePromise): Promise
    {
        return new Promise($swoolePromise, $this);
    }
}
