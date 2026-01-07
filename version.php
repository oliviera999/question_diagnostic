<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_question_diagnostic';
$plugin->version = 2025121910;  // YYYYMMDDXX format (v1.14.3 - Renforcement détection doublons pour éviter suppressions par erreur)
$plugin->requires = 2025100100; // Moodle 5.1+ (PHP 8.2+ requis) - Ajusté pour compatibilité avec toutes les versions 5.1.x
$plugin->maturity = MATURITY_STABLE;
$plugin->release = 'v1.14.3';

