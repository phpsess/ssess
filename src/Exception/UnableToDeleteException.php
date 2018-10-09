<?php

namespace Ssess\Exception;

class UnableToDeleteException extends \RuntimeException
{

    protected $message = 'The session storage driver was unable to delete the session.';

}