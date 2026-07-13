<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

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
            'general'  => ['store_name', 'tagline_fr', 'tagline_ar', 'currency', 'announcement_fr', 'announcement_ar'],
            'contact'  => ['phone', 'email', 'address_fr', 'address_ar', 'hours'],
            'social'   => ['facebook', 'instagram', 'tiktok'],
            'seo'      => ['meta_title', 'meta_description'],
            'telegram' => ['telegram_bot_token', 'telegram_chat_ids', 'telegram_channel_id'],
            'noest'    => ['noest_token', 'noest_guid', 'noest_enabled', 'noest_station_code'],
            'socialapi'=> ['fb_page_id', 'fb_page_token', 'ig_user_id', 'ig_token', 'fb_graph_version'],
            'imgsearch'=> ['google_cse_key', 'google_cse_cx', 'google_cse_reuse_only'],
        ];

        foreach ($fields as $group => $keys) {
            foreach ($keys as $key) {
                Setting::put($key, $request->input($key, ''), $group);
            }
        }

        // Logo upload — validate as a real raster image (no SVG: avoids inline-SVG XSS).
        if ($request->hasFile('logo')) {
            $request->validate(['logo' => 'image|mimes:jpeg,jpg,png,webp|max:2048']);
            $path = $request->file('logo')->store('branding', 'public');
            Setting::put('logo', $path, 'general');
        }

        Setting::flush();

        return back()->with('success', 'Paramètres enregistrés.');
    }

    /** Send a test message to all configured Telegram chats. */
    public function telegramTest(\App\Services\TelegramNotifier $telegram)
    {
        if (! $telegram->isConfigured()) {
            return back()->with('error', 'Configurez le token du bot et au moins un chat ID Telegram.');
        }

        $store = Setting::get('store_name', 'Saidi Papetrie');
        $res = $telegram->broadcast("✅ <b>Test Telegram</b> — {$store}\nLes notifications de commande fonctionnent.");

        return back()->with('success', "Test envoyé : {$res['ok']} réussi(s), {$res['failed']} échec(s).");
    }

    /**
     * Danger zone — clear TEST/DEMO data once the client approves the site.
     * Deletes orders, clients, incidents, notifications and the demo catalog,
     * while KEEPING wilayas, settings, pixels and admin accounts.
     *
     * Guarded by: full-admin middleware, current-password re-entry, and a typed
     * confirmation word.
     */
    public function resetData(Request $request)
    {
        // Even if the "settings" permission is delegated, the destructive reset
        // stays restricted to full administrators.
        abort_unless($request->user()->isFullAdmin(), 403, 'Réservé aux administrateurs.');

        $request->validate([
            'confirm'  => 'required|in:REINITIALISER',
            'password' => 'required|string',
        ], [
            'confirm.in' => 'Tapez REINITIALISER pour confirmer.',
        ]);

        if (! Hash::check($request->password, Auth::user()->password)) {
            return back()->withErrors(['password' => 'Mot de passe incorrect.']);
        }

        // Tables holding test/demo data. Order-independent because FK checks are
        // disabled around the truncation.
        $tables = [
            'order_items', 'orders',
            'client_transactions', 'clients',
            'inventory_incidents', 'admin_notifications',
            'pixel_product', 'product_variants', 'product_images', 'products',
            'categories',
        ];

        DB::transaction(function () use ($tables) {
            Schema::disableForeignKeyConstraints();
            foreach ($tables as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->truncate();
                }
            }
            Schema::enableForeignKeyConstraints();
        });

        // Remove uploaded product images + generated thumbnails from storage.
        foreach (['products', 'thumbs'] as $dir) {
            Storage::disk('public')->deleteDirectory($dir);
        }

        Setting::flush();

        Log::warning('Admin data reset performed', [
            'user_id' => Auth::id(),
            'email'   => Auth::user()->email,
            'ip'      => $request->ip(),
        ]);

        return redirect()->route('admin.settings.edit')
            ->with('success', 'Données de test effacées. La boutique est prête pour la production.');
    }
}
