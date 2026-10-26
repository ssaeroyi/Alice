<?php

use App\Support\Section;

test('section normalizes emoji and special characters', function () {
    $result = Section::normalize('diamOnd ✨');
    $this->assertMatchesRegularExpression('/^[A-Z]/', $result);
    $this->assertStringNotContainsString('✨', $result);
    $this->assertSame('diamond', mb_strtolower($result));
});

test('section preserves hyphens with title-case', function () {
    $result = Section::normalize('ilang-ilang');
    $this->assertSame('Ilang-Ilang', $result);
});

test('section handles multi-word with numbers', function () {
    $result = Section::normalize('9-diamond');
    $this->assertSame('9-Diamond', $result);
});

test('section strips quotes and symbols', function () {
    $result = Section::normalize('"St. Anne"');
    $this->assertSame('St Anne', $result);
});

test('section collapses whitespace', function () {
    $result = Section::normalize('  hello    world  ');
    $this->assertSame('Hello World', $result);
});

test('section handles unicode correctly', function () {
    $result = Section::normalize('CAFÉ');
    $this->assertSame('CAFÉ', $result);
});
