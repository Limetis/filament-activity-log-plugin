<?php

namespace Limetis\FilamentActivityLogPlugin\Filament\Table\Actions\Traits;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Closure;
use Filament\Actions\StaticAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;
use Limetis\FilamentActivityLogPlugin\Filament\Table\Components\TimeLineIconEntry;
use Limetis\FilamentActivityLogPlugin\Filament\Table\Components\TimeLinePropertiesEntry;
use Limetis\FilamentActivityLogPlugin\Filament\Table\Components\TimeLineRepeatableEntry;

/**
 * Trait ActivityLogContent
 * 
 * This trait provides functionality for displaying activity logs in a Filament UI.
 * It handles the configuration of infolists, modals, and queries for retrieving and displaying activity logs.
 * The trait can be used to show a timeline of activities related to a model and its relations.
 * 
 * @package Limetis\FilamentActivityLogPlugin\Filament\Table\Actions\Traits
 */
trait ActivityLogContent
{
    /**
     * Array of relation names to include in the activity log query.
     *
     * @var array|null
     */
    private ?array $withRelations = null;

    /**
     * Array of icons to use for different activity events in the timeline.
     *
     * @var array|null
     */
    private ?array $timelineIcons = null;

    /**
     * Array of colors to use for different activity events in the timeline.
     *
     * @var array|null
     */
    private ?array $timelineIconColors = null;

    /**
     * Maximum number of activities to display.
     *
     * @var int|null
     */
    private ?int $limit = 10;

    /**
     * Closure for modifying the activity query.
     *
     * @var Closure
     */
    protected Closure $modifyQueryUsing;

    /**
     * The base query for retrieving activities.
     *
     * @var Closure|Builder
     */
    protected Closure|Builder $query;

    /**
     * Custom closure for retrieving activities.
     *
     * @var Closure|null
     */
    protected ?Closure $activitiesUsing;

    /**
     * Closure for modifying the activity title.
     *
     * @var Closure|null
     */
    protected ?Closure $modifyTitleUsing;

    /**
     * Closure that determines whether the title should be modified.
     *
     * @var Closure|null
     */
    protected ?Closure $shouldModifyTitleUsing;

    /**
     * Get the default name for the activity log timeline.
     *
     * @return string|null
     */
    public static function getDefaultName(): ?string
    {
        return 'activitylog_timeline';
    }

    /**
     * Set up the activity log content.
     * Configures the infolist, modal, and default values for various properties.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->configureInfolist();
        $this->configureModal();
        $this->activitiesUsing = null;
        $this->modifyTitleUsing = null;
        $this->shouldModifyTitleUsing = fn () => true;
        $this->modifyQueryUsing = fn ($builder) => $builder;
        $this->modalHeading = 'Log aktivit';
        $this->modalDescription = 'Je zobrazeno posledních 10 změn uživatele';
        $this->query = function (?Model $record) {
            $activities = Activity::query()
                ->with(['subject', 'causer'])
                ->where(function (Builder $query) use ($record) {
                    $query->where(function (Builder $q) use ($record) {
                        $q->where('subject_type', $record->getMorphClass())
                            ->where('subject_id', $record->getKey());
                    })->when($this->getWithRelations(), function (Builder $query, array $relations) use ($record) {
                        foreach ($relations as $relation) {
                            $model = get_class($record->{$relation}()->getRelated());
                            $query->orWhere(function (Builder $q) use ($record, $model, $relation) {
                                $q->where('subject_type', (new $model)->getMorphClass())
                                    ->whereIn('subject_id', $record->{$relation}()->withTrashed()->pluck('id'));
                            });
                        }

                    });
                });

            return $activities;
        };
    }

    /**
     * Configure the infolist for displaying activity logs.
     *
     * @return void
     */
    private function configureInfolist(): void
    {
        $this->infolist(function (?Model $record, Infolist $infolist) {
            return $infolist
                ->state(['activities' => $this->getActivityLogRecord($record, $this->getWithRelations())])
                ->schema($this->getSchema($record));
        });
    }

    /**
     * Configure the modal for displaying activity logs.
     *
     * @return void
     */
    private function configureModal(): void
    {
        $this->slideOver()
            ->modalIcon('heroicon-o-eye')
            ->modalFooterActions(fn () => [])
            ->icon('heroicon-o-bell-alert');
    }

