<?php
/**    
 * This function lets you resize, convert, or simply direct show a picture.
 * JPG, GIF, and PNG formats are supported.
 * GIF and PNG formats support transparent background as well.
 * 
 * You can choose how to manipulate the image, that means you may give either
 * a new width as a new height or you can even inform both.
 * 
 * Do not worry about proportion. The image is supposed to keep its original
 * ratio.
 * 
 * The default set limits maximum width and height to the original ones.
 * However, with a unique parameter you can enable enlarging the picture.
 * 
 * Do you prefer to fit the source picture in the new image file or fill the
 * whole rectangle is the best choice?
 * Determining this parameter is as easy as reading this text.
 * 
 * Wow! If you are not working with transparent background, why not select
 * which color to fill it?
 * Of course you can define the quality for saving files in jpg or png
 * formats.
 * 
 * In order to improve the performance, the function first checks if the
 * target file already exists into the temporary folder. Take it easy, the
 * script also create a folder named 'tmp' if it does not exist.
 *
 * Feel free to use, improve, and spread this function however you need.
 * Please, the only thing I ask you is that you keep my credits.
 *
 * Copyright 2011-02-07 Emanuel Poletto
 * 
 * For instructions on how to use and further information:
 * Access: http://www.emanuelpoletto.com/projects/
 * (Suddenly, you may also find updates there)
 *
 * Update: 2011-03-08:
 *         Now, it checks the cache of the browser instead of just showing
 *         the existing thumbnail directly.
 *         It also verifies the modification time of the file in order to
 *         return the most updated version of the picture.
 *
 * Update: 2011-09-23:
 *         What if you could use a grayscale filter?
 *         This feature was added today. Just add the boolean parameter
 *         'g=1' in order to transform your color picture to a grayscale
 *         image.
 */

/**
 * @todo Check compatibility with PHP 7.
 * @todo Review and update comments and README file.
 */

// Parameters
$picture = !empty($_GET['p']) ? $_GET['p'] : false; // string   - picture file name
$newW    = !empty($_GET['w']) ? $_GET['w'] : 0;     // integer  - new width (optional)
$newH    = !empty($_GET['h']) ? $_GET['h'] : 0;     // integer  - new height (optional)
$crop    = !empty($_GET['c']) ? true : false;       // boolean  - if true, the original picture will fill in the rectangle. Otherwise, it will fit in the rectangle (optional)
$id      = !empty($_GET['i']) ? $_GET['i'] : false; // integer  - unique element id (recommended)
$enlarge = !empty($_GET['e']) ? true : false;       // boolean  - allow script to enlarge the picture if needed (optional)
$format  = !empty($_GET['f']) ? $_GET['f'] : false; // string   - jpg, png, or gif (optional) if not sent, original image format will be preserved
$bgColor = !empty($_GET['b']) ? $_GET['b'] : 'fff'; // integer  - hexadecimal color to fill the background in if cropping and alpha are false (optional)
$alpha   = !empty($_GET['a']) ? 127 : 0;            // boolean  - allow transparent background (optional) only for gif and png formats
$quality = !empty($_GET['q']) ? $_GET['q'] : 0;     // integer  - compression rate that defines the quality and size of image (optional) only for png and jpg formats
$gray    = !empty($_GET['g']) ? true : false;       // boolean  - turns the grayscale filter on (optional)

// Converts HTML like f00 or ff0087 to rgb
function html2rgb($color) {
    if (substr($color, 0, 1) == '#')
        $color = substr($color, 1);

    if (strlen($color) == 6)
        list($r, $g, $b) = array($color[0].$color[1],
                                 $color[2].$color[3],
                                 $color[4].$color[5]);
    elseif (strlen($color) == 3)
        list($r, $g, $b) = array($color[0].$color[0], $color[1].$color[1], $color[2].$color[2]);
    else
        return false;

    $r = hexdec($r); $g = hexdec($g); $b = hexdec($b);

    return array('r' => $r, 'g' => $g, 'b' => $b);
}

