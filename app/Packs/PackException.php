<?php

namespace App\Packs;

use RuntimeException;

class PackException extends RuntimeException
{
    /** @param string[] $errors */
    public function __construct(public readonly array $errors)
    {
        parent::__construct("Invalid pack:\n - ".implode("\n - ", $errors));
    }
}
