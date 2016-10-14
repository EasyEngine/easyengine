<?php

class EE_Mail {

	public static function send( $to, $subject, $message, $from = '', $headers = '' ) {
		if ( empty( $headers ) ) {
			$headers = 'From: ' . $from . "\r\n" .
			           'Reply-To: ' . $from . "\r\n" .
			           'X-Mailer: PHP/' . phpversion();
		}
		mail($to, $subject, $message, $headers);
	}
}