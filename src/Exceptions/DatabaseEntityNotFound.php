<?php

namespace VictorOpusculo\MyOrm\Exceptions;

class DatabaseEntityNotFound extends \Exception
{
    public readonly string $databaseTable;

    public function __construct(string $message, string $databaseTable)
    {
        parent::__construct($message);
        $this->databaseTable = $databaseTable;
    }
}