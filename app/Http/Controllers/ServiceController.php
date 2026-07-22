<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Contracts\View\View;

class ServiceController extends Controller
{
    public function show(string $slug): View
    {
        $service = Service::published()
            ->where('slug', $slug)
            ->where('has_detail_page', true)
            ->firstOrFail();

        return view('services.show', [
            'service' => $service,
            'others' => Service::published()->ordered()->whereKeyNot($service->id)->get(),
        ]);
    }
}
