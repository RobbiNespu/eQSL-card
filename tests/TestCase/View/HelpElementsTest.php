<?php
declare(strict_types=1);

namespace App\Test\TestCase\View;

use Cake\TestSuite\TestCase;
use Cake\View\View;

final class HelpElementsTest extends TestCase
{
    private View $view;

    protected function setUp(): void
    {
        parent::setUp();
        $this->view = new View();
    }

    public function testHelpSidebarRendersAllCategoryLabels(): void
    {
        $out = $this->view->element('ui/help_sidebar', [
            'activeCategory' => null,
            'activeSlug' => null,
        ]);
        $this->assertStringContainsString('Getting started', $out);
        $this->assertStringContainsString('Logging QSOs', $out);
        $this->assertStringContainsString('Cards &amp; sharing', $out);
        $this->assertStringContainsString('Admin guide', $out);
    }

    public function testHelpSidebarMarksActivePage(): void
    {
        $out = $this->view->element('ui/help_sidebar', [
            'activeCategory' => 'getting-started',
            'activeSlug' => 'welcome',
        ]);
        // Active link gets aria-current="page" + a CSS class hook.
        $this->assertMatchesRegularExpression(
            '/<a[^>]+aria-current="page"[^>]*>Welcome to eQSL Card<\/a>/',
            $out
        );
    }

    public function testHelpSidebarLinksUseHelpUrls(): void
    {
        $out = $this->view->element('ui/help_sidebar', [
            'activeCategory' => null,
            'activeSlug' => null,
        ]);
        $this->assertStringContainsString('href="/help/getting-started/welcome"', $out);
        $this->assertStringContainsString('href="/help/admin/install"', $out);
    }
}
