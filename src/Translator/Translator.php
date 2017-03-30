<?php
/*
This is part of Wedeto, the WEb DEvelopment TOolkit.
It is published under the BSD 3-Clause License.

Wedeto\I18n\Translator\Translator was adapted from
Zend\I18n\Translator\Translator.
The modifications are: Copyright 2017, Egbert van der Wal.

The original source code is copyright Zend Technologies USA Inc. The original
licence information is included below.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:

Redistributions of source code must retain the above copyright notice, this
list of conditions and the following disclaimer. Redistributions in binary form
must reproduce the above copyright notice, this list of conditions and the
following disclaimer in the documentation and/or other materials provided with
the distribution. Neither the name of Zend or Rogue Wave Software, nor the
names of its contributors may be used to endorse or promote products derived
from this software without specific prior written permission. THIS SOFTWARE IS
PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR
IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF
MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO
EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT,
INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF
LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE
OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF
ADVISED OF THE POSSIBILITY OF SUCH DAMAGE. 
*/

/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Wedeto\I18n\Translator;

use Locale;

use Wedeto\Util\LoggerAwareStaticTrait;
use Wedeto\Util\Cache;

/**
 * Translator.
 */
class Translator
{
    use LoggerAwareStaticTrait;

    /** Messages loaded by the translator. */
    protected $messages = array();

    /** Patterns used for loading messages. */
    protected $patterns = array();

    /** Default locale. */
    protected $locale;

    /** Locale to use as fallback if there is no translation. */
    protected $fallbackLocale;
    
    /** Default text domain */
    protected $textDomain = 'default';

    /** Translation cache. */
    protected $cache;

    /** Instantiate a translator */
    public function __construct()
    {
        self::getLogger();
        $this->cache = new Cache('translate');
    }

    /**
     * Set the default locale.
     *
     * @param  string $locale
     * @return Translator Provides fluent interface
     */
    public function setLocale(string $locale)
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * Get the default locale.
     *
     * @return string
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * Set the fallback locale.
     *
     * @param  string $locale
     * @return Translator Provides fluent interface
     */
    public function setFallbackLocale($locale)
    {
        $this->fallbackLocale = $locale;

        return $this;
    }

    /**
     * Get the fallback locale.
     *
     * @return string
     */
    public function getFallbackLocale()
    {
        return $this->fallbackLocale;
    }

    /**
     * Set the default text domain
     * @param string $domain The domain to set
     * @return Translator Provides fluent interface
     */
    public function setTextDomain(string $domain)
    {
        $this->textDomain = $domain;
        return $this;
    }

    /**
     * Get the current default text domain
     * @return string The current default text domain
     */
    public function getTextDomain()
    {
        return $this->textDomain;
    }

    /*
     * Translate a message.
     *
     * @param string $message The message to translate
     * @param string $textDomain The text domain of the message. Null to use default.
     * @param string $locale The locale for the translation
     * @return string The translated string
     */
    public function translate($message, $textDomain = null, $locale = null)
    {
        $locale = ($locale ?: $this->getLocale());
        if ($locale === 'C')
            return $message;

        $textDomain = $textDomain ?: $this->getTextDomain();
        $translation = $this->getTranslatedMessage($message, $locale, $textDomain);

        if ($translation !== null && $translation !== '')
            return $translation;

        // Log untranslated message
        self::$logger->debug(
            "Untranslated message: \"{msgid}\"", 
            ["msgid" => $message, "locale" => $locale, "domain" => $textDomain]
        );

        if (
            null !== ($fallbackLocale = $this->getFallbackLocale())
            && $locale !== $fallbackLocale
        ) {
            return $this->translate($message, $textDomain, $fallbackLocale);
        }

        return $message;
    }

