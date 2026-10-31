<?php

namespace Langsys\SDK\Tests\Migration;

use Langsys\SDK\Messages\MessageCatalogCommand;
use Langsys\SDK\Migration\LegacyKeys;
use Langsys\SDK\Migration\LegacyKeysSource;
use PHPUnit\Framework\TestCase;

/**
 * MIG-4 and MIG-7 in the listing command: a value no conversion recognises, and
 * a key held by more than one of the app's own files, are named with the file,
 * the key and the fix, and fail the build.
 */
class LegacyKeysListingTest extends TestCase
{
    /** @var string */
    private $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/langsys-mig-list-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*') ?: []);
        rmdir($this->dir);
    }

    private function run_(array $files, array $fallback = [], array $options = [])
    {
        $out = fopen('php://memory', 'w+');
        $err = fopen('php://memory', 'w+');
        $code = MessageCatalogCommand::run([new LegacyKeysSource(new LegacyKeys(['files' => $files, 'fallback_files' => $fallback]))], null, $options, $out, $err);
        rewind($err);

        return [$code, stream_get_contents($err)];
    }

    public function testACleanMigrationListsNoProblems(): void
    {
        file_put_contents($this->dir . '/en.json', json_encode(['checkout' => ['submit' => 'Place order']]));

        $this->assertSame([0, ''], $this->run_([$this->dir . '/en.json']));
    }

    public function testAnUnrecognisedValueAndADuplicateKeyAreReportedByName(): void
    {
        file_put_contents($this->dir . '/en.json', json_encode(['greeting' => ['hello' => 'Hello, :Name'], 'checkout' => ['submit' => 'Place order']]));
        file_put_contents($this->dir . '/checkout.php', '<?php return ["submit" => "Order now"];');

        list($code, $err) = $this->run_([$this->dir . '/en.json', $this->dir . '/checkout.php']);

        $this->assertSame(0, $code, 'reported, not a failed build');
        $this->assertStringContainsString($this->dir . '/en.json.greeting.hello: uses a case-transforming placeholder', $err);
        $this->assertStringContainsString($this->dir . '/en.json.checkout.submit: defines a key also defined in ' . $this->dir . '/checkout.php', $err);

        list($strict) = $this->run_([$this->dir . '/en.json', $this->dir . '/checkout.php'], [], ['strict' => true]);
        $this->assertSame(1, $strict);
    }

    public function testAnOverrideOfAFallbackKeyIsNotAProblem(): void
    {
        file_put_contents($this->dir . '/auth.php', '<?php return ["failed" => "Wrong details."];');
        mkdir($this->dir . '/framework');
        file_put_contents($this->dir . '/framework/auth.php', '<?php return ["failed" => "These credentials do not match.", "throttle" => "Too many attempts. Retry in :Seconds."];');

        $this->assertSame([0, ''], $this->run_([$this->dir . '/auth.php'], [$this->dir . '/framework/auth.php']), 'a fallback tier is not the app\'s to fix');

        unlink($this->dir . '/framework/auth.php');
        rmdir($this->dir . '/framework');
    }
}
