<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends order notifications to one or more Telegram chats via the Bot API.
 * Bot token + the list of chat IDs are configured in admin settings.
 */
class TelegramNotifier
{
    private const API = 'https://api.telegram.org/bot';

    /** Parsed, de-duplicated list of configured chat IDs. */
    public function chatIds(): array
    {
        $raw = (string) Setting::get('telegram_chat_ids', '');

        return collect(preg_split('/[\s,]+/', $raw))
            ->map(fn ($s) => trim($s))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function isConfigured(): bool
    {
        return trim((string) Setting::get('telegram_bot_token', '')) !== '' && ! empty($this->chatIds());
    }

    /** Fan out a "new order" message to every configured chat. */
    public function orderCreated(Order $order): void
    {
        $this->broadcast($this->orderMessage($order));
    }

    /** Send an arbitrary message to all chats; returns [ok => n, failed => n]. */
    public function broadcast(string $text): array
    {
        $token = trim((string) Setting::get('telegram_bot_token', ''));
        if ($token === '') {
            return ['ok' => 0, 'failed' => 0];
        }

        $ok = 0;
        $failed = 0;
        foreach ($this->chatIds() as $chatId) {
            try {
                $res = Http::timeout(8)->asJson()->post(self::API . $token . '/sendMessage', [
                    'chat_id'                  => $chatId,
                    'text'                     => $text,
                    'parse_mode'               => 'HTML',
                    'disable_web_page_preview' => true,
                ]);
                $res->successful() ? $ok++ : $failed++;
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('Telegram send failed', ['chat' => $chatId, 'error' => $e->getMessage()]);
            }
        }

        return ['ok' => $ok, 'failed' => $failed];
    }

    private function orderMessage(Order $order): string
    {
        $store = Setting::get('store_name', 'Saidi Papetrie');
        $lines = [
            "🧾 <b>Nouvelle commande</b> — {$store}",
            "Réf : <b>{$order->reference}</b>",
            "Client : {$order->customer_name}",
            "Tél : {$order->phone}",
            'Wilaya : ' . (optional($order->wilaya)->name ?: '—'),
            'Livraison : ' . ($order->delivery_type === 'stopdesk' ? 'Stop desk' : 'Domicile'),
            'Total : <b>' . number_format((float) $order->total, 2, ',', ' ') . ' DA</b>',
        ];

        return implode("\n", array_filter($lines));
    }
}
