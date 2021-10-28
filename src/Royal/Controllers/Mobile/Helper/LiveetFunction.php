<?php

namespace Royal\Controllers\Mobile\Helper;

use Royal\Domain\Constants;


/**
 * helper functions for Liveet Mobile
 */
trait LiveetFunction
{
  public function formatMoney($number, $cents = 2) { // cents: 0=never, 1=if needed, 2=always
    if (is_numeric($number)) { // a number
      if (!$number) { // zero
        $money = ($cents == 2 ? '0.00' : '0'); // output zero
      } else { // value
        if (floor($number) == $number) { // whole number
          $money = number_format($number, ($cents == 2 ? 2 : 0)); // format
        } else { // cents
          $money = number_format(round($number, 2), ($cents == 0 ? 0 : 2)); // format
        } // integer or decimal
      } // value
      return '$'.$money;
    } // numeric
  }
}
