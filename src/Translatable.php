<?php
/*
 * This file is part of Laravel Translator.
 *
 * (c) Vincent Klaiber <hello@vinkla.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Vinkla\Translator;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;

/**
 * This is the translatable trait.
 *
 * @author Vincent Klaiber <hello@vinkla.com>
 */
trait Translatable
{

    /**
     * The translations cache.
     *
     * @var array
     */
    protected $cache = [];
    public $AttributeKey = "locale";
    public $CodeKey = "name";
    public $currentLocaleId = null;
    public $fallbackLocaleId = null;

    /**
     * Get a translation.
     *
     * @param string|null $locale
     * @param bool $fallback
     *
     * @return \Illuminate\Database\Eloquent\Model|null|static
     */
    public function translate($locale = null, $fallback = true)
    {
        $locale = $locale ? : $this->getLocale();

        $translation = $this->getTranslation($locale);

        if (!$translation && $fallback) {
            $translation = $this->getTranslation($this->getFallback());
        }

        if (!$translation && !$fallback) {
            $translation = $this->getEmptyTranslation($locale);
        }

        return $translation;
    }

    /**
     * Get a translation or create new.
     *
     * @param string $locale
     *
     * @return \Illuminate\Database\Eloquent\Model|static
     */
    protected function translateOrNew($locale)
    {
        $translation = $this->getTranslation($locale);

        if (!$translation) {
            return $this->translations()
                    ->where($this->AttributeKey, $locale)
                    ->firstOrNew([$this->AttributeKey => $locale]);
        }

        return $translation;
    }

    /**
     * Get a translation.
     *
     * @param string $locale
     *
     * @return \Illuminate\Database\Eloquent\Model|static|null
     */
    protected function getTranslation($locale)
    {
        if (isset($this->cache[$locale])) {
            return $this->cache[$locale];
        }

        $translation = $this->translations()
            ->where($this->AttributeKey, $locale)
            ->first();

        if ($translation) {
            $this->cache[$locale] = $translation;
        }

        return $translation;
    }

    /**
     * Get an empty translation.
     *
     * @param string $locale
     *
     * @return mixed
     */
    protected function getEmptyTranslation($locale)
    {
        $appLocale = $this->getLocale();

        $this->setLocale($locale);

        $translation = null;

        foreach ($this->translatedAttributes as $attribute) {
            $translation = $this->setAttribute($attribute, null);
        }

        $this->setLocale($appLocale);

        return $translation;
    }

    /**
     * Get an attribute from the model or translation.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function getAttribute($key)
    {
        if (in_array($key, $this->translatedAttributes)) {
            return $this->translate() ? $this->translate()->$key : null;
        }

        return parent::getAttribute($key);
    }

    /**
     * Set a given attribute on the model or translation.
     *
     * @param string $key
     * @param mixed $value
     *
     * @return $this
     */
    public function setAttribute($key, $value)
    {
        if (in_array($key, $this->translatedAttributes)) {
            $translation = $this->translateOrNew($this->getLocale());

            $translation->$key = $value;

            $this->cache[$this->getLocale()] = $translation;

            return $translation;
        }

        return parent::setAttribute($key, $value);
    }

    /**
     * Finish processing on a successful save operation.
     *
     * @param array $options
     *
     * @return void
     */
    protected function finishSave(array $options)
    {
        $this->translations()->saveMany($this->cache);

        parent::finishSave($options);
    }

    /**
     * Set the locale.
     *
     * @param string $locale
     *
     * @return void
     */
    protected function setLocale($locale)
    {
        $this->currentLocaleId = $locale;
    }

    /**
     * Get the locale.
     *
     * @return string
     */
    protected function getLocale()
    {
        $localeInDB = \App\Models\Locale::find($this->currentLocaleId);
        if (!$localeInDB) {
            $localeInDB = \App\Models\Locale::first();
            if ($localeInDB) {
                return $localeInDB->id;
            }
        }

        return null;
    }

    /**
     * Get the fallback locale.
     *
     * @return string
     */
    protected function getFallback()
    {
        return $this->fallbackLocaleId;
    }

    /**
     * Get the translations relation.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    abstract public function translations();
}
