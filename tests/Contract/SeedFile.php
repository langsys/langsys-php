<?php

namespace Langsys\SDK\Tests\Contract;

/**
 * The worlds the SDK test clone is seeded with before a run (spec CONF-2), and
 * the handle a test case reaches its own through.
 *
 * Each contract test file declares its worlds in a module in
 * tests/contract-seeds/ (`<slug>.php`), and the seed file beside it
 * (`<slug>.json`, read by the clone's seeder) is that module rendered:
 * CloneSeedFilesTest fails when the two differ, and
 * `LANGSYS_WRITE_SEEDS=1 vendor/bin/phpunit tests/CloneSeedFilesTest.php`
 * writes the JSON again. A changed seed file takes effect at the next reseed.
 *
 * Every test case has projects of its own, so a world is a pool of identical
 * instances and a case takes the next unused one: no case reads another's
 * writes, whatever order the cases run in. A module's `runs` sets how many
 * runs one reseed serves, and `LANGSYS_CONTRACT_RUN` (from 0) picks the run, so
 * a mutant runs on instances no earlier run wrote to. An instance's ids, keys,
 * user token and caller addresses are fixed by its place in the file, inside
 * this SDK's ranges on the clone (langsys docs/sdk-test-clone.md): ids
 * `7700xxxx-…`, raw keys `php-…`, user token ids 120000 to 129999, addresses in
 * 10.77.0.0/16.
 */
final class SeedFile
{
    /** @var string */
    public $slug;

    /** @var int */
    public $index;

    /** @var int */
    public $runs;

    /** @var array{projects: array, keys: array} What the clone's seeder reads */
    public $document;

    /** @var array<string, World[]> */
    private $pools = [];

    /** @var array<string, int> */
    private $counts = [];

    /** @var array<string, int> */
    private $taken = [];

    /**
     * The directory holding the world modules and the seed files.
     *
     * @return string
     */
    public static function directory()
    {
        return dirname(__DIR__) . '/contract-seeds';
    }

    /**
     * The seed file a module in tests/contract-seeds/ declares.
     *
     * @param string $slug
     * @return self
     */
    public static function load($slug)
    {
        $module = require self::directory() . '/' . $slug . '.php';

        return new self($slug, $module['index'], isset($module['runs']) ? $module['runs'] : 1, $module['worlds']);
    }