// Converts rgb to HTML
function rgb2html($r, $g = -1, $b = -1) {
    if (is_array($r) && sizeof($r) == 3)
        list($r, $g, $b) = $r;

    $r = intval($r); $g = intval($g); $b = intval($b);

    $r = dechex($r < 0 ? 0 : ($r > 255 ? 255 : $r));
    $g = dechex($g < 0 ? 0 : ($g > 255 ? 255 : $g));
    $b = dechex($b < 0 ? 0 : ($b > 255 ? 255 : $b));

    $color = (strlen($r) < 2 ? '0' : '') . $r;
    $color.= (strlen($g) < 2 ? '0' : '') . $g;
    $color.= (strlen($b) < 2 ? '0' : '') . $b;
    
    return '#' . $color;
}

// Processes the image with parameters given
function imageProcess($picture, $type, $format, $width, $height, $newW, $newH, $imgW, $imgH, $crop, $id, $enlarge, $bgColor, $alpha, $filename, $quality, $gray) {
    
    // Create a new image and load the file
    $bg  = html2rgb($bgColor);
    $img = imagecreatetruecolor($imgW, $imgH);
    
    if ($type == 2) {
        $source = imagecreatefromjpeg($picture);
        if ($gray) imagefilter($source, IMG_FILTER_GRAYSCALE);
        if ($crop == false) {
            $back   = imagecolorallocate($img, $bg['r'], $bg['g'], $bg['b']);
            imagefilledrectangle($img, 0, 0, $imgW, $imgH, $back);
        }
    } elseif ($type == 1 || $type == 3) {
        $source = ($type == 1) ? imagecreatefromgif($picture) : imagecreatefrompng($picture);
        if ($gray) imagefilter($source, IMG_FILTER_GRAYSCALE);
        if ($crop == false && $alpha == 0) {
            $back = imagecolorallocate($img, $bg['r'], $bg['g'], $bg['b']);
            imagefilledrectangle($img, 0, 0, $imgW, $imgH, $back);
        } elseif ($alpha == 127) {
            imagealphablending($img, false);
            imagesavealpha($img, true);
            $back = imagecolorallocatealpha($img, 0, 0, 0, 127);
            imagecolortransparent($img, $back);
        } 
    }
    
    // Resize e position source picture inside the new image
    $x = 0;
    $y = 0;
    if ($crop == false) {
        if ($imgW <> $newW) {
            $x = ($imgW - $newW) / 2;
            $x = floor($x);
        }
        if ($imgH <> $newH) {
            $y = ($imgH - $newH) / 2;
            $y = floor($y);
        }
    }
    imagecopyresampled($img, $source, $x, $y, 0, 0, $newW, $newH, $width, $height);
    
    // Save and output the file
    $quality = intval($quality);
    if ($format == 'jpg') {
        if ($quality > 0 && $quality <= 100) {
            imagejpeg($img, './tmp/' . $filename, $quality);
        } else {
            imagejpeg($img, './tmp/' . $filename);
        }
        header('Content-type: image/jpeg');
    } elseif ($format == 'gif') { 
        if ($alpha == 127)
            imagecolortransparent($img, $back);
        imagegif($img, './tmp/' . $filename);
        header('Content-type: image/gif');
    } elseif ($format == 'png') {
        if ($quality > 0 && $quality <= 100) {
            $quality = 10 - (round($quality / 10));
            imagepng($img, './tmp/' . $filename, $quality);
        } else {
            imagepng($img, './tmp/' . $filename);
        }
        header('Content-type: image/png');
    }
    
    imagedestroy($img);
    readfile('./tmp/' . $filename);

}

