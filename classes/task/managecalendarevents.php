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
 * Add events to the calendar based on an XML file.
 *
 * @package   local_calendarevents
 * @copyright 2018 Laurent Guillet <laurent.guillet@u-cergy.fr>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * File : task/managecalendarevents.php
 * Main file
 */

namespace local_calendarevents\task;

defined('MOODLE_INTERNAL') || die();

class managecalendarevents extends \core\task\scheduled_task {

    public function get_name() {

        return get_string('managecalendarevents', 'local_calendarevents');
    }

    public function execute() {

        global $DB, $CFG;

        require_once($CFG->dirroot .'/course/lib.php');
        require_once($CFG->libdir .'/filelib.php');
        require_once($CFG->libdir .'/accesslib.php');

        $now = time();

        $xmldoc = new \DOMDocument();
        $xmldoc->load('/home/referentiel/sefiap_edt_enseignants_droit.xml');

        $xpathvar = new \Domxpath($xmldoc);

        $querytext = '//Etape/ELP/Enseignant/EDT';
        $query = $xpathvar->query($querytext);

        foreach ($query as $edt) {

            $elp = $edt->parentNode->parentNode;
            $etape = $elp->parentNode;
            $modulecode = $elp->getAttribute('ModuleCode');

            if (substr($modulecode, 0, 1) == 1) {

                // On ignore les cours de Droit.

                continue;
            } else {

                $roomcode = $edt->getAttribute('RoomCode');
                $starthour = explode(':', $edt->getAttribute('EventStartTime')); //  16:30:00
                $endhour = explode(':', $edt->getAttribute('EventEndTime'));     //  18:30:00
                $date = explode('-', $edt->getAttribute('EventDate'));           // 24-01-2017
                $timestart = mktime($starthour[0], $starthour[1], $starthour[2], $date[1], $date[0], $date[2]);
                $timeend = mktime($endhour[0], $endhour[1], $endhour[2], $date[1], $date[0], $date[2]);

                $courseidnumber = $CFG->yearprefix."-".$etape->getAttribute('flag_etp')."-".$modulecode;

                if ($DB->record_exists('course', array('idnumber' => $courseidnumber))) {

                    $course = $DB->get_record('course', array('idnumber' => $courseidnumber));
                    $description = '<div class="no-overflow"><p>'.$roomcode.'</p></div>';
                    $timeduration = $timeend - $timestart;
                    $localcoursetime = 'local_coursetime';

                    if ($DB->record_exists('event', array(`description` => $description, `courseid` => $course->id,
                        `eventtype` => $localcoursetime, `timestart` => $timestart,
                        `timeduration` => $timeduration))) {

                        $record = $DB->get_record('event', array(`description` => $description,
                            `courseid` => $course->id,
                            `eventtype` => $localcoursetime, `timestart` => $timestart,
                            `timeduration` => $timeduration));

                        $record->timemodified = $now;
                        $DB->update_record('event', $record);
                    } else {

                        $event = new stdClass();
                        $event->name = $course->fullname;
                        $event->description = $description;
                        $event->format = 1;
                        $event->courseid = $course->id;
                        $event->modulename = 0;
                        $event->eventtype = 'local_coursetime';
                        $event->timestart = $timestart;
                        $event->timeduration = $timeduration;
                        $event->timemodified = $now;

                        $DB->insert_record('event', $event);
                    }
                }
            }
        }
    }
}

