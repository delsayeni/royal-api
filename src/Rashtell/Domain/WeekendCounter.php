<?php

namespace Rashtell\Domain;

class WeekendCounter{

    public function getWeekend($day, $today = FALSE){
        $today = !$today? time() : $today;
        $days = [4, 3, 2, 1, 0, -1, -2];
        $day = $this->getDayFirstMinute($day);
        $today = $this->getDayFirstMinute($today);
        $weekend = 0;
        if($this->dayOfWeek($day) == 5){
            if($this->nextDay($day, 2) > $today){
                return 1;
            }else{
                $weekend += 2;
                $day = $this->nextDay($day, 2);
            }
        }elseif($this->dayOfWeek($day) == 6){
            $weekend += 1;
            $day = $this->nextDay($day, 1);
            //$day += 86400;
        }
        while($day <= $today){
            $t = $this->nextDay($day,$days[$this->dayOfWeek($day)]);
            if($t >= $today){
                break;
            }else{
                if($this->nextDay($t,2) <= $today){
                    $weekend += 2;
                    $day = $this->nextDay($t, 3);
                }elseif($this->nextDay($t, 1) <= $today){
                    $weekend += 1;
                    $day = $this->nextDay($t, 2);
                }else{
                    $day = $t;
                }
            }
        }
        return $weekend;
    }

    function nextDay($days, $n){
        return $days + $n*86400;
    }

    function dayOfWeek($day){
        return date("w", $day);
    }

    function getDayFirstMinute($day){
        $t = ($day%86400);
        $today = ($day - $t);
        return $today;
    }
}