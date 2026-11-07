<?php

namespace App\Services\Import;

use RuntimeException;

/** A single spreadsheet row failed validation; the rest of the import continues. */
class ImportRowException extends RuntimeException {}
