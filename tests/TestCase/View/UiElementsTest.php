<?php
declare(strict_types=1);

namespace App\Test\TestCase\View;

use Cake\TestSuite\TestCase;
use Cake\View\View;

final class UiElementsTest extends TestCase
{
    private View $view;

    protected function setUp(): void
    {
        parent::setUp();
        $this->view = new View();
    }

    public function testCallsignRendersMonoSpan(): void
    {
        $out = $this->view->element('ui/callsign', ['call' => '9W2NSP']);
        $this->assertStringContainsString('class="callsign"', $out);
        $this->assertStringContainsString('9W2NSP', $out);
    }

    public function testCallsignEmpty(): void
    {
        $out = $this->view->element('ui/callsign', ['call' => '']);
        $this->assertStringContainsString('—', $out);
    }

    public function testBadgeShareStatusShared(): void
    {
        $card = (object)['share_slug' => 'abc', 'share_revoked_at' => null];
        $out = $this->view->element('ui/badge_share_status', ['card' => $card]);
        $this->assertStringContainsString('bg-success', $out);
        $this->assertStringContainsString('Shared', $out);
    }

    public function testBadgeShareStatusRevoked(): void
    {
        $card = (object)['share_slug' => 'abc', 'share_revoked_at' => new \DateTime()];
        $out = $this->view->element('ui/badge_share_status', ['card' => $card]);
        $this->assertStringContainsString('bg-secondary', $out);
        $this->assertStringContainsString('revoked', $out);
    }

    public function testBadgeShareStatusPrivate(): void
    {
        $card = (object)['share_slug' => null, 'share_revoked_at' => null];
        $out = $this->view->element('ui/badge_share_status', ['card' => $card]);
        $this->assertStringContainsString('Private', $out);
    }

    public function testBadgeQsoTypeNet(): void
    {
        $qso = (object)['qso_type' => 'net', 'net_title' => 'Daily Net'];
        $out = $this->view->element('ui/badge_qso_type', ['qso' => $qso]);
        $this->assertStringContainsString('NET', $out);
        $this->assertStringContainsString('Daily Net', $out);
    }

    public function testBadgeQsoTypeContact(): void
    {
        $qso = (object)['qso_type' => 'contact'];
        $out = $this->view->element('ui/badge_qso_type', ['qso' => $qso]);
        $this->assertSame('', trim($out));
    }

    public function testBadgeTransportInternet(): void
    {
        $qso = (object)['transport' => 'echolink', 'transport_meta' => 'node 12345'];
        $out = $this->view->element('ui/badge_transport', ['qso' => $qso]);
        $this->assertStringContainsString('ECHOLINK', $out);
    }

    public function testBadgeTransportRf(): void
    {
        $qso = (object)['transport' => 'rf', 'transport_meta' => null];
        $out = $this->view->element('ui/badge_transport', ['qso' => $qso]);
        $this->assertSame('', trim($out));
    }

    public function testEmptyStateWithCta(): void
    {
        $out = $this->view->element('ui/empty_state', [
            'message' => 'Nothing here yet.',
            'cta_url' => '/x',
            'cta_label' => 'Add one',
        ]);
        $this->assertStringContainsString('Nothing here yet.', $out);
        $this->assertStringContainsString('href="/x"', $out);
        $this->assertStringContainsString('Add one', $out);
        $this->assertStringContainsString('alert-info', $out);
    }

    public function testEmptyStateMessageOnly(): void
    {
        $out = $this->view->element('ui/empty_state', [
            'message' => 'Empty.',
        ]);
        $this->assertStringContainsString('Empty.', $out);
        $this->assertStringNotContainsString('<a ', $out);
    }

    public function testPageHeaderWithLede(): void
    {
        $out = $this->view->element('ui/page_header', [
            'title' => 'Logbook',
            'lede'  => 'Your QSOs.',
        ]);
        $this->assertStringContainsString('<h1>Logbook</h1>', $out);
        $this->assertStringContainsString('Your QSOs.', $out);
    }

    public function testPageHeaderWithoutLede(): void
    {
        $out = $this->view->element('ui/page_header', ['title' => 'Hello']);
        $this->assertStringContainsString('<h1>Hello</h1>', $out);
        $this->assertStringNotContainsString('<p>', $out);
    }

    public function testDlItemWithValue(): void
    {
        $out = $this->view->element('ui/dl_item', [
            'term'  => 'Callsign',
            'value' => '9W2NSP',
        ]);
        $this->assertStringContainsString('<dt class="col-sm-3">Callsign</dt>', $out);
        $this->assertStringContainsString('<dd class="col-sm-9">9W2NSP</dd>', $out);
    }

    public function testDlItemEmptyValueShowsEmDash(): void
    {
        $out = $this->view->element('ui/dl_item', [
            'term'  => 'Callsign',
            'value' => '',
        ]);
        $this->assertStringContainsString('—', $out);
    }
}
