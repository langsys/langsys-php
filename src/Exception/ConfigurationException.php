<?php

namespace Langsys\SDK\Exception;

/**
 * The client was built without an API key or a project id, so it cannot reach
 * Langsys.
 *
 * An explicit write throws it, and with `require_credentials` on so does every
 * call. The read paths catch it and render source text.
 */
class ConfigurationException extends LangsysException
{
}