    /**
     * Translate a plural message.
     *
     * @param string $singular The singular version
     * @param string $plural The plural version
     * @param int $number The number determining which version should be used
     * @param string $textDomain The text domain of the message. Null to use default.
     * @param string|null $locale The locale for the translation
     * @return string The translated string
     * @throws \OutOfBoundsException If the returned index does not exist in the translated string.
     */
    public function translatePlural(string $singular, string $plural, int $number, string $textDomain = null, string $locale = null)
    {
        $locale = $locale ?: $this->getLocale();
        if ($locale === 'C')
            return $number === 1 ? $singular : $plural;

        $textDomain = $textDomain ?: $this->getTextDomain();
        $translation = $this->getTranslatedMessage($singular, $locale, $textDomain);

        if (empty($translation))
        {
            // Log untranslated message
            self::$logger->debug(
                "Untranslated message: \"{msgid}\"", 
                ["msgid" => $singular, "msgid_plural" => $plural, "locale" => $locale, "domain" => $textDomain]
            );

            if (null !== ($fallbackLocale = $this->getFallbackLocale()) && $locale !== $fallbackLocale)
                return $this->translatePlural($singular, $plural, $number, $textDomain, $fallbackLocale);

            return $number == 1 ? $singular : $plural;
        }
        elseif (is_string($translation))
            $translation = array($translation);

        $index = $this->cache->get($textDomain, $locale)
            ->getPluralRule()
            ->evaluate($number);

        if (!isset($translation[$index]))
            throw new \OutOfBoundsException(sprintf('Provided index $index does not exist in plural array', $index));

        return $translation[$index];
    }

    /**
     * Get a translated message.
     *
     * @param string $message
     * @param string $locale
     * @param string $textDomain
     * @return string|null
     */
    protected function getTranslatedMessage($message, $locale, $textDomain = 'default')
    {
        if (empty($message))
            return '';

        if (!$this->cache->has($textDomain, $locale))
            $this->loadMessages($textDomain, $locale);

        if ($this->cache->has($textDomain, $locale, $message))
            return $this->cache->get($textDomain, $locale, $message);

        return;
    }

    /**
     * Add multiple translations with a file pattern.
     *
     * @param string $baseDir
     * @param string $pattern
     * @param string $textDomain
     * @return Translator Provides fluent interface
     */
    public function addPattern(string $baseDir, string $pattern, string $textDomain = 'default')
    {
        if (!isset($this->patterns[$textDomain]))
            $this->patterns[$textDomain] = array();

        $this->patterns[$textDomain][] = array(
            'baseDir' => rtrim($baseDir, '/'),
            'pattern' => $pattern,
        );

        return $this;
    }

    /**
     * Load messages for a given language and domain.
     *
     * @param string $textDomain
     * @param string $locale
     * @throws RuntimeException
     * @return void
     */
    protected function loadMessages(string $textDomain, string $locale)
    {
        $result = $this->cache->get($textDomain, $locale);
        if ($result !== null)
            return;

        $messagesLoaded = $this->loadMessagesFromPatterns($textDomain, $locale);

        if (!$messagesLoaded)
            $this->cache->put($textDomain, $locale, array());
    }

    /**
     * Load messages from patterns.
     *
     * @param string $textDomain
     * @param string $locale
     * @return bool
     */
    protected function loadMessagesFromPatterns(string $textDomain, string $locale)
    {
        $messagesLoaded = false;

        if (isset($this->patterns[$textDomain]))
        {
            foreach ($this->patterns[$textDomain] as $pattern)
            {
                $filename = $pattern['baseDir'] . '/' . sprintf($pattern['pattern'], $locale);

                if (!is_file($filename))
                    continue;
                $loader = new GetText;

                if ($this->cache->has($textDomain, $locale))
                    $this->cache->get($textDomain, $locale)->merge($loader->load($locale, $filename));
                else
                    $this->cache->put($textDomain, $locale, $loader->load($locale, $filename));

                $messagesLoaded = true;
            }
        }

        return $messagesLoaded;
    }

    /**
     * Return all the messages.
     *
     * @param string $textDomain
     * @param null $locale
     *
     * @return mixed
     */
    public function getAllMessages(string $textDomain = 'default', $locale = null)
    {
        $locale = $locale ?: $this->getLocale();

        if (!$this->cache->has($textDomain, $locale))
            $this->loadMessages($textDomain, $locale);

        return $this->cache->get($textDomain, $locale);
    }
}
