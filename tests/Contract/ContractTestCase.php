<?php

namespace Langsys\SDK\Tests\Contract;

use Langsys\SDK\Cache\CacheInterface;
use Langsys\SDK\Cache\NullCache;
use Langsys\SDK\Client;
use PHPUnit\Framework\TestCase;

/**
 * Tests that run against the contract fixture (spec CONF-2): a real Langsys API
 * on an SDK test clone, seeded before the run, reached through
 * tests/contract-fixture/forwarder.mjs, vendored byte for byte from
 * langsys-js-typescript v0.7.0 at tree f72fab59caae1822687ca0a61f6711dcfefc4e6d.
 *
 * Each test class names its seed module in `$seed`; a case takes a world of its
 * own with world(), which names it to the forwarder and confirms through the API
 * that its seed landed before it acts. A test asserts on what the API holds,
 * read back through its routes as a client or the project's owner reads it:
 * held() is the catalog, storedBlocks() a block's stored content, hints() the
 * hints the clone stored, the one result no route returns. There is no record
 * of what the fixture received.
 *
 * LANGSYS_CONTRACT_TARGET names the clone's origin and
 * LANGSYS_CONTRACT_CLONE_ROOT its langsys checkout, and without them every test
 * here errors: a contract test never passes by skipping. NODE_EXTRA_CA_CERTS
 * names the certificate a clone served over HTTPS uses, and
 * LANGSYS_CONTRACT_LOG the file the forwarder logs to.
 */
abstract class ContractTestCase extends TestCase
{
    /** @var string The module in tests/contract-seeds/ that declares this class's worlds */
    protected static $seed;

    /** @var resource|null */
    private static $process;

    /** @var array */
    private static $pipes = [];

    /** @var string|null */
    protected static $baseUrl;

    /** @var string|null */
    protected static $fixtureUrl;

    /** @var SeedFile|null */
    protected static $seeds;

    public static function setUpBeforeClass(): void
    {
        self::$seeds = SeedFile::load(static::$seed);

        $target = getenv('LANGSYS_CONTRACT_TARGET');
        $cloneRoot = getenv('LANGSYS_CONTRACT_CLONE_ROOT');

        if (!$target || !$cloneRoot) {
            throw new \RuntimeException(
                'The contract tests run against an SDK test clone, a real Langsys API (spec CONF-2), and none is named. '
                . 'Set LANGSYS_CONTRACT_TARGET to its origin, LANGSYS_CONTRACT_CLONE_ROOT to its langsys checkout, and NODE_EXTRA_CA_CERTS '
                . 'to the certificate it serves with: tests/README.md, "Contract tests".'
            );
        }

        $node = trim((string) shell_exec('command -v node 2>/dev/null'));
        if ($node === '') {
            throw new \RuntimeException('The contract fixture needs Node 18 or later, and no node is on the PATH');
        }

        $command = [$node, dirname(__DIR__) . '/contract-fixture/forwarder.mjs', '--target', $target, '--clone-root', $cloneRoot];
        $log = getenv('LANGSYS_CONTRACT_LOG');
        if ($log) {
            array_push($command, '--log', $log);
        }

        self::$process = proc_open($command, [1 => ['pipe', 'w'], 2 => STDERR], self::$pipes);
        if (!is_resource(self::$process)) {
            throw new \RuntimeException('The contract fixture could not be started');
        }

        $read = [self::$pipes[1]];
        $none = null;
        $line = stream_select($read, $none, $none, 20) ? fgets(self::$pipes[1]) : false;
        $ready = $line === false ? null : json_decode($line, true);

        if (!is_array($ready) || empty($ready['ready'])) {
            self::stopForwarder();
            throw new \RuntimeException('The contract fixture did not report ready within 20s: ' . var_export($line, true));
        }

        self::$baseUrl = $ready['base_url'];
        self::$fixtureUrl = $ready['fixture_url'];
    }

    public static function tearDownAfterClass(): void
    {
        self::stopForwarder();
    }

    private static function stopForwarder()
    {
        if (is_resource(self::$process)) {
            proc_terminate(self::$process);
            proc_close(self::$process);
        }

        self::$process = null;
    }

