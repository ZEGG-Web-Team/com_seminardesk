<?php
/**
 * @package     Com_Seminardesk
 * @author      Benno Flory <benno.flory@gmx.ch>
 * @copyright   2022 Benno Flory
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Seminardesk\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;

/**
 * Display Controller for com_seminardesk admin
 *
 * @since  2.0.0
 */
class DisplayController extends BaseController
{
    /**
     * The default view.
     *
     * @var    string
     * @since  2.0.0
     */
    protected $default_view = 'seminardesk';

    /**
     * Method to display a view.
     *
     * @param   boolean  $cachable   If true, the view output will be cached
     * @param   array    $urlparams  An array of safe URL parameters
     *
     * @return  static  This object to support chaining.
     *
     * @since   2.0.0
     */
    public function display($cachable = false, $urlparams = []): static
    {
        // This component has no backend UI; its only settings are the Options.
        // Redirect the admin entry point there so the (nonexistent) "seminardesk" view is never requested.
        $this->setRedirect(Route::_('index.php?option=com_config&view=component&component=com_seminardesk', false));

        return $this;
    }
}
