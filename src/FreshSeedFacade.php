<?php

namespace Nomanurrahman\FreshSeed;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Nomanurrahman\FreshSeed\Skeleton\SkeletonClass
 */
class FreshSeedFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'fresh-seed';
    }
}
