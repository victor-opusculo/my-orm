<?php

namespace VictorOpusculo\MyOrm;

require_once __DIR__ . '/Option.php';

final class None extends Option
{
    public function __construct() { }
    public function __toString() { return '(Option: None)'; }
}
