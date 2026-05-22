<?php

declare(strict_types=1);

namespace App\Exceptions\Auth;

use RuntimeException;

class TokenReplayException extends RuntimeException {}
