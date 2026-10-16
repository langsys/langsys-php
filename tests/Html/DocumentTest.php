<?php

namespace Langsys\SDK\Tests\Html;

use Langsys\SDK\Html\Document;
use PHPUnit\Framework\TestCase;

class DocumentTest extends TestCase
{
    public function pages(): array
    {
        return [
            'elements in order' => ['<div><p>Hi</p></div><footer>x</footer>', ['div', 'p', 'footer']],
            'a comment' => ['<!-- 1 > 0, so <html> --><p>Hi</p>', ['p']],
            'an empty comment ends at once' => ['<!--><p>Hi</p><!---><b>x</b>', ['p', 'b']],
            'a comment ended by --!>' => ['<!-- a --!><p>Hi</p>', ['p']],
            'each comment at its first end' => ['<!-- a --><p>Hi</p><!-- b --!><b>x</b>', ['p', 'b']],
            'a long comment' => ['<!--' . str_repeat('x', 1000000) . '--><html>', ['html']],
            'a stray quote after a value' => ['<img src="a.png"" alt="Logo"><p class="x">Hi</p><p>Two</p>', ['img', 'p', 'p']],
            'a stray quote in a meta' => ['<meta name="viewport" content="width=device-width""><title>Welcome</title><p>Hi</p>', ['meta', 'title', 'p']],
            'a quote in an unquoted value' => ["<img alt=Don't><p>Two</p><p title='x'>Three</p>", ['img', 'p', 'p']],
            'a value quoted after spaces' => ['<p title = "a > <i>b" id=x>Hi</p><b>y</b>', ['p', 'b']],
            'a quoted value the input ends inside' => ['<p>a</p><p title="x > <b>', ['p', 'p']],
            'a doctype and a processing instruction' => ['<!DOCTYPE html><?php echo "<html>" ?><p>Hi</p>', ['p']],
            'an attribute value' => ['<p title="1 > 0" data-note="<html>">Hi</p><b title=\'a>b<body>\'>x</b>', ['p', 'b']],
            'a script body' => ['<script>document.write("<html><body>")</script><p>Hi</p>', ['script', 'p']],
            'a title body' => ['<title>Use <body> wisely</title><p>Hi</p>', ['title', 'p']],
            'a textarea body' => ['<textarea><head></textarea><p>Hi</p>', ['textarea', 'p']],
            'an svg and its own title' => ['<svg><svg><title>Inner</title></svg><title>Drawing</title></svg><title>Page</title>', ['svg', 'title']],
            'a self-closed svg' => ['<svg/><title>Page</title>', ['svg', 'title']],
            'an svg whose last value ends in a slash is open' => ['<svg width=10/><title>Drawing</title></svg><title>Page</title>', ['svg', 'title']],
            'an svg closed after a bare attribute' => ['<svg focusable/><title>Page</title>', ['svg', 'title']],
            'math' => ['<math><mi>x</mi></math><head>', ['math', 'head']],
            'HTML in an svg foreignObject is the drawing\'s' => ['<svg viewBox="0 0 10 10"><foreignObject width="10"><div>Label</div></foreignObject><rect width="1"/></svg><p>Next</p>', ['svg', 'p']],
            'HTML in an svg title is the drawing\'s' => ['<svg><title><b>Close</b></title><desc><p>Shape</p></desc></svg><p>x</p>', ['svg', 'p']],
            'a script in a foreignObject keeps its text' => ['<svg><foreignObject><script>x = "</foreignObject><p>";</script></foreignObject></svg><i>y</i>', ['svg', 'i']],
            'an svg closed inside its foreignObject' => ['<svg><foreignObject><p>x</svg><svg><p>out</p></svg>', ['svg', 'svg', 'p']],
            'an svg in a foreignObject' => ['<svg><foreignObject><svg><g/></svg><p>x</p></foreignObject></svg><b>y</b>', ['svg', 'b']],
            'HTML in MathML text' => ['<math><mi><b>x</b></mi><mtext><div>t</div></mtext></math><head>', ['math', 'head']],
            'HTML in an HTML annotation' => ['<math><annotation-xml encoding="text/html"><div>x</div></annotation-xml></math><p>y</p>', ['math', 'p']],
            'a tag that ends an annotation with no encoding' => ['<math><annotation-xml><div>x</div></annotation-xml></math>', ['math', 'div']],
            'a tag that ends svg content' => ['<svg><title>Drawing</title><p>Hi</p><title>Page</title>', ['svg', 'p', 'title']],
            'plaintext' => ['<plaintext><html><body>', ['plaintext']],
            'a stray end tag and a lone <' => ['</div>a < b<html>', ['html']],
            'tag names lowercased' => ['<HTML><Body>', ['html', 'body']],
        ];
    }

