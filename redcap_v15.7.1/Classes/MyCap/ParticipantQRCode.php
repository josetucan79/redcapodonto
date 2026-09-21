<?php

namespace Vanderbilt\REDCap\Classes\MyCap;

/**
 * Class QRCode
 * @package MyCap
 */
class ParticipantQRCode
{
    /**
     * Creates a QR code suitable for base 64 images. Optionally super-imposes an image on top of the QR code to
     * make it distinct.
     *
     * Usage: <img src="data:image/png;base64,' . QRCode::makeSrc('{"foo":"bar"}') . '"/>
     *
     * @param $text
     * @param string $overlayPngPath
     * @return string
     */
    public static function makeBase64($text, $overlayPngPath = '', $outfile=null)
    {
        if ($outfile === null) {
            ob_start();
            \QRcode::png(
                $text,
                null,
                'H',
                4
            );
            $data = ob_get_contents();
            ob_end_clean();
        } else {
            \QRcode::png($text, $outfile, 'H', 4, 4, true);
            $data = file_get_contents($outfile);
        }

        if (strlen($overlayPngPath) && file_exists($overlayPngPath) && self::hasImageManipulationFunctions()) {
            $pngBack = imagecreatefromstring($data);
            $pngFront = imagecreatefrompng($overlayPngPath);

            if ($pngBack && $pngFront) {
                list($bw, $bh) = getimagesizefromstring($data);
                list($fw, $fh) = getimagesize($overlayPngPath);
                $data = self::superImpose($pngBack, $bw, $bh, $pngFront, $fw, $fh);
                if ($outfile !== null) {
                    file_put_contents($outfile, $data);
                }
            }
            try { } catch (\Throwable $e) {
                imagedestroy($pngBack);
                imagedestroy($pngFront);
            }
        }

        return base64_encode($data);
    }

    public static function superImpose($pngBack, $bw, $bh, $pngFront, $fw, $fh)
    {
        $pngFrontResized = imagecreatetruecolor($bw, $bh);
        imagecopyresized($pngFrontResized, $pngFront, 0, 0, 0, 0, $bw, $bh, $fw, $fh);

        $final = imagecreatetruecolor($bw, $bh);
        $red = imagecolorallocate($final, 255, 0, 0);
        $black = imagecolorallocate($final, 0, 0, 0);
        $white = imagecolorallocate($final, 255, 255, 255);
        imagefill($final, 0, 0, $white);
        imagesavealpha($final, true);

        for ($x = 0; $x <= $bw; $x++) {
            for ($y = 0; $y <= $bh; $y++) {
                if (imagecolorat($pngBack, $x, $y) > 0 && imagecolorat($pngFrontResized, $x, $y) > 0) {
                    imagesetpixel($final, $x, $y, $red);
                } elseif (imagecolorat($pngBack, $x, $y) > 0) {
                    imagesetpixel($final, $x, $y, $black);
                }
            }
        }

        ob_start();
        imagepng($final);
        $data = ob_get_contents();
        ob_end_clean();
        imagedestroy($pngFrontResized);
        imagedestroy($final);

        return $data;
    }

    public static function hasImageManipulationFunctions()
    {
        if (function_exists('getimagesize') &&
            function_exists('imagecreatetruecolor') &&
            function_exists('imagecopyresized') &&
            function_exists('imagecolorallocate') &&
            function_exists('imagefill') &&
            function_exists('imagesavealpha') &&
            function_exists('imagecolorat') &&
            function_exists('imagesetpixel') &&
            function_exists('imagepng') &&
            function_exists('imagedestroy')
        ) {
            return true;
        }
        return false;
    }
}
