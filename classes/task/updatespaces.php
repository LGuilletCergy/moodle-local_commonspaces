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
 * Initially developped for :
 * Université de Cergy-Pontoise
 * 33, boulevard du Port
 * 95011 Cergy-Pontoise cedex
 * FRANCE
 *
 * Creates common courses (one for each faculty).
 *
 * @package   local_commonspaces
 * @copyright 2018 Brice Errandonea <brice.errandonea@u-cergy.fr>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * File : classes/task/updatespaces.php
 * Cron task creating and/or updating the common spaces.
 */
namespace local_commonspaces\task;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->dirroot/local/commonspaces/lib.php");

class updatespaces extends \core\task\scheduled_task {
    
    public function get_name() {

	$name = get_string('updatespaces', 'local_commonspaces');
        return $name;
    }

    public function execute() {

        local_commonspaces_createspaces();
    }
}
