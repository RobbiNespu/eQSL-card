<?php
declare(strict_types=1);

namespace App\Test\TestCase\View;

use Cake\TestSuite\TestCase;
use Cake\View\View;

final class FlashElementAccessibilityTest extends TestCase
{
    /**
     * Every flash element must wrap its message in role="alert" so
     * assistive tech announces it. We render each element with a stub
     * message and assert role="alert" appears in the output.
     */
    public function flashElementProvider(): array
    {
        return [
            ['flash/default', 'alert'],
            ['flash/success', 'alert alert-success'],
            ['flash/error',   'alert alert-danger'],
            ['flash/warning', 'alert alert-warning'],
            ['flash/info',    'alert alert-info'],
        ];
    }

    /**
     * @dataProvider flashElementProvider
     */
    public function testFlashElementHasRoleAlert(string $element, string $expectedClass): void
    {
        $view = new View();
        $out = $view->element($element, [
            'params' => [],
            'message' => 'Hello world',
        ]);
        $this->assertStringContainsString('role="alert"', $out);
        $this->assertStringContainsString($expectedClass, $out);
        $this->assertStringContainsString('Hello world', $out);
    }
}
