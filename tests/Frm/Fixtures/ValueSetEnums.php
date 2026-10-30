<?php

// PHP 8.1+ only: loaded by ValueSetsTest when enums exist. Declared at run
// time, so the file still parses on PHP 7.4, where CI lints it.

eval(<<<'PHP'
namespace Langsys\SDK\Tests\Frm\Fixtures;

use Langsys\SDK\Messages\TranslatesAs;

#[TranslatesAs('status')]
enum OrderStatus: string
{
    case Shipped = 'shipped';
    case OnHold = 'on_hold';

    public function label(): string
    {
        return match ($this) {
            self::Shipped => 'Shipped',
            self::OnHold => 'On hold',
        };
    }
}

#[TranslatesAs('size')]
enum Size: string
{
    case Small = 'Small';
    case Large = 'Large';
}

enum Undeclared: string
{
    case Blue = 'Blue';
}
PHP
);
