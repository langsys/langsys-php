<?php

namespace Langsys\SDK\Tests\Html;

use Langsys\SDK\Html\HtmlParser;
use Langsys\SDK\Tests\Support\BuildsMockClient;
use PHPUnit\Framework\TestCase;

/**
 * WIRE-3's read half: the catalog serves every no-category entry under
 * `__uncategorized__`, content blocks included, so a block rendered with no
 * category is looked up there - never under an empty key, which misses it.
 */
class UncategorisedBlockReadTest extends TestCase
{
    use BuildsMockClient;

    public function testABlockWithNoCategoryIsReadFromTheUncategorisedKey(): void
    {
        $id = (new HtmlParser())->generateCustomId(null, ['Café', 'Thé']);

        foreach ([null, '', '__uncategorized__'] as $category) {
            $client = $this->mockClient(['__uncategorized__' => [$id => ['Café' => 'Cafe ES', 'Thé' => 'Te ES']]]);
            $client->setLocale('es-es');

            $rendered = $category === '__uncategorized__'
                ? $client->translateContentBlock('<div><p>Café</p><p>Thé</p></div>')
                : $client->translateContentBlock('<div><p>Café</p><p>Thé</p></div>', $category);

            $this->assertStringContainsString('<p>Cafe ES</p><p>Te ES</p>', $rendered, var_export($category, true));
            $this->assertFalse($client->hasPendingRegistrations(), var_export($category, true) . ': found, not queued');
        }
    }
}
