<?php

declare(strict_types=1);

namespace CloakWP\ACF\Tests\FieldTree;

use CloakWP\ACF\Block;
use Extended\ACF\Fields\Text;
use PHPUnit\Framework\TestCase;

final class BlockSettingsTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = tempnam(sys_get_temp_dir(), 'block-settings-');
        file_put_contents($this->path, json_encode(['name' => 'acf/settings-test', 'title' => 'Settings test']));
    }

    protected function tearDown(): void
    {
        unlink($this->path);
    }

    public function testFieldConfigurationInvalidatesSettingsReadByAnIndex(): void
    {
        $first = Text::make('First');
        $second = Text::make('Second');
        $block = Block::make($this->path)->fields([$first]);
        $before = $block->getFieldGroupSettings();
        $block->fields([$second])->collapsible(false);
        $after = $block->getFieldGroupSettings();
        self::assertSame($before['key'], $after['key']);
        self::assertSame([$first, $second], $after['fields']);
    }

    public function testRegistrationMessageSurvivesAnEarlierSettingsRead(): void
    {
        $block = Block::make($this->path)->collapsible(false);
        self::assertSame([], $block->getFieldGroupSettings()['fields']);
        $block->emptyFieldsMessage('Drop this block wherever needed.');
        self::assertCount(1, $block->getFieldGroupSettings()['fields']);
    }
}
