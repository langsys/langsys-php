<?php

namespace Langsys\SDK\Tests\Messages;

use Langsys\SDK\Messages\HasAppMessageTemplate;
use Langsys\SDK\Messages\HasMessageTemplate;
use Langsys\SDK\Messages\MessageCatalog;
use Langsys\SDK\Messages\ServerMessage;
use PHPUnit\Framework\TestCase;

/**
 * MSG-7's app-message contract: a message the app defines itself states its
 * sentence and its code, is listed once from them, and is emitted as the same
 * template and code with the values its public properties hold.
 */
class AppMessageTemplateTest extends TestCase
{
    private function quotaExceeded($limit = 500)
    {
        return new class ($limit) implements HasAppMessageTemplate {
            public $limit;

            /** A public property no marker names is no param. */
            public $retryable = false;

            public function __construct($limit)
            {
                $this->limit = $limit;
            }

            public function template()
            {
                return 'You have used all {limit} of this month\'s requests.';
            }

            public function code()
            {
                return 'quota_exceeded';
            }
        };
    }

    public function testAnAppMessageIsListedOnceWithItsCode(): void
    {
        $catalog = new MessageCatalog();

        $this->assertTrue($catalog->addMessage($this->quotaExceeded(), 'App\\Errors\\QuotaExceeded'));
        $this->assertTrue($catalog->addMessage($this->quotaExceeded(10), 'App\\Http\\Controller'));

        $this->assertSame([[
            'template' => 'You have used all {limit} of this month\'s requests.',
            'source' => 'App\\Errors\\QuotaExceeded',
            'code' => 'quota_exceeded',
        ]], $catalog->templates());
        $this->assertSame([], $catalog->problems());
    }

    /**
     * A template's code is its first source's, as its listed source is.
     */
    public function testTheFirstSourcesCodeIsKept(): void
    {
        $catalog = new MessageCatalog();
        $coded = function ($code) {
            return new class ($code) implements HasAppMessageTemplate {
                private $code;

                public function __construct($code)
                {
                    $this->code = $code;
                }

                public function template()
                {
                    return 'Try again later.';
                }

                public function code()
                {
                    return $this->code;
                }
            };
        };

        $catalog->addMessage($coded('busy'), 'App\Errors\Busy');
        $catalog->addMessage($coded('maintenance'), 'App\Errors\Maintenance');

        $this->assertSame([['template' => 'Try again later.', 'source' => 'App\Errors\Busy', 'code' => 'busy']], $catalog->templates());
    }

    public function testAMarkerWithoutAPublicPropertyIsReported(): void
    {
        $catalog = new MessageCatalog();
        $message = new class implements HasAppMessageTemplate {
            private $limit = 5;

            public function template()
            {
                return 'Only {limit} left.';
            }

            public function code()
            {
                return null;
            }
        };

        $this->assertFalse($catalog->addMessage($message, 'App\\Errors\\Low'));
        $this->assertCount(1, $catalog->problems());
        $this->assertStringContainsString('{limit}', $catalog->problems()[0]);
        $this->assertSame([['template' => 'Only {limit} left.', 'source' => 'App\\Errors\\Low']], $catalog->templates(), 'listed, with no code');
    }

    /**
     * A class implementing both contracts is a rule where the binding lists
     * it as one - once per field, label written in - and the same template
     * and code are never listed twice.
     */
    public function testAClassImplementingBothIsNeverListedTwice(): void
    {
        $both = new class implements HasMessageTemplate, HasAppMessageTemplate {
            public $max = 3;

            public function template()
            {
                return 'Choose at most {max}.';
            }

            public function code()
            {
                return 'too_many';
            }
        };
        $catalog = new MessageCatalog();

        $catalog->addRule($both, 'tags', 'Choose at most 3.', 'App\\Rules\\Max', 'tags');
        $catalog->addMessage($both, 'App\\Rules\\Max');

        $this->assertSame(['Choose at most {max}.'], array_column($catalog->templates(), 'template'));
    }

