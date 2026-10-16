<?php

namespace Langsys\SDK\Html;

/**
 * The WHATWG Encoding Standard's decoders, as a browser runs them, over the
 * standard's own indexes (Encoding/, written by Encoding/generate.mjs from
 * Firefox's decoder). Each turns bytes in its encoding into UTF-8, an error as
 * U+FFFD, and a byte an error did not consume read again, as the standard's
 * "prepend" says, so an ASCII letter after a bad lead byte is kept.
 *
 * @internal Charset is the way in.
 */
final class Decoder
{
    /** The single-byte encodings, in the order index-single-byte.bin holds them. */
    const SINGLE_BYTE = [
        'IBM866', 'ISO-8859-2', 'ISO-8859-3', 'ISO-8859-4', 'ISO-8859-5', 'ISO-8859-6', 'ISO-8859-7',
        'ISO-8859-8', 'ISO-8859-10', 'ISO-8859-13', 'ISO-8859-14', 'ISO-8859-15', 'ISO-8859-16', 'KOI8-R',
        'KOI8-U', 'macintosh', 'windows-874', 'windows-1250', 'windows-1251', 'windows-1252', 'windows-1253',
        'windows-1254', 'windows-1255', 'windows-1256', 'windows-1257', 'windows-1258', 'x-mac-cyrillic',
    ];

    /** @var string[] Index files read so far, by name */
    private static $indexes = [];

    /** @var array[] Each single-byte encoding's bytes above 0x7F as UTF-8 */
    private static $byteMaps = [];

    /**
     * $bytes in $encoding as UTF-8.
     *
     * @param string $bytes
     * @param string $encoding A name Charset::ENCODINGS gives, other than
     *                         UTF-8, UTF-16 and x-user-defined
     * @return string
     */
    public static function decode($bytes, $encoding)
    {
        $bytes = (string) $bytes;

        if ($encoding === 'replacement') {
            return $bytes === '' ? '' : "\u{FFFD}";
        }
        if ($encoding === 'ISO-8859-8-I') {
            $encoding = 'ISO-8859-8';
        }
        if (in_array($encoding, self::SINGLE_BYTE, true)) {
            return strtr($bytes, self::byteMap($encoding));
        }

        switch ($encoding) {
            case 'GBK':
            case 'gb18030':
                return self::gb18030($bytes);
            case 'Big5':
                return self::big5($bytes);
            case 'EUC-JP':
                return self::eucJp($bytes);
            case 'ISO-2022-JP':
                return self::iso2022Jp($bytes);
            case 'Shift_JIS':
                return self::shiftJis($bytes);
            case 'EUC-KR':
                return self::eucKr($bytes);
        }

        return strtr($bytes, self::byteMap('windows-1252'));
    }

