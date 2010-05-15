<?php

// Location of PO/MO files
$config['directory'] = 'i18n';

// List of available locales
$config['locales'] = array('en_US', 'fr_FR', 'it_IT', 'de_DE', 'es_ES', 'sv_SE', 'nl_NL', 'da_DK', 'nn_NO', 'fi_FI');

// Gettext domain name
$config['domain'] = 'app';

// GET query var to check for to force language MO file refresh, or FALSE to disable
$config['refresh_var'] = '_refreshlang';

// GET query var to force PO file generation, or FALSE to disable
$config['regenerate_var'] = '_regenlang';

// Auto refresh timeout for language MO file reload in seconds, or FALSE to disable
$config['auto_refresh'] = 600;

// Files to parse for language PO generation
$config['includes'] = array('views', 'models', 'controllers');