    /**
     * Take the next unused instance of a world, name it to the forwarder from
     * its own address, and confirm its seed landed before anything spends its
     * faults or lists.
     *
     * @param string $name
     * @param array $setup caller, faults, reference
     * @return World
     */
    protected function world($name, array $setup = [])
    {
        $world = self::$seeds->take($name);
        $setup = $this->withFormat($world, $setup);
        $caller = isset($setup['caller']) ? $setup['caller'] : $world->address;

        $this->fixture('/setup', $world->names() + ['caller' => $caller, 'faults' => [], 'reference' => null]);
        $this->confirmSeed($world);

        if (!empty($setup['faults']) || !empty($setup['reference'])) {
            $this->fixture('/setup', $world->names() + [
                'caller' => $caller,
                'faults' => isset($setup['faults']) ? $setup['faults'] : [],
                'reference' => isset($setup['reference']) ? $setup['reference'] : null,
            ]);
        }

        return $world;
    }

    /**
     * Change a case's setup, such as a move to another address; what it leaves
     * out stays.
     *
     * @param World $world
     * @param array $setup
     */
    protected function update(World $world, array $setup)
    {
        $this->fixture('/setup', $world->names() + $this->withFormat($world, $setup));
    }

    /**
     * A real Client against the clone. Every call goes over HTTP.
     *
     * @param string $key
     * @param string $project
     * @param CacheInterface|null $cache
     * @param array $options
     * @return Client
     */
    protected function client($key, $project, CacheInterface $cache = null, array $options = [])
    {
        return new Client($key, $project, array_merge([
            'api_url' => self::$baseUrl,
            'cache' => $cache !== null ? $cache : new NullCache(),
        ], $options));
    }

    /**
     * What the catalog serves for a project at a locale, read with `key`, by
     * category: phrases by text, blocks by custom id with their phrases. Sorted
     * throughout, categories in byte order, since the API promises no order.
     *
     * @param string $key
     * @param string $project
     * @param string $locale
     * @return array{phrases: array<string, string[]>, blocks: array<string, array<string, string[]>>}
     */
    protected function held($key, $project, $locale)
    {
        $data = $this->read('/translations?' . http_build_query(['project_id' => $project, 'locale' => $locale]), ['X-Authorization: ' . $key]);
        $held = ['phrases' => [], 'blocks' => []];

        foreach (is_array($data) ? $data : [] as $category => $entries) {
            foreach (is_array($entries) ? $entries : [] as $name => $value) {
                if (is_array($value)) {
                    $phrases = array_map('strval', array_keys($value));
                    sort($phrases, SORT_STRING);
                    $held['blocks'][$category][$name] = $phrases;
                } else {
                    $held['phrases'][$category][] = (string) $name;
                }
            }
        }

        foreach ($held['phrases'] as $category => $phrases) {
            sort($phrases, SORT_STRING);
            $held['phrases'][$category] = $phrases;
        }
        ksort($held['phrases'], SORT_STRING);
        ksort($held['blocks'], SORT_STRING);
        foreach ($held['blocks'] as $category => $blocks) {
            ksort($blocks, SORT_STRING);
            $held['blocks'][$category] = $blocks;
        }

        return $held;
    }

    /**
     * What the catalog serves a project at a locale, as the API sends it.
     *
     * @param string $key
     * @param string $project
     * @param string $locale
     * @return array
     */
    protected function catalog($key, $project, $locale)
    {
        $data = $this->read('/translations?' . http_build_query(['project_id' => $project, 'locale' => $locale]), ['X-Authorization: ' . $key]);

        return is_array($data) ? $data : [];
    }

    /**
     * Every phrase text a catalog holds, under any category: what an absence is
     * checked against.
     *
     * @param array $held
     * @return string[]
     */
    protected static function anyPhrase(array $held)
    {
        return $held['phrases'] === [] ? [] : array_merge(...array_values($held['phrases']));
    }

    /**
     * Every block id a catalog holds, under any category.
     *
     * @param array $held
     * @return string[]
     */
    protected static function anyBlock(array $held)
    {
        $ids = [];
        foreach ($held['blocks'] as $blocks) {
            foreach (array_keys($blocks) as $id) {
                $ids[] = (string) $id;
            }
        }

        return $ids;
    }

