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

/**
 * Text Formatter Helper
 *
 * Static utility functions for text formatting, HTML cleanup, and slug creation.
 *
 * @since  2.0.0
 */
class FormatHelper
{
    /**
     * Replace multiple (>= 3) underscores and dashes by <hr> tag.
     *
     * Only standalone runs (bounded by whitespace or string/line boundaries) are
     * replaced, so dash sequences inside words or URLs (e.g. a "a---b" slug) are
     * left intact.
     *
     * @param   string  $text  The text to process
     *
     * @return  string  Processed text
     */
    public static function replaceHR(string $text): string
    {
        return preg_replace('/(?<![^\s])[_-]{3,}(?![^\s])/', '<hr>', $text);
    }

    /**
     * Replace characters that are missing in custom font, like superscript numbers
     *
     * @param   string  $text  The text to process
     *
     * @return  string  Processed text
     */
    public static function replaceMissingFontChars(string $text): string
    {
        $replacements = [
            '⁰' => '<sup>0</sup>',
            '¹' => '<sup>1</sup>',
            '²' => '<sup>2</sup>',
            '³' => '<sup>3</sup>',
            '⁴' => '<sup>4</sup>',
            '⁵' => '<sup>5</sup>',
            '⁶' => '<sup>6</sup>',
            '⁷' => '<sup>7</sup>',
            '⁸' => '<sup>8</sup>',
            '⁹' => '<sup>9</sup>',
            'ⁿ' => '<sup>n</sup>',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }

    /**
     * Remove attributes from tags and strip some tags, if desired
     * and replace multiple (>= 3) underscores and dashes by <hr> tag.
     *
     * @param   string       $text                The text to process
     * @param   string|bool  $stripTagsExceptions Tags to keep or false = do not strip tags
     * @param   bool         $stripAttrs          Remove all attributes within tags?
     *
     * @return  string  Processed text
     */
    public static function cleanupHtml(
        string $text,
        string|bool $stripTagsExceptions = '<h1><h2><h3><h4><p><br><b><hr><strong>',
        bool $stripAttrs = true
    ): string {
        if ($stripAttrs) {
            $text = preg_replace("/<([a-z][a-z0-9]*)[^>]*?(\/?)>/si", '<$1$2>', $text);
        }
        $text = strip_tags($text, $stripTagsExceptions);
        $text = str_replace('&nbsp;', ' ', $text); // Current font does not support &nbsp;

        return self::replaceHR($text);
    }

    /**
     * Remove all style attributes (but keep them on images, tables, table headers and cells)
     * @param string $text
     * @return string - text without style attributes
     */
    public static function cleanupStyles($text) {
        // The negative lookahead (?!(?:img|table|th|td)\b) skips those tags.
        return preg_replace(
            '/(<(?!(?:img|table|th|td)\b)[a-z][a-z0-9]*\b[^>]*?) style=".*?"/i',
            '$1',
            $text
        );
    }

    /**
     * Remove all given tags from a text
     *
     * @param   string  $text     The text to process
     * @param   array   $taglist  List of tags to remove
     *
     * @return  string  Text without specified tags
     */
    public static function cleanupTags(string $text, array $taglist): string
    {
        foreach ($taglist as $tag) {
            $text = preg_replace(["/<$tag.*?>/im", "/<\/$tag>/im"], "", $text);
        }

        return $text;
    }

    /**
     * Remove all font tags and style attributes
     * and replace multiple (>= 3) underscores and dashes by <hr> tag.
     *
     * @param   string  $text  The text to process
     *
     * @return  string  Processed text
     */
    public static function cleanupFormatting(string $text): string
    {
        // Replace nbsp because our font does not support it.
        $text = self::replaceHR(str_replace(['&nbsp;'], [' '], $text));
        $text = self::cleanupStyles($text);
        $text = self::cleanupTags($text, ["font", "pre"]);

        return $text;
    }

    /**
     * Create a slug from a string.
     *
     * @param   string  $string  The string to transform
     *
     * @return  string  The resulting slug
     *
     * @see https://stackoverflow.com/questions/2955251/php-function-to-make-slug-url-string
     */
    public static function createSlug(string $string): string
    {
        $table = [
            'Š'=>'S', 'š'=>'s', 'Đ'=>'Dj', 'đ'=>'dj', 'Ž'=>'Z', 'ž'=>'z', 'Č'=>'C', 'č'=>'c', 'Ć'=>'C', 'ć'=>'c',
            'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'AE', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E',
            'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O',
            'Õ'=>'O', 'Ö'=>'OE', 'Ø'=>'O', 'Ù'=>'U', 'Ú'=>'U', 'Û'=>'U', 'Ü'=>'UE', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss',
            'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'ae', 'å'=>'a', 'æ'=>'a', 'ç'=>'c', 'è'=>'e', 'é'=>'e',
            'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o',
            'ô'=>'o', 'õ'=>'o', 'ö'=>'oe', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ü'=>'ue', 'ý'=>'y', 'þ'=>'b',
            'ÿ'=>'y', 'Ŕ'=>'R', 'ŕ'=>'r'
        ];

        // Replace special chars etc.
        $slug = strtolower(strtr($string, $table));
        // Replace remaining non characters
        $slug = preg_replace('/[^A-Za-z0-9-]+/', '-', $slug);
        // Remove duplicate divider
        $slug = preg_replace('~-+~', '-', $slug);

        return $slug;
    }

    /**
     * Format start - end date interval, omitting same months / years
     *
     * @param   int     $beginDate  Timestamp in seconds
     * @param   int     $endDate    Timestamp in seconds
     * @param   string  $separator  Separator between dates
     * @param   bool    $withYear   Always add year?
     *
     * @return  string  Formatted date range
     */
    public static function getDateFormatted(
        int $beginDate,
        int $endDate,
        string $separator = ' - ',
        bool $withYear = false
    ): string {
        $dateParts = [];
        $sameYear = date('Y', $beginDate) == date('Y', $endDate);
        $withYear = $withYear || (date('Y', $beginDate) != date('Y')); // If event is in future / past year, add year

        // Set formatted start date if different from end date
        if (date('d.m.Y', $beginDate) !== date('d.m.Y', $endDate)) {
            if (date('m.Y', $beginDate) == date('m.Y', $endDate)) {
                $dateParts[] = date('d.', $beginDate);
            } elseif ($sameYear) {
                $dateParts[] = date('d.m.', $beginDate);
            } else {
                $dateParts[] = date('d.m.Y', $beginDate);
            }
        }

        // Add end date (with or without year)
        $dateParts[] = date(($withYear || !$sameYear) ? 'd.m.Y' : 'd.m.', $endDate);

        // Join and return
        $separator = '<span class="date-separator">' . $separator . '</span>';

        return (
            '<span class="sd-event-begindate">'
            . implode(
                '</span>' . $separator . '<span class="sd-event-enddate">',
                $dateParts
            )
            . '</span>'
        );
    }
    
    /**
     * Check if given label ID is assigned in the eventDates labels list.
     * 
     * @param stdClass $event
     * @param integer $label
     * @return boolean
     */
    public static function hasLabel($event, $label) {
        return array_key_exists($label, $event->labels);
    }
    
    /**
     * Render categories as links (currently not in use)
     * 
     * @param array $categories
     * @return array - category links
     */
    /*
    public static function getCategoryLinks($categories, $link_url = '.')
    {
        array_walk($categories, function(&$category, $key, $link_url) {
            $category = '<a href="' . $link_url . '?category=' . $key . '">' . htmlentities($category, ENT_QUOTES) . '</a>';
        }, $link_url);
        return $categories;
    }
    */
    
}
