<?php

namespace App\Models\Concerns;

trait HasTranslations
{
    public function localized(string $attribute): mixed
    {
        $translations = $this->getAttribute('translations') ?? [];
        $localeValues = $translations[app()->getLocale()] ?? [];

        return array_key_exists($attribute, $localeValues) ? $localeValues[$attribute] : $this->getAttribute($attribute);
    }
}
