<?php

namespace Langsys\SDK\Tests\Messages;

use Langsys\SDK\Messages\ErrorClassSource;
use Langsys\SDK\Messages\MessageCatalog;
use PHPUnit\Framework\TestCase;

abstract class ProbeBaseError
{
    const CODE = 'probe_base';
    const MESSAGE = 'Something about probes went wrong.';
}

class ProbeLimitError extends ProbeBaseError
{
    const CODE = 'probe_limit_reached';
    const MESSAGE = 'The probe limit was reached.';
}

class ProbeQuotaError
{
    const CODE = 'probe_quota_used';
    const MESSAGE = 'You have used {used} of your {limit} probes.';

    public $used;
    public $limit;
}

class ProbeMissingPropertyError
{
    const CODE = 'probe_missing_property';
    const MESSAGE = 'Your plan allows {limit} probes.';
}

class ProbeInheritedMessageError extends ProbeLimitError
{
    const CODE = 'probe_inherited';
}

class ProbeBadCodeError
{
    const CODE = 'ProbeBad';
    const MESSAGE = 'A probe was bad.';
}

class ProbeFieldError
{
    const CODE = 'too_short';
    const TEMPLATE = 'The probe name must be at least {min} characters.';

    public $min;
}

/**
 * MSG-3 / MSG-7 / MSG-11: every template an app can send, listed from its code,
 * and every message that can't be listed named with where it is and how to fix it.
 */
class MessageCatalogTest extends TestCase
{
    public function testTemplatesAreListedOnceSortedWithTheirFirstSource(): void
    {
        $catalog = new MessageCatalog();
        $catalog->add('The password is required.', 'SignupRequest.password');
        $catalog->add('The name is required.', 'SignupRequest.name');
        $catalog->add('The password is required.', 'LoginRequest.password');

        $this->assertSame([
            ['template' => 'The name is required.', 'source' => 'SignupRequest.name'],
            ['template' => 'The password is required.', 'source' => 'SignupRequest.password'],
        ], $catalog->templates());
        $this->assertFalse($catalog->hasProblems());
    }

    public function testABraceThatIsNotAMarkerIsAProblemAndIsNotListed(): void
    {
        $catalog = new MessageCatalog();
        $catalog->add('The {Field} is required.', 'SignupRequest.email');

        $this->assertSame([], $catalog->templates());
        $this->assertCount(1, $catalog->problems());
        $this->assertStringContainsString('SignupRequest.email', $catalog->problems()[0]);
        $this->assertStringContainsString('{Field}', $catalog->problems()[0]);
    }

    public function testAnUnfilledFrameworkPlaceholderIsAProblem(): void
    {
        foreach ([':attribute', ':other', ':values', ':min', ':input', ':date', ':value'] as $placeholder) {
            $catalog = new MessageCatalog();
            $catalog->add("The thing $placeholder is wrong.", 'SignupRequest.email');

            $this->assertSame([], $catalog->templates(), $placeholder);
            $this->assertStringContainsString($placeholder, $catalog->problems()[0]);
        }

        foreach (['Meet at 10:30.', 'See https://langsys.dev for help.', 'Note:this reads fine.'] as $text) {
            $catalog = new MessageCatalog();
            $catalog->add($text, 'SignupRequest');

            $this->assertFalse($catalog->hasProblems(), $text);
        }
    }

    public function testAFieldIsNamedAlongsideItsSource(): void
    {
        $catalog = new MessageCatalog();
        $catalog->add('The email is required.', 'SignupRequest', 'email');
        $catalog->add('The {Field} is required.', 'SignupRequest', 'name');

        $this->assertSame([['template' => 'The email is required.', 'source' => 'SignupRequest.email']], $catalog->templates());
        $this->assertStringStartsWith('SignupRequest.name: ', $catalog->problems()[0]);
    }

    public function testALabelCarriedInAMarkerIsAProblem(): void
    {
        foreach (MessageCatalog::LABEL_MARKERS as $marker) {
            $catalog = new MessageCatalog();
            $catalog->add("The {{$marker}} is required.", 'SignupRequest');

            $this->assertSame([], $catalog->templates(), $marker);
            $this->assertStringContainsString("{{$marker}}", $catalog->problems()[0]);
        }

        $numeric = new MessageCatalog();
        $numeric->add('The password must be at least {min} characters.', 'SignupRequest.password');
        $this->assertFalse($numeric->hasProblems(), 'a number in a marker is the accepted case');
    }

    public function testAProblemNamesTheSourceTheFieldTheIssueAndTheFix(): void
    {
        $catalog = new MessageCatalog();
        $catalog->problem('App\\Http\\Requests\\CardRequest', 'has no label', 'add it to CardRequest::attributes()', 'cc_number');

        $this->assertSame(['App\\Http\\Requests\\CardRequest.cc_number: has no label — add it to CardRequest::attributes()'], $catalog->problems());
        $this->assertTrue($catalog->hasProblems());
    }

    public function testErrorClassesAreListedFromTheirDeclarations(): void
    {
        $catalog = new MessageCatalog();
        (new ErrorClassSource([ProbeLimitError::class, ProbeQuotaError::class, ProbeFieldError::class, ProbeBaseError::class]))->collect($catalog);

        $this->assertSame([
            'The probe limit was reached.',
            'The probe name must be at least {min} characters.',
            'You have used {used} of your {limit} probes.',
        ], array_column($catalog->templates(), 'template'));
        $this->assertSame([], $catalog->problems());
    }

    public function testAnErrorClassThatCannotBeListedIsNamedWithItsFix(): void
    {
        $catalog = new MessageCatalog();
        (new ErrorClassSource([ProbeMissingPropertyError::class, ProbeInheritedMessageError::class, ProbeBadCodeError::class, 'App\\Errors\\DoesNotExist']))->collect($catalog);

        $problems = implode("\n", $catalog->problems());

        $this->assertStringContainsString(ProbeMissingPropertyError::class . ': uses the marker {limit} but has no $limit property to fill it', $problems);
        $this->assertStringContainsString(ProbeInheritedMessageError::class . ': inherits its MESSAGE', $problems);
        $this->assertStringContainsString(ProbeBadCodeError::class . ": its code 'ProbeBad' is not a snake_case slug", $problems);
        $this->assertStringContainsString('App\\Errors\\DoesNotExist: is not a loadable class', $problems);
        $this->assertCount(4, $catalog->problems());
    }
}