    /**
     * The blocks the world's project `role` holds, each with the content it
     * stored, as its owner reads them, sorted by custom id.
     *
     * @param World $world
     * @param string $key
     * @param string $locale
     * @param string $role
     * @return array<int, array{custom_id: string, category: string|null, content: string|null}>
     */
    protected function storedBlocks(World $world, $key, $locale, $role = 'p1')
    {
        $rows = $this->read('/translations/data?' . http_build_query(['project_id' => $world->project($role), 'locale' => $locale]), ['X-Authorization: ' . $key]);
        $blocks = [];

        foreach (is_array($rows) ? $rows : [] as $items) {
            foreach (is_array($items) ? $items : [] as $row) {
                if (!is_array($row) || (isset($row['type']) ? $row['type'] : null) !== 'content_block') {
                    continue;
                }

                $item = $this->asUser($world, '/translatable-items/' . rawurlencode($row['id']), ['role' => $role]);
                if ($item['status'] !== 200) {
                    $this->fail("the owner's read of block {$row['custom_id']} answered {$item['status']}: " . json_encode($item['body']));
                }

                $blocks[] = [
                    'custom_id' => isset($row['custom_id']) ? (string) $row['custom_id'] : '',
                    'category' => isset($row['category']) ? $row['category'] : null,
                    'content' => isset($item['body']['data']['content']) ? $item['body']['data']['content'] : null,
                ];
            }
        }

        usort($blocks, function ($a, $b) {
            return strcmp($a['custom_id'], $b['custom_id']);
        });

        return $blocks;
    }

    /**
     * A call to the API under an API key, made by the test itself.
     *
     * @param string $path
     * @param array $init key, method, body
     * @return array{status: int, body: mixed}
     */
    protected function api($path, array $init = [])
    {
        $headers = isset($init['key']) ? ['X-Authorization: ' . $init['key']] : [];

        return $this->call($path, isset($init['method']) ? $init['method'] : null, $headers, array_key_exists('body', $init) ? $init['body'] : null, array_key_exists('body', $init));
    }

    /**
     * A call to the API's user routes as the seeded user who owns the world's
     * project `role`.
     *
     * @param World $world
     * @param string $path
     * @param array $init method, body, role
     * @return array{status: int, body: mixed}
     */
    protected function asUser(World $world, $path, array $init = [])
    {
        $user = $world->user(isset($init['role']) ? $init['role'] : 'p1');
        $headers = ['Authorization: Bearer ' . $user['token'], 'x-device-id: ' . $user['device_id'], 'X-Langsys-Capabilities: icu'];

        return $this->call($path, isset($init['method']) ? $init['method'] : null, $headers, array_key_exists('body', $init) ? $init['body'] : null, array_key_exists('body', $init));
    }

    /**
     * Change one of the world's keys as its project's owner would
     * (`PATCH /api-keys/{id}`): its type, its allow-list.
     *
     * @param World $world
     * @param string $role
     * @param array $fields
     */
    protected function editKey(World $world, $role, array $fields)
    {
        $projectRole = null;
        foreach ($world->declared['keys'] as $key) {
            if ($key['role'] === $role) {
                foreach ($world->declared['projects'] as $project) {
                    if ($project['id'] === $key['project']) {
                        $projectRole = $project['role'];
                    }
                }
            }
        }

        $answer = $this->asUser($world, '/api-keys/' . rawurlencode($world->keyId($role)), ['method' => 'PATCH', 'body' => $fields, 'role' => $projectRole ?: 'p1']);
        if ($answer['status'] !== 200) {
            $this->fail("editing key $role answered {$answer['status']}: " . json_encode($answer['body']));
        }
    }

    /**
     * The hints the clone stored for the world's projects, in the order it
     * accepted them.
     *
     * @param World $world
     * @return array<int, array{project_id: string, url: string}>
     */
    protected function hints(World $world)
    {
        $query = implode('&', array_map(function ($project) {
            return 'project=' . rawurlencode($project);
        }, $world->names()['projects']));
        $answer = $this->http('GET', self::$fixtureUrl . '/state?' . $query, []);

        if ($answer['status'] !== 200) {
            $this->fail("fixture /state answered {$answer['status']}: {$answer['raw']}");
        }

        return isset($answer['body']['hints']) ? $answer['body']['hints'] : [];
    }

