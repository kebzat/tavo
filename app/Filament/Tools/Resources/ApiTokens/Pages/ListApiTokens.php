<?php

namespace App\Filament\Tools\Resources\ApiTokens\Pages;

use App\Enums\ApiAbility;
use App\Filament\Tools\Resources\ApiTokens\ApiTokenResource;
use App\Models\ApiToken;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class ListApiTokens extends ListRecords
{
    protected static string $resource = ApiTokenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nový token')
                ->createAnother(false)
                ->successNotification(null)
                ->using(function (array $data): Model {
                    [$token, $plain] = ApiToken::issue(
                        $data['name'],
                        array_map(fn (string $value): ApiAbility => ApiAbility::from($value), $data['abilities']),
                        Auth::user(),
                    );

                    // Jediná chvíle, kdy je token vidět. Proto trvalá notifikace,
                    // ať nezmizí dřív, než si ho člověk zkopíruje.
                    Notification::make()
                        ->title('Token pro „'.$token->name.'“')
                        ->body(new HtmlString(
                            'Zkopíruj si ho teď, znovu už se zobrazit nedá:<br>'
                            .'<code style="user-select: all; word-break: break-all;">'.e($plain).'</code>'
                        ))
                        ->success()
                        ->persistent()
                        ->send();

                    return $token;
                }),
        ];
    }
}
