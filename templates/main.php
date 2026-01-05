<?php
declare(strict_types=1);

use OCP\Util;

// Loads compiled JS and CSS (name must match vite.config.js)
Util::addScript('timetracking', 'timetracking-main');
Util::addStyle('timetracking', 'timetracking-style');
?>

<div id="content" class="app-timetracking"></div>
