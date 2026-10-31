<?php

namespace Langsys\SDK\Tests;

use Langsys\SDK\Client;
use Langsys\SDK\Format\Interpolator;
use Langsys\SDK\Html\HtmlParser;
use Langsys\SDK\Html\PageTranslator;
use Langsys\SDK\Http\HttpClient;
use PHPUnit\Framework\TestCase;

/**
 * Precondition guard: this suite must be exercising THIS tree.
 *
 * Everything else in the suite assumes it. Nothing else checks it.
 *
 * The failure this exists for is not hypothetical — a sibling repo's
 * verification pass reported a clean red-first run that was actually loading
 * FIXED source through a vendor symlink, so the "before" state never existed
 * and the check could not have failed. A whole suite can be green, and a
 * red-first claim can be sincere, while the classes under test come from
 * somewhere else entirely.
 *
 * `ReflectionClass::getFileName()` answers where a class was actually loaded
 * from, which is the only thing that settles it.
 */
class ProvenanceTest extends TestCase
{
    /**
     * @dataProvider sdkClassProvider
     */
    public function testSdkClassesLoadFromThisTree($class)
    {
        $file = (new \ReflectionClass($class))->getFileName();
        $expectedRoot = realpath(dirname(__DIR__) . '/src');

        $this->assertNotFalse($file, $class . ' has no file - loaded from an extension or eval?');
        $this->assertStringStartsWith(
            $expectedRoot . DIRECTORY_SEPARATOR,
            realpath($file),
            $class . ' was loaded from outside this tree: ' . $file
        );
    }

    public function sdkClassProvider()
    {
        return [
            'Client'         => [Client::class],
            'HttpClient'     => [HttpClient::class],
            'HtmlParser'     => [HtmlParser::class],
            'PageTranslator' => [PageTranslator::class],
            'Interpolator'   => [Interpolator::class],
        ];
    }

    /**
     * A vendored copy of this package would shadow src/ silently.
     */
    public function testNoVendoredCopyOfThisPackageShadowsSrc()
    {
        $this->assertDirectoryDoesNotExist(
            dirname(__DIR__) . '/vendor/langsys',
            'a vendored copy of this package would shadow src/ without any test noticing'
        );
    }

    /**
     * Negative control: the guard must be able to fail. If this assertion ever
     * passes, the check above proves nothing.
     */
    public function testTheGuardCanActuallyFail()
    {
        $outsider = (new \ReflectionClass(TestCase::class))->getFileName();
        $expectedRoot = realpath(dirname(__DIR__) . '/src');

        $this->assertStringStartsNotWith(
            $expectedRoot . DIRECTORY_SEPARATOR,
            realpath($outsider),
            'a class from vendor/ must NOT resolve under src/ - otherwise the guard is vacuous'
        );
    }
}
