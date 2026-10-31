<?php

// A plain-PHP app's message configuration for bin/langsys-messages.

namespace SampleApp\Errors;

class LimitError
{
    const CODE = 'limit_reached';
    const MESSAGE = 'The limit was reached.';
}

return [
    'sources' => [new \Langsys\SDK\Messages\ErrorClassSource([LimitError::class])],
];
