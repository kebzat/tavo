<?php

namespace App\Filament\Tools\Livewire;

use App\Support\Agent\AgentServer;
use App\Support\Agent\ClaudeUsage;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Využití předplatného Claude uprostřed hlavičky nástrojů: relace (5 h)
 * a týden, procenta a pruh. Kliknutím se rozbalí podrobnosti s časem obnovy.
 * Obnovuje se každé dvě minuty, jen když je záložka vidět.
 *
 * Zapojená v ToolsPanelProvider (render hook TOPBAR_END), jen když je
 * nastavená služba agenta.
 */
class ClaudeUsageMeter extends Component
{
    public function render(): View
    {
        return view('filament.tools.partials.claude-usage', [
            'usage' => ClaudeUsage::current(app(AgentServer::class)),
        ]);
    }
}
