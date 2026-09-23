<?php

namespace Langsys\SDK\Tests\Html;

use Langsys\SDK\Html\HtmlParser;
use Langsys\SDK\Tests\Support\BuildsMockClient;
use PHPUnit\Framework\TestCase;

/**
 * TOK-4 on every lookup site: an attribute value registered collapsed must be
 * looked up collapsed, or the key that was written is never found again and the
 * attribute re-registers on every render. Each whitespace form an author can
 * write in an attribute is pinned on each site that looks one up.
 */
class AttributeLookupPinsTest extends TestCase
{
    use BuildsMockClient;

    const COLLAPSED = 'A long description';

    public function whitespaceForms()
    {
        return [
            'line break' => ["A long\n    description"],
            'doubled space' => ['A  long description'],
            'no-break space' => ["A\u{00A0}long description"],
        ];
    }

    private function forms()
    {
        $out = [];

        foreach (['alt' => '<img alt="%s">', 'placeholder' => '<input placeholder="%s">'] as $attr => $tag) {
            foreach ($this->whitespaceForms() as $label => list($value)) {
                $out["$attr, $label"] = [$attr, sprintf($tag, $value)];
            }
        }

        return $out;
    }

    public function testThePagePathRegistersTheCollapsedValueInsideABlock(): void
    {
        foreach ($this->forms() as $label => list($attr, $tag)) {
            $client = $this->mockClient(['__uncategorized__' => []]);
            $client->setLocale('es-es');
            $client->translatePage("<html><body><p>Look $tag here</p></body></html>");
            $client->flushPendingRegistrations();

            $this->assertSame([['block', ['Look', self::COLLAPSED, 'here']]], $this->registered(), $label);
        }
    }

    public function testThePagePathFindsTheCollapsedValueInsideABlock(): void
    {
        $id = (new HtmlParser())->generateCustomId('__uncategorized__', ['Look', self::COLLAPSED, 'here']);

        foreach ($this->forms() as $label => list($attr, $tag)) {
            $client = $this->mockClient(['__uncategorized__' => [$id => ['Look' => 'Mira', self::COLLAPSED => 'Una larga descripcion', 'here' => 'aqui']]]);
            $client->setLocale('es-es');
            $page = $client->translatePage("<html><body><p>Look $tag here</p></body></html>");
            $client->flushPendingRegistrations();

            $this->assertStringContainsString($attr . '="Una larga descripcion"', $page, $label);
            $this->assertSame([], $this->registered(), "$label: a found value is not registered again");
        }
    }

    public function testTheBlockPathRegistersAndFindsTheCollapsedValue(): void
    {
        $id = (new HtmlParser())->generateCustomId('UI', ['Look', self::COLLAPSED, 'here']);

        foreach ($this->forms() as $label => list($attr, $tag)) {
            $client = $this->mockClient(['UI' => []]);
            $client->setLocale('es-es');
            $client->translateContentBlock("<p>Look $tag here</p>", 'UI');
            $client->flushPendingRegistrations();
            $this->assertSame([['block', ['Look', self::COLLAPSED, 'here']]], $this->registered(), "$label: registered");

            $client = $this->mockClient(['UI' => [$id => ['Look' => 'Mira', self::COLLAPSED => 'Una larga descripcion', 'here' => 'aqui']]]);
            $client->setLocale('es-es');
            $this->assertStringContainsString($attr . '="Una larga descripcion"', $client->translateContentBlock("<p>Look $tag here</p>", 'UI'), "$label: found");
        }
    }
}
