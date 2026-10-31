<?php

namespace Langsys\SDK\Tests\Contract;

/**
 * SRV-3 against the contract fixture, in a separate PHP process so its shutdown
 * handler really runs. The render prints its page; the registration it collected
 * reaches the API only after that, when the process ends. The fixture holds each
 * registration for a second, so a collection made inline would be in the catalog
 * before the page was printed.
 */
class ShutdownFlushContractTest extends ContractTestCase
{
    protected static $seed = 'shutdown-flush';

    private function phrases(World $world, $role)
    {
        return $this->held($world->key($role), $world->project(), 'en-us')['phrases'];
    }

    private function render(World $world, $role, $after = '')
    {
        $key = $world->key($role);
        $script = sys_get_temp_dir() . '/langsys-srv3-' . bin2hex(random_bytes(4)) . '.php';
        file_put_contents($script, '<?php
require ' . var_export(dirname(__DIR__, 2) . '/vendor/autoload.php', true) . ';
$client = new \\Langsys\\SDK\\Client(' . var_export($key, true) . ', ' . var_export($world->project(), true) . ', ["api_url" => ' . var_export(self::$baseUrl, true) . ', "cache" => new \\Langsys\\SDK\\Cache\\NullCache()]);
$client->setLocale("es-es");
echo $client->translatePage("<html><body><p>Collected after the response</p></body></html>"), "\n";
fflush(STDOUT);
' . $after);

        // Each line the process prints, and the catalog as it was read then.
        $process = proc_open([PHP_BINARY, $script], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $lines = [];
        $states = [];
        while (($line = fgets($pipes[1])) !== false) {
            $lines[] = $line;
            $states[] = $this->phrases($world, $role);
        }
        proc_close($process);
        @unlink($script);

        return [$lines, $states];
    }

    public function testAWriteKeyRegistersAfterThePageIsOut(): void
    {
        $world = $this->world('writer', ['faults' => [['method' => 'POST', 'path' => '/translatable-items', 'delay_ms' => 1000]]]);

        list($lines, $states) = $this->render($world, 'write');

        $this->assertStringContainsString('Collected after the response', $lines[0]);
        $this->assertSame([], $states[0], 'nothing was registered before the page was printed');
        $this->assertSame(['__uncategorized__' => ['Collected after the response']], $this->phrases($world, 'write'), 'and it was registered once the process ended');
    }

    /**
     * The send at the end runs with the process's shutdown functions, while
     * the framework that built the Client is still up, and not as PHP frees
     * what is left, in no set order, once they are done. A shutdown function
     * registered after the render finds the registration made, and holds the
     * process there while the catalog is read.
     */
    public function testTheSendAtTheEndRunsWithTheShutdownFunctions(): void
    {
        $world = $this->world('writer');

        list($lines, $states) = $this->render($world, 'write', 'register_shutdown_function(function () {
    echo "shutdown functions running\n";
    fflush(STDOUT);
    usleep(1500000);
});
');

        $marker = array_search("shutdown functions running\n", $lines, true);
        $this->assertNotFalse($marker);
        $this->assertSame(['__uncategorized__' => ['Collected after the response']], $states[$marker]);
    }

    public function testAReadKeyOnTheSameRenderRegistersNothing(): void
    {
        $world = $this->world('reader');

        list($lines) = $this->render($world, 'read');

        $this->assertStringContainsString('Collected after the response', $lines[0]);
        $this->assertSame([], $this->phrases($world, 'read'));
    }
}
