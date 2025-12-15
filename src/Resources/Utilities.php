<?php

namespace Langsys\SDK\Resources;

use Langsys\SDK\Http\HttpClient;
use Langsys\SDK\Log\LoggerInterface;
use Langsys\SDK\Log\NullLogger;

/**
 * Resource for handling utility API operations.
 */
class Utilities
{
    /**
     * @var HttpClient
     */
    protected $http;

    /**
     * @var string|null
     */
    protected $projectId;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Create a new Utilities instance.
     *
     * @param HttpClient $http
     * @param string|null $projectId
     * @param LoggerInterface $logger
     */
    public function __construct(HttpClient $http, $projectId = null, $logger = null)
    {
        $this->http = $http;
        $this->projectId = $projectId;
        $this->logger = $logger !== null ? $logger : new NullLogger();
    }

    /**
     * Get a list of all countries.
     *
     * @param string $displayLocale Locale to display country names in (e.g., 'en-us', 'es-es')
     * @param array $options Optional parameters: page, records_per_page, order_by, filter_by
     * @return array Paginated list of countries with 'label' and 'code'
     */
    public function getCountries($displayLocale, array $options = [])
    {
        return $this->http->get('countries/' . $displayLocale, $options);
    }

    /**
     * Get all countries without pagination.
     *
     * @param string $displayLocale Locale to display country names in
     * @return array List of countries [['label' => 'Costa Rica', 'code' => 'CR'], ...]
     */
    public function getAllCountries($displayLocale)
    {
        $response = $this->getCountries($displayLocale, ['records_per_page' => 300]);
        return isset($response['data']) ? $response['data'] : [];
    }

    /**
     * Get a list of country dial codes.
     *
     * @param string $displayLocale Locale to display country names in
     * @param array $options Optional parameters: page, records_per_page, order_by, filter_by
     * @return array Paginated list with 'country_code', 'dial_code', 'name'
     */
    public function getDialCodes($displayLocale, array $options = [])
    {
        return $this->http->get('countries/dial-codes/' . $displayLocale, $options);
    }

    /**
     * Get all dial codes without pagination.
     *
     * @param string $displayLocale Locale to display country names in
     * @return array List of dial codes [['country_code' => 'CR', 'dial_code' => '506', 'name' => 'Costa Rica (+506)'], ...]
     */
    public function getAllDialCodes($displayLocale)
    {
        $response = $this->getDialCodes($displayLocale, ['records_per_page' => 300]);
        return isset($response['data']) ? $response['data'] : [];
    }

    /**
     * Get locales grouped by language.
     *
     * @param array $displayLocales Locales to display the data in (e.g., ['en-us', 'es-es'])
     * @param bool $appendTargetLocales Whether to append project's target locales
     * @return array Locales grouped by language for each display locale
     */
    public function getLocalesGrouped(array $displayLocales = [], $appendTargetLocales = false)
    {
        $params = [];

        if (!empty($displayLocales)) {
            $params['locales'] = $displayLocales;
        }

        if ($this->projectId) {
            $params['project_id'] = $this->projectId;
        }

        if ($appendTargetLocales) {
            $params['append_target_locales'] = 'true';
        }

        return $this->http->get('locales', $params);
    }

    /**
     * Get a flat list of all locales.
     *
     * @param array $displayLocales Locales to display the data in
     * @param bool $appendTargetLocales Whether to append project's target locales
     * @return array Flat list of locales for each display locale
     */
    public function getLocalesFlat(array $displayLocales = [], $appendTargetLocales = false)
    {
        $params = [];

        if (!empty($displayLocales)) {
            $params['locales'] = $displayLocales;
        }

        if ($this->projectId) {
            $params['project_id'] = $this->projectId;
        }

        if ($appendTargetLocales) {
            $params['append_target_locales'] = 'true';
        }

        return $this->http->get('locales/flat', $params);
    }

    /**
     * Get detailed list of all locales with language information.
     *
     * @param array $displayLocales Locales to display the data in
     * @param bool $appendTargetLocales Whether to append project's target locales
     * @return array Detailed list of locales for each display locale
     */
    public function getLocalesDetailed(array $displayLocales = [], $appendTargetLocales = false)
    {
        $params = [];

        if (!empty($displayLocales)) {
            $params['locales'] = $displayLocales;
        }

        if ($this->projectId) {
            $params['project_id'] = $this->projectId;
        }

        if ($appendTargetLocales) {
            $params['append_target_locales'] = 'true';
        }

        return $this->http->get('locales/data', $params);
    }

    /**
     * Get simple list of locale codes and names for a single display locale.
     *
     * @param string $displayLocale Locale to display names in (e.g., 'en-us')
     * @param bool $appendTargetLocales Whether to append project's target locales
     * @return array List of locales [['code' => 'es-cr', 'name' => 'Spanish (Costa Rica)'], ...]
     */
    public function getLocaleList($displayLocale, $appendTargetLocales = false)
    {
        $response = $this->getLocalesFlat([$displayLocale], $appendTargetLocales);

        if (isset($response['data'][$displayLocale])) {
            return $response['data'][$displayLocale];
        }

        return [];
    }

    /**
     * Build a country select dropdown array.
     *
     * @param string $displayLocale Locale to display country names in
     * @return array ['US' => 'United States', 'CR' => 'Costa Rica', ...]
     */
    public function getCountrySelectOptions($displayLocale)
    {
        $countries = $this->getAllCountries($displayLocale);
        $options = [];

        foreach ($countries as $country) {
            $options[$country['code']] = $country['label'];
        }

        return $options;
    }

    /**
     * Build a dial code select dropdown array.
     *
     * @param string $displayLocale Locale to display country names in
     * @return array ['US' => 'United States (+1)', 'CR' => 'Costa Rica (+506)', ...]
     */
    public function getDialCodeSelectOptions($displayLocale)
    {
        $dialCodes = $this->getAllDialCodes($displayLocale);
        $options = [];

        foreach ($dialCodes as $dialCode) {
            $options[$dialCode['country_code']] = $dialCode['name'];
        }

        return $options;
    }

    /**
     * Build a locale select dropdown array.
     *
     * @param string $displayLocale Locale to display names in
     * @param bool $appendTargetLocales Whether to append project's target locales
     * @return array ['es-cr' => 'Spanish (Costa Rica)', 'fr-ca' => 'French (Canada)', ...]
     */
    public function getLocaleSelectOptions($displayLocale, $appendTargetLocales = false)
    {
        $locales = $this->getLocaleList($displayLocale, $appendTargetLocales);
        $options = [];

        foreach ($locales as $locale) {
            $options[$locale['code']] = $locale['name'];
        }

        return $options;
    }
}
