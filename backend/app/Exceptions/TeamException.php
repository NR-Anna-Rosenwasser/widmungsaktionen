<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A team change that is not allowed; the message is shown to the admin.
 */
class TeamException extends RuntimeException {}
