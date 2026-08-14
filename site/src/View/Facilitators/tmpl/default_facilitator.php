<?php
/**
 * @package     SeminarDesk
 * @subpackage  com_seminardesk
 *
 * @copyright   Copyright (C) 2022 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */
 
// No direct access to this file
defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Language\Text;
use Joomla\Component\Seminardesk\Site\Helper\FormatHelper;

$heading = $this->facilitator->heading ?? 'h2';
?>

<div class="<?= $this->facilitator->cssClasses ?>" itemscope itemtype="https://schema.org/Person">

  <a class="facilitator-picture noicon wfpopup" href="<?= $this->facilitator->detailsUrl ?>" itemprop="url" rel="{handler: 'iframe'}">
    <?= ($this->facilitator->pictureUrl)?'<img src="' . $this->facilitator->pictureUrl . '" alt="' . $this->facilitator->fullName . '">':'' ?>
  </a>
  <div class="facilitator-description">
    <<?= $heading ?> class="facilitator-name">
      <a href="<?= $this->facilitator->detailsUrl ?>" itemprop="url" class="wfpopup" rel="{handler: 'iframe'}">
        <?= $this->facilitator->title ?> <span itemprop="givenName"><?= $this->facilitator->firstName ?></span> <span itemprop="familyName"><?= $this->facilitator->lastName ?></span>
      </a>
    </<?= $heading ?>>
    <div class="facilitator-about">
      <?= FormatHelper::cleanupHtml($this->facilitator->about, '<p><br>') ?>
    </div>
  </div>
  <div class="facilitator-details">
    <a href="<?= $this->facilitator->detailsUrl ?>" itemprop="url" class="readmore noicon wfpopup" rel="{handler: 'iframe'}">
      <i class="fas fa-chevron-right"></i>
      <?= Text::_("COM_SEMINARDESK_FACILITATORS_READMORE"); ?>
    </a>
  </div>

</div>