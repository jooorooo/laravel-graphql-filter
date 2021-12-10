<?php

declare(strict_types=1);

namespace Simexis\GraphQLFilter;

use Exception;

class GraphQLFilterException extends Exception
{
    public function __construct($message) {
        parent::__construct($message);
    }
}
