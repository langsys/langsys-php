<?php

namespace Langsys\SDK\Tests\Migration;

use Langsys\SDK\Migration\LegacyKeys;
use PHPUnit\Framework\TestCase;

/**
 * MIG-5 and MIG-7: keys resolve against the app's kept source files - JSON and
 * PHP arrays, nested, several at once, in two tiers - and the namespace a key
 * sits under becomes its category.
 */
class LegacyKeysTest extends TestCase
{
    /** @var string */
    private $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/langsys-mig-' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/app/lang/en', 0777, true);
        mkdir($this->dir . '/framework/lang/en', 0777, true);
        mkdir($this->dir . '/vendor/courier/lang/en', 0777, true);
        mkdir($this->dir . '/app/lang/vendor/courier/en', 0777, true);
    }

    protected function tearDown(): void
    {
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->dir);
    }

    private function json($path, array $data)
    {
        file_put_contents($this->dir . '/' . $path, json_encode($data));

        return $this->dir . '/' . $path;
    }

    private function php($path, array $data)
    {
        file_put_contents($this->dir . '/' . $path, '<?php return ' . var_export($data, true) . ';');

        return $this->dir . '/' . $path;
    }

    public function testNestedKeysResolveInJsonAndPhpArrayFiles(): void
    {
        $json = $this->json('app/en.json', ['checkout' => ['submit' => 'Place order', 'form' => ['title' => 'Your details']]]);
        $php = $this->php('app/lang/en/account.php', ['profile' => ['title' => 'Your profile'], 'save' => 'Save changes']);
        $keys = new LegacyKeys(['files' => [$json, $php]]);

        $this->assertSame(['phrase' => 'Place order', 'category' => 'checkout', 'key' => 'checkout.submit', 'file' => $json, 'recognised' => true, 'issue' => null], $keys->resolve('checkout.submit'));
        $this->assertSame('Your details', $keys->resolve('checkout.form.title')['phrase']);
        $this->assertSame('Your profile', $keys->resolve('account.profile.title')['phrase']);
        $this->assertSame('account', $keys->resolve('account.save')['category'], 'a PHP file group is the namespace');
        $this->assertNull($keys->resolve('checkout.missing'));
        $this->assertNull($keys->resolve('checkout.form'), 'a subtree is not a phrase');
    }

    public function testAFlatJsonKeyIsTriedAsWrittenFirst(): void
    {
        $json = $this->json('app/en.json', ['checkout.submit' => 'Flat wins', 'checkout' => ['submit' => 'Nested'], 'Welcome back. Glad to see you.' => 'Welcome back. Glad to see you.']);
        $keys = new LegacyKeys(['files' => [$json]]);

        $this->assertSame('Flat wins', $keys->resolve('checkout.submit')['phrase']);
        $this->assertSame('checkout', $keys->resolve('checkout.submit')['category']);
        $this->assertNull($keys->resolve('Welcome back. Glad to see you.')['category'], 'a sentence key has no namespace');
    }

    public function testTheFirstConfiguredFileWinsAndADuplicateIsReported(): void
    {
        $first = $this->json('app/en.json', ['checkout' => ['submit' => 'First']]);
        $second = $this->php('app/lang/en/checkout.php', ['submit' => 'Second']);
        $keys = new LegacyKeys(['files' => [$first, $second]]);

        $this->assertSame('First', $keys->resolve('checkout.submit')['phrase']);
        $this->assertSame(['checkout.submit' => [$first, $second]], $keys->duplicates());
    }

    public function testAFallbackTierAnswersWhatTheAppDoesNotAndAnOverrideIsNotADuplicate(): void
    {
        $app = $this->php('app/lang/en/auth.php', ['failed' => 'Those details are wrong.']);
        $framework = $this->php('framework/lang/en/auth.php', ['failed' => 'These credentials do not match our records.', 'throttle' => 'Too many login attempts.']);
        $keys = new LegacyKeys(['files' => [$app], 'fallback_files' => [$framework]]);

        $this->assertSame('Those details are wrong.', $keys->resolve('auth.failed')['phrase'], 'the app overrides');
        $this->assertSame('Too many login attempts.', $keys->resolve('auth.throttle')['phrase'], 'the framework answers the rest');
        $this->assertSame([], $keys->duplicates());
    }

    public function testAPackageKeyResolvesThroughItsNamespaceInTwoTiers(): void
    {
        $override = $this->php('app/lang/vendor/courier/en/messages.php', ['welcome' => 'Hi there']);
        $package = $this->php('vendor/courier/lang/en/messages.php', ['welcome' => 'Welcome', 'bye' => 'Goodbye']);
        $keys = new LegacyKeys(['namespaces' => ['courier' => ['files' => [$override], 'fallback_files' => [$package]]]]);

        $this->assertSame(['phrase' => 'Hi there', 'category' => 'messages', 'key' => 'courier::messages.welcome', 'file' => $override, 'recognised' => true, 'issue' => null], $keys->resolve('courier::messages.welcome'));
        $this->assertSame('Goodbye', $keys->resolve('courier::messages.bye')['phrase']);
        $this->assertNull($keys->resolve('courier::messages.missing'));
        $this->assertNull($keys->resolve('unknown::messages.welcome'));
        $this->assertSame([], $keys->duplicates());

        $short = new LegacyKeys(['namespaces' => ['courier' => [$package]]]);
        $this->assertSame('Welcome', $short->resolve('courier::messages.welcome')['phrase'], 'a bare list is the files tier');
    }

    public function testPluralKeyPairsResolveAsOneIcuPhrase(): void
    {
        $json = $this->json('app/en.json', ['cart' => ['items_one' => '{{count}} item', 'items_other' => '{{count}} items', 'old' => 'one entry', 'old_plural' => '{{count}} entries']]);
        $keys = new LegacyKeys(['files' => [$json]]);

        $this->assertSame('{count, plural, one {# item} other {# items}}', $keys->resolve('cart.items')['phrase']);
        $this->assertSame('{count, plural, one {one entry} other {# entries}}', $keys->resolve('cart.old')['phrase']);
        $this->assertSame('cart', $keys->resolve('cart.items')['category']);
    }

    public function testAnUnrecognisedValueResolvesAsWrittenAndIsReported(): void
    {
        $php = $this->php('app/lang/en/greeting.php', ['hello' => 'Hello, :Name']);
        $keys = new LegacyKeys(['files' => [$php]]);

        $entry = $keys->resolve('greeting.hello');
        $this->assertSame('Hello, :Name', $entry['phrase']);
        $this->assertFalse($entry['recognised']);

        $problems = $keys->problems();
        $this->assertCount(1, $problems);
        $this->assertSame([$php, 'greeting.hello'], [$problems[0]['file'], $problems[0]['key']]);
    }

    public function testFilesAreReadOnlyWhenAKeyIsFirstLookedUp(): void
    {
        $json = $this->json('app/en.json', ['checkout' => ['submit' => 'Place order']]);
        $keys = new LegacyKeys(['files' => [$json]]);

        $this->assertSame([], $keys->loadedFiles(), 'constructing reads nothing');
        $this->assertSame('Place order', $keys->resolve('checkout.submit')['phrase']);
        $this->assertSame([$json], $keys->loadedFiles(), 'the first lookup reads the file');
    }

    public function testAnUnreadableFileIsReportedAndResolvesNothing(): void
    {
        $keys = new LegacyKeys(['files' => [$this->dir . '/does-not-exist.json']]);

        $this->assertNull($keys->resolve('anything'));
        $this->assertSame([], $keys->loadedFiles());
        $this->assertStringContainsString('does-not-exist.json', $keys->problems()[0]['file']);
    }
}
