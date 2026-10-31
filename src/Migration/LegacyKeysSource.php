<?php

namespace Langsys\SDK\Migration;

use Langsys\SDK\Messages\MessageCatalog;
use Langsys\SDK\Messages\MessageSource;

/**
 * Names, for the listing command, what a legacy-key migration cannot carry as
 * it stands (MIG-4, MIG-7): a value no conversion recognises, a key held by more
 * than one of the app's own files, a file that cannot be read. It lists no
 * templates - these are phrases, not server messages.
 */
final class LegacyKeysSource implements MessageSource
{
    /** @var LegacyKeys */
    private $keys;

    public function __construct(LegacyKeys $keys)
    {
        $this->keys = $keys;
    }

    /**
     * @param MessageCatalog $catalog
     * @return void
     */
    public function collect(MessageCatalog $catalog)
    {
        foreach ($this->keys->problems() as $problem) {
            $catalog->problem($problem['file'], $problem['issue'], $problem['fix'], $problem['key']);
        }

        foreach ($this->keys->duplicates() as $key => $files) {
            $catalog->problem($files[0], 'defines a key also defined in ' . implode(', ', array_slice($files, 1)) . '; the first configured file wins', 'keep the key in one file', $key);
        }
    }
}