    /**
     * Confirm through the API that a world's seed landed before its case acts
     * (spec CONF-2), so a seed that did not land fails as one instead of passing
     * an absence vacuously, and that no run has written to the world since, as
     * its catalog and the hints stored for it show. Every read is the project's
     * owner's, from the case's own address: an owner's read is metered against
     * no usage and counted by no duplicate guard, so the case observes none of
     * them.
     *
     * @param World $world
     */
    private function confirmSeed(World $world)
    {
        $gaps = [];
        foreach ($world->declared['projects'] as $project) {
            $gaps = array_merge($gaps, $this->projectGaps($world, $project));
        }

        $stored = $this->hints($world);
        if ($stored !== []) {
            $gaps[] = 'the clone holds ' . count($stored) . ' hint(s) for its projects, which its seed does not: a run has used this world, so reseed the clone';
        }

        if ($gaps !== []) {
            $this->fail("seed not landed: {$world->name} " . implode('; ', $gaps));
        }
    }

    /**
     * Where what the API holds for one of a world's projects differs from its
     * seed, as the project's owner reads it: its keys on every field the key
     * listing returns; its settings as its own read returns them; the catalog at
     * its base locale, which holds exactly what the seed declares, and at each
     * target locale where it seeds phrases or blocks; and, as each item's own, a
     * block's markup and a phrase's translation seeded at the base locale. A
     * suspended project answers every catalog read 402 `subscription_suspended`,
     * its owner's too, which is its seed landing.
     *
     * @param World $world
     * @param array $project
     * @return string[]
     */
    private function projectGaps(World $world, array $project)
    {
        $role = $project['role'];
        $seed = $project['seed'];
        $base = self::formatLocale(isset($seed['base_locale']) ? $seed['base_locale'] : 'en-us');
        $targets = array_map([self::class, 'formatLocale'], isset($seed['target_locales']) ? $seed['target_locales'] : []);
        $owner = function ($path) use ($world, $role) {
            return $this->asUser($world, $path, ['role' => $role]);
        };
        $query = function ($locale) use ($project) {
            return http_build_query(['project_id' => $project['id'], 'locale' => $locale]);
        };
        $gaps = [];

        // The keys, on every field the key listing returns.
        $listed = $owner('/api-keys?' . http_build_query(['project_id' => $project['id']]));
        if ($listed['status'] !== 200) {
            $gaps[] = "$role's key listing answered {$listed['status']}";
        } else {
            $rows = [];
            foreach (isset($listed['body']['data']) && is_array($listed['body']['data']) ? $listed['body']['data'] : [] as $row) {
                $rows[$row['id']] = $row;
            }
            foreach ($world->declared['keys'] as $key) {
                if ($key['project'] !== $project['id']) {
                    continue;
                }
                if (!isset($rows[$key['id']])) {
                    $gaps[] = "key {$key['role']} ({$key['id']}) is not listed";
                    continue;
                }
                $row = $rows[$key['id']];
                $held = ['type' => $row['type'], 'ip_allowlist' => isset($row['ip_allowlist']) ? $row['ip_allowlist'] : null, 'active' => isset($row['active']) ? $row['active'] : null, 'write_grant_secret' => isset($row['write_grant_secret_set_at'])];
                $seeded = ['type' => $key['type'], 'ip_allowlist' => $key['ip_allowlist'], 'active' => true, 'write_grant_secret' => $key['write_grant_secret']];
                if ($held !== $seeded) {
                    $gaps[] = "key {$key['role']} is " . json_encode($held) . ', seeded ' . json_encode($seeded);
                }
            }
        }

        $suspended = !empty($seed['subscription_suspended']);
        $hasItems = !empty($seed['phrases']) || !empty($seed['blocks']);
        $locales = [$base];
        if (!$suspended && $hasItems) {
            $locales = array_merge($locales, $targets);
        }

        foreach ($locales as $locale) {
            $answer = $owner('/translations?' . $query($locale));
            if ($suspended) {
                if (!($answer['status'] === 402 && (isset($answer['body']['error']['code']) ? $answer['body']['error']['code'] : null) === 'subscription_suspended')) {
                    $gaps[] = "$role is not suspended: its catalog answered {$answer['status']}";
                }
                continue;
            }
            if ($answer['status'] !== 200) {
                $gaps[] = "$role's catalog at $locale answered {$answer['status']}";
                continue;
            }
            $data = isset($answer['body']['data']) && is_array($answer['body']['data']) ? $answer['body']['data'] : [];
            foreach (self::catalogGaps($seed, $locale, $base, $data) as $gap) {
                $gaps[] = "$role: $gap";
            }
            if ($locale === $base) {
                $extras = self::catalogExtras($seed, $data);
                if ($extras !== []) {
                    $gaps[] = "$role holds " . implode(', ', $extras) . ', which its seed does not: a run has used this world, so reseed the clone';
                }
            }
        }

        if ($suspended) {
            return $gaps;
        }

        // The settings the project's own read returns, as the seeder writes them.
        $read = $owner('/projects/' . rawurlencode($project['id']));
        if ($read['status'] !== 200) {
            $gaps[] = "$role's project read answered {$read['status']}";
        } else {
            $data = isset($read['body']['data']) ? $read['body']['data'] : [];
            $settings = isset($data['settings']['locales']) ? $data['settings']['locales'] : [];
            $heldTargets = array_map([self::class, 'formatLocale'], isset($settings['target_locales']) ? $settings['target_locales'] : []);
            sort($heldTargets);
            $seededTargets = $targets;
            sort($seededTargets);
            $held = [
                'website_url' => isset($data['website_url']) ? $data['website_url'] : null,
                'base_locale' => self::formatLocale(isset($settings['base_locale']) ? $settings['base_locale'] : ''),
                'target_locales' => $heldTargets,
                'discovery_base_locale_only' => isset($settings['discovery_base_locale_only']) ? $settings['discovery_base_locale_only'] : false,
                'machine_translate_new_phrases' => isset($data['settings']['ai_translator']['machine_translate_new_phrases']) ? $data['settings']['ai_translator']['machine_translate_new_phrases'] : null,
            ];
            $seeded = [
                'website_url' => isset($seed['website_url']) ? $seed['website_url'] : null,
                'base_locale' => $base,
                'target_locales' => $seededTargets,
                'discovery_base_locale_only' => isset($seed['discovery_base_locale_only']) ? $seed['discovery_base_locale_only'] : false,
                'machine_translate_new_phrases' => isset($seed['machine_translate_new_phrases']) ? $seed['machine_translate_new_phrases'] : false,
            ];
            if ($held !== $seeded) {
                $gaps[] = "$role's settings are " . json_encode($held) . ', seeded ' . json_encode($seeded);
            }
        }

        // A block's markup, and a phrase's translation seeded at the base locale, read as the item's own.
        $baseTranslated = array_filter(isset($seed['phrases']) ? $seed['phrases'] : [], function ($phrase) use ($base) {
            return (!isset($phrase['translatable']) || $phrase['translatable'] !== false) && self::seededAt($phrase, $base) !== null;
        });
        if (!empty($seed['blocks']) || $baseTranslated !== []) {
            $rows = $owner('/translations/data?' . $query($base));
            if ($rows['status'] !== 200) {
                return array_merge($gaps, ["$role's catalog rows answered {$rows['status']}"]);
            }
            $blockIds = [];
            $phraseIds = [];
            foreach (isset($rows['body']['data']) && is_array($rows['body']['data']) ? $rows['body']['data'] : [] as $category => $items) {
                foreach (is_array($items) ? $items : [] as $row) {
                    if (isset($row['type']) && $row['type'] === 'content_block' && !empty($row['custom_id'])) {
                        $blockIds[$row['custom_id']] = $row['id'];
                    }
                    if (isset($row['type']) && $row['type'] === 'phrase') {
                        $phraseIds[json_encode([(string) $category, $row['phrase']])] = $row['id'];
                    }
                }
            }
            $owned = function ($id, $what, callable $check) use ($owner, $role) {
                if ($id === null) {
                    return ["$role: $what has no row"];
                }
                $item = $owner('/translatable-items/' . rawurlencode($id));
                if ($item['status'] !== 200) {
                    return ["$role: $what's read answered {$item['status']}"];
                }
                $gap = $check(isset($item['body']['data']) ? $item['body']['data'] : []);

                return $gap === null ? [] : ["$role: $what $gap"];
            };
            foreach (isset($seed['blocks']) ? $seed['blocks'] : [] as $block) {
                $want = isset($block['content']) ? $block['content'] : null;
                $gaps = array_merge($gaps, $owned(isset($blockIds[$block['custom_id']]) ? $blockIds[$block['custom_id']] : null, "block {$block['custom_id']}", function ($data) use ($want) {
                    $content = isset($data['content']) ? $data['content'] : null;

                    return $content === $want ? null : 'holds ' . json_encode($content) . ', seeded ' . json_encode($want);
                }));
            }
            foreach ($baseTranslated as $phrase) {
                $category = self::servedCategory(isset($phrase['category']) ? $phrase['category'] : null);
                $want = self::seededAt($phrase, $base);
                $id = json_encode([$category, $phrase['phrase']]);
                $gaps = array_merge($gaps, $owned(isset($phraseIds[$id]) ? $phraseIds[$id] : null, "\"{$phrase['phrase']}\" in $category", function ($data) use ($want, $base) {
                    $translation = isset($data['translation']) ? $data['translation'] : null;

                    return $translation === $want ? null : 'holds ' . json_encode($translation) . " at $base, seeded " . json_encode($want);
                }));
            }
        }

        return $gaps;
    }

