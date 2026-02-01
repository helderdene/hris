<?php

use App\Services\HtmlSanitizerService;

beforeEach(function () {
    $this->sanitizer = new HtmlSanitizerService();
});

it('returns null for null input', function () {
    expect($this->sanitizer->sanitize(null))->toBeNull();
});

it('returns empty string for empty input', function () {
    expect($this->sanitizer->sanitize(''))->toBe('');
});

it('preserves safe HTML elements', function () {
    $html = '<p>Hello <strong>World</strong></p>';
    expect($this->sanitizer->sanitize($html))->toBe($html);
});

it('preserves heading elements', function () {
    $html = '<h1>Title</h1><h2>Subtitle</h2><h3>Section</h3>';
    expect($this->sanitizer->sanitize($html))->toBe($html);
});

it('preserves text formatting elements', function () {
    $html = '<strong>bold</strong> <em>italic</em> <b>bold2</b> <i>italic2</i> <u>underline</u>';
    expect($this->sanitizer->sanitize($html))->toBe($html);
});

it('preserves list elements', function () {
    $html = '<ul><li>Item 1</li><li>Item 2</li></ul>';
    expect($this->sanitizer->sanitize($html))->toBe($html);
});

it('preserves ordered list elements', function () {
    $html = '<ol><li>First</li><li>Second</li></ol>';
    expect($this->sanitizer->sanitize($html))->toBe($html);
});

it('preserves links with href attribute', function () {
    $html = '<a href="https://example.com">Link</a>';
    expect($this->sanitizer->sanitize($html))->toBe($html);
});

it('preserves span and div with class attribute', function () {
    $html = '<div class="container"><span class="text">Content</span></div>';
    expect($this->sanitizer->sanitize($html))->toBe($html);
});

it('preserves blockquote code and pre elements', function () {
    $html = '<blockquote>Quote</blockquote><code>code</code><pre>preformatted</pre>';
    expect($this->sanitizer->sanitize($html))->toBe($html);
});

it('preserves br and hr elements', function () {
    $html = '<p>Line 1<br>Line 2</p><hr>';
    $result = $this->sanitizer->sanitize($html);

    expect($result)->toContain('<br');
    expect($result)->toContain('<hr');
    expect($result)->toContain('Line 1');
    expect($result)->toContain('Line 2');
});

it('removes script tags completely', function () {
    $html = '<p>Safe content</p><script>alert("xss")</script>';
    $result = $this->sanitizer->sanitize($html);

    expect($result)->not->toContain('<script');
    expect($result)->not->toContain('alert');
    expect($result)->toContain('<p>Safe content</p>');
});

it('removes inline script event handlers', function () {
    $html = '<p onclick="alert(\'xss\')">Click me</p>';
    $result = $this->sanitizer->sanitize($html);

    expect($result)->not->toContain('onclick');
    expect($result)->not->toContain('alert');
    expect($result)->toContain('<p>Click me</p>');
});

it('removes javascript protocol in href', function () {
    $html = '<a href="javascript:alert(\'xss\')">Click</a>';
    $result = $this->sanitizer->sanitize($html);

    expect($result)->not->toContain('javascript:');
});

it('removes onerror event handlers', function () {
    $html = '<img src="x" onerror="alert(\'xss\')">';
    $result = $this->sanitizer->sanitize($html);

    expect($result)->not->toContain('onerror');
    expect($result)->not->toContain('alert');
});

it('removes iframe elements', function () {
    $html = '<iframe src="https://evil.com"></iframe><p>Safe</p>';
    $result = $this->sanitizer->sanitize($html);

    expect($result)->not->toContain('<iframe');
    expect($result)->toContain('<p>Safe</p>');
});

it('removes object elements', function () {
    $html = '<object data="data:text/html,<script>alert(1)</script>"></object>';
    $result = $this->sanitizer->sanitize($html);

    expect($result)->not->toContain('<object');
});

it('removes embed elements', function () {
    $html = '<embed src="evil.swf"><p>Content</p>';
    $result = $this->sanitizer->sanitize($html);

    expect($result)->not->toContain('<embed');
    expect($result)->toContain('<p>Content</p>');
});

it('removes style tags', function () {
    $html = '<style>body{background:url("javascript:alert(1)")}</style><p>Text</p>';
    $result = $this->sanitizer->sanitize($html);

    expect($result)->not->toContain('<style');
    expect($result)->toContain('<p>Text</p>');
});

it('removes form elements', function () {
    $html = '<form action="https://evil.com"><input type="text"></form>';
    $result = $this->sanitizer->sanitize($html);

    expect($result)->not->toContain('<form');
    expect($result)->not->toContain('<input');
});

it('removes svg with script', function () {
    $html = '<svg onload="alert(\'xss\')"><script>alert(1)</script></svg>';
    $result = $this->sanitizer->sanitize($html);

    expect($result)->not->toContain('<svg');
    expect($result)->not->toContain('<script');
    expect($result)->not->toContain('alert');
});

it('removes base64 data in img src', function () {
    $html = '<img src="data:image/svg+xml;base64,PHN2ZyBvbmxvYWQ9ImFsZXJ0KDEpIj4=">';
    $result = $this->sanitizer->sanitize($html);

    expect($result)->not->toContain('<img');
});

it('handles mixed safe and unsafe content', function () {
    $html = '<h1>Title</h1><script>evil()</script><p>Paragraph</p><iframe src="bad"></iframe><ul><li>Item</li></ul>';
    $result = $this->sanitizer->sanitize($html);

    expect($result)->toContain('<h1>Title</h1>');
    expect($result)->toContain('<p>Paragraph</p>');
    expect($result)->toContain('<ul><li>Item</li></ul>');
    expect($result)->not->toContain('<script');
    expect($result)->not->toContain('<iframe');
});

it('removes meta tags', function () {
    $html = '<meta http-equiv="refresh" content="0;url=https://evil.com"><p>Content</p>';
    $result = $this->sanitizer->sanitize($html);

    expect($result)->not->toContain('<meta');
    expect($result)->toContain('<p>Content</p>');
});

it('removes link tags', function () {
    $html = '<link rel="stylesheet" href="https://evil.com/style.css"><p>Content</p>';
    $result = $this->sanitizer->sanitize($html);

    expect($result)->not->toContain('<link');
    expect($result)->toContain('<p>Content</p>');
});
