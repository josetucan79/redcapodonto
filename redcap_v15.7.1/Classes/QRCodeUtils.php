<?php

require_once APP_PATH_LIBRARIES . "phpqrcode/lib/full/qrlib.php";

/**
 * A class that provide helper functions for QR code generation
 */
class QRCodeUtils
{
	/**
	 * Outputs a PNG image of a QR code
	 * @param string $text The text to be encoded
	 * @return void 
	 */
	public static function output_qr_code_png($text) {
		QRcode::png($text,  false, 'H', 4, 2);
	}

	/**
	 * Generates a QR code SVG
	 * @param string $text The text to be encoded
	 * @return string 
	 */
	public static function generate_qr_code_svg($text) {
		return QRcode::svg($text, false, false, 'H', 300);
	}

}
