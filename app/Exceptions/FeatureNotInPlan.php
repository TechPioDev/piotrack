<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The workspace's plan does not include the feature a page belongs to.
 *
 * Still a 403, with the same message an API client always got - but as its own
 * type, so the error page can tell "your plan does not cover this" apart from
 * "your role may not do this". Both used to read "Not authorized, contact your
 * administrator", which sends an owner whose trial just ended looking for a
 * permission that was never the problem.
 */
class FeatureNotInPlan extends HttpException
{
    public function __construct(public readonly string $feature)
    {
        parent::__construct(403, __('Your plan does not include this feature.'));
    }
}
