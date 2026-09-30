<?php

namespace App\Filament\Support;

use Filament\Actions\Action;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Alignment;

/**
 * The named section of a record's edit panel that holds its rarer actions (docs/UI.md §2 Kural
 * 6 and 7, §4): "Borç işlemleri", "Hizmet işlemleri", … — never a "Diğer" drawer. The dangerous
 * ones (Sil, İptal et) sit on a row of their own at the end, apart from the form fields and from
 * the panel's Vazgeç / Kaydet buttons. Every action closes the edit panel when it runs
 * (`cancelParentActions()`) instead of also submitting the form. The section hides itself in a
 * create panel (no record yet) and when none of its actions is visible for the record.
 */
final class RecordActionsSection
{
    public const string ACTIONS_KEY = 'record_actions';

    public const string DANGER_ACTIONS_KEY = 'record_danger_actions';

    /**
     * @param  list<Action>  $actions
     * @param  list<Action>  $dangerActions
     */
    public static function make(string $heading, array $actions, array $dangerActions = []): Section
    {
        $components = [];

        if ($actions !== []) {
            $components[] = Actions::make(self::closingParent($actions, danger: false))
                ->key(self::ACTIONS_KEY)
                ->alignment(Alignment::Start);
        }

        if ($dangerActions !== []) {
            $components[] = Actions::make(self::closingParent($dangerActions, danger: true))
                ->key(self::DANGER_ACTIONS_KEY)
                ->alignment(Alignment::Start);
        }

        return Section::make($heading)
            ->schema($components)
            ->columnSpanFull()
            ->hidden(fn (Section $component): bool => $component->getRecord() === null
                || $component->getChildSchema()->getComponents() === []);
    }

    /**
     * Outlined, never filled: the panel's "Kaydet" stays the one primary button (docs/UI.md §4).
     * The rarer actions are gray, the dangerous ones keep their red.
     *
     * @param  list<Action>  $actions
     * @return list<Action>
     */
    private static function closingParent(array $actions, bool $danger): array
    {
        return array_map(function (Action $action) use ($danger): Action {
            $action->cancelParentActions()->outlined();

            return $danger ? $action : $action->color('gray');
        }, $actions);
    }
}
