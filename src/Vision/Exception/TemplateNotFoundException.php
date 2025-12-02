<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Exception;

/**
 * Exception levée lorsque le template n'est pas trouvé
 */
class TemplateNotFoundException extends VisionException
{
    public function __construct(string $template)
    {
        parent::__construct("Template non trouvé : {$template}");
    }
}
