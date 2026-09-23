<?php

namespace Langsys\SDK\Tests\Contract;

/**
 * SRV-3 against the contract fixture, in a separate PHP process so its shutdown
 * handler really runs. The render prints its page; the registration it collected
 * reaches the server only after that, when the process ends. The double is seeded
 * to hold each registration for a second, so a collection made inline would be in
 * the server's state before the page was printed.
 */
class ShutdownFlushContractTest extends ContractTestCase
{
    private function render($key)
    {
        $script = sys_get_temp_dir() . '/langsys-srv3-' . bin2hex(random_bytes(4)) . '.php';
        file_put_contents($script, '<?php
require ' . var_export(dirname(__DIR__, 2) . '/vendor/autoload.php', true) . ';
$client = new \\Langsys\\SDK\\Client(' . var_export($key, true) . ', ' . var_export(self::PROJECT, true) . ', ["api_url" => ' . var_export(self::$baseUrl, true) . ', "cache" => new \\Langsys\\SDK\\Cache\\NullCache()]);
$client->setLocale("es-es");
echo $client->translatePage("<html><body><p>Collected after the response</p></body></html>"), "\n";
fflush(STDOUT);
');

        $process = proc_open([PHP_BINARY, $script], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $page = fgets($pipes[1]);
        $stateWhenPrinted = $this->registeredPhrases();
        stream_get_contents($pipes[1]);
        proc_close($process);
        @unlink($script);

        return [$page, $stateWhenPrinted];
    }

    public function testAWriteKeyRegistersAfterThePageIsOut(): void
    {
        $this->seedProject(['k-write' => ['type' => 'write']], [], [], [['method' => 'POST', 'path' => '/translatable-items', 'delay_ms' => 1000]]);

        list($page, $stateWhenPrinted) = $this->render('k-write');

        $this->assertStringContainsString('Collected after the response', $page);
        $this->assertSame([], $stateWhenPrinted, 'nothing was registered before the page was printed');
        $this->assertSame([[null, 'Collected after the response']], $this->registeredPhrases(), 'and it was registered once the process ended');
    }

    public function testAReadKeyOnTheSameRenderRegistersNothing(): void
    {
        $this->seedProject(['k-read' => ['type' => 'read']]);

        list($page) = $this->render('k-read');

        $this->assertStringContainsString('Collected after the response', $page);
        $this->assertSame([], $this->registeredPhrases());
    }
}
