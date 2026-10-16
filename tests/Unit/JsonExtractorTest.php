<?php

namespace Tests\Unit;

use App\Services\Research\JsonExtractor;
use PHPUnit\Framework\TestCase;

class JsonExtractorTest extends TestCase
{
    public function testPlainJson(): void
    {
        $this->assertSame(['a' => 1], JsonExtractor::parse('{"a":1}'));
    }

    public function testCodeFence(): void
    {
        $this->assertSame(['a' => 1], JsonExtractor::parse("Here you go:\n```json\n{\"a\": 1}\n```\nDone."));
    }

    public function testThinkBlockAndChatter(): void
    {
        $this->assertSame(['a' => ['b' => 2]], JsonExtractor::parse("<think>hmm {not json}</think>\nSure! {\"a\":{\"b\":2}} hope that helps"));
    }

    public function testGarbageReturnsNull(): void
    {
        $this->assertNull(JsonExtractor::parse('I could not do that.'));
        $this->assertNull(JsonExtractor::parse('{broken'));
    }
}
