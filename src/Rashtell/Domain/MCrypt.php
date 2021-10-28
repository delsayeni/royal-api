<?php

namespace Rashtell\Domain;

class MCrypt{

	public function mCryptThis($str){
		$outstr = "";
		$st = sha1($str);
		$len = strlen($st);
		$asciMap = array($len);
		$start = 0;
		$genC = 0;
		while ($start < $len){
			$asciMap[$start] = $this->tener(ord(substr($st,$start,1)));
			//echo $asciMap[$start]."<br/>";
			$start++;
		}
		$xi = array($len/4);
		$start = 0;
		while($start < $len){
			$a = $asciMap[$start];
			$b = $asciMap[$start + 1];
			$c = $asciMap[$start + 2];
			$d = $asciMap[$start + 3];
			$sumer = 0;
			$sub = 0;
			while($sumer < 4){
				$t1 = 0;
				$t2 = 0;
				$t3 = 0;
				$t4 = 0;
				while($sub < 4){
					if($sumer == 0){
						if($sub == 0){
							$t1 = $t1 + substr($a,0,1);
						}
						elseif($sub == 1){
							$t1 = $t1 + substr($b,1,1);
						}
						elseif($sub == 2){
							$t1 = $t1 + substr($c,2,1);
						}
						else{
							$t1 = $t1 + substr($d,3,1);
						}
					}
					elseif($sumer == 1){
						if($sub == 0){
							$t2 = $t2 + substr($b,0,1);
						}
						elseif($sub == 1){
							$t2 = $t2 + substr($c,1,1);
						}
						elseif($sub == 2){
							$t2 = $t2 + substr($d,2,1);
						}
						else{
							$t2 = $t2 + substr($a,3,1);
						}
					}
					elseif($sumer == 2){
						if($sub == 0){
							$t3 = $t3 + substr($c,0,1);
						}
						elseif($sub == 1){
							$t3 = $t3 + substr($d,1,1);
						}
						elseif($sub == 2){
							$t3 = $t3 + substr($a,2,1);
						}
						else{
							$t3 = $t3 + substr($a,3,1);
						}
					}
					elseif($sumer == 3){
						if($sub == 0){
							$t4 = $t4 + substr($d,0,1);
						}
						elseif($sub == 1){
							$t4 = $t4 + substr($a,1,1);
						}
						elseif($sub == 2){
							$t4 = $t4 + substr($b,2,1);
						}
						else{
							$t4 = $t4 + substr($c,3,1);
						}
					}
						$sub++;
				}
					$sub = 0;
					$xi[$genC] = $t1 + $t2 + $t3 + $t4;
					$outstr = $outstr."".$xi[$genC];
					$genC++;
					$sumer++;
				}
				//echo $xi[$start] +$xi[$start+1] +$xi[$start+2] + $xi[start+3]."<br/>";
			$start = $start + 4;
		}
		return $outstr;	
	}

	function tener($binInt)
	{
		$Quad = "";
		$binstack = array(8);
		$start = 128;
		$counter = 0;
		while ($start >= 1){
			if($start <= $binInt){
				$binInt = $binInt - $start;
				$binstack[$counter] = 1;
			}
			else{
				$binstack[$counter] = 0;
			}
			//echo $binstack[$counter];
			$start = $start/2;
			$counter++;
		}
		$start = 0;
		$binstack = array_reverse($binstack);
		while($start < 8){
			if($binstack[$start] == 1 && $binstack[$start+1] == 0){
				$Quad = $Quad."1";
			}
			elseif($binstack[$start] == 0  && $binstack[$start+1] == 1){
				$Quad = $Quad."3";
			}
			elseif($binstack[$start] == 0 && $binstack[$start + 1] == 0){
				$Quad = $Quad."0";
			}
			else{
				$Quad = $Quad."4";
			}
			//echo $binstack[$start]."and".$binstack[$start+1]."<br/>";
			$start = $start + 2;
		}
		return $Quad;
	}
}
