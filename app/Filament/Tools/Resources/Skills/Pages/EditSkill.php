<?php

namespace App\Filament\Tools\Resources\Skills\Pages;

use App\Filament\Tools\Pages\Agent\AgentProject;
use App\Filament\Tools\Resources\KnowledgeSources\KnowledgeSourceResource;
use App\Filament\Tools\Resources\Skills\SkillResource;
use App\Models\Skill;
use App\Support\Agent\ClientProjects;
use App\Support\Knowledge\Archive;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

/** Pokyny skillu, odkud čerpá, a cesta k jeho agentovi, poznatkům a archivu. */
class EditSkill extends EditRecord
{
    protected static string $resource = SkillResource::class;

    public function getSubheading(): ?string
    {
        /** @var Skill $skill */
        $skill = $this->getRecord();

        return collect([
            $skill->categoryLabel(),
            '/'.$skill->slug,
            'agent: '.SkillResource::agentLabel($skill),
        ])->implode(' · ');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('agent')
                ->label('Otevřít agenta')
                ->icon(Heroicon::OutlinedCommandLine)
                ->url(fn (): ?string => filled($this->getRecord()->agent_project) ? AgentProject::getUrl(['project' => $this->getRecord()->agent_project]) : null)
                ->visible(fn (): bool => filled($this->getRecord()->agent_project)),
            Action::make('createAgent')
                ->label('Založit agenta')
                ->icon(Heroicon::OutlinedCommandLine)
                ->visible(fn (): bool => blank($this->getRecord()->agent_project) && ($this->getRecord()->agent_project_request['status'] ?? null) !== ClientProjects::STATUS_QUEUED)
                ->modalDescription('Projekt bez kódu, kde se se skillem probírá a učí. Založí se na pozadí do minuty.')
                ->schema([
                    TextInput::make('project')
                        ->label('Název projektu')
                        ->default(fn (): string => $this->getRecord()->projectName())
                        ->helperText('Malá písmena, číslice a pomlčky. Když je název obsazený jiným projektem, zvolte jiný.')
                        ->required()
                        ->regex('/^[a-z0-9][a-z0-9-]{1,40}$/'),
                ])
                ->action(function (array $data): void {
                    $skill = $this->getRecord();
                    $skill->forceFill(['agent_project_request' => null])->save();
                    app(ClientProjects::class)->requestSkill($skill, auth()->user()?->name, $data['project']);
                    Notification::make()->success()->title('Agent se zakládá')->body('Do minuty bude v Projektech mezi skilly.')->send();
                }),
            Action::make('upload')
                ->label('Nahrát do archivu')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->color('gray')
                ->modalHeading(fn (): string => 'Nahrát do archivu skillu '.$this->getRecord()->name)
                ->modalDescription('Case studies, výsledky klientů, vzorové weby, poznámky. Knihovník z nich navrhne poznatky skillu.')
                ->modalWidth('3xl')
                ->modalSubmitActionLabel('Uložit')
                ->schema(KnowledgeSourceResource::uploadFields(withSkill: false))
                ->action(function (array $data): void {
                    $source = app(Archive::class)->store([...$data, 'skill_id' => $this->getRecord()->getKey()], auth()->user()?->name);

                    Notification::make()
                        ->title(Archive::storedTitle($source))
                        ->status($source->isFailed() ? 'warning' : 'success')
                        ->send();
                }),
            ActionGroup::make([
                Action::make('entries')
                    ->label('Poznatky skillu')
                    ->icon(Heroicon::OutlinedLightBulb)
                    ->url(fn (): string => SkillResource::entriesUrl($this->getRecord())),
                Action::make('sources')
                    ->label('Archiv skillu')
                    ->icon(Heroicon::OutlinedArchiveBox)
                    ->url(fn (): string => SkillResource::sourcesUrl($this->getRecord())),
                DeleteAction::make()
                    ->modalDescription('Agenti ho přestanou volat. Poznatky a dokumenty skillu v mozku zůstanou, jen bez skillu. Projekt agenta na serveru zůstane.'),
            ])->color('gray'),
        ];
    }
}