    /**
     * Where a catalog read at `locale` differs from the phrases and blocks a
     * project's seed declares.
     */
    private static function catalogGaps(array $seed, $locale, $base, array $data)
    {
        $gaps = [];
        $check = function ($served, array $phrase, $where) use (&$gaps, $locale, $base) {
            if (!is_array($served) || !array_key_exists($phrase['phrase'], $served)) {
                $gaps[] = "\"{$phrase['phrase']}\" $where is not served at $locale";

                return;
            }
            // An untranslatable phrase is served as null; at a target locale a
            // seeded translation as given; otherwise the API decides.
            if (isset($phrase['translatable']) && $phrase['translatable'] === false) {
                $seeded = null;
            } elseif ($locale === $base || self::seededAt($phrase, $locale) === null) {
                return;
            } else {
                $seeded = self::seededAt($phrase, $locale);
            }
            if ($served[$phrase['phrase']] !== $seeded) {
                $gaps[] = "\"{$phrase['phrase']}\" $where is served at $locale as " . json_encode($served[$phrase['phrase']]) . ', seeded ' . json_encode($seeded);
            }
        };

        foreach (isset($seed['phrases']) ? $seed['phrases'] : [] as $phrase) {
            $category = self::servedCategory(isset($phrase['category']) ? $phrase['category'] : null);
            $check(isset($data[$category]) ? $data[$category] : null, $phrase, "in $category");
        }
        foreach (isset($seed['blocks']) ? $seed['blocks'] : [] as $block) {
            $category = self::servedCategory(isset($block['category']) ? $block['category'] : null);
            $served = isset($data[$category][$block['custom_id']]) ? $data[$category][$block['custom_id']] : null;
            if (!is_array($served)) {
                $gaps[] = "block {$block['custom_id']} is not served at $locale";
                continue;
            }
            foreach (isset($block['phrases']) ? $block['phrases'] : [] as $phrase) {
                $check($served, $phrase, "in block {$block['custom_id']}");
            }
        }

        return $gaps;
    }

