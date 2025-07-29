<?php

declare(strict_types=1);

class ResourceInUseException extends RuntimeException
{
    /** @var string[] */
    public array $dependants;

    public function __construct(array $dependants)
    {
        parent::__construct(
            'Resource is in use by: '. implode(', ', $dependants)
        );
        $this->dependants = $dependants;
    }
}
