<?php

declare(strict_types=1);

namespace Distantmagic\GraphqlSwoolePromiseAdapter;

use Distantmagic\SwooleFuture\SwooleFuture;
use Distantmagic\SwooleFuture\SwooleFutureResult;

/**
 * @template TThenble of object
 */
interface GraphQLResolverPromiseAdapterInterface
{
    /**
     * I wish PHP had generics.
     *
     * @param TThenble $thenable
     */
    public function convertThenable(object $thenable): SwooleFuture|SwooleFutureResult;
}