if ((strtolower(substr($picture, -3)) == 'jpg') ||
    (strtolower(substr($picture, -3)) == 'gif') ||
    (strtolower(substr($picture, -3)) == 'png')) {

    // Get and check picture size
    list($width, $height, $type) = getimagesize($picture);
    $originalRatio = $width / $height;
    
    // Keep image size and ratio
    if ($newW == 0 && $newH == 0) {
        $newW  = $width;
        $newH  = $height;
    // Image resize based on width
    } elseif ($newW > 0 && $newH == 0) {
        if ($enlarge == false)
            $newW = ($newW > $width) ? $width : $newW;
        $newH  = $newW / $originalRatio;
    // Image resize based on height
    } elseif ($newW == 0 && $newH > 0) {
        if ($enlarge == false)
            $newH = ($newH > $height) ? $height : $newH;
        $newW  = $newH * $originalRatio;
    // Image resize based on width and height
    } elseif ($newW > 0 && $newH > 0) {
        if ($enlarge == false && ($newW > $width || $newH > $height)) {
            $newW = $width;
            $newH = $height;
        } else {
            $ratio = $newW / $newH;
            if (($ratio >= $originalRatio && $crop == true) || ($ratio < $originalRatio && $crop == false)) {
                $imgH = $newH;
                $newH = $newW / $originalRatio;
            } elseif (($ratio < $originalRatio && $crop == true) || ($ratio >= $originalRatio && $crop == false)) {
                $imgW = $newW;
                $newW = $newH * $originalRatio;
            }
        }
    }
    
    // Validate, and round width and height
    $imgW = empty($imgW) ? $newW : $imgW;
    $imgH = empty($imgH) ? $newH : $imgH;
    $imgW = round($imgW);
    $imgH = round($imgH);
    $newW = round($newW);
    $newH = round($newH);
    
    // Determine file format
    if (empty($format))
        $format = ($type == 1) ? 'gif' : (($type == 2) ? 'jpg' : (($type == 3) ? 'png' : false));
    else
        $format = strtolower($format);
 
    // Give new file a specific name
    $filename = substr(basename($picture), 0, -4) .
        (($id) ? '_id' . $id : '_') . ('w' . $imgW) . ('h' . $imgH) . (($crop) ? 'filled' : '') .
        (($alpha == 127) ? '_alpha' : '') . (($crop == false) ? '_bg' . $bgColor : '') .
        (($quality > 0 && $quality <= 100) ? '_q' . $quality : '') .
        (($gray) ? '_gray' : '') . '.' . $format;

    // Check if target folder and target file already exist
    if (!is_dir('./tmp')) {
        mkdir('./tmp');
    }
    
    if (file_exists('./tmp/' . $filename)) {
        $pictureDate = filemtime($picture);
        $thumbDate   = filemtime('./tmp/' . $filename);
        if ($pictureDate > $thumbDate) {
            imageProcess($picture, $type, $format, $width, $height, $newW, $newH, $imgW, $imgH, $crop, $id, $enlarge, $format, $bgColor, $alpha, $filename, $quality, $gray);
        }
        
        // Check the cache of the browser
        $gmt  = gmdate('D, d M Y H:i:s', filemtime('./tmp/' . $filename)) . ' GMT';
        $hash = md5($gmt);
        //$headers = getallheaders();
        
        if ((!empty($_SERVER['HTTP_IF_NONE_MATCH'])) || (!empty($_SERVER['HTTP_IF_MODIFIED_SINCE']))) {
          	if (((preg_match("/$hash/", $_SERVER['HTTP_IF_NONE_MATCH']))) || ($_SERVER['HTTP_IF_MODIFIED_SINCE'] == $gmt)) {
            		header('HTTP/1.1 304 Not Modified');
            		exit;
          	}
        }
        
        header('Last-Modified: ' . $gmt);
        header('ETag: ' . $hash);

        header('Content-type: image/' . ($format == 'jpg' ? 'jpeg' : $format));
        readfile('./tmp/' . $filename);
    } else {
        imageProcess($picture, $type, $format, $width, $height, $newW, $newH, $imgW, $imgH, $crop, $id, $enlarge, $format, $bgColor, $alpha, $filename, $quality, $gray);
    }

}
