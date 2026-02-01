<?php

namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\OfferTemplate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OfferTemplatePageController extends Controller
{
    /**
     * Display a listing of offer templates.
     */
    public function index(Request $request): Response
    {
        $templates = OfferTemplate::query()
            ->orderBy('name')
            ->paginate(25)
            ->through(fn (OfferTemplate $template) => [
                'id' => $template->id,
                'name' => $template->name,
                'is_default' => $template->is_default,
                'is_active' => $template->is_active,
                'created_at' => $template->created_at?->format('Y-m-d H:i:s'),
            ]);

        return Inertia::render('Recruitment/OfferTemplates/Index', [
            'templates' => $templates,
        ]);
    }

    /**
     * Show the form for creating a new offer template.
     */
    public function create(): Response
    {
        return Inertia::render('Recruitment/OfferTemplates/Create');
    }

    /**
     * Show the form for editing an offer template.
     */
    public function edit(string $tenant, OfferTemplate $offerTemplate): Response
    {
        return Inertia::render('Recruitment/OfferTemplates/Edit', [
            'template' => [
                'id' => $offerTemplate->id,
                'name' => $offerTemplate->name,
                'content' => $offerTemplate->content,
                'is_default' => $offerTemplate->is_default,
                'is_active' => $offerTemplate->is_active,
            ],
        ]);
    }
}
