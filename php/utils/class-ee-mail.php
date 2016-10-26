<?php

class EE_Mail {

	/**
	 * Send Mail.
	 * @param string $to
	 * @param string $subject
	 * @param string $message
	 * @param string $from
	 * @param string $headers
	 */
	public static function send( $to, $subject, $message, $from = '', $headers = '' ) {
		if ( empty( $headers ) ) {
			$headers = 'From: ' . $from . "\r\n" .
			           'Reply-To: ' . $from . "\r\n" .
			           'X-Mailer: PHP/' . phpversion();
		}
		mail($to, $subject, $message, $headers);
	}
}