<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LegalController extends Controller
{
    public function privacy(): View
    {
        return $this->render('privacy', 'Политика обработки персональных данных');
    }

    public function consent(): View
    {
        return $this->render('consent', 'Согласие на обработку персональных данных');
    }

    public function requisites(): View
    {
        return $this->render('requisites', 'Реквизиты и контакты');
    }

    private function render(string $document, string $title): View
    {
        $path = resource_path('legal/'.$document.'.md');
        abort_unless(File::exists($path), 404);

        return view('legal.show', [
            'title' => $title,
            'content' => Str::markdown(File::get($path)),
            'document' => $document,
        ]);
    }
}
