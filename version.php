<?php
/**
 * My Progress block version information.
 *
 * @package    block_my_progress
 * @copyright  2025 AI Grader <support@lmshostingservices.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'block_my_progress';
$plugin->version   = 2026042300228;
$plugin->requires = 2022112800; // Moodle 4.1+
$plugin->maturity = MATURITY_STABLE;
$plugin->release   = '2.2.8'; // FIX -- Added settings.php to register blocksettingmy_progress section, preventing sectionerror on admin/settings.php. No DB schema changes.
$plugin->supported = [401, 500]; // Moodle 4.1 to 5.0