    /**
     * @param string $slug
     * @param int $index The file's place in this SDK's range, 1 to 20
     * @param int $runs
     * @param array $worlds Projects and keys by role, per world name
     */
    public function __construct($slug, $index, $runs, array $worlds)
    {
        if (!is_int($index) || $index < 1 || $index > 20) {
            throw new \LogicException("seed file index $index is outside 1 to 20");
        }

        $this->slug = $slug;
        $this->index = $index;
        $this->runs = $runs;

        $projects = [];
        $keys = [];
        $instance = 0;
        $projectSerial = 0;
        $keySerial = 0;

        foreach ($worlds as $name => $world) {
            $count = isset($world['count']) ? $world['count'] : 1;
            $this->counts[$name] = $count;
            $this->pools[$name] = [];

            for ($i = 0; $i < $count * $runs; $i++) {
                $instance++;
                if ($instance > 500) {
                    throw new \LogicException("$slug: more than 500 world instances in one file");
                }

                $label = $name . '#' . ($i + 1);
                // Two /24s per file, 250 addresses in each, and the moved addresses 100 above.
                $block = 2 * $index + intdiv($instance - 1, 250);
                $host = (($instance - 1) % 250) + 1;
                $address = "10.77.$block.$host";
                $moved = '10.77.' . (100 + $block) . ".$host";
                $net = "10.77.$block.0/24";

                $ids = [];
                $users = [];
                $declared = ['projects' => [], 'keys' => []];

                foreach ($world['projects'] as $role => $project) {
                    $projectSerial++;
                    // 500 token ids per file, from 120000.
                    if ($projectSerial > 499) {
                        throw new \LogicException("$slug: more than 499 projects in one file");
                    }

                    $id = sprintf('7700%02x00-%04x-4000-8000-000000000000', $index, $projectSerial);
                    $secret = substr(hash('sha256', "php:$slug:$projectSerial"), 0, 40);
                    $user = [
                        'token' => (120000 + ($index - 1) * 500 + $projectSerial) . '|' . $secret,
                        'device_id' => "php-$slug-$projectSerial",
                    ];
                    $ids[$role] = $id;
                    $users[$role] = $user;
                    $declared['projects'][] = ['role' => $role, 'id' => $id, 'seed' => $project];
                    $projects[] = array_merge(['id' => $id, 'title' => "php $slug $label $role"], self::objects($project), ['user' => $user]);
                }

                $raw = [];
                $keyIds = [];

                foreach ($world['keys'] as $role => $key) {
                    $keySerial++;
                    $id = sprintf('7700%02x01-%04x-4000-8000-000000000000', $index, $keySerial);
                    $value = "php-$slug-$instance-$role";

                    if ($key['project'] !== null && !isset($ids[$key['project']])) {
                        throw new \LogicException("$slug $name: key $role names no project {$key['project']}");
                    }

                    $allowlist = null;
                    if (isset($key['ip_allowlist'])) {
                        $allowlist = [];
                        foreach ($key['ip_allowlist'] as $entry) {
                            $named = ['@self' => $address, '@moved' => $moved, '@net' => $net];
                            if ($entry !== '' && $entry[0] === '@' && !isset($named[$entry])) {
                                throw new \LogicException("$slug $name: key $role names no address $entry");
                            }
                            $allowlist[] = isset($named[$entry]) ? $named[$entry] : $entry;
                        }
                    }

                    $raw[$role] = $value;
                    $keyIds[$role] = $id;
                    // The clone stores an empty allow-list as none.
                    $declared['keys'][] = [
                        'role' => $role,
                        'id' => $id,
                        'project' => $key['project'] === null ? null : $ids[$key['project']],
                        'type' => $key['type'],
                        'ip_allowlist' => $allowlist ?: null,
                        'write_grant_secret' => isset($key['write_grant_secret']),
                    ];

                    $rest = $key;
                    unset($rest['project'], $rest['ip_allowlist']);
                    $entry = array_merge(['id' => $id, 'key' => $value], $rest, ['project' => $key['project'] === null ? null : $ids[$key['project']]]);
                    if ($allowlist !== null) {
                        $entry['ip_allowlist'] = $allowlist;
                    }
                    $keys[] = $entry;
                }

                $unknown = isset($world['unknown']) ? $world['unknown'] : [];
                foreach (isset($unknown['keys']) ? $unknown['keys'] : [] as $role) {
                    $raw[$role] = "php-$slug-$instance-$role";
                }
                foreach (array_values(isset($unknown['projects']) ? $unknown['projects'] : []) as $n => $role) {
                    $ids[$role] = sprintf('7700%02x02-%04x-4000-8000-%012x', $index, $instance, $n + 1);
                }

                $this->pools[$name][] = new World($label, $address, $moved, $ids, $raw, $keyIds, $users, $declared);
            }
        }

        $this->document = ['projects' => $projects, 'keys' => $keys];
    }

    /**
     * The next unused instance of a world in this run.
     *
     * @param string $name
     * @return World
     */
    public function take($name)
    {
        if (!isset($this->pools[$name])) {
            throw new \LogicException("{$this->slug} declares no world $name");
        }

        $run = (int) getenv('LANGSYS_CONTRACT_RUN');
        if ($run < 0 || $run >= $this->runs) {
            throw new \LogicException("tests/contract-seeds/{$this->slug}.json serves {$this->runs} run(s) a reseed and this is run " . ($run + 1) . ': raise its runs, write the JSON again and reseed the clone');
        }

        $next = isset($this->taken[$name]) ? $this->taken[$name] : 0;
        if ($next >= $this->counts[$name]) {
            throw new \LogicException("tests/contract-seeds/{$this->slug}.json holds {$this->counts[$name]} \"$name\" worlds a run and this is case " . ($next + 1) . " to take one: raise its count in tests/contract-seeds/{$this->slug}.php, write the JSON again and reseed the clone");
        }
        $this->taken[$name] = $next + 1;

        return $this->pools[$name][$run * $this->counts[$name] + $next];
    }

    /**
     * The seed file as the clone's seeder reads it.
     *
     * @return string
     */
    public function json()
    {
        return json_encode($this->document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }

    /**
     * A project's seed with each translations map written as a JSON object, as
     * the seeder reads it, however few locales it names.
     *
     * @param array $project
     * @return array
     */
    private static function objects(array $project)
    {
        foreach (['phrases', 'blocks'] as $list) {
            foreach (isset($project[$list]) ? $project[$list] : [] as $i => $item) {
                if (isset($item['translations'])) {
                    $project[$list][$i]['translations'] = (object) $item['translations'];
                }
                foreach (isset($item['phrases']) ? $item['phrases'] : [] as $k => $phrase) {
                    if (isset($phrase['translations'])) {
                        $project[$list][$i]['phrases'][$k]['translations'] = (object) $phrase['translations'];
                    }
                }
            }
        }

        return $project;
    }
}
