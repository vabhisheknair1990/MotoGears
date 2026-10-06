<?php

namespace App\Services\ProductImport;

/** The file as a whole cannot be used (wrong format, missing sheet or columns, too many rows). */
class ImportFileException extends \RuntimeException {}
