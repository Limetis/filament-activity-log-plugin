<?php

namespace Limetis\FilamentActivityLogPlugin\Filament\Table\Components;

use Closure;
use Filament\Infolists\Components\Entry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

/**
 * Class TimeLinePropertiesEntry
 *
 * This component displays property changes in an activity log timeline.
 * It formats and renders the changes made to model properties, showing the old and new values,
 * who made the changes, and when they were made.
 *
 * @package Limetis\FilamentActivityLogPlugin\Filament\Table\Components
 */
class TimeLinePropertiesEntry extends Entry
{
    /**
     * The model record being displayed.
     *
     * @var Model|null
     */
    protected ?Model $record = null;

    /**
     * The view used to render this component.
     *
     * @var string
     */
    protected string $view = 'activitylog::filament.infolists.components.time-line-propertie-entry';

    /**
     * Set up the component.
     * Configures the properties entry with hidden label and modified state.
     *
     * @return void
     */
    protected function setup(): void
    {
        parent::setup();

        $this->configurePropertieEntry();
    }

    /**
     * Set the record to be used by this component.
     *
     * @param Model $record The model record
     * @return static
     */
    public function withRecord($record): static
    {
        $this->record = $record;

        return $this;
    }

    /**
     * Configure the properties entry.
     * Hides the label and sets up the state modification.
     *
     * @return void
     */
    private function configurePropertieEntry(): void
    {
        $this->hiddenLabel()
            ->modifyState(fn ($state) => $this->modifiedProperties($state));
    }

    /**
     * Format the properties for display in the timeline.
     * Processes the state data to create an HTML representation of the property changes.
     *
     * @param array $state The state data containing properties, subject, causer, etc.
     * @return HtmlString|null HTML representation of the property changes or null if no properties
     */
    private function modifiedProperties($state): ?HtmlString
    {

        $properties = $state['properties'];
        if (empty($properties)) {
            return null;
        }

        $updatedAt = $state['update']->format('d/m/Y H:i:s');
        $subjectClassName = $state['subject_type'];
        $causer = $state['causer'];
        $translatedProperties = $this->translateProperties($properties, $subjectClassName);
        $changes = $this->getPropertyChanges($translatedProperties);
        $causerFieldName = config('activitylog.filament.causer_field_name', 'name');
        $causerName = $this->getCauserName($causer, $causerFieldName);
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

    /**
     * Get the transformed name of the subject class.
     * Uses translations from config if available, otherwise extracts the class name.
     *
     * @param string $subjectClassName The full class name of the subject
     * @return string The transformed subject name
     */
    private function getTransformedSubjectName(string $subjectClassName): string
    {
        $translationsFromConfig = config('activitylog.filament.subject_translations');
        return $subjectClassName && isset($translationsFromConfig[$subjectClassName])
            ? $translationsFromConfig[$subjectClassName]
            : collect(explode('\\', $subjectClassName))->last();
    }

    /**
     * Translate property keys using configuration-based translations.
     * Maps property keys to their translated versions based on the subject model.
     *
     * @param array $properties The properties to translate
     * @param string|null $subjectType The subject model
     * @return array The translated properties
     */
    protected function translateProperties(array $properties, ?string $subjectType): array
    {
        $translationsFromConfig = config('activitylog.filament.property_translates');
        $translations = $subjectType && isset($translationsFromConfig[$subjectType])
            ? $translationsFromConfig[$subjectType]
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

    /**
     * The state modification callback.
     *
     * @var Closure
     */
    protected $state;

    /**
     * Set the callback to modify the component's state.
     *
     * @param Closure $callback The callback to modify the state
     * @return static
     */
    public function modifyState(Closure $callback): static
    {
        $this->state = $callback;

        return $this;
    }

    /**
     * Get the modified state by evaluating the state callback.
     *
     * @return null|string|HtmlString The modified state
     */
    public function getModifiedState(): null|string|HtmlString
    {
        return $this->evaluate($this->state);
    }

    /**
     * Get the name of the causer (user who made the change).
     * Tries to find a suitable name field on the causer model.
     *
     * @param Model $causer The causer model
     * @param string|null $nameField The name field to use
     * @return string The causer's name
     */
    private function getCauserName(Model $causer, ?string $nameField = null): string
    {
        if ($nameField) {
            return $causer->$nameField;
        }

        return $causer->name ?? $causer->first_name ?? $causer->last_name ?? $causer->username ?? 'Unknown';
    }

    /**
     * Translate an event name to a human-readable form.
     * Maps standard event types to their Czech translations.
     *
     * @param string $event The event name
     * @return string The translated event name
     */
    private function translateEvent(string $event)
    {
        return match ($event) {
            'updated' => 'upravil',
            'created' => 'vytvořil',
            'deleted' => 'smazal',
            default => $event,
        };
    }

    /**
     * Get the property changes from the properties array.
     * Determines the appropriate method to use based on the available data.
     *
     * @param array $properties The properties array containing 'old' and/or 'attributes'
     * @return array The formatted property changes
     */
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

    /**
     * Compare old and new values to generate a list of changes.
     * Creates formatted strings showing what changed from old to new value.
     *
     * @param array $oldValues The old values
     * @param array $newValues The new values
     * @return array The formatted changes
     */
    private function compareOldAndNewValues(array $oldValues, array $newValues): array
    {
        $changes = [];

        foreach ($newValues as $key => $newValue) {
            if (!array_key_exists($key, $oldValues)) {
                continue;
            }

            $oldValue = $this->formatNewValue($oldValues[$key]);
            $newValue = $this->formatNewValue($newValue);

            // If both are null (meaning both are "Null" string), skip
            if ($oldValue === null && $newValue === null) {
                continue;
            }

            if ($oldValue !== $newValue) {
                $changes[] = sprintf(
                    '- %s z <strong>%s</strong> na <strong>%s</strong>',
                    htmlspecialchars(strval($key)),
                    htmlspecialchars($oldValue),
                    htmlspecialchars($newValue)
                );
            }
        }

        return $changes;
    }

    /**
     * Format an array of new values for display.
     * Creates a formatted string for each new value.
     *
     * @param array $newValues The new values to format
     * @return array The formatted new values
     */
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

    /**
     * Format a single value for display.
     * Handles arrays by converting them to JSON.
     *
     * @param mixed $value The value to format
     * @return string The formatted value
     */
    private function formatNewValue($value): string
    {
        if (is_bool($value)) {
            return $value ? 'True' : 'False';
        }

        if (in_array($value, ['0', '1'], true)) {
            return $value === '1' ? 'True' : 'False';
        }

        if (is_null($value)) {
            return 'Null';
        }

        return is_array($value) ? json_encode($value) : strval($value);
    }
}