    /**
     * What a catalog read serves that a project's seed does not declare. A world
     * a run has already written to holds what that run registered, so its
     * absences and first reads prove nothing until the clone is reseeded.
     */
    private static function catalogExtras(array $seed, array $data)
    {
        $phrases = [];
        foreach (isset($seed['phrases']) ? $seed['phrases'] : [] as $phrase) {
            $phrases[json_encode([self::servedCategory(isset($phrase['category']) ? $phrase['category'] : null), $phrase['phrase']])] = true;
        }
        $blocks = [];
        foreach (isset($seed['blocks']) ? $seed['blocks'] : [] as $block) {
            $blocks[json_encode([self::servedCategory(isset($block['category']) ? $block['category'] : null), $block['custom_id']])] = array_column(isset($block['phrases']) ? $block['phrases'] : [], 'phrase');
        }

        $extras = [];
        foreach ($data as $category => $entries) {
            foreach (is_array($entries) ? $entries : [] as $name => $value) {
                $key = json_encode([(string) $category, (string) $name]);
                if (!is_array($value)) {
                    if (!isset($phrases[$key])) {
                        $extras[] = "\"$name\" in $category";
                    }
                    continue;
                }
                if (!isset($blocks[$key])) {
                    $extras[] = "block $name in $category";
                    continue;
                }
                foreach (array_keys($value) as $phrase) {
                    if (!in_array((string) $phrase, $blocks[$key], true)) {
                        $extras[] = "\"$phrase\" in block $name";
                    }
                }
            }
        }

        return $extras;
    }