    /**
     * @param string $bytes
     * @return string
     */
    private static function gb18030($bytes)
    {
        $out = '';
        $length = strlen($bytes);
        list($first, $second, $third) = [0, 0, 0];

        for ($at = 0; $at < $length; $at++) {
            $byte = ord($bytes[$at]);

            if ($first === 0 && $byte < 0x80) {
                // A run of ASCII, as itself, at once.
                $run = strspn($bytes, self::ascii(), $at);
                $out .= substr($bytes, $at, $run);
                $at += $run - 1;
                continue;
            }
            if ($third !== 0) {
                if ($byte < 0x30 || $byte > 0x39) {
                    // Second, third and this byte are read again.
                    $at -= 3;
                    list($first, $second, $third) = [0, 0, 0];
                    $out .= "\u{FFFD}";
                    continue;
                }
                $point = self::gb18030Range((($first - 0x81) * 12600) + (($second - 0x30) * 1260) + (($third - 0x81) * 10) + $byte - 0x30);
                list($first, $second, $third) = [0, 0, 0];
                $out .= $point === null ? "\u{FFFD}" : self::utf8($point);
                continue;
            }
            if ($second !== 0) {
                if ($byte >= 0x81 && $byte <= 0xFE) {
                    $third = $byte;
                    continue;
                }
                $at -= 2;
                list($first, $second) = [0, 0];
                $out .= "\u{FFFD}";
                continue;
            }
            if ($first !== 0) {
                if ($byte >= 0x30 && $byte <= 0x39) {
                    $second = $byte;
                    continue;
                }
                $lead = $first;
                $first = 0;
                $point = null;
                if (($byte >= 0x40 && $byte <= 0x7E) || ($byte >= 0x80 && $byte <= 0xFE)) {
                    $point = self::index('gb18030', ($lead - 0x81) * 190 + $byte - ($byte < 0x7F ? 0x40 : 0x41));
                }
                if ($point !== null) {
                    $out .= self::utf8($point);
                    continue;
                }
                if ($byte < 0x80) {
                    $at--;
                }
                $out .= "\u{FFFD}";
                continue;
            }

            if ($byte < 0x80) {
                $out .= chr($byte);
            } elseif ($byte === 0x80) {
                $out .= "\u{20AC}";
            } elseif ($byte <= 0xFE) {
                $first = $byte;
            } else {
                $out .= "\u{FFFD}";
            }
        }

        return $first !== 0 || $second !== 0 || $third !== 0 ? $out . "\u{FFFD}" : $out;
    }

    /**
     * The code point a gb18030 four-byte pointer names, or null.
     *
     * @param int $pointer
     * @return int|null
     */
    private static function gb18030Range($pointer)
    {
        if (($pointer > 39419 && $pointer < 189000) || $pointer > 1237575) {
            return null;
        }
        if ($pointer === 7457) {
            return 0xE7C7;
        }
        if ($pointer >= 189000) {
            return 0x10000 + $pointer - 189000;
        }

        $ranges = self::file('gb18030-ranges');
        $low = 0;
        $high = intdiv(strlen($ranges), 8) - 1;
        while ($low < $high) {
            $middle = intdiv($low + $high + 1, 2);
            if (unpack('N', $ranges, $middle * 8)[1] <= $pointer) {
                $low = $middle;
            } else {
                $high = $middle - 1;
            }
        }
        list(, $start, $point) = unpack('N2', $ranges, $low * 8);

        return $point + $pointer - $start;
    }

    /**
     * @param string $bytes
     * @return string
     */
    private static function big5($bytes)
    {
        $out = '';
        $length = strlen($bytes);
        $lead = 0;

        for ($at = 0; $at < $length; $at++) {
            $byte = ord($bytes[$at]);

            if ($lead === 0 && $byte < 0x80) {
                // A run of ASCII, as itself, at once.
                $run = strspn($bytes, self::ascii(), $at);
                $out .= substr($bytes, $at, $run);
                $at += $run - 1;
                continue;
            }
            if ($lead !== 0) {
                $pointer = null;
                if (($byte >= 0x40 && $byte <= 0x7E) || ($byte >= 0xA1 && $byte <= 0xFE)) {
                    $pointer = ($lead - 0x81) * 157 + $byte - ($byte < 0x7F ? 0x40 : 0x62);
                }
                $lead = 0;
                $pairs = [1133 => "\u{00CA}\u{0304}", 1135 => "\u{00CA}\u{030C}", 1164 => "\u{00EA}\u{0304}", 1166 => "\u{00EA}\u{030C}"];
                if ($pointer !== null && isset($pairs[$pointer])) {
                    $out .= $pairs[$pointer];
                    continue;
                }
                $point = $pointer === null ? null : self::index('big5', $pointer);
                if ($point !== null) {
                    $out .= self::utf8($point);
                    continue;
                }
                if ($byte < 0x80) {
                    $at--;
                }
                $out .= "\u{FFFD}";
                continue;
            }

            if ($byte < 0x80) {
                $out .= chr($byte);
            } elseif ($byte >= 0x81 && $byte <= 0xFE) {
                $lead = $byte;
            } else {
                $out .= "\u{FFFD}";
            }
        }

        return $lead !== 0 ? $out . "\u{FFFD}" : $out;
    }

