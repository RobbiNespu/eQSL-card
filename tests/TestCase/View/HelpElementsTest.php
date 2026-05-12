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

    public function testCalloutDefaultIsNote(): void
    {
        $out = $this->view->element('ui/callout', ['body' => 'Heads up.']);
        $this->assertStringContainsString('callout callout-note', $out);
        $this->assertStringContainsString('Heads up.', $out);
    }

    public function testCalloutVariantTip(): void
    {
        $out = $this->view->element('ui/callout', ['body' => 'Try this.', 'variant' => 'tip']);
        $this->assertStringContainsString('callout-tip', $out);
        $this->assertStringContainsString('Tip', $out); // emoji prefix label
    }

    public function testCalloutVariantWarning(): void
    {
        $out = $this->view->element('ui/callout', ['body' => 'Careful.', 'variant' => 'warning']);
        $this->assertStringContainsString('callout-warning', $out);
        $this->assertStringContainsString('Warning', $out);
    }

    public function testCalloutEscapesBody(): void
    {
        $out = $this->view->element('ui/callout', ['body' => '<script>x</script>']);
        $this->assertStringNotContainsString('<script>', $out);
        $this->assertStringContainsString('&lt;script&gt;', $out);
    }
}
