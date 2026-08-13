<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * My Progress block version information.
 *
 * @package    block_my_progress
 * @copyright  2025 AI Grader <support@lmshostingservices.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'block_my_progress';
$plugin->version   = 2026042300;
$plugin->requires = 2022112800; // Moodle 4.1+
$plugin->maturity = MATURITY_STABLE;
$plugin->release   = '2.2.9'; // FIX -- Added settings.php to register blocksettingmy_progress section, preventing sectionerror on admin/settings.php. No DB schema changes.
$plugin->supported = [401, 500]; // Moodle 4.1 to 5.0
