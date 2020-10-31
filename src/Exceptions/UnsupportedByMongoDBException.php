<?php

namespace Recoded\MongoDB\Exceptions;

use Throwable;

class UnsupportedByMongoDBException extends \Exception
{
    protected string $feature;

    public function __construct($feature = 'This feature', bool $plural = false, $code = 0, Throwable $previous = null)
    {
        $this->feature = $feature;

        $message = sprintf('%s %s unsupported by MongoDB', $feature, $plural ? 'are' : 'is');

        parent::__construct($message, $code, $previous);
    }

    public function getFeature(): string
    {
        return $this->feature;
    }
}
