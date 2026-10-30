<?php

/**
 * Unit tests for the HTML sanitizer: strips scripts/event handlers/dangerous
 * URLs while keeping rich formatting, tables, media and safe links.
 */

declare(strict_types=1);

namespace InfoLoop\Tests\Unit;

use InfoLoop\Services\HtmlSanitizer;

final class HtmlSanitizerTest extends \TestCase
{
    private function sanitizer(): HtmlSanitizer
    {
        return new HtmlSanitizer();
    }

    public function testKeepsBasicFormatting(): void
    {
        $html = '<h1>Title</h1><p>Hello <strong>world</strong> <em>again</em></p><ul><li>one</li><li>two</li></ul>';
        $out = $this->sanitizer()->sanitize($html);
        $this->assertStringContains('<h1>Title</h1>', $out);
        $this->assertStringContains('<strong>world</strong>', $out);
        $this->assertStringContains('<li>one</li>', $out);
    }

    public function testStripsScriptTags(): void
    {
        $out = $this->sanitizer()->sanitize('<p>ok</p><script>alert(1)</script>');
        $this->assertStringNotContains('<script', $out);
        $this->assertStringNotContains('alert(1)', $out);
        $this->assertStringContains('<p>ok</p>', $out);
    }

    public function testStripsEventHandlers(): void
    {
        $out = $this->sanitizer()->sanitize('<img src="/files/a/b/c.png" onerror="alert(1)">');
        $this->assertStringNotContains('onerror', $out);
    }

    public function testBlocksJavascriptUrls(): void
    {
        $out = $this->sanitizer()->sanitize('<a href="javascript:alert(1)">x</a>');
        $this->assertStringNotContains('javascript:', $out);
        $out2 = $this->sanitizer()->sanitize('<a href="https://example.com" target="_blank">ok</a>');
        $this->assertStringContains('https://example.com', $out2);
        $this->assertStringContains('rel="noopener noreferrer"', $out2);
    }

    public function testKeepsTables(): void
    {
        $html = '<table><thead><tr><th>H</th></tr></thead><tbody><tr><td colspan="2">cell</td></tr></tbody></table>';
        $out = $this->sanitizer()->sanitize($html);
        $this->assertStringContains('<table>', $out);
        $this->assertStringContains('colspan="2"', $out);
    }

    public function testKeepsImagesAndMedia(): void
    {
        $out = $this->sanitizer()->sanitize('<img src="/files/ab/cd/x.png" alt="pic"><video src="/files/a/b/v.mp4" controls></video>');
        $this->assertStringContains('/files/ab/cd/x.png', $out);
        $this->assertStringContains('<video', $out);
    }

    public function testStripsIframeWithUnsafeSource(): void
    {
        $out = $this->sanitizer()->sanitize('<iframe src="javascript:alert(1)"></iframe>');
        $this->assertStringNotContains('javascript:', $out);
        $out2 = $this->sanitizer()->sanitize('<iframe src="https://www.youtube.com/embed/abc"></iframe>');
        $this->assertStringContains('youtube.com', $out2);
    }

    public function testEmptyInput(): void
    {
        $this->assertSame('', $this->sanitizer()->sanitize(''));
        $this->assertSame('', $this->sanitizer()->sanitize('   '));
    }

    public function testStyleSanitisation(): void
    {
        $out = $this->sanitizer()->sanitize('<div style="color:red">x</div>');
        $this->assertStringContains('color:red', $out);
        $out2 = $this->sanitizer()->sanitize('<div style="background:url(javascript:alert(1))">x</div>');
        $this->assertStringNotContains('javascript:', $out2);
    }
}
