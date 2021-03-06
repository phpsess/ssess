<?php

declare(strict_types=1);

namespace PHPSess\Exception;

class UnableToSaveException extends \RuntimeException
{

    protected $message = 'The session storage driver was unable to save the session.';
}
