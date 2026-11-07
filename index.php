<?php
/**
 * Root entry point.
 * Redirects requests from the project root directory to the public frontend using a relative path.
 */
header('Location: public/index.html');
exit;
