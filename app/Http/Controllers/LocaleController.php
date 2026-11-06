<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    /** @var list<string> */
    public const SUPPORTED = ['en', 'ru', 'zh_CN'];

    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        abort_unless(in_array($locale, self::SUPPORTED, true), 404);

        $request->session()->put('locale', $locale);

        if ($request->user() !== null) {
            $request->user()->update(['locale' => $locale]);
        }

        return redirect()->back();
    }
}