    /**
     * @dataProvider pages
     */
    public function testStartTagsAreTheElementsABrowserReads(string $html, array $names): void
    {
        $this->assertSame($names, Document::startTags($html));
    }

    public function depths(): array
    {
        return [
            'paragraphs a block closes' => ['<p>One<p>Two<div>Three</div>', ['p@0', 'p@0', 'div@0']],
            'list items and cells' => ['<ul><li>a<li>b</ul><table><tr><td>1<td>2<tr><th>3</table><p>x', ['ul@0', 'li@1', 'li@1', 'table@0', 'tr@1', 'td@2', 'td@2', 'tr@1', 'th@2', 'p@0']],
            'a list in a list item' => ['<ul><li><ul><li>x</ul></ul><b>t</b>', ['ul@0', 'li@1', 'ul@2', 'li@3', 'b@0']],
            'definitions' => ['<dl><dt>a<dd>b<dt>c</dl>', ['dl@0', 'dt@1', 'dd@1', 'dt@1']],
            'row groups' => ['<table><thead><tr><td>h<tbody><tr><td>b</table>', ['table@0', 'thead@1', 'tr@2', 'td@3', 'tbody@1', 'tr@2', 'td@3']],
            'options' => ['<select><option>a<option>b<optgroup><option>c</select><hr>', ['select@0', 'option@1', 'option@1', 'optgroup@1', 'option@2', 'hr@0']],
            'a paragraph in a button' => ['<button><p>a<div>b</div></button><em>c</em>', ['button@0', 'p@1', 'div@1', 'em@0']],
            'a button in a paragraph' => ['<p>a<button><div>b</div></button>c</p><i>d</i>', ['p@0', 'button@1', 'div@2', 'i@0']],
            'a void element' => ['<p>a<br><img src=x>b</p><hr><i>c</i>', ['p@0', 'br@1', 'img@1', 'hr@0', 'i@0']],
            'a self-closed div opens' => ['<div/><span>x</span>', ['div@0', 'span@1']],
            'svg content and a tag that ends it' => ['<svg><g></g><p>out</p></svg><i>z</i>', ['svg@0', 'p@0', 'i@0']],
            'a closed svg' => ['<svg><g></g></svg><i>z</i>', ['svg@0', 'i@0']],
            'a script body' => ['<script>x = "<p>"</script><p>y', ['script@0', 'p@0']],
            'a stray end tag' => ['<div></span><p>x</p></div><b>y</b>', ['div@0', 'p@1', 'b@0']],
        ];
    }

    /**
     * @dataProvider depths
     */
    public function testEachStartTagIsAsDeepAsABrowserBuildsIt(string $html, array $depths): void
    {
        $this->assertSame($depths, array_map(function ($tag) {
            return $tag[0] . '@' . $tag[3];
        }, Document::startTagsAt($html)));
    }

    /**
     * A page's elements are read in time linear in its size, however many it
     * leaves open: a listing whose row template misses its </div>, end tags
     * that close nothing.
     */
    public function testUnclosedElementsAreReadInLinearTime(): void
    {
        foreach ([
            'rows left open' => function ($n) {
                return '<p>Intro</p><div id="list">' . str_repeat('<div class="row"><span>Item</span>', $n) . '</div><p>after</p>';
            },
            'list items left open' => function ($n) {
                return '<ul>' . str_repeat('<li><div>Item', $n) . '</ul><p>after</p>';
            },
            'end tags that close nothing' => function ($n) {
                return str_repeat('<span>', $n) . str_repeat('</i>', $n) . '<p>after</p>';
            },
        ] as $shape => $page) {
            $small = $page(4000);
            $large = $page(32000);
            $started = microtime(true);
            Document::startTagsAt($small);
            $smallTime = microtime(true) - $started;
            $started = microtime(true);
            $tags = Document::startTagsAt($large);
            $largeTime = microtime(true) - $started;

            $this->assertSame('p', end($tags)[0], $shape);
            $this->assertLessThan(max(0.25, 30 * $smallTime), $largeTime, $shape . ': eight times the page, not sixty-four times the time');
        }
    }

    public function testAStartTagIsFoundWhereItIsWritten(): void
    {
        $html = '<!-- x --><p class="a">One</p><br/>';
        foreach (Document::startTagsAt($html) as $tag) {
            $this->assertSame(0, strpos(substr($html, $tag[1], $tag[2]), '<' . $tag[0]));
            $this->assertSame('>', substr($html, $tag[1] + $tag[2] - 1, 1));
        }
        $this->assertSame([[ 'p', 10, 13, 0], ['br', 30, 5, 0]], Document::startTagsAt($html));
    }
}
