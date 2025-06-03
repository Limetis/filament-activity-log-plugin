<?php

namespace Limetis\FilamentActivityLogPlugin\Filament\Table\Components;

use Closure;
use Filament\Infolists\Components\Entry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class TimeLinePropertiesEntry extends Entry
{
    protected ?Model $record = null;
    protected string $view = 'activitylog::filament.infolists.components.time-line-propertie-entry';

    protected function setup(): void
    {
        parent::setup();

        $this->configurePropertieEntry();
    }

    public function withRecord($record): static
    {
        $this->record = $record;

        return $this;
    }

    private function configurePropertieEntry(): void
    {
        $this->hiddenLabel()
            ->modifyState(fn ($state) => $this->modifiedProperties($state));
    }

    private function modifiedProperties($state): ?HtmlString
    {
        $properties = $state['properties'];
        if (empty($properties)) {
            return null;
        }

        $updatedAt = $state['update']->format('d/m/Y H:i:s');

        $subject = $state['subject'];
        $causer = $state['causer'];

        $translatedProperties = $this->translateProperties($properties);
        $changes = $this->getPropertyChanges($translatedProperties);
        $causerFieldName = config('activitylog.filament.causer_field_name', 'name');

        $causerName = $this->getCauserName($causer, $causerFieldName);
        $subjectClassName = get_class($subject);
        if ($subjectClassName !== get_class($this->record)) {
            return new HtmlString(
                sprintf(
                    '<strong>%s </strong> %s <strong>%s:</strong> <br>%s <br><small> Upraveno v: <strong>%s</strong></small>',
                    $causerName,
                    $this->translateEvent($state['event']),
                    $this->getTransformedSubjectName($subjectClassName),
                    implode('<br>', $changes),
                    $updatedAt,
                ));
        }

        return new HtmlString(
            sprintf(
                '<strong>%s</strong> %s následující hodnoty: <br>%s <br><small> Upraveno v: <strong>%s</strong></small>',
                $causerName,
                $this->translateEvent($state['event']),
                implode('<br>', $changes),
                $updatedAt,
            ));
    }

    private function getTransformedSubjectName(string $subjectClassName): string
    {

        $translationsFromConfig = config('activitylog.filament.subject_translations');
        return $subjectClassName && isset($translationsFromConfig[$subjectClassName])
            ? $translationsFromConfig[$subjectClassName]
            : collect(explode('\\', $subjectClassName))->last();
    }

    protected function translateProperties(array $properties): array
    {
        $recordClass = get_class($this->record);
        $translationsFromConfig = config('activitylog.filament.property_translates');
        $translations = $recordClass && isset($translationsFromConfig[$recordClass])
            ? $translationsFromConfig[$recordClass]
            : [];

        $translated = [];

        foreach (['attributes', 'old'] as $section) {
            if (! isset($properties[$section]) || ! is_array($properties[$section])) {
                continue;
            }

            foreach ($properties[$section] as $key => $value) {
                $translated[$section][$translations[$key] ?? $key] = $value;
            }
        }

        return $translated;
    }

    protected $state;

    public function modifyState(Closure $callback): static
    {
        $this->state = $callback;

        return $this;
    }

    public function getModifiedState(): null|string|HtmlString
    {
        return $this->evaluate($this->state);
    }

    private function getCauserName(Model $causer, ?string $nameField = null): string
    {
        if ($nameField) {
            return $causer->$nameField;
        }

        return $causer->name ?? $causer->first_name ?? $causer->last_name ?? $causer->username ?? 'Unknown';
    }

    private function translateEvent(string $event)
    {
        return match ($event) {
            'updated' => 'upravil',
            'created' => 'vytvořil',
            'deleted' => 'smazal',
            default => $event,
        };
    }

    private function getPropertyChanges(array $properties): array
    {
        $changes = [];

        if (isset($properties['old'], $properties['attributes'])) {
            $changes = $this->compareOldAndNewValues($properties['old'], $properties['attributes']);
        } elseif (isset($properties['attributes'])) {
            $changes = $this->getNewValues($properties['attributes']);
        } elseif (isset($properties['old'])) {
            $changes = $this->getNewValues($properties['old']);
        }

        return $changes;
    }

    private function compareOldAndNewValues(array $oldValues, array $newValues): array
    {
        $changes = [];

        foreach ($newValues as $key => $newValue) {
            $oldValue = is_array($oldValues[$key]) ? json_encode($oldValues[$key]) : $oldValues[$key] ?? '-';
            $newValue = $this->formatNewValue($newValue);
            if (isset($oldValues[$key]) && $oldValues[$key] != $newValue) {
                $changes[] = sprintf('- %s z <strong>%s</strong> na <strong>%s</strong>', $key, htmlspecialchars($oldValue), htmlspecialchars($newValue));
            }
        }

        return $changes;
    }

    private function getNewValues(array $newValues): array
    {
        return array_map(
            fn ($key, $value) => sprintf(
                __('activitylog::timeline.properties.getNewValues'),
                $key,
                htmlspecialchars($this->formatNewValue($value))
            ),
            array_keys($newValues),
            $newValues
        );
    }

    private function formatNewValue($value): string
    {
        return is_array($value) ? json_encode($value) : $value ?? '—';
    }
}