    /**
     * The runtime entry is the listed template and code, filled from the
     * message's properties, so emitting it is the same call as any entry.
     */
    public function testTheRuntimeEntryIsWhatWasListed(): void
    {
        $entry = ServerMessage::fromApp($this->quotaExceeded(250));

        $this->assertSame('quota_exceeded', $entry->getCode());
        $this->assertSame('You have used all {limit} of this month\'s requests.', $entry->getTemplate());
        $this->assertSame(['limit' => 250], $entry->getParams());
        $this->assertSame('You have used all 250 of this month\'s requests.', $entry->getMessage());
    }

    /**
     * A listing has no runtime values: given the class, the core builds it
     * without its constructor and checks each marker against the declared
     * public properties, so a typed property the constructor would set is
     * found, not reported.
     */
    public function testAClassIsListedFromItsDeclaredProperties(): void
    {
        $catalog = new MessageCatalog();

        $this->assertTrue($catalog->addMessage(TypedQuota::class, TypedQuota::class));

        $this->assertSame([['template' => 'You have used all {limit} requests.', 'source' => TypedQuota::class, 'code' => 'quota_exceeded']], $catalog->templates());
        $this->assertSame([], $catalog->problems());
    }

    /**
     * A static property holds no instance's value, so it fills no marker.
     */
    public function testAStaticPropertyFillsNoMarker(): void
    {
        $catalog = new MessageCatalog();

        $this->assertFalse($catalog->addMessage(StaticLimit::class, StaticLimit::class));
        $this->assertStringContainsString('{limit}', $catalog->problems()[0]);
    }

    public function testATemplateThatNeedsConstructorStateIsReported(): void
    {
        $catalog = new MessageCatalog();

        $this->assertFalse($catalog->addMessage(StatefulMessage::class, StatefulMessage::class));
        $this->assertSame([], $catalog->templates());
        $this->assertStringContainsString('constructor state', $catalog->problems()[0]);
    }

    public function testAClassThatIsNotAnAppMessageIsReported(): void
    {
        $catalog = new MessageCatalog();

        $this->assertFalse($catalog->addMessage(\stdClass::class, 'stdClass'));
        $this->assertStringContainsString(HasAppMessageTemplate::class, $catalog->problems()[0]);
    }

    /**
     * A class that is also a validation rule is listed per field, with its
     * label written in, and never as an app message.
     */
    public function testARuleIsLeftToItsPerFieldListing(): void
    {
        $catalog = new MessageCatalog();
        $both = new class implements HasMessageTemplate, HasAppMessageTemplate {
            public $max = 3;

            public function template()
            {
                return 'The :attribute may list at most {max}.';
            }

            public function code()
            {
                return 'too_many';
            }
        };

        $this->assertFalse($catalog->addMessage($both, 'App\\Rules\\Max'));
        $catalog->addRule($both, 'tags', 'The tags may list at most 3.', 'App\\Rules\\Max', 'tags');

        $this->assertSame(['The tags may list at most {max}.'], array_column($catalog->templates(), 'template'));
        $this->assertSame([], $catalog->problems());
    }
}

final class TypedQuota implements HasAppMessageTemplate
{
    /** @var int */
    public int $limit;

    public function __construct(int $limit)
    {
        $this->limit = $limit;
    }

    public function template()
    {
        return 'You have used all {limit} requests.';
    }

    public function code()
    {
        return 'quota_exceeded';
    }
}

final class StatefulMessage implements HasAppMessageTemplate
{
    /** @var \ArrayObject */
    private $sentences;

    public function __construct(\ArrayObject $sentences)
    {
        $this->sentences = $sentences;
    }

    public function template()
    {
        return $this->sentences->offsetGet('quota');
    }

    public function code()
    {
        return null;
    }
}

final class StaticLimit implements HasAppMessageTemplate
{
    /** @var int */
    public static $limit = 5;

    public function template()
    {
        return 'Only {limit} left.';
    }

    public function code()
    {
        return null;
    }
}