    /**
     * The translation a seeded phrase is given at a locale, if any.
     */
    private static function seededAt(array $phrase, $locale)
    {
        foreach (isset($phrase['translations']) ? $phrase['translations'] : [] as $given => $text) {
            if (self::formatLocale($given) === $locale) {
                return $text;
            }
        }

        return null;
    }

    /** The category the catalog serves a seeded item under. */
    private static function servedCategory($category)
    {
        return $category === null || $category === '' ? '__uncategorized__' : $category;
    }

    /** A locale as the clone stores it. */
    private static function formatLocale($locale)
    {
        return strtolower(str_replace('_', '-', (string) $locale));
    }

    /**
     * A case's lists take the locale-code format its world's seed gives its
     * organisation, so a setup never names one.
     */
    private function withFormat(World $world, array $setup)
    {
        if (empty($setup['reference'])) {
            return $setup;
        }
        if (array_key_exists('locale_code_format', $setup['reference'])) {
            throw new \LogicException("{$world->name}: a case's lists take the locale-code format its world's seed gives its organisation, so a setup does not name one");
        }
        $formats = array_values(array_unique(array_map(function ($project) {
            return isset($project['seed']['locale_code_format']) ? $project['seed']['locale_code_format'] : 'lowercase-hyphen';
        }, $world->declared['projects'])));
        if (count($formats) > 1) {
            throw new \LogicException("{$world->name}: its projects' organisations write locale codes as " . implode(' and ', $formats) . ', and a case\'s lists take one format');
        }
        $setup['reference']['locale_code_format'] = $formats === [] ? 'lowercase-hyphen' : $formats[0];

        return $setup;
    }

    /**
     * A read the test makes, failing the test on anything but a 2xx.
     *
     * @return mixed The answer's data
     */
    private function read($path, array $headers)
    {
        $answer = $this->http('GET', self::$baseUrl . $path, array_merge(['Accept: application/json'], $headers));
        if ($answer['status'] < 200 || $answer['status'] >= 300 || !is_array($answer['body'])) {
            $this->fail("$path answered {$answer['status']}: {$answer['raw']}");
        }

        return isset($answer['body']['data']) ? $answer['body']['data'] : null;
    }

    private function call($path, $method, array $headers, $payload, $hasPayload)
    {
        $answer = $this->http(
            $method !== null ? $method : ($hasPayload ? 'POST' : 'GET'),
            self::$baseUrl . $path,
            array_merge(['Accept: application/json', 'Content-Type: application/json'], $headers),
            $hasPayload ? json_encode($payload) : null
        );

        return ['status' => $answer['status'], 'body' => $answer['body']];
    }

    private function fixture($path, array $body)
    {
        $answer = $this->http('POST', self::$fixtureUrl . $path, ['Content-Type: application/json'], json_encode($body));
        if ($answer['status'] < 200 || $answer['status'] >= 300) {
            $this->fail("fixture $path answered {$answer['status']}: {$answer['raw']}");
        }
    }

    /**
     * @return array{status: int, body: mixed, raw: string}
     */
    private function http($method, $url, array $headers, $body = null)
    {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
        ]);
        if ($body !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }

        $raw = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        $raw = $raw === false ? '' : (string) $raw;

        return ['status' => $status, 'body' => json_decode($raw, true), 'raw' => $raw];
    }
}
