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

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\Component\Seminardesk\Site\Helper\TranslationHelper;

/**
 * Configuration Service for SeminarDesk
 *
 * Manages configuration settings and label constants for the SeminarDesk component.
 *
 * @since  2.0.0
 */
class ConfigService
{
    // Default tenant ID
    public const DEFAULT_TENANT_ID = 'zegg';

    // Label IDs
    public const LABELS_FESTIVALS_ID = 12;
    public const LABELS_EXTERNAL_ID = 55;
    public const LABELS_ON_APPLICATION_ID = 69;
    public const LABELS_ON_APPLICATION_WITH_REGISTRATION_ID = 82;
    public const LABELS_WITHOUT_REGISTRATION_ID = 81;
    public const LABELS_GERMAN_ID = 1;
    public const LABELS_GERMAN_POSSIBLE_ID = 54;
    public const LABELS_ENGLISH_ID = 2;
    public const LABELS_ENGLISH_POSSIBLE_ID = 53;
    public const LABELS_SPANISH_ID = 3;
    public const LABELS_FACILITATOR_ZEGG_ID = 59;
    public const LABELS_FACILITATOR_EXTERNAL_ID = 60;

    // Labels to hide from display
    public const LABELS_TO_HIDE = [
        1, 2, 3, 53, 54, // Languages
        self::LABELS_ON_APPLICATION_ID,
//      self::LABELS_FESTIVALS_ID, 
        self::LABELS_EXTERNAL_ID,
        self::LABELS_ON_APPLICATION_WITH_REGISTRATION_ID,
    ];

    // Facilitators to hide from display
    public const FACILITATORS_TO_HIDE = [
        '& Team',
    ];

    // Lodging types to exclude from pricing
    public const LODGING_TO_EXCLUDE = ['exÜN'];

    /**
     * @var CMSApplicationInterface
     */
    private CMSApplicationInterface $app;

    /**
     * @var array|null Cached configuration
     */
    private ?array $config = null;

    /**
     * Constructor
     *
     * @param   CMSApplicationInterface  $app  The application
     */
    public function __construct(CMSApplicationInterface $app)
    {
        $this->app = $app;
    }

    /**
     * Get configuration array
     *
     * @return  array  Configuration settings
     */
    public function getConfiguration(): array
    {
        if ($this->config === null) {
            // Get key for translations from SeminarDesk (e.g. 'DE', 'EN')
            $langKey = strtoupper(TranslationHelper::getCurrentLanguageKey());
            // Get SeminarDesk API settings from the component configuration (#__extensions.params)
            $params   = ComponentHelper::getParams('com_seminardesk');
            $tenantId = $params->get('tenant_id', self::DEFAULT_TENANT_ID);
            $apiKey   = $params->get('api_key', '');

            $menu = $this->app->getMenu()->getActive();
            
            // Get menu item IDs from menu query (request fields), fallback to current menu
            $eventsMenu = (int) ($menu->query['events_page'] ?? 0) ?: $menu->id;
            $facilitatorsMenu = (int) ($menu->query['facilitators_page'] ?? 0) ?: $menu->id;

            $this->config = [
                'tenant_id' => $tenantId,
                'langKey' => $langKey,
                'api' => 'https://' . $tenantId . '.seminardesk.de/api',
                'private_api' => 'https://' . $tenantId . '.seminardesk.de/private-api',
                'api_key' => $apiKey,
                'booking_base' => 'https://booking.seminardesk.de/' . strtolower($langKey) . '/' . $tenantId . '/',
                'eventlist_base' => 'index.php?option=com_seminardesk&Itemid=' . $eventsMenu . '&lang=' . strtolower($langKey),
                'facilitators_base' => 'index.php?option=com_seminardesk&Itemid=' . $facilitatorsMenu . '&lang=' . strtolower($langKey),
                'lodging_to_exclude' => self::LODGING_TO_EXCLUDE,
            ];
        }

        return $this->config;
    }

    /**
     * Get the SeminarDesk API base URL
     *
     * @return  array  API base URL as an associative array with key 'url'
     */
    public function getPublicApi(): array
    {
        return [
            'type' => 'public_api',
            'base_url' => $this->getConfiguration()['api'],
            'api_key' => '',
        ];
    }

    /**
     * Get the SeminarDesk Private API base URL
     *
     * @return  array  API base URL as an associative array with key 'url'
     */
    public function getPrivateApi(): array
    {
        return [
            'type' => 'private_api',
            'base_url' => $this->getConfiguration()['private_api'],
            'api_key' => $this->getConfiguration()['api_key'],
        ];
    }

    /**
     * Get the booking base URL
     *
     * @return  string  Booking base URL
     */
    public function getBookingBaseUrl(): string
    {
        return $this->getConfiguration()['booking_base'];
    }

    /**
     * Get the event list base URL
     *
     * @return  string  Event list base URL
     */
    public function getEventListBaseUrl(): string
    {
        return $this->getConfiguration()['eventlist_base'];
    }

    /**
     * Get the facilitators base URL
     *
     * @return  string  Facilitators base URL
     */
    public function getFacilitatorsBaseUrl(): string
    {
        return $this->getConfiguration()['facilitators_base'];
    }

    /**
     * Get the language key from configuration
     *
     * @return  string  Language key (uppercase, e.g. 'DE', 'EN')
     */
    public function getLangKey(): string
    {
        return $this->getConfiguration()['langKey'];
    }

    /**
     * Get the current language key (e.g. 'de', 'en')
     * Delegates to TranslationHelper for consistency.
     *
     * @return  string  Language key in lowercase
     */
    public function getCurrentLanguageKey(): string
    {
        return TranslationHelper::getCurrentLanguageKey();
    }

    /**
     * Get lodging types to exclude
     *
     * @return  array  Lodging types to exclude
     */
    public function getLodgingToExclude(): array
    {
        return self::LODGING_TO_EXCLUDE;
    }

    /**
     * Get the application instance
     *
     * @return  CMSApplicationInterface
     */
    public function getApplication(): CMSApplicationInterface
    {
        return $this->app;
    }
}
