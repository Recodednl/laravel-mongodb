<?php

namespace Recoded\MongoDB\Exceptions;

use Throwable;

class UnsupportedByMongoDBException extends \Exception
{
    protected string $feature;

    public function __construct($feature = 'This feature', $code = 0, Throwable $previous = null)
    {
        $this->feature = $feature;

        parent::__construct($feature . ' is unsupported by MongoDB', $code, $previous);
    }

    public function getFeature(): string
    {
        return $this->feature;
    }
}
