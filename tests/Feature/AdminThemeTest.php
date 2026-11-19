<?php

namespace Tests\Feature;

use App\Filament\Widgets\DonationsChart;
use App\Filament\Widgets\LatestDonations;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\TopChurchesWidget;
use App\Filament\Widgets\TrendChartWidget;
use Filament\Enums\ThemeMode;
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
        $this->assertTrue($panel->hasDarkMode());
        $this->assertTrue($panel->hasThemeSwitcher());
        $this->assertSame(ThemeMode::Light, $panel->getDefaultThemeMode());
        $this->assertSame('Geist', $panel->getFontFamily());
        $this->assertSame('#9333EA', $panel->getColors()['primary']);
        $this->assertStringContainsString(
            'css/filament/admin/theme.css',
            $panel->getTheme()->getHref(),
        );
    }

    public function test_dashboard_widgets_render_immediately(): void
    {
        foreach ([
            StatsOverview::class,
            TopChurchesWidget::class,
            LatestDonations::class,
            DonationsChart::class,
            TrendChartWidget::class,
        ] as $widget) {
            $this->assertFalse($widget::isLazy(), $widget);
        }
    }

    public function test_filament_interface_strings_are_french(): void
    {
        $this->assertSame('Aller au contenu', __('filament-panels::layout.skip_to_content.label'));
        $this->assertSame('Activer le mode sombre', __('filament-panels::layout.actions.theme_switcher.dark.label'));
        $this->assertSame('3 résultats', trans_choice('filament-tables::table.result_count', 3, ['count' => 3]));
        $this->assertSame('Oui', __('filament-tables::table.columns.icon.boolean.true'));
        $this->assertSame('Actions', __('filament-forms::components.key_value.columns.actions.label'));
    }
}
