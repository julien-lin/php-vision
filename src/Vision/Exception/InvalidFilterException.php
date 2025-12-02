<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Exception;

/**
 * Exception levée lorsque un filtre invalide est utilisé
 */
class InvalidFilterException extends VisionException
{
    public function __construct(string $filter)
    {
        parent::__construct("Filtre invalide : {$filter}");
    }
}
