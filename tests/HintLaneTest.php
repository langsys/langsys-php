<?php

namespace Langsys\SDK\Tests;

use PHPUnit\Framework\TestCase;

/**
 * HINT-2: a server SDK never sends hints.
 *
 * A hint asks the backend to load a page in a headless browser so that the
 * page's own SDK can register it. For a server SDK that page's SDK IS this SDK,
 * so a hint could only ask the backend to render content this SDK could have
 * registered directly - a configuration problem the backend already surfaces
 * through a better channel.
 *
 * The rule is a MUST NOT, so the only evidence is an absence, and an absence is
 * only worth something if the test would notice the first hint call anyone adds.
 *
 * Deliberately PURE: no HTTP double is involved. A double that records outgoing
 * requests can only show that the paths a given test happened to drive sent no
 * hint. Scanning the source shows that no path can.
 */
class HintLaneTest extends TestCase
{
    /**
     * The endpoint family the hint lane uses (`POST /api/discovery/hint`).
     */
    const FORBIDDEN = ['discovery/hint', 'discovery/'];

    public function testAServerSdkNeverSendsAHint(): void
    {
        $hits = [];

        foreach ($this->sourceFiles() as $path => $source) {
            foreach (self::FORBIDDEN as $needle) {
                if (stripos($source, $needle) !== false) {
                    $hits[] = $path . ' references ' . $needle;
                }
            }
        }

        $this->assertSame([], $hits, 'a server SDK must not send hints');
    }

    /**
     * Control: the scan really reaches the SDK source.
     *
     * If the path were wrong or src/ were empty, the test above would pass by
     * scanning nothing. A known endpoint must be found, and more than a handful
     * of files must have been read.
     */
    public function testTheScanReachesTheSdkSource(): void
    {
        $files = $this->sourceFiles();

        $this->assertGreaterThan(10, count($files), 'the scan must read the SDK source, not an empty tree');

        $found = array_filter($files, function ($source) {
            return strpos($source, 'authorize-project') !== false;
        });

        $this->assertNotEmpty($found, 'a real endpoint must be visible to the scan');
    }

    /**
     * @return array<string, string> path relative to the repo root => source
     */
    private function sourceFiles(): array
    {
        $root = dirname(__DIR__);
        $out = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root . '/src', \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if (strtolower($file->getExtension()) !== 'php') {
                continue;
            }

            $out[substr($file->getPathname(), strlen($root) + 1)] = file_get_contents($file->getPathname());
        }

        return $out;
    }
}
