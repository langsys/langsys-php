<?php

namespace Langsys\SDK\Tests\Messages;

use Langsys\SDK\Messages\HasMessageTemplate;
use Langsys\SDK\Messages\MessageCatalog;
use Langsys\SDK\Messages\RuleTemplate;
use PHPUnit\Framework\TestCase;

/**
 * FRM-2's rule-object contract: a rule states its sentence through the core's
 * message-template interface, and is listed from it, marker and all; a rule
 * without it is listed from its filled message and reported.
 */
class RuleTemplateTest extends TestCase
{
    private function maxRule($max = 255)
    {
        return new class ($max) implements HasMessageTemplate {
            public $max;

            /** A public property no marker names is no param. */
            public $strict = true;

            public function __construct($max)
            {
                $this->max = $max;
            }

            public function template()
            {
                return 'The :attribute may not be more than {max} characters.';
            }
        };
    }

    /**
     * The spec's test: a rule with a {max} marker lists its sentence with the
     * marker, not the number, once per field, with the field's label written
     * in.
     */
    public function testARuleIsListedWithItsMarkerPerField(): void
    {
        $catalog = new MessageCatalog();

        $this->assertTrue($catalog->addRule($this->maxRule(), 'name', 'The name may not be more than 255 characters.', 'App\\Rules\\Max', 'name'));
        $this->assertTrue($catalog->addRule($this->maxRule(), 'title', 'The title may not be more than 255 characters.', 'App\\Rules\\Max', 'title'));

        $this->assertSame([
            'The name may not be more than {max} characters.',
            'The title may not be more than {max} characters.',
        ], array_column($catalog->templates(), 'template'));
        $this->assertSame([], $catalog->problems());
    }

    /**
     * A rule without the interface is listed from its filled message and
     * reported, naming the interface - which is what fails --strict.
     */
    public function testARuleWithoutATemplateIsListedFromItsMessageAndReported(): void
    {
        $catalog = new MessageCatalog();
        $rule = new class {
            public $max = 255;
        };

        $this->assertFalse($catalog->addRule($rule, 'name', 'The name may not be more than 255 characters.', 'App\\Rules\\Legacy', 'name'));

        $this->assertSame(['The name may not be more than 255 characters.'], array_column($catalog->templates(), 'template'));
        $this->assertCount(1, $catalog->problems());
        $this->assertStringContainsString(HasMessageTemplate::class, $catalog->problems()[0]);
        $this->assertStringContainsString('App\\Rules\\Legacy.name', $catalog->problems()[0]);
    }

    public function testAMarkerWithoutAPublicPropertyIsReported(): void
    {
        $catalog = new MessageCatalog();
        $rule = new class implements HasMessageTemplate {
            private $min = 3;

            public function template()
            {
                return 'The :attribute needs {min} items.';
            }
        };

        $this->assertFalse($catalog->addRule($rule, 'tags', 'The tags needs 3 items.', 'App\\Rules\\Min', 'tags'));
        $this->assertStringContainsString('{min}', $catalog->problems()[0]);
    }

    /**
     * The runtime entry reads the same template and the same values the
     * listing did, so runtime and listing agree (MSG-7).
     */
    public function testTheRuntimeEntryMatchesTheListing(): void
    {
        $stated = RuleTemplate::forField($this->maxRule(40), 'name');

        $this->assertSame('The name may not be more than {max} characters.', $stated['template']);
        $this->assertSame(['max' => 40], $stated['params']);
        $this->assertSame(['max' => 40], RuleTemplate::params($this->maxRule(40)));
        $this->assertSame([], RuleTemplate::params(new class {
            public $max = 1;
        }));
        $this->assertNull(RuleTemplate::forField(new \stdClass(), 'name'));
    }
}
