<?php
// Fail-closed guard — require_once FIRST in each bb/ entry-point.
// Encapsulates the architectural standard \bb\Base::loginCheck().
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Base.php');
\bb\Base::loginCheck();
