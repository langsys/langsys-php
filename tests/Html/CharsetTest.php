<?php

namespace Langsys\SDK\Tests\Html;

use Langsys\SDK\Html\Charset;
use PHPUnit\Framework\TestCase;

/**
 * A page is decoded as a browser decodes it (WHATWG Encoding): its label read
 * through the browser's table, each encoding by the browser's decoder, a byte
 * invalid in it as U+FFFD.
 */
class CharsetTest extends TestCase
{
    private static function page($label, $bytes)
    {
        return '<html><head><meta charset="' . $label . '"></head><body><p>' . $bytes . '</p></body></html>';
    }

    public function declaredPages(): array
    {
        return [
            'chinese names GBK' => ['chinese', "\xC4\xE3\xBA\xC3", '你好'],
            'iso-ir-58 names GBK' => ['iso-ir-58', "\xC4\xE3\xBA\xC3", '你好'],
            'gb_2312-80 names GBK' => ['gb_2312-80', "\xC4\xE3\xBA\xC3", '你好'],
            'gb2312 reads its GBK superset' => ['gb2312', "\xE9\x46", '镕'],
            'euc-kr reads as windows-949' => ['euc-kr', "\x8C\x63", '똠'],
            'iso-8859-9 reads as windows-1254' => ['iso-8859-9', "\x93hi\x94", '“hi”'],
            'big5 reads its HKSCS superset' => ['big5', "\x87\x40", '䏰'],
            'cn-big5 names Big5' => ['cn-big5', "\x87\x40", '䏰'],
            'dos-874 names windows-874' => ['dos-874', "\xA1", 'ก'],
            'logical names ISO-8859-8-I' => ['logical', "\xE0", 'א'],
            'visual names ISO-8859-8' => ['visual', "\xE0", 'א'],
            'koi8-u has the Belarusian letters' => ['koi8-u', "\xAE\xBE", 'ўЎ'],
            'iso-8859-16' => ['iso-8859-16', "\xAA\xDE", 'ȘȚ'],
            'shift_jis, an invalid byte' => ['shift_jis', "a\xFDb", "a\u{FFFD}b"],
            'gbk, an invalid byte' => ['gbk', "a\xFFb", "a\u{FFFD}b"],
            'windows-1252 keeps an undefined byte as its control' => ['windows-1252', "\x80\x81", "€\u{81}"],
            'x-user-defined reads as windows-1252' => ['x-user-defined', "\x93hi\x94", '“hi”'],
            'a label in any case, padded' => [' Shift_JIS ', "\x93\xFA\x96\x7B", '日本'],
        ];
    }

    /**
     * @dataProvider declaredPages
     */
    public function testAPageIsReadByTheBrowsersDecoderForItsLabel(string $label, string $bytes, string $text): void
    {
        $this->assertStringContainsString('<body><p>' . $text . '</p></body>', Charset::toUtf8(self::page($label, $bytes)));
    }

    public function testALabelIsReadTrimmedAndInAnyCase(): void
    {
        $this->assertSame('Shift_JIS', Charset::encoding(" \tShift_JIS\n"));
        $this->assertSame('windows-1252', Charset::encoding('LATIN1'));
        $this->assertNull(Charset::encoding('utf-7'));
    }

    /**
     * A label the browser's table lacks declares nothing: the page is read
     * as an undeclared one, UTF-8 when it is and windows-1252 when not.
     */
    public function testALabelTheBrowserDoesNotKnowDeclaresNothing(): void
    {
        foreach (['utf-7' => 'C++ fans', 'utf-32' => 'Hello', 'cp037' => 'Hello', 'ebcdic-cp-us' => 'Hello', 'latin-9' => "caf\xC3\xA9"] as $label => $text) {
            $this->assertSame(self::page($label, $text), Charset::toUtf8(self::page($label, $text)), $label);
        }
        $this->assertStringContainsString('<p>café</p>', Charset::toUtf8(self::page('utf-7', "caf\xE9")));
    }

    public function prescans(): array
    {
        $cafe = "<p>Caf\xE9</p>";

        return [
            'charset= in a description, no http-equiv' => ['<meta name="description" content="How to set charset=utf-8 in a meta">' . $cafe, 'Café'],
            'charset= in a content without http-equiv' => ['<meta content="text/html; charset=koi8-r">' . $cafe, 'Café'],
            'http-equiv with charset= in its content' => ['<meta http-equiv="Content-Type" content="text/html; charset=koi8-r">' . $cafe, 'CafИ'],
            'http-equiv after the content' => ['<meta content="text/html;charset=\'koi8-r\'" http-equiv=content-type>' . $cafe, 'CafИ'],
            'a comment closed as <!-->' => ["<!--><meta charset=\"windows-1251\"><p>\xCF\xF0\xE8\xE2\xE5\xF2</p>", 'Привет'],
            'a meta in a comment' => ['<!-- <meta charset="koi8-r"> -->' . $cafe, 'Café'],
            'a meta in another tag\'s attribute' => ['<a title=\'<meta charset="koi8-r">\'>x</a>' . $cafe, 'Café'],
            'a label naming nothing, then one that does' => ['<meta charset="utf-7"><meta charset="koi8-r">' . $cafe, 'CafИ'],
            'a charset naming nothing outranks its content' => ['<meta charset="bogus" http-equiv="content-type" content="charset=koi8-r"><meta charset="windows-1252">' . $cafe, 'Café'],
            'the first of a repeated attribute' => ['<meta charset="koi8-r" charset="windows-1252">' . $cafe, 'CafИ'],
            'a quoted label with spaces' => ['<meta charset=" KOI8-R ">' . $cafe, 'CafИ'],
            'an unquoted label' => ['<meta charset=koi8-r>' . $cafe, 'CafИ'],
            'an unquoted label before another attribute' => ['<meta charset=koi8-r data-x="1">' . $cafe, 'CafИ'],
            'a meta in capitals' => ['<META HTTP-EQUIV="Content-Type" CONTENT="text/html; CHARSET=KOI8-R">' . $cafe, 'CafИ'],
            'a self-closed meta' => ['<meta charset="koi8-r"/>' . $cafe, 'CafИ'],
            'a meta inside a <! construct' => ['<!x <meta charset="koi8-r">' . $cafe, 'Café'],
            'a meta after a processing instruction' => ['<?xml version="1.0"?><meta charset="koi8-r">' . $cafe, 'CafИ'],
            'a metadata tag is no meta' => ['<metadata charset="koi8-r">' . $cafe, 'Café'],
            'a meta past the first 1024 bytes' => [str_repeat(' ', 1024) . '<meta charset="koi8-r">' . $cafe, 'Café'],
        ];
    }