    /**
     * @param string $bytes
     * @return string
     */
    private static function eucJp($bytes)
    {
        $out = '';
        $length = strlen($bytes);
        $lead = 0;
        $jis0212 = false;

        for ($at = 0; $at < $length; $at++) {
            $byte = ord($bytes[$at]);

            if ($lead === 0 && $byte < 0x80) {
                // A run of ASCII, as itself, at once.
                $run = strspn($bytes, self::ascii(), $at);
                $out .= substr($bytes, $at, $run);
                $at += $run - 1;
                continue;
            }
            if ($lead === 0x8E && $byte >= 0xA1 && $byte <= 0xDF) {
                $lead = 0;
                $out .= self::utf8(0xFF61 - 0xA1 + $byte);
                continue;
            }
            if ($lead === 0x8F && $byte >= 0xA1 && $byte <= 0xFE) {
                $jis0212 = true;
                $lead = $byte;
                continue;
            }
            if ($lead !== 0) {
                $point = null;
                if ($lead >= 0xA1 && $lead <= 0xFE && $byte >= 0xA1 && $byte <= 0xFE) {
                    $point = self::index($jis0212 ? 'jis0212' : 'jis0208', ($lead - 0xA1) * 94 + $byte - 0xA1);
                }
                $lead = 0;
                $jis0212 = false;
                if ($point !== null) {
                    $out .= self::utf8($point);
                    continue;
                }
                if ($byte < 0x80) {
                    $at--;
                }
                $out .= "\u{FFFD}";
                continue;
            }

            if ($byte < 0x80) {
                $out .= chr($byte);
            } elseif ($byte === 0x8E || $byte === 0x8F || ($byte >= 0xA1 && $byte <= 0xFE)) {
                $lead = $byte;
            } else {
                $out .= "\u{FFFD}";
            }
        }

        return $lead !== 0 ? $out . "\u{FFFD}" : $out;
    }

