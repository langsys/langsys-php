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
            'a doctype and a processing instruction' => ['<!DOCTYPE html><?php echo "<html>" ?><p>Hi</p>', ['p']],
            'an attribute value' => ['<p title="1 > 0" data-note="<html>">Hi</p><b title=\'a>b<body>\'>x</b>', ['p', 'b']],
            'a script body' => ['<script>document.write("<html><body>")</script><p>Hi</p>', ['script', 'p']],
            'a title body' => ['<title>Use <body> wisely</title><p>Hi</p>', ['title', 'p']],
            'a textarea body' => ['<textarea><head></textarea><p>Hi</p>', ['textarea', 'p']],
            'an svg and its own title' => ['<svg><svg><title>Inner</title></svg><title>Drawing</title></svg><title>Page</title>', ['svg', 'title']],
            'a self-closed svg' => ['<svg/><title>Page</title>', ['svg', 'title']],
            'math' => ['<math><mi>x</mi></math><head>', ['math', 'head']],
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
}
