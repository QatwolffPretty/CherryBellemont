<?php

namespace App\Http\Controllers;

use App\Models\Faq;
use Illuminate\View\View;

class FaqController extends Controller
{
    public function index(): View
    {
        return view('faq.index', [
            'faqsByCategory' => Faq::query()->active()
                ->orderBy('category')
                ->orderBy('sort_order')
                ->orderBy('question')
                ->get()
                ->groupBy(fn (Faq $faq) => $faq->category ?: 'General'),
        ]);
    }
}
