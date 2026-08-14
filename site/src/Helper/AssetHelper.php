<?php
/**
 * @package     Com_Seminardesk
 * @subpackage  Site
 * @author      Benno Flory <benno.flory@gmx.ch>
 * @copyright   2022-2026 Benno Flory
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Seminardesk\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Document\Document;
use Joomla\CMS\Uri\Uri;

/**
 * Asset Helper
 *
 * Registers the component's CSS / JS assets with cache-busting hashes.
 *
 * @since  2.0.0
 */
class AssetHelper
{
    /**
     * Component media assets, relative to the site root.
     */
    private const CSS_PATH = 'media/com_seminardesk/css/styles.css';
    private const JS_PATH  = 'media/com_seminardesk/js/seminardesk.js';

    /**
     * Register and use the component's CSS / JS via the WebAssetManager.
     *
     * Note: Using Uri::root() for absolute paths because Joomla's relative media
     * path resolution (HTMLHelper::mediaPath) does not resolve 'com_seminardesk/...'
     * URIs correctly. The cache-busting hash is the asset's file modification time
     * (single stat() call, OS-cached), so browsers reload assets exactly when the
     * CSS/JS actually changes.
     *
     * @param   Document  $document  The document to load the assets into.
     *
     * @return  void
     */
    public static function loadAssets(Document $document): void
    {
        $wa = $document->getWebAssetManager();
        $wa->registerAndUseStyle(
            'com_seminardesk.styles',
            Uri::root() . self::CSS_PATH . '?hash=' . self::getAssetHash(self::CSS_PATH)
        );
        $wa->registerAndUseScript(
            'com_seminardesk.scripts',
            Uri::root() . self::JS_PATH . '?hash=' . self::getAssetHash(self::JS_PATH),
            [],
            [],
            ['jquery']
        );
    }

    /**
     * Get a cache-busting hash for a media asset based on its modification time.
     *
     * Uses a single filemtime() stat() call (OS-cached) and memoizes the result
     * per request per file.
     *
     * @param   string  $relativePath  Asset path relative to the site root.
     *
     * @return  string  The modification-time hash, or a date-based fallback.
     */
    private static function getAssetHash(string $relativePath): string
    {
        static $hashes = [];

        if (!isset($hashes[$relativePath])) {
            $file = \JPATH_ROOT . '/' . $relativePath;
            $hashes[$relativePath] = is_file($file) ? (string) filemtime($file) : date('Ymd');
        }

        return $hashes[$relativePath];
    }
}
