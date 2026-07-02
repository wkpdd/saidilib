<?php

namespace App\Support;

/**
 * Returns the right column (_fr / _ar) for the current app locale,
 * falling back to French when the Arabic value is empty.
 */
trait Localizable
{
    public function tr(string $base): ?string
    {
        $locale = app()->getLocale();
        $value = $this->{$base . '_' . $locale} ?? null;

        if (empty($value)) {
            $value = $this->{$base . '_fr'} ?? null;
        }

        return $value;
    }
}