    /**
     * Get the schema for the activity log timeline.
     *
     * @param Model $record The model record
     * @return array The schema configuration
     */
    private function getSchema(Model $record): array
    {
        return [
            TimeLineRepeatableEntry::make('activities')
                ->schema([
                    TimeLineIconEntry::make('activityData.event')
                        ->icon(function ($state) {
                            return $this->getTimelineIcons()[$state] ?? 'heroicon-m-check';
                        })
                        ->color(function ($state) {
                            return $this->getTimelineIconColors()[$state] ?? 'primary';
                        }),
                    TimeLinePropertiesEntry::make('activityData')
                        ->withRecord($record),
                    TextEntry::make('updated_at')
                        ->hiddenLabel()
                        ->since()
                        ->badge(),
                ]),
        ];
    }

    /**
     * Set the relations to include in the activity log query.
     *
     * @param array|null $relations The relations to include
     * @return StaticAction|null
     */
    public function withRelations(?array $relations = null): ?StaticAction
    {
        $this->withRelations = $relations;

        return $this;
    }

    /**
     * Get the relations to include in the activity log query.
     *
     * @return array|null
     */
    public function getWithRelations(): ?array
    {
        return $this->evaluate($this->withRelations);
    }

    /**
     * Set the icons to use for different activity events in the timeline.
     *
     * @param array|null $timelineIcons The icons to use
     * @return StaticAction|null
     */
    public function timelineIcons(?array $timelineIcons = null): ?StaticAction
    {
        $this->timelineIcons = $timelineIcons;

        return $this;
    }

    /**
     * Get the icons to use for different activity events in the timeline.
     *
     * @return array|null
     */
    public function getTimelineIcons(): ?array
    {
        return $this->evaluate($this->timelineIcons);
    }

    /**
     * Set the colors to use for different activity events in the timeline.
     *
     * @param array|null $timelineIconColors The colors to use
     * @return StaticAction|null
     */
    public function timelineIconColors(?array $timelineIconColors = null): ?StaticAction
    {
        $this->timelineIconColors = $timelineIconColors;

        return $this;
    }

    /**
     * Get the colors to use for different activity events in the timeline.
     *
     * @return array|null
     */
    public function getTimelineIconColors(): ?array
    {
        return $this->evaluate($this->timelineIconColors);
    }

    /**
     * Set the maximum number of activities to display.
     *
     * @param int|null $limit The maximum number of activities
     * @return StaticAction|null
     */
    public function limit(?int $limit = 10): ?StaticAction
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * Get the maximum number of activities to display.
     *
     * @return int|null
     */
    public function getLimit(): ?int
    {
        return $this->evaluate($this->limit);
    }

    /**
     * Set the base query for retrieving activities.
     *
     * @param Closure|Builder|null $query The query
     * @return static
     */
    public function query(Closure|Builder|null $query): static
    {
        $this->query = $query;

        return $this;
    }

    /**
     * Get the base query for retrieving activities.
     *
     * @return Builder|null
     */
    public function getQuery(): ?Builder
    {
        return $this->evaluate($this->query);
    }

    /**
     * Set a closure for modifying the activity query.
     *
     * @param Closure $closure The closure
     * @return static
     */
    public function modifyQueryUsing(Closure $closure): static
    {
        $this->modifyQueryUsing = $closure;

        return $this;
    }

    /**
     * Apply the query modification closure to the given builder.
     *
     * @param Builder $builder The query builder
     * @return Builder The modified builder
     */
    public function getModifyQueryUsing(Builder $builder): Builder
    {
        return $this->evaluate($this->modifyQueryUsing, ['builder' => $builder]);
    }

    /**
     * Set a closure for modifying the activity title.
     *
     * @param Closure $closure The closure
     * @return static
     */
    public function modifyTitleUsing(Closure $closure): static
    {
        $this->modifyTitleUsing = $closure;

        return $this;
    }

    /**
     * Set a closure that determines whether the title should be modified.
     *
     * @param Closure $closure The closure
     * @return static
     */
    public function shouldModifyTitleUsing(Closure $closure): static
    {
        $this->shouldModifyTitleUsing = $closure;

        return $this;
    }

    /**
     * Set a custom closure for retrieving activities.
     *
     * @param Closure $closure The closure
     * @return static
     */
    public function activitiesUsing(Closure $closure): static
    {
        $this->activitiesUsing = $closure;

        return $this;
    }