    /**
     * @param string $bytes
     * @return string
     */
    private static function iso2022Jp($bytes)
    {
        $out = '';
        $length = strlen($bytes);
        $state = 'ascii';
        $outputState = 'ascii';
        $lead = 0;
        $output = false;

        for ($at = 0; $at <= $length; $at++) {
            $end = $at === $length;
            $byte = $end ? null : ord($bytes[$at]);

            switch ($state) {
                case 'ascii':
                case 'roman':
                    if ($end) {
                        break 2;
                    }
                    if ($byte === 0x1B) {
                        $state = 'escape start';
                    } elseif ($state === 'roman' && $byte === 0x5C) {
                        $output = false;
                        $out .= "\u{00A5}";
                    } elseif ($state === 'roman' && $byte === 0x7E) {
                        $output = false;
                        $out .= "\u{203E}";
                    } elseif ($byte < 0x80 && $byte !== 0x0E && $byte !== 0x0F) {
                        $output = false;
                        $out .= chr($byte);
                    } else {
                        $output = false;
                        $out .= "\u{FFFD}";
                    }
                    break;

                case 'katakana':
                    if ($end) {
                        break 2;
                    }
                    if ($byte === 0x1B) {
                        $state = 'escape start';
                    } elseif ($byte >= 0x21 && $byte <= 0x5F) {
                        $output = false;
                        $out .= self::utf8(0xFF61 - 0x21 + $byte);
                    } else {
                        $output = false;
                        $out .= "\u{FFFD}";
                    }
                    break;

                case 'lead':
                    if ($end) {
                        break 2;
                    }
                    if ($byte === 0x1B) {
                        $state = 'escape start';
                    } elseif ($byte >= 0x21 && $byte <= 0x7E) {
                        $output = false;
                        $lead = $byte;
                        $state = 'trail';
                    } else {
                        $output = false;
                        $out .= "\u{FFFD}";
                    }
                    break;

                case 'trail':
                    if ($end) {
                        // The end is read again, in the lead state.
                        $state = 'lead';
                        $out .= "\u{FFFD}";
                        $at--;
                        break;
                    }
                    if ($byte === 0x1B) {
                        $state = 'escape start';
                        $out .= "\u{FFFD}";
                        break;
                    }
                    $state = 'lead';
                    if ($byte >= 0x21 && $byte <= 0x7E) {
                        $point = self::index('jis0208', ($lead - 0x21) * 94 + $byte - 0x21);
                        $out .= $point === null ? "\u{FFFD}" : self::utf8($point);
                    } else {
                        $out .= "\u{FFFD}";
                    }
                    break;

                case 'escape start':
                    if (!$end && ($byte === 0x24 || $byte === 0x28)) {
                        $lead = $byte;
                        $state = 'escape';
                        break;
                    }
                    $output = false;
                    $state = $outputState;
                    $out .= "\u{FFFD}";
                    $at--;
                    break;

                case 'escape':
                    $previous = $lead;
                    $lead = 0;
                    $next = null;
                    if ($previous === 0x28 && $byte === 0x42) {
                        $next = 'ascii';
                    } elseif ($previous === 0x28 && $byte === 0x4A) {
                        $next = 'roman';
                    } elseif ($previous === 0x28 && $byte === 0x49) {
                        $next = 'katakana';
                    } elseif ($previous === 0x24 && ($byte === 0x40 || $byte === 0x42)) {
                        $next = 'lead';
                    }
                    if ($next !== null) {
                        $state = $outputState = $next;
                        $wasOutput = $output;
                        $output = true;
                        if ($wasOutput) {
                            $out .= "\u{FFFD}";
                        }
                        break;
                    }
                    // Lead and this byte are read again (at the end, the lead
                    // then the end): the next read is the lead, one back.
                    $at -= 2;
                    $output = false;
                    $state = $outputState;
                    $out .= "\u{FFFD}";
                    break;
            }
        }

        return $out;
    }

    /**
     * @param string $bytes
     * @return string
     */
    private static function shiftJis($bytes)
    {
        $out = '';
        $length = strlen($bytes);
        $lead = 0;

        for ($at = 0; $at < $length; $at++) {
            $byte = ord($bytes[$at]);

            if ($lead === 0 && $byte < 0x80) {
                // A run of ASCII, as itself, at once.
                $run = strspn($bytes, self::ascii(), $at);
                $out .= substr($bytes, $at, $run);
                $at += $run - 1;
                continue;
            }
            if ($lead !== 0) {
                $pointer = null;
                if (($byte >= 0x40 && $byte <= 0x7E) || ($byte >= 0x80 && $byte <= 0xFC)) {
                    $pointer = ($lead - ($lead < 0xA0 ? 0x81 : 0xC1)) * 188 + $byte - ($byte < 0x7F ? 0x40 : 0x41);
                }
                $lead = 0;
                if ($pointer !== null && $pointer >= 8836 && $pointer <= 10715) {
                    $out .= self::utf8(0xE000 - 8836 + $pointer);
                    continue;
                }
                $point = $pointer === null ? null : self::index('jis0208', $pointer);
                if ($point !== null) {
                    $out .= self::utf8($point);
                    continue;
                }
                if ($byte < 0x80) {
                    $at--;
                }
                $out .= "\u{FFFD}";
                continue;
            }

            if ($byte <= 0x80) {
                $out .= self::utf8($byte);
            } elseif ($byte >= 0xA1 && $byte <= 0xDF) {
                $out .= self::utf8(0xFF61 - 0xA1 + $byte);
            } elseif (($byte >= 0x81 && $byte <= 0x9F) || ($byte >= 0xE0 && $byte <= 0xFC)) {
                $lead = $byte;
            } else {
                $out .= "\u{FFFD}";
            }
        }

        return $lead !== 0 ? $out . "\u{FFFD}" : $out;
    }

