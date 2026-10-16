<?php
/**
 * @package     Com_Seminardesk
 * @subpackage  Site
 * @author      Benno Flory <benno.flory@gmx.ch>
 * @copyright   2022-2026 Benno Flory
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Seminardesk\Site\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\Http\HttpFactory;
use Joomla\Uri\Uri;
use Joomla\CMS\Log\Log;
//use Joomla\Component\Seminardesk\Site\Service\ConfigService;

/**
 * API Service for SeminarDesk
 * Handles all API communication with the SeminarDesk backend.
 * @since  2.0.0
 */
class ApiService
{
    /**
     * @var object|null Cache controller instance
     */
    private ?object $cache = null;

    /**
     * Constructor
     *
     * @param   ConfigService                    $configService  The config service
     * @param   CacheControllerFactoryInterface  $cacheFactory   The cache factory
     */
    public function __construct(
        private ConfigService $configService,
        private CacheControllerFactoryInterface $cacheFactory
    ) {
        $this->configService = $configService;
        $this->cacheFactory = $cacheFactory;
    }

    /**
     * Get the cache controller
     *
     * @return  object  Cache controller
     */
    private function getCache(): object
    {
        if ($this->cache === null) {
            $this->cache = $this->cacheFactory->createCacheController('output', [
                'defaultgroup' => 'com_seminardesk'
            ]);
        }

        return $this->cache;
    }

    /**
     * Clear the cache for a specific route or all routes
     *
     * @param   string|null  $route  The route to clear, or null for all
     * @return  void
     */
    public function clearCache(?string $route = null): void
    {
        if ($route === null) {
            $this->getCache()->clean('com_seminardesk');
        } else {
            $cacheKey = 'seminardesk_api_' . str_replace('/', '_', $route);
            $this->getCache()->remove($cacheKey, 'com_seminardesk');
        }
    }

    /**
     * Fetch data from the API (no caching)
     *
     * @param   string  $route  The API route
     * @param   array   $api    The API type, base URL and API key (if private api)
     * @return  mixed   The API response data (decoded JSON) or null on failure
     */
    protected function fetchFromApi(string $route, array $api): mixed
    {
        try {
            $http = (new HttpFactory())->getAvailableDriver();
            $url  = $api['base_url'] . $route;

            // HTTP request headers
            $headers = [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ];
            // Send API key for private API requests
            if ($api['type'] === 'private_api') {
                $headers['X-Api-Key'] = $api['api_key'];
            }

            $timeout = 30;
            
            // request(method, uri, data, headers, timeout) returns PSR-7 Response
            $response = $http->request('GET', new Uri($url), null, $headers, $timeout);
            
            // PSR-7: body is a stream, read it with getContents() or cast to string
            $body = (string) $response->getBody();
            
            if ($response->getStatusCode() !== 200) {
                Log::add(
                    'SeminarDesk API returned status ' . $response->getStatusCode() . ' for ' . $route,
                    Log::WARNING,
                    'com_seminardesk'
                );
                return null;
            }
            
            return json_decode($body);
        } catch (\Exception $exception) {
            Log::add(
                'Failed to fetch SeminarDesk API data: ' . $exception->getMessage(),
                Log::ERROR,
                'com_seminardesk'
            );
            return null;
        }
    }

    /**
     * Get data from SeminarDesk API with caching
     *
     * @param   string  $route  The API route (e.g., '/eventDates', '/events/123')
     * @param   bool  $usePrivateApi  The API type, base URL and API key (if private api)
     * @return  mixed           The API response data or null on failure
     */
    public function getData(string $route, bool $usePrivateApi = false): mixed
    {
        $api = $usePrivateApi ? $this->configService->getPrivateApi() : $this->configService->getPublicApi();
        $cacheKey = 'seminardesk_' . $api['type'] . '_' . str_replace('/', '_', $route);
        
        $data = $this->getCache()->get($cacheKey);
        if (!$data) {
            $data = $this->fetchFromApi($route, $api);
            if ($data) {
                $this->getCache()->store($data, $cacheKey);
            }
        }
        return $data;
    }

    /**
     * Get event dates from the API
     * 
     * @return  array  List of event dates
     */
    public function getEventDates(): ?array
    {
        $eventDates = $this->getData('/eventDates');
        return $eventDates ? ($eventDates->dates ?? []) : [];
    }

    /**
     * Get event from the API
     * 
     * @param string $eventId
     * @return  object  Event data
     */
    public function getEvent(string $eventId): ?object
    {
        return $this->getData('/events/' . $eventId) ?? null;
    }

    /**
     * Get facilitators from the public API
     * 
     * @return  array  Facilitator data
     */
    public function getFacilitators(): ?array
    {
        $facilitators = $this->getData('/facilitators', false);
        return $facilitators ? ($facilitators->data ??  []) : [];
    }

    /**
     * Get facilitators from the private API
     * 
     * @return  array  Facilitator data
     */
    public function getFacilitatorsPrivateInfo(): ?array
    {
        // Fetch all facilitators with a large page size to ensure completeness
        $facilitators = $this->getData('/facilitators?page%5Bnumber%5D=1&page%5Bsize%5D=10000', true);
        return $facilitators ? ($facilitators->data ??  []) : [];
    }

    /**
     * Get facilitator from the API
     * 
     * @param string $facilitatorId
     * @return  object  Facilitator data
     */
    public function getFacilitator(string $facilitatorId): ?object
    {
        return $this->getData('/facilitators/' . $facilitatorId) ?? null;
    }

    /**
     * Get event dates of a given facilitator from the API
     * 
     * @param string $facilitatorId
     * @return  object  Event dates of the facilitator
     */
    public function getFacilitatorEventDates(string $facilitatorId): ?array
    {
        $eventDates = $this->getData('/facilitators/' . $facilitatorId . '/eventDates');
        return $eventDates ? ($eventDates->data ?? []) : [];   
    }

}
