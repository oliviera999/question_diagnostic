<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_question_diagnostic';
$plugin->version = 2025121510;  // YYYYMMDDXX format (v1.11.40 - Olution duplicates: strict (qtype + questiontext) duplicates only)
$plugin->requires = 2022041900; // Moodle 4.0+ (architecture question_bank_entries requise)
$plugin->maturity = MATURITY_STABLE;
$plugin->release = 'v1.11.40';

