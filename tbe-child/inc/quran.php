<?php
/**
 * Qur'an cross-reference helpers: reference parsing, validation and links.
 * Only references are stored. No Qur'anic text is bundled.
 *
 * @package tbe-child
 */

defined( 'ABSPATH' ) || exit;

/** @return array<int,array{name:string,ayat:int}> Surahs keyed 1..114. */
function tbe_surahs(): array {
	static $surahs = null;
	if ( null !== $surahs ) {
		return $surahs;
	}
	$names = array(
		'Al-Fatihah', 'Al-Baqarah', 'Ali \'Imran', 'An-Nisa', 'Al-Ma\'idah', 'Al-An\'am', 'Al-A\'raf', 'Al-Anfal', 'At-Tawbah', 'Yunus',
		'Hud', 'Yusuf', 'Ar-Ra\'d', 'Ibrahim', 'Al-Hijr', 'An-Nahl', 'Al-Isra', 'Al-Kahf', 'Maryam', 'Ta-Ha',
		'Al-Anbiya', 'Al-Hajj', 'Al-Mu\'minun', 'An-Nur', 'Al-Furqan', 'Ash-Shu\'ara', 'An-Naml', 'Al-Qasas', 'Al-\'Ankabut', 'Ar-Rum',
		'Luqman', 'As-Sajdah', 'Al-Ahzab', 'Saba', 'Fatir', 'Ya-Sin', 'As-Saffat', 'Sad', 'Az-Zumar', 'Ghafir',
		'Fussilat', 'Ash-Shura', 'Az-Zukhruf', 'Ad-Dukhan', 'Al-Jathiyah', 'Al-Ahqaf', 'Muhammad', 'Al-Fath', 'Al-Hujurat', 'Qaf',
		'Adh-Dhariyat', 'At-Tur', 'An-Najm', 'Al-Qamar', 'Ar-Rahman', 'Al-Waqi\'ah', 'Al-Hadid', 'Al-Mujadilah', 'Al-Hashr', 'Al-Mumtahanah',
		'As-Saff', 'Al-Jumu\'ah', 'Al-Munafiqun', 'At-Taghabun', 'At-Talaq', 'At-Tahrim', 'Al-Mulk', 'Al-Qalam', 'Al-Haqqah', 'Al-Ma\'arij',
		'Nuh', 'Al-Jinn', 'Al-Muzzammil', 'Al-Muddaththir', 'Al-Qiyamah', 'Al-Insan', 'Al-Mursalat', 'An-Naba', 'An-Nazi\'at', '\'Abasa',
		'At-Takwir', 'Al-Infitar', 'Al-Mutaffifin', 'Al-Inshiqaq', 'Al-Buruj', 'At-Tariq', 'Al-A\'la', 'Al-Ghashiyah', 'Al-Fajr', 'Al-Balad',
		'Ash-Shams', 'Al-Layl', 'Ad-Duha', 'Ash-Sharh', 'At-Tin', 'Al-\'Alaq', 'Al-Qadr', 'Al-Bayyinah', 'Az-Zalzalah', 'Al-\'Adiyat',
		'Al-Qari\'ah', 'At-Takathur', 'Al-\'Asr', 'Al-Humazah', 'Al-Fil', 'Quraysh', 'Al-Ma\'un', 'Al-Kawthar', 'Al-Kafirun', 'An-Nasr',
		'Al-Masad', 'Al-Ikhlas', 'Al-Falaq', 'An-Nas',
	);
	$ayat   = array(
		7, 286, 200, 176, 120, 165, 206, 75, 129, 109,
		123, 111, 43, 52, 99, 128, 111, 110, 98, 135,
		112, 78, 118, 64, 77, 227, 93, 88, 69, 60,
		34, 30, 73, 54, 45, 83, 182, 88, 75, 85,
		54, 53, 89, 59, 37, 35, 38, 29, 18, 45,
		60, 49, 62, 55, 78, 96, 29, 22, 24, 13,
		14, 11, 11, 18, 12, 12, 30, 52, 52, 44,
		28, 28, 20, 56, 40, 31, 50, 40, 46, 42,
		29, 19, 36, 25, 22, 17, 19, 26, 30, 20,
		15, 21, 11, 8, 8, 19, 5, 8, 8, 11,
		11, 8, 3, 9, 5, 4, 7, 3, 6, 3,
		5, 4, 5, 6,
	);
	$surahs = array();
	foreach ( $names as $i => $name ) {
		$surahs[ $i + 1 ] = array( 'name' => $name, 'ayat' => $ayat[ $i ] );
	}
	return $surahs;
}

/**
 * Parse "2:255", "112:1-4" or "112" (whole surah).
 *
 * @return array{surah:int,from:int,to:int}|null from/to are 0 for a whole surah.
 */
function tbe_parse_quran_ref( string $input ): ?array {
	$input = trim( str_replace( array( '–', '—', '،' ), array( '-', '-', '' ), $input ) );
	if ( ! preg_match( '/^(\d{1,3})(?:\s*[:.]\s*(\d{1,3})(?:\s*-\s*(\d{1,3}))?)?$/', $input, $m ) ) {
		return null;
	}
	$surahs = tbe_surahs();
	$surah  = (int) $m[1];
	if ( ! isset( $surahs[ $surah ] ) ) {
		return null;
	}
	if ( ! isset( $m[2] ) || '' === $m[2] ) {
		return array( 'surah' => $surah, 'from' => 0, 'to' => 0 );
	}
	$from = (int) $m[2];
	$to   = isset( $m[3] ) && '' !== $m[3] ? (int) $m[3] : $from;
	if ( $from < 1 || $to < $from || $to > $surahs[ $surah ]['ayat'] ) {
		return null;
	}
	return array( 'surah' => $surah, 'from' => $from, 'to' => $to );
}

function tbe_quran_label( array $ref ): string {
	$surah = tbe_surahs()[ $ref['surah'] ];
	$label = $ref['surah'];
	if ( $ref['from'] ) {
		$label .= ':' . $ref['from'] . ( $ref['to'] > $ref['from'] ? '–' . $ref['to'] : '' );
	}
	/* translators: 1: reference such as 112:1–4, 2: surah name */
	return sprintf( __( 'Qur\'an %1$s (%2$s)', 'tbe-child' ), $label, $surah['name'] );
}

function tbe_quran_url( array $ref ): string {
	$url = 'https://quran.com/' . $ref['surah'];
	if ( $ref['from'] ) {
		$url .= '/' . $ref['from'] . ( $ref['to'] > $ref['from'] ? '-' . $ref['to'] : '' );
	}
	return $url;
}
