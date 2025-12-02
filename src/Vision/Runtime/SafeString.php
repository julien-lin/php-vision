<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Runtime;

/**
 * Wrapper pour marquer une chaîne comme "safe" (déjà échappée/rendue)
 * Évite le double-échappement pour le contenu généré par template/component/slot
 */
class SafeString
{
    private string $value;

    public function __construct(string $value)
    {
        $this->value = $value;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
