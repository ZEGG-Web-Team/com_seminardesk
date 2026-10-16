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

use Joomla\CMS\Router\Route;
use Joomla\Component\Seminardesk\Site\Helper\FormatHelper;
use Joomla\Component\Seminardesk\Site\Helper\TranslationHelper;

/**
 * Facilitator Service for SeminarDesk
 * Handles all logic related to facilitators, such as loading from the API and formatting.
 *
 * @since  2.0.0
 */
class FacilitatorService
{
    /**
     * @var ConfigService
     */
    private ConfigService $configService;

    /**
     * @var ApiService
     */
    private ApiService $apiService;

    /**
     * @var EventDateService|null
     */
    private ?EventDateService $eventDateService = null;

    /**
     * Constructor
     *
     * @param  ConfigService $configService  The config service
     * @param  ApiService    $apiService     The API service
     */
    public function __construct(
        ConfigService $configService,
        ApiService $apiService
    ) {
        $this->configService = $configService;
        $this->apiService = $apiService;
    }

    /**
     * Set the EventDateService (to avoid circular dependency)
     *
     * @param EventDateService $eventDateService
     */
    public function setEventDateService(EventDateService $eventDateService): void
    {
        $this->eventDateService = $eventDateService;
    }

    /**
     * Get url for SeminarDesk facilitator detail page
     * 
     * @param stdClass $facilitator - must contain id and name
     * @return string URL to facilitator detail page
     */
    public function getFacilitatorUrl($facilitator): string
    {
        $config = $this->configService->getConfiguration();
        return Route::_($config['facilitators_base'] . "&view=facilitator&id=" . $facilitator->id . '&name=' . FormatHelper::createSlug($facilitator->name));
    }

    /**
     * Preprocess fields of facilitator for use in views
     * 
     * @param stdClass $facilitator
     * @param array $facilitatorsIntExtInfo - Mapping of facilitator IDs to their external/internal status (info from private API)
     */
    public function prepareFacilitator(&$facilitator, $facilitatorsIntExtInfo = []): void
    {
        // Fullname (title + name), translations and URLs
        $facilitator->fullName = trim(implode(' ', [$facilitator->title, $facilitator->name]));
        $facilitator->about = FormatHelper::cleanupFormatting(TranslationHelper::translate($facilitator->about));
        $facilitator->detailsUrl = $this->getFacilitatorUrl($facilitator);

        // Set external/internal status based on labels (from private API)
        // Since public and private APIs have different facilitator IDs, use the picture file name to match facilitators. If no picture, the facilitator is considered external.
        $picture = basename($facilitator->pictureUrl);
        $facilitator->isExternal = ($picture && $facilitatorsIntExtInfo[$picture]) 
            ? ($facilitatorsIntExtInfo[$picture]['external'] ?? false) 
            : false;
        
        // Add css classes
        $classes = ['facilitator'];
        if (!$facilitator->pictureUrl) { $classes[] = 'no-image'; }
        if (!$facilitator->about)      { $classes[] = 'no-description'; }
        $facilitator->cssClasses = implode(' ', $classes);

        // Sort event dates by beginDate (not done by SeminarDesk, has been announced 2022)
        if (!empty($facilitator->eventDates)) {
            usort($facilitator->eventDates, function($a, $b) {
                return strcmp($a->beginDate, $b->beginDate);
            });
            
            // Separate past and future events. Past events only from 1 year ago, not cancelled and in reverse order
            $facilitator->pastEventDates = array_filter($facilitator->eventDates, function($eventDate) {
                return $eventDate->endDate < time() 
                    && $eventDate->status != 'canceled'
                    && (intval(date("Y")) - intval(date("Y", $eventDate->endDate)) <= 1);
            });
            $facilitator->pastEventDates = array_reverse($facilitator->pastEventDates);
            $facilitator->eventDates = array_filter($facilitator->eventDates, function($eventDate) {
                return $eventDate->endDate >= time();
            });
        } else {
            $facilitator->pastEventDates = [];
        }
    }

    /**
     * Load Facilitators from SeminarDesk API
     *
     * @return  array Facilitators (stdClass)
     * @since   3.0
     */
    public function loadFacilitators(): array
    {
        // Get facilitators from the public API
        $facilitators = $this->apiService->getFacilitators() ?? [];
        // Get internal/external flag for facilitators from the private API
        $facilitatorsIntExtInfo = $this->loadFacilitatorIntExtInfo();
        
        // Filter FACILITATORS_TO_HIDE
        $facilitators = array_filter($facilitators, function($facilitator) {
            return !in_array($facilitator->name, ConfigService::FACILITATORS_TO_HIDE);
        });

        // Get translations, labels, facilitators, categories etc. for each facilitator
        foreach ($facilitators as &$facilitator) {
            $this->prepareFacilitator($facilitator, $facilitatorsIntExtInfo);
        }

        // Order list by facilitator name
        usort($facilitators, function($a, $b) { 
            return strcmp($a->name, $b->name);
        });
        
        return $facilitators;
    }
    
    /**
     * Get facilitators internal/external info from the private API
     * @return array Facilitators internal/external info
     */
    public function loadFacilitatorIntExtInfo(): array
    {
        $facilitatorsPrivateInfo = $this->apiService->getFacilitatorsPrivateInfo() ?? [];
        $facilitatorsIntExtInfo = [];
        foreach ($facilitatorsPrivateInfo as $facilitator) {
            // Because public and private APIs have different facilitator IDs (!), only the portrait picture name can be used to match facilitators.
            $pictureFile = basename($facilitator->portraitPicture ?? '');
            if ($pictureFile) {
                $facilitatorsIntExtInfo[$pictureFile] = [
                    // id, email and picture: for debugging only
                    //'id' => $facilitator->id,
                    //'email' => $facilitator->email,
                    //'picture' => $pictureFile,
                    'external' => $this->hasLabel($facilitator, ConfigService::LABELS_FACILITATOR_EXTERNAL_ID)
                ];
            }
        }
        return $facilitatorsIntExtInfo;
    }

    /**
     * Load a single facilitator from SeminarDesk API
     *
     * @param string $facilitatorId
     * @return object|array Facilitator or empty array
     *
     * @since   3.0
     */
    public function loadFacilitator($facilitatorId)
    {
        $facilitator = $this->apiService->getFacilitator($facilitatorId);
        
        if ($facilitator) {
            // Load events of this facilitator
            $facilitator->eventDates = $this->apiService->getFacilitatorEventDates($facilitatorId) ?? [];

            // Get translations, labels, facilitators, categories etc. for each event date
            if ($this->eventDateService) {
                foreach ($facilitator->eventDates as &$eventDate) {
                    $this->eventDateService->prepareEventDate($eventDate);
                }
            }
            
            // Get values in current language, with fallback to first language in set
            $this->prepareFacilitator($facilitator);
        }
        else {
            $facilitator = null;
        }
        
        return $facilitator;
    }

    /**
     * Check if given label ID is assigned in the facilitators labels list.
     * 
     * @param stdClass $facilitator
     * @param integer $labelId
     * @return boolean
     */
    public static function hasLabel($facilitator, $labelId) {
        // Find label ID in the labels array, the element with the key "id" should match the given label ID.
        $labels = $facilitator->labels ?? [];
        foreach ($labels as $item) {
            if ($item->id == $labelId) {
                return true;
            }
        }
        return false;
    }
}