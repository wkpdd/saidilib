# Delivery integrations

The shipping layer lives in `app/Services/Delivery/` and is built around one interface so
new carriers are easy to add:

```
ShippingDriver (interface)
├── NoestDriver      → Noest Express API
├── YalidineDriver   → manual now, API-ready
└── ManualDriver     → your own delivery / any carrier
DeliveryManager      → registry + dispatch used by the admin "Expédier" button
```

When you open an order in **/admin/orders/{id}** you pick a provider and (for manual mode)
paste a tracking number, then press **Expédier**. The order is stamped with the provider,
tracking number, the raw provider response, and `dispatched_at`.

---

## Noest Express (API)

1. Get your **API token** and **GUID** from your Noest account.
2. In `.env`:
   ```ini
   NOEST_ENABLED=true
   NOEST_BASE_URL=https://app.noest-dz.com
   NOEST_API_TOKEN=your_token
   NOEST_GUID=your_guid
   ```
3. When you share the **official Noest documentation**, only one file usually needs editing:
   `app/Services/Delivery/NoestDriver.php` →
   - `createShipment()`: confirm the **endpoint path** and **field names** in `$payload`
     (client, phone, wilaya_id, commune, montant, stop_desk, produit, …).
   - the success/tracking extraction (`$body['tracking']` etc.).
   Everything else (auth, error handling, order stamping) already works.

The driver only appears as "configured" in the admin dropdown once `NOEST_ENABLED=true` and
token + GUID are set; otherwise it falls back to manual entry.

## Yalidine (manual for now)

Default is **manual**: create the parcel in your Yalidine dashboard, then paste the tracking
number in the order's *Expédition* box and choose **Yalidine**.

To automate later, fill:
```ini
YALIDINE_ENABLED=true
YALIDINE_BASE_URL=https://api.yalidine.app/v1
YALIDINE_API_ID=your_id
YALIDINE_API_TOKEN=your_token
```
The API path in `YalidineDriver::createShipment()` is already scaffolded — adjust field names
to match your Yalidine API docs and it will create parcels automatically.

## Adding another carrier (ZR Express, Maystro, …)

1. Create `app/Services/Delivery/ZrExpressDriver.php` implementing `ShippingDriver`.
2. Register it in `DeliveryManager::__construct()`:
   ```php
   'zrexpress' => new ZrExpressDriver(),
   ```
That's it — it shows up in the admin dispatch dropdown.

## Per-wilaya pricing

Delivery fees are **not** hard-coded. Edit home + stop-desk fees per wilaya in
**/admin/wilayas**. The checkout page reads these live to show the customer the exact total
before they confirm.
