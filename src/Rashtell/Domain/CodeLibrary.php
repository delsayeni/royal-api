<?php

namespace Rashtell\Domain;

use Exception;
use PHPMailer\PHPMailer\PHPMailer;

class CodeLibrary
{

	function genID($count, $type = 0)
	{
		$strings = array();
		$strings[0] = "1234567890ABCDEFGHIJKLMNOPQRSTUVWXYZ";
		$strings[1] = "1234567890abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
		$strings[2] = "1234567890";
		$chars = str_split($strings[$type]);
		$v = "";
		$max = count($chars);
		for ($i = 0; $i < $count; $i++) {
			$v = $v . "" . $chars[rand(0, $max - 1)];
		}
		return $v;
	}

	function is_anum($str)
	{
		if (!preg_match("/[^A-Za-z]/", $str)) {
			return false;
		}
		if (!preg_match("/[^0-9]/", $str)) {
			return false;
		}
		return true;
	}

	function find($obj, $key)
	{
		if (isset($obj->{$key})) {
			return $obj->{$key};
		}
		return null;
	}

	function  base64ToMedia($string, $output, $quality = 65)
	{
		$ifp = fopen($output, "wb");
		$data = explode(",", $string);

		try {
			if (count($data) == 2) {
				fwrite($ifp, base64_decode($data[1]));
				fclose($ifp);

				$imgsize = getimagesize($output);
				if ($imgsize) {
					$width = $imgsize[0];
					$height = $imgsize[1];

					$thumbName = "thumb_" . $output;
					//$this->resize_crop_image(270, 270, $output, $thumbName, 75);

					$this->resize_crop_image($width, $height, $output, $output, $quality);
				}
			} elseif (count($data) == 1) {
				fwrite($ifp, base64_decode($data[0]));
				fclose($ifp);

				$imgsize = getimagesize($output);
				if ($imgsize) {
					$width = $imgsize[0];
					$height = $imgsize[1];

					$thumbName = "thumb_" . $output;
					//$this->resize_crop_image(270, 270, $output, $thumbName, 75);

					$this->resize_crop_image($width, $height, $output, $output, $quality);
				}
			} else {
				$output = null;
			}
		} catch (Exception $e) {
			$output = null;
		}

		return $output;
	}

	function resize_crop_image($max_width, $max_height, $source_file, $dst_dir, $quality)
	{
		$imgsize = getimagesize($source_file);
		if (!$imgsize) {
			return false;
		}
		$width = $imgsize[0];
		$height = $imgsize[1];

		$max_width = $width < $max_width ? $width : $max_width;
		$max_height = $height < $max_height ? $height : $max_height;

		$mime = $imgsize["mime"];
		switch ($mime) {
			case "image/gif":
				$image_create = "imagecreatefromgif";
				$image = "imagegif";
				break;
			case "image/png":
				$image_create = "imagecreatefrompng";
				$image = "imagepng";
				$quality = $quality / 100;
				break;
			case "image/jpeg":
			case "image/jpg":
				$image_create = "imagecreatefromjpeg";
				$image = "imagejpeg";
				$quality = $quality / 1;
				break;
			case "image/webp":
				$image_create = "imagecreatefromwebp";
				$image = "imagewebp";
				$quality = $quality / 1;
				break;
			default:
				return false;
				break;
		}
		$dst_img = imagecreatetruecolor($max_width, $max_height);
		$src_img = $image_create($source_file);
		$width_new = $height * $max_width / $max_height;
		$height_new = $width * $max_height / $max_width;
		//if the new width is greater than the actual width of the image, then the height is too large and the rest cut off, or vice versa
		if ($width_new > $width) {
			//cut point by height
			$h_point = (($height - $height_new) / 2);
			//copy image
			imagecopyresampled($dst_img, $src_img, 0, 0, 0, $h_point, $max_width, $max_height, $width, $height_new);
		} else {
			//cut point by width
			$w_point = (($width - $width_new) / 2);
			imagecopyresampled($dst_img, $src_img, 0, 0, $w_point, 0, $max_width, $max_height, $width_new, $height);
		}
		$image($dst_img, $dst_dir, $quality);
		if ($dst_img) {
			imagedestroy($dst_img);
		}
		if ($src_img) {
			imagedestroy($src_img);
		}

		return true;
	}

	function dataTohtml($data)
	{
	}

	function num2word($num = false)
	{
		$num = str_replace(array(",", " "), "", trim($num));
		if (!$num) {
			return false;
		}
		$num = (int) $num;
		$words = array();
		$list1 = array(
			"", "one", "two", "three", "four", "five", "six", "seven", "eight", "nine", "ten", "eleven",
			"twelve", "thirteen", "fourteen", "fifteen", "sixteen", "seventeen", "eighteen", "nineteen"
		);
		$list2 = array("", "ten", "twenty", "thirty", "forty", "fifty", "sixty", "seventy", "eighty", "ninety", "hundred");
		$list3 = array(
			"", "thousand", "million", "billion", "trillion", "quadrillion", "quintillion", "sextillion", "septillion",
			"octillion", "nonillion", "decillion", "undecillion", "duodecillion", "tredecillion", "quattuordecillion",
			"quindecillion", "sexdecillion", "septendecillion", "octodecillion", "novemdecillion", "vigintillion"
		);
		$num_length = strlen($num);
		$levels = (int) (($num_length + 2) / 3);
		$max_length = $levels * 3;
		$num = substr("00" . $num, -$max_length);
		$num_levels = str_split($num, 3);
		for ($i = 0; $i < count($num_levels); $i++) {
			$levels--;
			$hundreds = (int) ($num_levels[$i] / 100);
			$hundreds = ($hundreds ? " " . $list1[$hundreds] . " hundred" . " " : "");
			$tens = (int) ($num_levels[$i] % 100);
			$singles = "";
			if ($tens < 20) {
				$tens = ($tens ? " " . $list1[$tens] . " " : "");
			} else {
				$tens = (int) ($tens / 10);
				$tens = " " . $list2[$tens] . " ";
				$singles = (int) ($num_levels[$i] % 10);
				$singles = " " . $list1[$singles] . " ";
			}
			$words[] = $hundreds . $tens . $singles . (($levels && (int) ($num_levels[$i])) ? " " . $list3[$levels] . " " : "");
		} //end for loop
		$commas = count($words);
		if ($commas > 1) {
			$commas = $commas - 1;
		}
		return implode(" ", $words);
	}

	function isValidMd5($md5 = "")
	{
		return preg_match("/^[a-f0-9]{32}$/", $md5);
	}

	public function in_array_partial($arr, $keyword)
	{
		foreach ($arr as $index => $string) {

			if (strpos($keyword, $string) !== FALSE) {

				return true;
			}
		}
	}
}
