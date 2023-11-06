<?php

declare(strict_types=1);

namespace Distantmagic\GraphqlSwoolePromiseAdapter;

use Distantmagic\SwooleFuture\SwooleFuture;
use Distantmagic\SwooleFuture\SwooleFutureResult;
use Ds\Map;
use WeakMap;

readonly class GraphQLResolverPromiseAdapterRegistry
{
    /**
     * @var Map<class-string, GraphQLResolverPromiseAdapterInterface>
     */
    private Map $resolverPromiseAdapters;

    /**
     * @var WeakMap<object, GraphQLResolverPromiseAdapterInterface>
     */
    private WeakMap $thenablePromiseAdapter;

    public function __construct()
    {
        $this->resolverPromiseAdapters = new Map();

        /**
         * @var WeakMap<object, GraphQLResolverPromiseAdapterInterface>
         */
        $this->thenablePromiseAdapter = new WeakMap();
    }

    public function canConvert($thenable): bool
    {
        if (!is_object($thenable)) {
            return false;
        }

        if ($this->thenablePromiseAdapter->offsetExists($thenable)) {
            return true;
        }

        $resolver = $this->findAdapter($thenable);

        if (!$resolver) {
            return false;
        }

        $this->thenablePromiseAdapter->offsetSet($thenable, $resolver);

        return true;
    }

    public function convertThenable(object $thenable): SwooleFuture|SwooleFutureResult
    {
        return $this
            ->thenablePromiseAdapter
            ->offsetGet($thenable)
            ->convertThenable($thenable)
        ;
    }

    /**
     * @template TThenable of object
     *
     * @param class-string<TThenable>                           $className
     * @param GraphQLResolverPromiseAdapterInterface<TThenable> $resolverPromiseAdapter
     */
    public function registerResolverPromiseAdapter(
        string $className,
        GraphQLResolverPromiseAdapterInterface $resolverPromiseAdapter,
    ): void {
        $this->resolverPromiseAdapters->put($className, $resolverPromiseAdapter);
    }

    /**
     * @template TThenable of object
     *
     * @param TThenable $thenable
     *
     * @return null|GraphQLResolverPromiseAdapterInterface<TThenable>
     */
    private function findAdapter(object $thenable): ?GraphQLResolverPromiseAdapterInterface
    {
        /**
         * @var class-string                           $supportedClassName
         * @var GraphQLResolverPromiseAdapterInterface $adapter
         */
        foreach ($this->resolverPromiseAdapters as $supportedClassName => $adapter) {
            if (is_a($thenable, $supportedClassName, true)) {
                /**
                 * @var GraphQLResolverPromiseAdapterInterface<TThenable>
                 */
                return $adapter;
            }
        }

        return null;
    }
}
