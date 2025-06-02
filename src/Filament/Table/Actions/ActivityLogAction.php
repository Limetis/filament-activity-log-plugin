<?php

namespace Limetis\FilamentActivityLogPlugin\Filament\Table\Actions;

use Filament\Tables\Actions\Action;
use Limetis\FilamentActivityLogPlugin\Filament\Table\Actions\Traits\ActivityLogContent;

class ActivityLogAction extends Action
{
    use ActivityLogContent;
}