<?php

namespace Tests\Feature;

use Filament\Facades\Filament;
use Tests\TestCase;

class AdminThemeTest extends TestCase
{
    public function test_admin_panel_uses_the_migrated_nextjs_design(): void
    {
        $panel = Filament::getPanel('admin');

        $this->assertSame('ChurchManager', (string) $panel->getBrandName());
        $this->assertSame('18rem', $panel->getSidebarWidth());
        $this->assertSame('80rem', $panel->getMaxContentWidth());
        $this->assertSame('28rem', $panel->getSimplePageMaxContentWidth());
        $this->assertFalse($panel->hasDarkMode());
        $this->assertSame('Geist', $panel->getFontFamily());
        $this->assertSame('#9333EA', $panel->getColors()['primary']);
        $this->assertStringContainsString(
            'css/filament/admin/theme.css',
            $panel->getTheme()->getHref(),
        );
    }
}