    /**
     * Get the activities using the custom closure if set.
     *
     * @return Collection|null
     */
    public function getActivitiesUsing(): ?Collection
    {
        return $this->evaluate($this->activitiesUsing);
    }

    /**
     * Get the activities for the given record and relations.
     *
     * @param Model|null $record The model record
     * @param array|null $relations The relations to include
     * @return Collection The activities
     */
    protected function getActivities(?Model $record, ?array $relations = null): Collection
    {
        if ($activities = $this->getActivitiesUsing()) {
            return $activities;
        } else {
            $builder = $this->getQuery();

            return $this->getModifyQueryUsing($builder)
                ->get();
        }
    }

    /**
     * Get the formatted activity log records for the given record and relations.
     *
     * @param Model|null $record The model record
     * @param array|null $relations The relations to include
     * @return Collection The formatted activity log records
     */
    protected function getActivityLogRecord(?Model $record, ?array $relations = null): Collection
    {
        $activities = $this->getActivities($record, $relations);
        $activities = $activities->transform(function ($activity) {
            $activity->activityData = $this->formatActivityData($activity);

            return $activity;
        });

        $activities = $activities
            ->sortByDesc(fn ($activity) => $activity->created_at)
            ->filter(function ($activity) {
                return ! empty($activity->activityData['properties']['attributes'])
                    || ! empty($activity->activityData['properties']['old']);
            })
            ->take($this->getLimit());

        return $activities;
    }

    /**
     * Format the activity data for display.
     *
     * @param Activity $activity The activity to format
     * @return array The formatted activity data
     */
    protected function formatActivityData($activity): array
    {
        $activityProperties = self::clearUnchangedAttributes(json_decode($activity->properties, true));

        return [
            'log_name' => $activity->log_name,
            'description' => $activity->description,
            'subject' => $activity->subject,
            'event' => $activity->event,
            'causer' => $activity->causer,
            'properties' => $this->formatDateValues($activityProperties),
            'batch_uuid' => $activity->batch_uuid,
            'update' => $activity->updated_at,
            'subject_type' => $activity->subject_type,
        ];
    }

    /**
     * Remove unchanged attributes from the activity properties.
     *
     * @param array|string|null $value The activity properties
     * @return array|string|null The cleaned activity properties
     */
    private static function clearUnchangedAttributes(array|string|null $value): array|string|null
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! isset($value['attributes'], $value['old'])) {
            return $value;
        }

        $attributes = $value['attributes'];
        $old = $value['old'];

        foreach ($attributes as $key => $newValue) {
            if (array_key_exists($key, $old) && $old[$key] === $newValue) {
                unset($attributes[$key], $old[$key]);
            }
        }

        return [
            'attributes' => $attributes,
            'old' => $old,
        ];

    }

    /**
     * Format date values in the activity properties.
     *
     * @param mixed $value The value to format
     * @return mixed The formatted value
     */
    private static function formatDateValues(mixed $value): mixed
    {
        if($value === null){
            return null;
        }

        if (is_array($value)) {
            foreach ($value as &$item) {
                $item = self::formatDateValues($item);
            }

            return $value;
        }

        if (is_bool($value) || is_numeric($value) && ! preg_match('/^\d{10,}$/', $value)) {
            return $value;
        }

        if (self::isValidDate($value)) {
            return Carbon::parse($value)
                ->format(config('filament-activitylog.datetime_format', 'd/m/Y H:i:s'));
        }

        return $value;
    }

    /**
     * Check if a string is a valid date.
     *
     * @param string $dateString The string to check
     * @param string $dateFormat The date format to check against
     * @param string $dateTimeFormat The datetime format to check against
     * @return bool|string True if the string is a valid date, false otherwise
     */
    private static function isValidDate(string $dateString, string $dateFormat = 'Y-m-d', string $dateTimeFormat = 'Y-m-d H:i:s'): bool|string
    {
        try {

            $dateTime = CarbonImmutable::createFromFormat($dateFormat, $dateString);

            if ($dateTime && $dateTime->format($dateFormat) === $dateString) {
                return true;
            }

        } catch (InvalidFormatException $e) {

        }

        try {

            $dateTime = CarbonImmutable::createFromFormat($dateTimeFormat, $dateString);

            if ($dateTime && $dateTime->format($dateTimeFormat) === $dateString) {
                return true;
            }

        } catch (InvalidFormatException $e) {

        }

        return false;
    }
}