    /**
     * @param string $bytes
     * @return string
     */
    private static function eucKr($bytes)
    {
        $out = '';
        $length = strlen($bytes);
        $lead = 0;

        for ($at = 0; $at < $length; $at++) {
            $byte = ord($bytes[$at]);

            if ($lead === 0 && $byte < 0x80) {
                // A run of ASCII, as itself, at once.
                $run = strspn($bytes, self::ascii(), $at);
                $out .= substr($bytes, $at, $run);
                $at += $run - 1;
                continue;
            }
            if ($lead !== 0) {
                $point = $byte >= 0x41 && $byte <= 0xFE ? self::index('euc-kr', ($lead - 0x81) * 190 + $byte - 0x41) : null;
                $lead = 0;
                if ($point !== null) {
                    $out .= self::utf8($point);
                    continue;
                }
                if ($byte < 0x80) {
                    $at--;
                }
                $out .= "\u{FFFD}";
                continue;
            }

            if ($byte < 0x80) {
                $out .= chr($byte);
            } elseif ($byte >= 0x81 && $byte <= 0xFE) {
                $lead = $byte;
            } else {
                $out .= "\u{FFFD}";
            }
        }

        return $lead !== 0 ? $out . "\u{FFFD}" : $out;
    }

    /**
     * @return string Every ASCII byte, for strspn()
     */
    private static function ascii()
    {
        static $ascii = null;
        if ($ascii === null) {
            $ascii = '';
            for ($byte = 0; $byte < 0x80; $byte++) {
                $ascii .= chr($byte);
            }
        }

        return $ascii;
    }

    /**
     * The code point an index holds for $pointer, or null.
     *
     * @param string $name
     * @param int $pointer
     * @return int|null
     */
    private static function index($name, $pointer)
    {
        $index = self::file($name);
        if ($pointer < 0 || ($pointer + 1) * 3 > strlen($index)) {
            return null;
        }
        $at = $pointer * 3;
        $point = (ord($index[$at]) << 16) | (ord($index[$at + 1]) << 8) | ord($index[$at + 2]);

        return $point === 0 ? null : $point;
    }

    /**
     * @param string $name
     * @return string
     */
    private static function file($name)
    {
        if (!isset(self::$indexes[$name])) {
            self::$indexes[$name] = (string) file_get_contents(__DIR__ . '/Encoding/index-' . $name . '.bin');
        }

        return self::$indexes[$name];
    }

    /**
     * @param string $encoding One of SINGLE_BYTE
     * @return string[] Each byte above 0x7F as its UTF-8, U+FFFD where the index has none
     */
    private static function byteMap($encoding)
    {
        if (!isset(self::$byteMaps[$encoding])) {
            $offset = array_search($encoding, self::SINGLE_BYTE, true) * 128;
            $map = [];
            for ($byte = 0x80; $byte <= 0xFF; $byte++) {
                $point = self::index('single-byte', $offset + $byte - 0x80);
                $map[chr($byte)] = $point === null ? "\u{FFFD}" : self::utf8($point);
            }
            self::$byteMaps[$encoding] = $map;
        }

        return self::$byteMaps[$encoding];
    }

    /**
     * @param int $point
     * @return string
     */
    public static function utf8($point)
    {
        if ($point < 0x80) {
            return chr($point);
        }
        if ($point < 0x800) {
            return chr(0xC0 | $point >> 6) . chr(0x80 | $point & 0x3F);
        }
        if ($point < 0x10000) {
            return chr(0xE0 | $point >> 12) . chr(0x80 | $point >> 6 & 0x3F) . chr(0x80 | $point & 0x3F);
        }

        return chr(0xF0 | $point >> 18) . chr(0x80 | $point >> 12 & 0x3F) . chr(0x80 | $point >> 6 & 0x3F) . chr(0x80 | $point & 0x3F);
    }
}
