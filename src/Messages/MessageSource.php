<?php

namespace Langsys\SDK\Messages;

/**
 * Something that knows which templates an app can emit: error classes, a
 * framework's validation rules crossed with their labels, declared rule
 * failures. A framework binding implements one per kind of declaration.
 */
interface MessageSource
{
    /**
     * Add every template this source can list to the catalog, and report every
     * message it cannot list as a problem naming where it is and how to fix it.
     *
     * @param MessageCatalog $catalog
     * @return void
     */
    public function collect(MessageCatalog $catalog);
}
