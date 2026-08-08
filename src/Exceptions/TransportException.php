<?php

namespace EuroMail\Exceptions;

class TransportException extends EuroMailException
{
    public function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, null, null, null, null, $previous);
    }
}
