<?php
/**
 * @version    CVS: 1.0.0
 * @package    Com_Seminardesk
 * @author     Benno Flory <benno.flory@gmx.ch>
 * @copyright  2022 Benno Flory
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */
// No direct access
defined('_JEXEC') or die;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\Component\Seminardesk\Site\Helper\FormatHelper;
use Joomla\Component\Seminardesk\Site\Service\ConfigService;

$app = Factory::getApplication();
$configService = new ConfigService($app);
$config = $configService->getConfiguration();
$document = $app->getDocument();

//-- Set document title
$title = str_replace(['&ndash;', '&amp;'], ['-', '&'], html_entity_decode($this->event->title));
$facilitators = implode(', ', array_column($this->event->facilitators, 'name'));
$document->setTitle($title . ' - ' . $facilitators);

//-- Order dates by startDate
usort($this->event->dates, function($a, $b) {
  return $a->beginDate - $b->beginDate;
});

// Add template path for sub-templates (facilitator)
$this->addTemplatePath(JPATH_COMPONENT . '/src/View/Facilitators/tmpl');
?>

<div class="sd-component sd-event-details" data-lang-key="<?= $this->event->langKey ?>">
  <div class="event-header">
    <?php if ($this->event->headerPictureUrl) : ?>
      <div class="header-picture"><img src="<?= $this->event->headerPictureUrl ?>"></div>
    <?php endif; ?>
    <h1 class="title">
      <?= FormatHelper::replaceMissingFontChars($this->event->title); ?>
    </h1>
    <?php if ($this->event->subtitle) : ?>
      <h2 class="subtitle"><?= $this->event->subtitle; ?></h2>
    <?php endif; ?>
    <div class="teaser"><?= $this->event->teaser; ?></div>
    <div class="dates-list"><?= implode(' / ', $this->event->datesList); ?></div>

    <?php if ($this->event->isBookable) : ?>
      <div class="registration">
        <a href="<?= $this->event->bookingUrl ?>" class="btn noicon wfpopup" rel="{handler: 'iframe'}"
          <?= ($this->event->isExternal)?(' title="' . Text::_("COM_SEMINARDESK_EVENT_REGISTRATION_A_M_FULL") . '"'):'' ?>>
          <?= Text::_("COM_SEMINARDESK_EVENT_REGISTRATION" . ($this->event->isExternal?"_A_M":"")); ?>
        </a>
      </div>
    <?php elseif ($this->event->onApplication) : ?>
      <!-- Special case: If label "Anmeldestatus/Auf Bewerbung" is set -->
      <div class="registration">
        <a class="btn" href="#bewerbung"><?= Text::_("COM_SEMINARDESK_EVENTS_STATUS_ON_APPLICATION") ?></a>
      </div>
    <?php endif; ?>
  </div>

  <div class="event-details">
    <?php if ($this->event->catLinks) : ?>
      <div class="categories">
        <?= $this->event->catLinks; ?>
      </div>
    <?php endif; ?>
    
    <div id="description" class="description<?= $this->event->descriptionTooLong?' async loading"':''; ?>">
      <?= $this->event->description ?>
    </div>

    <?php if (count($this->event->dates) > 0) : ?>
      <h2 id="registration"><?= Text::_("COM_SEMINARDESK_EVENT_DATES_REGISTRATION"); ?></h2>

      <?php if ($this->event->infoDatesPrices) : ?>
        <div class="info-dates-prices"><?= $this->event->infoDatesPrices; ?></div>
      <?php endif; ?>
      <?php if ($this->event->isSelfAssessment) : ?>
        <div class="info-prices-self-assessment">
          <?= HTMLHelper::_('content.prepare', '{loadposition event-price-infos, column}') ?>
        </div>
      <?php endif; ?>
      <?php if ($this->event->infoBoardLodging) : ?>
        <div class="info-board-lodging"><?= $this->event->infoBoardLodging; ?></div>
      <?php endif; ?>

      <div class="dates">
        <?php $showRegNotice = !$this->event->settings->registrationAvailable ?>
        <?php foreach($this->event->dates as $date) : ?>
          <div class="date<?= ($date->facilitatorLinks?' has-facilitators':'') . (time() > $date->endDate?' past-event':'') ?>">
            <div class="date-date"><?= $date->dateFormatted ?></div>
            <div class="date-title"><?= $date->title ?></div>
            <div class="date-prices">
              <?php if ($date->attendanceFees) : ?>
                <?php if ($this->event->settings->showAttendanceFees) : ?>
                  <div class="date-fees">
                    <?php foreach ($date->attendanceFees as $fee) : ?>
                      <?= ($fee->name?:Text::_("COM_SEMINARDESK_EVENT_ATT_FEE_LABEL")) . ': <strong>' . (($fee->isSelfAssessment)?(sprintf('%.2f', $fee->priceRangeFrom) . '&#8239;&ndash;&#8239;' . sprintf('%.2f', $fee->priceRangeTo)):sprintf('%.2f', $fee->priceDefault)) . '&#8239;€</strong><br>'; ?>
                      <?php if ($fee->priceEarlyBird && $fee->earlyBirdDate >= time() * 1000) : ?>
                        <strong>
                          <?= Text::_("COM_SEMINARDESK_EVENT_ATT_EARLYBIRD") . ' ' . date('d.m.Y', intval($fee->earlyBirdDate)/1000) . ': ' . sprintf('%.2f', $fee->priceEarlyBird) . '&#8239;€'; ?>
                        </strong><br>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  </div>
                  <div class="date-accom-meals">
                    <?php 
                    // Get accomodation and meals (board) price range: 
                    // Get min / max price, removing unnecessary decimals, including full board (always) and taxes
                    $lodgingPrices = (count($date->lodgingPrices) > 0 ? $date->lodgingPrices : [0]);
                    $boardPrices = (count($date->boardPrices) > 0 ? $date->boardPrices : [0]);
                    $min_price = min($lodgingPrices) + max($boardPrices);
                    $max_price = max($lodgingPrices) + max($boardPrices);
                    if ($max_price > 0) {
                      $min_price = str_replace(['.00', ',00', '.'], ['', '', ','], sprintf('%.2f', $min_price));
                      $max_price = str_replace(['.00', ',00', '.'], ['', '', ','], sprintf('%.2f', $max_price));
                      echo sprintf(Text::_("COM_SEMINARDESK_EVENT_ACC_MEALS_ADDITIONAL"), $min_price, $max_price);
                    }
                    ?>
                  </div>
                <?php else : ?>
                  <div class="date-fees"><?= Text::_("COM_SEMINARDESK_EVENT_NO_FEES_TO_DISPLAY"); ?></div>
                <?php endif; ?>
              <?php elseif ($this->event->settings->onlyBoardAndLodging && ($date->availableLodging || $date->availableBoard)) : ?>
                <div class="date-accom-meals">
                  <?php if ($date->isExternal) : ?>
                    <?= Text::_("COM_SEMINARDESK_EVENT_ACC_MEALS_AVAILABLE_BOOKING"); ?>
                  <?php else : ?>
                    <?= Text::_("COM_SEMINARDESK_EVENT_ACC_MEALS_AVAILABLE"); ?>
                  <?php endif; ?>
                </div>
              <?php else : ?>
                <div class="date-accom-meals"><?= Text::_("COM_SEMINARDESK_EVENT_ACC_MEALS_FREE"); ?></div>
              <?php endif; ?>
            </div>
            
            <div class="date-facilitators"><?= $date->facilitatorLinks?$date->facilitatorLinks:''; ?></div>
            <div class="date-status"><?= $date->statusLabel ?></div>
            
            <div class="date-registration">
            <?php if ($date->isBookable) : ?>
              <a href="<?= $date->bookingUrl ?>" class="btn noicon wfpopup" rel="{handler: 'iframe'}"
                <?= ($this->event->isExternal)?(' title="' . Text::_("COM_SEMINARDESK_EVENT_REGISTRATION_A_M_FULL") . '"'):'' ?>>
                <?= Text::_("COM_SEMINARDESK_EVENT_REGISTRATION" . ($date->isExternal?"_A_M":"")); ?>
              </a>
            <?php elseif ($this->event->onApplication) : ?>
              <!-- Special case: If label "Anmeldestatus/Auf Bewerbung" is set -->
              <a class="btn" href="#bewerbung"><?= Text::_("COM_SEMINARDESK_EVENTS_STATUS_ON_APPLICATION") ?></a>
            <?php else : ?>
              <span class="notice">*) <?php $showRegNotice = true; ?></span>
            <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
        <?php if ($showRegNotice) : ?>
          <div class="no-registration">
            *) <?= Text::_("COM_SEMINARDESK_EVENT_NO_REGISTRATION"); ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if ($this->event->infoMisc) : ?>
      <div class="info-misc"><?= $this->event->infoMisc; ?></div>
    <?php endif; ?>

    <?php if (count($this->event->facilitators) > 0) : ?>
      <div id="facilitators" class="sd-facilitators">
        <h2><?= Text::_("COM_SEMINARDESK_TITLE_FACILITATOR"); ?></h2>
        <?php foreach($this->event->facilitators as $facilitator) {
          $facilitator->headings = "h3";
          $this->facilitator = $facilitator;
          echo $this->loadTemplate('facilitator');
        } ?>
      </div>
    <?php endif; ?>
  </div>
  
  <aside class="event-infos-container" id="event-infos">
    <div class="row">
      <?= HTMLHelper::_('content.prepare', '{loadposition event-infos, column}') ?>
    </div>
  </aside>

  <script type="application/ld+json">
    <?= json_encode($this->event->structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?>
  </script>

</div>

