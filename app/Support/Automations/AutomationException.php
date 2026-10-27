<?php

namespace App\Support\Automations;

use RuntimeException;

/** Běh automatizace nejde dokončit. Zpráva je pro lidi (správce i klienta), bez osobních údajů. */
class AutomationException extends RuntimeException {}