    /**
     * A meta declares an encoding as a browser's prescan of the first 1024
     * bytes reads it.
     *
     * @dataProvider prescans
     */
    public function testAMetaDeclaresAsABrowsersPrescanReadsIt(string $html, string $text): void
    {
        $this->assertStringContainsString('<p>' . $text . '</p>', Charset::toUtf8($html));
    }

    public function testAReplacementLabelReadsAsOneReplacementCharacter(): void
    {
        $this->assertSame("\u{FFFD}", Charset::toUtf8(self::page('iso-2022-kr', 'Hello')));
    }

    public function testUtf16ByItsByteOrderMark(): void
    {
        $this->assertSame('<p>Mü</p>', Charset::toUtf8("\xFE\xFF\x00<\x00p\x00>\x00M\x00\xFC\x00<\x00/\x00p\x00>"));
        $this->assertSame('<p>Mü</p>', Charset::toUtf8("\xFF\xFE<\x00p\x00>\x00M\x00\xFC\x00<\x00/\x00p\x00>\x00"));
        $this->assertSame("a😀\u{FFFD}b\u{FFFD}", Charset::toUtf8("\xFE\xFF\x00a\xD8\x3D\xDE\x00\xD8\x3D\x00b\x00"), 'a pair, a lone surrogate, an odd byte');
    }

    public function malformedUtf8(): array
    {
        return [
            'a surrogate' => ["\xED\xA0\x80", 3],
            'an overlong form' => ["\xC0\xAF", 2],
            'an overlong three-byte form' => ["\xE0\x80\xAF", 3],
            'an overlong four-byte form' => ["\xF0\x80\x80\x80", 4],
            'above U+10FFFF' => ["\xF4\x90\x80\x80", 4],
            'a truncated sequence' => ["\xE2\x82", 1],
            'a truncated sequence before ASCII' => ["a\xF0\x9F\x98b", 1],
            'two stray continuation bytes' => ["\x80\x80", 2],
            'a complete sequence then a bad byte' => ["\xE2\x82\xAC\xFF", 1],
            'two truncated sequences' => ["\xF1\x80\x80\xE1\x80", 2],
            'valid' => ["\xF0\x9F\x98\x80", 0],
        ];
    }

    /**
     * @dataProvider malformedUtf8
     */
    public function testEachInvalidUtf8SequenceIsOneReplacementCharacter(string $bytes, int $replacements): void
    {
        $scrubbed = Charset::scrub($bytes);

        $this->assertSame(1, preg_match('//u', $scrubbed));
        $this->assertSame($replacements, substr_count($scrubbed, "\u{FFFD}"));
    }

    /**
     * Read in windows that never split a sequence, so a long page with a bad
     * byte near a window's edge comes back whole.
     */
    /**
     * However a window's edge falls across a character followed by stray
     * continuation bytes, the result is the one-pass decode's.
     */
    public function testAWindowEdgeNeverSplitsACharacter(): void
    {
        foreach (["\xF0\x9F\x98\x80\x94" => "\u{1F600}\u{FFFD}", "\xE2\x82\xAC\x80\x80" => "€\u{FFFD}\u{FFFD}", "\xC3\xA9\x80\x80\x80" => "é\u{FFFD}\u{FFFD}\u{FFFD}"] as $bytes => $decoded) {
            for ($offset = 65530; $offset <= 65537; $offset++) {
                $this->assertSame(str_repeat('a', $offset) . $decoded . 'end', Charset::scrub(str_repeat('a', $offset) . $bytes . 'end'), bin2hex($bytes) . ' at ' . $offset);
            }
        }
    }

    public function testALongPageIsScrubbedWhole(): void
    {
        foreach ([65535, 65536, 65537, 65538] as $offset) {
            $text = str_repeat('a', $offset - 2) . "\xE2\x82\xAC\xFF" . str_repeat("\xC3\xA9", 40000);
            $scrubbed = Charset::scrub($text);

            $this->assertSame(strlen($text) + 2, strlen($scrubbed), (string) $offset);
            $this->assertSame(1, substr_count($scrubbed, "\u{FFFD}"), (string) $offset);
            $this->assertSame(1, substr_count($scrubbed, '€'), (string) $offset);
        }
    }
}
