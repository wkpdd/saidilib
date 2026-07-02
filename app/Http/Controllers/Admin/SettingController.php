<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function edit()
    {
        $settings = Setting::all()->keyBy('key');

        return view('admin.settings', compact('settings'));
    }

    public function update(Request $request)
    {
        $fields = [
            'general' => ['store_name', 'tagline_fr', 'tagline_ar', 'currency', 'announcement_fr', 'announcement_ar'],
            'contact' => ['phone', 'email', 'address_fr', 'address_ar', 'hours'],
            'social'  => ['facebook', 'instagram', 'tiktok'],
            'seo'     => ['meta_title', 'meta_description'],
        ];

        foreach ($fields as $group => $keys) {
            foreach ($keys as $key) {
                Setting::put($key, $request->input($key, ''), $group);
            }
        }

        // Logo upload
        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('branding', 'public');
            Setting::put('logo', $path, 'general');
        }

        Setting::flush();

        return back()->with('success', 'Paramètres enregistrés.');
    }
}
