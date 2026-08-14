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

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\Component\Seminardesk\Site\Helper\FormatHelper;
use Joomla\Component\Seminardesk\Site\Helper\TranslationHelper;

/**
 * Event Date Service for SeminarDesk
 * Handles all logic related to event dates, such as loading from the API and formatting.
 * @since  2.0.0
 */
class EventDateService
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
     * Load and filter EventDates
     *
     * @param $filters array - e.g. ['labels' => '1,2,3'] or ['labels' => [1],[2],[3]] or ['labels_exceptions' => 1,2,3'] or ['limit' => 5] or ['show_canceled' => true]
     * @return  array Event Dates (stdClass)
     * @since   3.0
     */
    public function loadEventDates($filters = []): array
    {
        $eventDates = $this->apiService->getEventDates() ?? [];

        // Get translations, labels, facilitators, categories etc. for each event date
        foreach ($eventDates as &$eventDate) {
            $this->prepareEventDate($eventDate);
        }
        
        // Apply filters
        $this->filterEvents($eventDates, $filters);

        return $eventDates;
    }

    /**
     * Get url for SeminarDesk event detail page
     * 
     * @param stdClass $event - must contain eventId and titleSlug
     * @return string URL to event detail page
     */
    public function getEventUrl($event): string
    {
        $config = $this->configService->getConfiguration();
        return Route::_($config['eventlist_base'] . "&view=event&eventId=" . $event->eventId . '&slug=' . $event->titleSlug) ?? '';
    }

    /**
     * Get url for SeminarDesk booking
     * - Preselection of a specific date: ?eventDateId=<id>
     * - For preselection of multiple dates of an event (e.g. regular annual group), 
     *   the URL would be ?eventDateId=<id1>&eventDateId=<id2>&eventDateId=<id3>
     * 
     * @param integer $eventId
     * @param string $slug
     * @param integer|array $eventDateIds - one or more DateId to preselect for booking
     * @return string URL to embedded event booking form
     */
    public function getBookingUrl($eventId, $slug, $eventDateIds = []): string
    {
        $config = $this->configService->getConfiguration();
        $url = $config['booking_base'] . $eventId . '/' . $slug . '/embed';
        if (is_array($eventDateIds) && count($eventDateIds) > 0) {
            $url .= '?eventDateId=' . implode('&eventDateId=', $eventDateIds);
        }
        elseif (is_numeric($eventDateIds)) {
            $url .= '?eventDateId=' . $eventDateIds;
        }
        return $url;
    }

    /**
     * Get eventDate languages based on labels
     * 
     * @param stdClass $eventDate - must contain labels array with id field
     * @return array - array of language codes ('en', 'es', 'de', ...)
     */
    public function getEventLanguages($eventDate)
    {
      $languages = [];
      // English?
      if (FormatHelper::hasLabel($eventDate, ConfigService::LABELS_ENGLISH_ID) || FormatHelper::hasLabel($eventDate, ConfigService::LABELS_ENGLISH_POSSIBLE_ID)) {
        $languages[] = 'en';
      }
      // Spanish?
      if (FormatHelper::hasLabel($eventDate, ConfigService::LABELS_SPANISH_ID)) {
        $languages[] = 'es';
      }
      // German? If not only English or Spanish, we assume a German speaking event, 
      // even if no German label is set, because many events do not have language labels at all.
      if (
        (!FormatHelper::hasLabel($eventDate, ConfigService::LABELS_ENGLISH_ID) && 
        !FormatHelper::hasLabel($eventDate, ConfigService::LABELS_SPANISH_ID)) || 
        (FormatHelper::hasLabel($eventDate, ConfigService::LABELS_GERMAN_ID) || 
        FormatHelper::hasLabel($eventDate, ConfigService::LABELS_GERMAN_POSSIBLE_ID))
      ) {
        $languages[] = 'de';
      }
      return $languages;
    }

    /**
     * Get status label in current language, or untranslated, but readable, if no 
     * translation has been found (e.g. fully_booked => Fully Booked), 
     * or empty, if no status is set. 
     * 
     * @param stdClass $eventDate - containing status field
     * @return string - Status label translated
     */
    public function getStatusLabel($eventDate)
    {
      $label = '';
      if ($eventDate->status) {
        // Set status label dynamically
        $key = "COM_SEMINARDESK_EVENTS_STATUS_" . strtoupper($eventDate->status);

        // Special case: If detailpageAvailable is set to false
        // Note: the attribute detailpageAvailable only exists in the 
        // API response from https://zegg.seminardesk.de/api/eventDates/
        // but not in the API response for a single event oder date. 
        if (property_exists($eventDate, 'detailpageAvailable') && !$eventDate->detailpageAvailable) {
          $key = "COM_SEMINARDESK_EVENTS_STATUS_DETAILS_LATER";
        }

        // Special case: If label "Ohne Anmeldung" is set
        if (FormatHelper::hasLabel($eventDate, ConfigService::LABELS_WITHOUT_REGISTRATION_ID)) {
          $key = "COM_SEMINARDESK_EVENTS_STATUS_WITHOUT_REGISTRATION";
        }

        // Special case: If label "Plätze frei trotz Warteliste" is set, 
        // then don't display "Warteliste" but "Plätze frei"
        if (FormatHelper::hasLabel($eventDate, ConfigService::LABELS_ON_APPLICATION_WITH_REGISTRATION_ID)) {
          $key = "COM_SEMINARDESK_EVENTS_STATUS_AVAILABLE";
        }

        // Label for past events
        if ($eventDate->isPastEvent) {
          $key = "COM_SEMINARDESK_EVENTS_STATUS_PAST_EVENT";
        }

        // Translate status. If no translation found, use status as label
        $label = Text::_($key);
        if ($label == $key) {
          $label = ucwords($eventDate->status, "_");
        }
      }
      return $label;
    }

    public function getBookingLabel($eventDate)
    {
      if ($eventDate->isBookable) {
        if (FormatHelper::hasLabel($eventDate, ConfigService::LABELS_ON_APPLICATION_WITH_REGISTRATION_ID)) {
          return Text::_("COM_SEMINARDESK_EVENT_...");
        } else {
          return Text::_("COM_SEMINARDESK_EVENT_REGISTRATION" . ($eventDate->isExternal?"_A_M":""));
        }
      }
      return "*)";
    }

    /**
     * Check if given eventDate matches the filters.
     * Used in site\views\events\tmpl\default_event.php to preselect visibility in frontend.
     * 
     * @param stdClass $eventDate
     * @param array $filters - containing keys 'date', 'cat', 'org', 'term' and/or 'lang'
     * @return boolean - true if eventDate matches all filters, false if not.
     */
    public static function matchingFilters($eventDate, $filters): bool {
      //-- If term is set: Match them
      if ($filters['term']) {
        $terms = explode(' ', strtolower(trim(str_replace('  ', ' ', $filters['term']))));
        $eventSearchableText = strtolower(
          $eventDate->title . ' ' . 
          implode($eventDate->facilitators) . ' ' . 
          implode($eventDate->categories));
        $terms_matching = array_filter($terms, function ($term) use ($eventSearchableText) {
          return strpos($eventSearchableText, $term) !== false;
        });
      }

      //-- Matching current filters?
      return
        // Check date filter
        (
          !$filters['date'] || 
          $filters['date'] <= date('Y-m-d', $eventDate->endDate)
        )
        &&
        // Check category filter
        (
          $filters['cat'] == 0 ||
          in_array($filters['cat'], array_keys($eventDate->categories))
        )
        &&
        // Check organisator filter
        (
          !in_array($filters['org'], ['zegg', 'external']) ||
          ($filters['org'] === 'zegg' && !$eventDate->isExternal) ||
          ($filters['org'] === 'external' && $eventDate->isExternal)
        )
        &&
        // Check search term filter (full text search)
        (
          !$filters['term'] || count($terms_matching) == count($terms)
        )
        &&
        // Check language filter
        (
          !$filters['lang'] || array_search(strtolower($filters['lang']), $eventDate->languages) !== false
        );
    }

    /**
     * Apply filters to event list
     * @param array<stdClass> $eventDates
     * @param array $filters
     */
    public function filterEvents(&$eventDates, $filters = [])
    {
      //-- Apply filters
      // convert labels to array and trim each label value
      $filters['labels'] = isset($filters['labels'])?array_map('trim', explode(',', $filters['labels'])):[];
      $filters['label_exceptions'] = isset($filters['label_exceptions'])?array_map('trim', explode(',', $filters['label_exceptions'])):[];

      $eventDates = array_filter($eventDates, function ($eventDate) use ($filters) {
        //-- Show canceled events?
        $show_canceled = $filters['show_canceled'] ?? false;
        if (!$show_canceled && $eventDate->status == "canceled") {
          return false;
        }
        
        //-- Show ongoing events (that have already started >= 1 day ago)
        $hide_ongoing = $filters['hide_ongoing'] ?? false;
        if ($hide_ongoing && $eventDate->beginDate < strtotime('tomorrow')) {
          return false;
        }

        //-- Filter by labels (IDs or labels)
        if ($filters['labels']) {
          // Allow both IDs or label text as filter
          $eventLabels = (is_numeric($filters['labels'][0]))?array_keys($eventDate->labels):$eventDate->labels;
          // Compare labels with filter. If some are matching, return event
          $labelsMatching = count(array_intersect($eventLabels, $filters['labels'])) > 0;
        }
        else {
          $labelsMatching = true;
        }

        //-- Filter by label exceptions (IDs or labels)
        if ($filters['label_exceptions'] && $eventDate->labels) {
          // Allow both IDs or label text as filter
          $eventLabels = (is_numeric($filters['label_exceptions'][0]))?array_keys($eventDate->labels):$eventDate->labels;
          // Compare labels with filter. If some are matching, return event
          $labelExceptionsMatching = count(array_intersect($eventLabels, $filters['label_exceptions'])) > 0;
        }
        else {
          $labelExceptionsMatching = false;
        }

        if (isset($filters['term']) && $filters['term']) {
          $terms = explode(' ', $filters['term']);
          $termsMatching = false;
          foreach ($terms as $term) {
            $termsMatching |= (strpos($eventDate->title . ' ' . $eventDate->facilitatorsList . ' ' . $eventDate->labelsList, $term) !== false);
          }
        }
        else {
          $termsMatching = true;
        }

        //-- Filter by language
        $filterLang = $filters['lang'] ?? '';
        if ($filterLang) {
          $langMatching = in_array($filterLang, $eventDate->languages);
        }
        else {
          $langMatching = true;
        }

        return $labelsMatching && !$labelExceptionsMatching && $termsMatching && $langMatching;
      });

      //-- Limit
      if (isset($filters['limit']) && $filters['limit'] > 0) {
        $eventDates = array_slice($eventDates, 0, $filters['limit']);
      }
    }

    /**
     * Preprocess / prepare fields of event date for use in views
     * 
     * @param stdClass $eventDate
     */
    public function prepareEventDate(&$eventDate)
    {
      // Map and translate fields - some have changed the structure (new sub object "eventInfo"), so include a fallback
      $eventDate->eventId = $eventDate->eventId??$eventDate->eventInfo->id;
      $eventDate->title = TranslationHelper::translate($eventDate->title, true);
      $eventDate->titleSlug = TranslationHelper::translate($eventDate->titleSlug??$eventDate->eventInfo->titleSlug);
      $eventDate->subtitle = TranslationHelper::translate($eventDate->subtitle??$eventDate->eventInfo->subtitle);
      $eventDate->eventDateTitle = TranslationHelper::translate($eventDate->eventDateTitle, true);
      $eventDate->teaser = TranslationHelper::translate($eventDate->teaser??$eventDate->eventInfo->teaser);
      $eventDate->teaserPictureUrl = TranslationHelper::translate($eventDate->teaserPictureUrl??$eventDate->eventInfo->teaserPictureUrl);
      $eventDate->detailpageAvailable = $eventDate->detailpageAvailable??$eventDate->eventInfo->detailpageAvailable??true;
      $eventDate->description = TranslationHelper::translate($eventDate->eventInfo->description); // See below: Adding to html response slows down page loading
      // Set 2nd title / subtitle to eventDateTitle, subtitile or teaser, if different from title
      $eventDate->mergedSubtitle = ($eventDate->title != $eventDate->eventDateTitle)?$eventDate->eventDateTitle:'';
      $eventDate->mergedSubtitle .= (!$eventDate->mergedSubtitle && $eventDate->title != $eventDate->subtitle)?$eventDate->subtitle:'';

      // Additional fields: Link to event website
      $eventDate->website = '';
      foreach ($eventDate->additionalFields as $item) {
        if ($item->field->name == 'Event-Website') {
          $eventDate->website = $item->value;
          break;
        }
      }

      // Add facilitators list
      $eventDate->facilitators = array_combine(
        array_column($eventDate->facilitators, 'id'), 
        array_column($eventDate->facilitators, 'name')
      );
      $eventDate->facilitatorsList = htmlentities(implode(', ', $eventDate->facilitators), ENT_QUOTES);
      // Add labels list
      $eventDate->labels = array_combine(
        array_column($eventDate->labels, 'id'), 
        array_column($eventDate->labels, 'name')
      );
      $eventDate->labelsList = htmlspecialchars(implode(', ', $eventDate->labels), ENT_QUOTES);
      // Get categories: Labels except LABELS_TO_HIDE
      $eventDate->categories = array_filter($eventDate->labels, function($key){
        return !in_array($key, ConfigService::LABELS_TO_HIDE);
      }, ARRAY_FILTER_USE_KEY);
      $eventDate->categoriesList = htmlspecialchars(implode(', ', $eventDate->categories), ENT_QUOTES);
      // Add languages
      $eventDate->languages = $this->getEventLanguages($eventDate);
      $eventDate->languageList = implode(',', $eventDate->languages);
      // Add searchable text for filters
      $eventDate->searchableText = htmlspecialchars(strip_tags(implode(' ', [
          $eventDate->title,
          $eventDate->subtitle,
          $eventDate->eventDateTitle,
          $eventDate->teaser,
          $eventDate->description, // Slows down page loading (+150kB / +0.5s)
          $eventDate->facilitatorsList,
          //implode(' ', array_keys($eventDate->categories)),
          //implode(' ', $eventDate->categories),
          $eventDate->labelsList,
      ])));
      
      // Fix event date / time
      $eventDate->beginDate = $eventDate->beginDate / 1000;
      $eventDate->endDate = $eventDate->endDate / 1000;

      //-- Set special event flags (festivals, external organisers)
      $eventDate->isFeatured = FormatHelper::hasLabel($eventDate, ConfigService::LABELS_FESTIVALS_ID);
      $eventDate->isExternal = FormatHelper::hasLabel($eventDate, ConfigService::LABELS_EXTERNAL_ID);
      $eventDate->onApplication = FormatHelper::hasLabel($eventDate, ConfigService::LABELS_ON_APPLICATION_ID);
      $eventDate->isPastEvent = $eventDate->endDate < time();
      $eventDate->isCanceled = $eventDate->status === 'canceled';
      // Add status label
      $eventDate->statusLabel = $this->getStatusLabel($eventDate);

      //-- Set event classes
      $classes = ['registration-available'];
      if ($eventDate->isFeatured)           { $classes[] = 'featured';         }
      if (!$eventDate->detailpageAvailable) { $classes[] = 'no-detail-page';  }
  //    if ($eventDate->categoriesList)     { $classes[] = 'has-categories';   } // Hide categories in List for now
      if ($eventDate->facilitatorsList)     { $classes[] = 'has-facilitators'; }
      if ($eventDate->website)              { $classes[] = 'has-event-website'; }
      if ($eventDate->isExternal)           { $classes[] = 'external-event';   } 
      if (!$eventDate->isExternal)          { $classes[] = 'zegg-event';       }
      if ($eventDate->isPastEvent)          { $classes[] = 'past-event';       }
      if ($eventDate->isCanceled)           { $classes[] = 'is-canceled';      }
      $eventDate->cssClasses = implode(' ', $classes);

      //-- Format date
      $eventDate->dateFormatted = FormatHelper::getDateFormatted($eventDate->beginDate, $eventDate->endDate);

      //-- URLs
      $eventDate->detailsUrl = ($eventDate->detailpageAvailable)?$this->getEventUrl($eventDate):'';
      $eventDate->bookingUrl = $this->getBookingUrl($eventDate->eventId, $eventDate->titleSlug, $eventDate->id);
    }
}