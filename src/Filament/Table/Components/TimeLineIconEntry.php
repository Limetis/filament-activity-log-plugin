<?php

namespace Limetis\FilamentActivityLogPlugin\Filament\Table\Components;

use Filament\Infolists\Components\IconEntry;

class TimeLineIconEntry extends IconEntry
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->configureIconEntry();
    }

    protected string $view = 'activitylog::filament.infolists.components.time-line-icon-entry';

    private function configureIconEntry()
    {
        $this
            ->hiddenLabel()
            ->size('w-4 h-4');
    }
}