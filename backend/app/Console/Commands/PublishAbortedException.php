<?php

namespace App\Console\Commands;

use RuntimeException;

/**
 * Internal control-flow exception used by PublishImportFinancingV2Command to roll
 * back the surrounding transaction (dry run or validation failure) so V1 and the
 * DRAFT are both left in a clean state.
 */
class PublishAbortedException extends RuntimeException {}
