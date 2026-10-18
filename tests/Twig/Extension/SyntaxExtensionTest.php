<?php

/**
 * This file is part of the PropelBundle package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

namespace Propel\Bundle\PropelBundle\Tests\Twig\Extension;

use PHPUnit\Framework\TestCase;
use Propel\Bundle\PropelBundle\Twig\Extension\SyntaxExtension;

class SyntaxExtensionTest extends TestCase
{
    public function testFilters(): void
    {
        $filters = (new SyntaxExtension())->getFilters();

        $this->assertCount(2, $filters);
        $this->assertSame('format_sql', $filters[0]->getName());
        $this->assertSame(['html'], $filters[0]->getSafe(new \Twig\Node\Node()));
        $this->assertSame('format_memory', $filters[1]->getName());
    }

    public function testFormatMemory(): void
    {
        $extension = new SyntaxExtension();

        $this->assertSame('0 B', $extension->formatMemory(0));
        $this->assertSame('1.000 B', $extension->formatMemory(1));
        $this->assertSame('2.00 kB', $extension->formatMemory(2048));
        $this->assertSame('-2.00 kB', $extension->formatMemory(-2048));
        $this->assertSame('1.50 MB', $extension->formatMemory(1572864));
    }

    public function testFormatSql(): void
    {
        $formatted = (new SyntaxExtension())->formatSQL('SELECT `book`.`id` FROM book WHERE id = 1 /* comment */');

        $this->assertStringContainsString('<span class="SQLKeyword">SELECT</span>', $formatted);
        $this->assertStringContainsString('<br /><span class="SQLKeyword">FROM</span>', $formatted);
        $this->assertStringContainsString('<br /><span class="SQLKeyword">WHERE</span>', $formatted);
        $this->assertStringContainsString('<span class="SQLName">`book`</span>', $formatted);
        $this->assertStringContainsString('<span class="SQLComment">/* comment */</span>', $formatted);
    }

    public function testFormatSqlArray(): void
    {
        $formatted = (new SyntaxExtension())->formatSQL(['SELECT 1', 'DELETE FROM book']);

        $this->assertCount(2, $formatted);
        $this->assertStringContainsString('<span class="SQLKeyword">SELECT</span>', $formatted[0]);
        $this->assertStringContainsString('<span class="SQLKeyword">DELETE</span>', $formatted[1]);
    }

    public function testToPrecision(): void
    {
        $this->assertSame('0', SyntaxExtension::toPrecision(0));
        $this->assertSame('1.23', SyntaxExtension::toPrecision(1.23456));
        $this->assertSame('1,230', SyntaxExtension::toPrecision(1234));
    }
}
