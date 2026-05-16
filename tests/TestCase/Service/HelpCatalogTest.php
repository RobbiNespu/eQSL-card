<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\HelpCatalog;
use Cake\TestSuite\TestCase;

final class HelpCatalogTest extends TestCase
{
    public function testExistsReturnsTrueForKnownPair(): void
    {
        $this->assertTrue(HelpCatalog::exists('getting-started', 'welcome'));
    }

    public function testExistsReturnsFalseForUnknownCategory(): void
    {
        $this->assertFalse(HelpCatalog::exists('not-a-category', 'welcome'));
    }

    public function testExistsReturnsFalseForUnknownSlugInRealCategory(): void
    {
        $this->assertFalse(HelpCatalog::exists('getting-started', 'not-a-page'));
    }

    public function testPageLabelReturnsExpectedString(): void
    {
        $this->assertSame('Welcome to eQSL Card', HelpCatalog::pageLabel('getting-started', 'welcome'));
    }

    public function testCategoryLabelReturnsExpectedString(): void
    {
        $this->assertSame('Getting started', HelpCatalog::categoryLabel('getting-started'));
    }

    public function testNeighboursReturnsPrevAndNext(): void
    {
        $n = HelpCatalog::neighbours('getting-started', 'create-account');
        $this->assertIsArray($n);
        $this->assertArrayHasKey('prev', $n);
        $this->assertArrayHasKey('next', $n);
        $this->assertSame(['category' => 'getting-started', 'slug' => 'welcome'], $n['prev']);
        $this->assertSame(['category' => 'getting-started', 'slug' => 'first-card'], $n['next']);
    }

    public function testNeighboursFirstPageHasNullPrev(): void
    {
        $n = HelpCatalog::neighbours('getting-started', 'welcome');
        $this->assertNull($n['prev']);
        $this->assertNotNull($n['next']);
    }

    public function testNeighboursLastPageHasNullNext(): void
    {
        $n = HelpCatalog::neighbours('reference', 'about');
        $this->assertNotNull($n['prev']);
        $this->assertNull($n['next']);
    }

    public function testNeighboursCrossesCategoryBoundary(): void
    {
        // Last page of one category links to first page of the next.
        $n = HelpCatalog::neighbours('getting-started', 'first-card');
        $this->assertSame(['category' => 'logging', 'slug' => 'add-qso'], $n['next']);
    }

    public function testAllPagesYieldsEveryPair(): void
    {
        $pairs = iterator_to_array(HelpCatalog::allPages());
        $this->assertGreaterThanOrEqual(24, count($pairs));
        // Each entry is [category, slug, label].
        foreach ($pairs as $entry) {
            $this->assertCount(3, $entry);
        }
    }
}
