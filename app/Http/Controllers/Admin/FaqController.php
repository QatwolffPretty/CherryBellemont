<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\FaqRequest;
use App\Models\Faq;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FaqController extends Controller
{
    public function index(Request $request): View
    {
        $faqs = Faq::query()->orderBy('category')->orderBy('sort_order')->orderBy('question');

        if ($search = $request->string('search')->trim()->value()) {
            $faqs->where(fn ($query) => $query->where('question', 'like', "%{$search}%")->orWhere('answer', 'like', "%{$search}%")->orWhere('category', 'like', "%{$search}%"));
        }
        if ($request->filled('active')) {
            $faqs->where('is_active', $request->boolean('active'));
        }

        return view('admin.faqs.index', ['faqs' => $faqs->paginate(25)->withQueryString()]);
    }

    public function create(): View
    {
        return view('admin.faqs.form', ['faq' => new Faq]);
    }

    public function store(FaqRequest $request): RedirectResponse
    {
        Faq::create($this->data($request));

        return to_route('admin.faqs.index')->with('success', 'FAQ created.');
    }

    public function edit(Faq $faq): View
    {
        return view('admin.faqs.form', compact('faq'));
    }

    public function update(FaqRequest $request, Faq $faq): RedirectResponse
    {
        $faq->update($this->data($request));

        return to_route('admin.faqs.index')->with('success', 'FAQ updated.');
    }

    public function destroy(Faq $faq): RedirectResponse
    {
        $faq->delete();

        return back()->with('success', 'FAQ deleted.');
    }

    private function data(FaqRequest $request): array
    {
        return [...$request->validated(), 'is_active' => $request->boolean('is_active')];
    }
}
