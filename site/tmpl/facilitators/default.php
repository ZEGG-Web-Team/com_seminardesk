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

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

$app = Factory::getApplication();

// Add template path for sub-templates
$this->addTemplatePath(JPATH_COMPONENT . '/src/View/Facilitators/tmpl');
?>

<div class="sd-component sd-facilitators<?php echo ($this->pageclass_sfx)?' sd-facilitators'.$this->pageclass_sfx:''; ?>">
  
	<?php // if ($app->input->get('show_page_heading')) : // to do: buggy ?>
		<div class="page-header">
			<h1><?= $this->escape($this->title) ?></h1>
		</div>
	<?php // endif; ?>
  
  <div class="sd-filter container">
    <form class="sd-filter-form">
      <input type="text" name="term" id="sd-filter-search-term" value="" placeholder="<?= Text::_("COM_SEMINARDESK_FILTER_FACILITATOR_PLACEHOLDER");?>">
      <!--<button class="btn btn-secondary" type="submit"><?= Text::_("COM_SEMINARDESK_FILTER_SUBMIT");?></button>-->
    </form>
  </div>
  
  <div class="sd-facilitatorlist container">
    <div class="row facilitators-zegg">
      <h2 class="col-lg-12"><?= Text::_("COM_SEMINARDESK_FACILITATORS_ZEGG");?></h2>
      <?php 
        foreach($this->facilitators->getItems() as $facilitator) {
          if (!$facilitator->isExternal) {
            $facilitator->cssClasses  .= ' col-lg-6';
            $facilitator->heading = 'h3';
            $this->facilitator = & $facilitator;
            echo $this->loadTemplate('facilitator');
          }
        }
      ?>
    </div>
    <div class="row facilitators-external">
      <h2 class="col-lg-12"><?= Text::_("COM_SEMINARDESK_FACILITATORS_EXTERNAL");?></h2>
      <?php 
        foreach($this->facilitators->getItems() as $facilitator) {
          if ($facilitator->isExternal) {
            $facilitator->cssClasses  .= ' col-lg-6';
            $facilitator->heading = 'h3';
            $this->facilitator = & $facilitator;
            echo $this->loadTemplate('facilitator');
          }
        }
      ?>
    </div>
    <div class="no-facilitators-found no-facilitators-zegg<?= ($this->facilitators->getItems())?' hidden':'' ?>">
      <p><?= Text::_("COM_SEMINARDESK_FACILITATORS_NO_FACILITATORS_FOUND");?></p>
    </div>
  </div>

</div>
