<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A rule an admin edit or new order breaks (e.g. gift with «Rechnung im Paket»).
 * The message is shown to the user as is; anything else is a real error.
 */
class OrderNotAllowed extends RuntimeException {}
