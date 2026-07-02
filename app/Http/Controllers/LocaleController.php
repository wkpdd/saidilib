<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Session;

class LocaleController extends Controller
{
    public function switch(string $locale)
    {
        if (in_array($locale, SetLocale::SUPPORTED, true)) {
            Session::put('locale', $locale);
        }

        return back();
    }
}
