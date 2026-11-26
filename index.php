<?php
/**
 * Entry redirect.
 *
 * A visitor who types only the folder name lands here and is forwarded to the
 * real application entry point. Apache serves actual .php files directly, so
 * this file only catches bare directory requests.
 */
declare(strict_types=1);

header('Location: public/index.php', true, 302);
exit;
