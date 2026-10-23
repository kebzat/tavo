<?php

namespace App\Http\Controllers\Tools;

use App\Support\Agent\AgentServer;
use App\Support\Agent\AgentUnavailable;
use Symfony\Component\HttpFoundation\Response;

/**
 * Snímek k hotové práci agenta (Nástroje → Agent → projekt → Hotová práce).
 * Leží v kontejneru agenta, web ho jen přepošle. Jen pro přihlášené do
 * nástrojů (routa je v ToolsPanelProvider::authenticatedRoutes).
 */
class AgentHandoverImageController
{
    public function __invoke(AgentServer $agent, string $project, string $name, string $file): Response
    {
        abort_unless(preg_match('/^[a-z0-9][a-z0-9-]{1,40}$/', $project) && preg_match('/^[a-z0-9][a-z0-9._-]{0,60}$/', $name), 404);
        abort_unless(preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,80}\.(png|jpe?g|webp)$/i', $file), 404);

        try {
            $image = $agent->handoverImage($project, $name, $file);
        } catch (AgentUnavailable) {
            abort(503);
        }

        abort_if($image === null, 404);

        return response($image->body(), 200, [
            'Content-Type' => match (strtolower(pathinfo($file, PATHINFO_EXTENSION))) {
                'webp' => 'image/webp',
                'jpg', 'jpeg' => 'image/jpeg',
                default => 'image/png',
            },
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
