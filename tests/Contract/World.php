<?php

namespace Langsys\SDK\Tests\Contract;

/**
 * One instance of a seeded world: what a contract test case needs to reach it,
 * and to name itself to the forwarder (spec CONF-2).
 *
 * Its projects and keys are named by role, as the seed module declares them.
 * `declared` is what the instance's seed holds, for the check that it landed
 * before the case acts.
 */
final class World
{
    /** @var string */
    public $name;

    /** @var string The address the case calls from */
    public $address;

    /** @var string A second address of the case's own, to move to when its world drifts */
    public $moved;

    /** @var array{projects: array, keys: array} */
    public $declared;

    /** @var array<string, string> */
    private $projects;

    /** @var array<string, string> */
    private $keys;

    /** @var array<string, string> */
    private $keyIds;

    /** @var array<string, array{token: string, device_id: string}> */
    private $users;

    public function __construct($name, $address, $moved, array $projects, array $keys, array $keyIds, array $users, array $declared)
    {
        $this->name = $name;
        $this->address = $address;
        $this->moved = $moved;
        $this->projects = $projects;
        $this->keys = $keys;
        $this->keyIds = $keyIds;
        $this->users = $users;
        $this->declared = $declared;
    }

    /**
     * @param string $role
     * @return string
     */
    public function project($role = 'p1')
    {
        return $this->lookup($this->projects, 'project', $role);
    }

    /**
     * @param string $role
     * @return string
     */
    public function key($role)
    {
        return $this->lookup($this->keys, 'key', $role);
    }

    /**
     * @param string $role
     * @return string
     */
    public function keyId($role)
    {
        return $this->lookup($this->keyIds, 'key', $role);
    }

    /**
     * The seeded user who owns the project, as the user routes take them.
     *
     * @param string $role
     * @return array{token: string, device_id: string}
     */
    public function user($role = 'p1')
    {
        return $this->lookup($this->users, 'user', $role);
    }

    /**
     * What names this case to the forwarder: its keys, projects and user tokens.
     *
     * @return array{keys: string[], projects: string[], tokens: string[]}
     */
    public function names()
    {
        return [
            'keys' => array_values($this->keys),
            'projects' => array_values($this->projects),
            'tokens' => array_values(array_map(function ($user) {
                return $user['token'];
            }, $this->users)),
        ];
    }

    private function lookup(array $table, $kind, $role)
    {
        if (!array_key_exists($role, $table)) {
            throw new \LogicException("{$this->name} has no $kind $role");
        }

        return $table[$role];
    }
}
