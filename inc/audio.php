<?php
/**
 * Recorded readings (Hebrew and Greek), timed word by word (see tools/align_audio.py).
 * One MP3 per chapter in assets/audio/{he|gr}/, with word timings in data/audio/{he|gr}/.
 *
 * @package tbe-child
 */

defined( 'ABSPATH' ) || exit;

final class TBE_Audio {

	/** @return array{file:string,url:string,duration:float,credit:string,words:array<string,array>}|null */
	public static function chapter( string $slug, int $chapter ): ?array {
		foreach ( array( 'he', 'gr' ) as $folder ) {
			$json = TBE_DIR . '/data/audio/' . $folder . '/' . $slug . '-' . $chapter . '.json';
			if ( ! is_readable( $json ) ) {
				continue;
			}
			$data = json_decode( (string) file_get_contents( $json ), true );
			if ( ! is_array( $data ) || empty( $data['file'] ) || ! is_readable( TBE_DIR . '/assets/audio/' . $folder . '/' . $data['file'] ) ) {
				continue;
			}
			$data['url'] = TBE_URI . '/assets/audio/' . $folder . '/' . rawurlencode( $data['file'] );
			return $data;
		}
		return null;
	}

	/** ISO 8601 duration, e.g. PT6M21S. */
	public static function iso_duration( float $seconds ): string {
		$s = (int) round( $seconds );
		return sprintf( 'PT%dM%dS', intdiv( $s, 60 ), $s % 60 );
	}
}
