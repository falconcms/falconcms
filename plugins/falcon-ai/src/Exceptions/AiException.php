<?php

namespace FalconAi\Exceptions;

use RuntimeException;

/**
 * A request that could not be answered, with a message meant for the person who asked: what
 * went wrong and what to do about it. It never carries a key or a raw API response.
 */
class AiException extends RuntimeException {}
