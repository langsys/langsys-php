<?php

// An app whose error class uses a marker nothing fills: the command must fail.

namespace SampleApp\Errors;

class QuotaError
{
    const CODE = 'quota_used';
    const MESSAGE = 'Your plan allows {limit} probes.';
}

return [
    'sources' => [new \Langsys\SDK\Messages\ErrorClassSource([QuotaError::class])],
];
