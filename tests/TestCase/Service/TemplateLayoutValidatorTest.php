<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\TemplateLayoutValidator;
use Cake\TestSuite\TestCase;

final class TemplateLayoutValidatorTest extends TestCase
{
    public function testValidLayoutPasses(): void
    {
        $json = json_encode(['fields' => [
            ['placeholder' => '{callsign}', 'x' => 100, 'y' => 200,
             'font' => 'Inter-Bold.ttf', 'size' => 96, 'color' => '#000000', 'rotation' => 0],
        ]]);
        $errors = (new TemplateLayoutValidator())->validate($json, 1500, 1000);
        $this->assertSame([], $errors);
    }

    public function testInvalidJson(): void
    {
        $errors = (new TemplateLayoutValidator())->validate('{not json', 1500, 1000);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('not valid JSON', $errors[0]);
    }

    public function testMissingFieldsArray(): void
    {
        $errors = (new TemplateLayoutValidator())->validate('{}', 1500, 1000);
        $this->assertNotEmpty($errors);
    }

    public function testRejectsUnknownFont(): void
    {
        $json = json_encode(['fields' => [
            ['placeholder' => 'x', 'x' => 1, 'y' => 1, 'font' => 'Comic.ttf', 'size' => 12, 'color' => '#000'],
        ]]);
        $errors = (new TemplateLayoutValidator())->validate($json, 1500, 1000);
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('font', $errors[0]);
    }

    public function testRejectsOutOfBoundsCoords(): void
    {
        $json = json_encode(['fields' => [
            ['placeholder' => 'x', 'x' => 9999, 'y' => -5,
             'font' => 'Inter-Regular.ttf', 'size' => 12, 'color' => '#000'],
        ]]);
        $errors = (new TemplateLayoutValidator())->validate($json, 1500, 1000);
        $this->assertCount(2, $errors);
    }

    public function testRejectsBadColor(): void
    {
        $json = json_encode(['fields' => [
            ['placeholder' => 'x', 'x' => 1, 'y' => 1,
             'font' => 'Inter-Regular.ttf', 'size' => 12, 'color' => 'red'],
        ]]);
        $errors = (new TemplateLayoutValidator())->validate($json, 1500, 1000);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('color', $errors[0]);
    }

    public function testRejectsTooManyFields(): void
    {
        $fields = array_fill(0, 51, [
            'placeholder' => 'x', 'x' => 1, 'y' => 1,
            'font' => 'Inter-Regular.ttf', 'size' => 12, 'color' => '#000',
        ]);
        $errors = (new TemplateLayoutValidator())->validate(json_encode(['fields' => $fields]), 1500, 1000);
        $this->assertNotEmpty($errors);
    }
}
