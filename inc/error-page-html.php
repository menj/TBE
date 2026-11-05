<?php
/**
 * The HTML of the themed error pages. Plain PHP with no WordPress calls: the drop-ins run when WordPress cannot,
 * and tools/build_error_pages.php uses it to write the static pages in errors/.
 *
 * @package tbe-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * The font address in the theme's own copies in errors/: relative to that folder, so it holds whatever the theme
 * folder is called (tbe, TBE). It resolves only where the page is opened at its own address; a page the web
 * server sends for another address (ErrorDocument) needs the full path, which the copies in wp-content/tbe-errors/
 * carry.
 */
const TBE_ERROR_FONT_RELATIVE = '../assets/fonts/Angel.woff2';

/**
 * The version in an error page's font address: the first 12 characters of the font file's MD5, '' when the file
 * is missing. MD5 rather than the hash of the theme's other assets (tbe_asset_version()), since the copies in
 * errors/ are built ahead and checked with --check, and must come out the same on every PHP version.
 *
 * @param string $file The font file.
 */
function tbe_error_font_version( string $file ): string {
	$md5 = is_file( $file ) ? md5_file( $file ) : false;
	return false === $md5 ? '' : substr( $md5, 0, 12 );
}

/**
 * A font address with its version: a changed font gets a new address, so no browser keeps the old one.
 *
 * @param string $url  The address without a version.
 * @param string $file The font file.
 */
function tbe_error_versioned_font( string $url, string $file ): string {
	$ver = tbe_error_font_version( $file );
	return '' === $ver ? $url : $url . '?ver=' . $ver;
}

/** The font address in the theme's own copies in errors/: relative, with the version. */
function tbe_error_root_font_url(): string {
	return tbe_error_versioned_font( TBE_ERROR_FONT_RELATIVE, dirname( __DIR__ ) . '/assets/fonts/Angel.woff2' );
}

/**
 * Every page: its code, whether the site's own links can be offered (not when the site itself is down),
 * and several variants of the message, so a different line greets each visit.
 */
function tbe_error_pages(): array {
	$v = static fn( string $title, string ...$lines ): array => array( 'title' => $title, 'lines' => $lines );
	return array(
		'400'         => array( 'links' => true, 'variants' => array(
			$v( 'That made no sense', 'Your request could not be understood, rather like the Council of Nicaea’s account of how one God is three.', 'Go back and try again; at least this one can be fixed.' ),
			$v( 'Bad request', 'Something in the request was malformed, which is more than can be said for the creeds: those were deliberately vague.', 'Please go back and try again.' ),
			$v( 'Say that again?', 'The server could not follow you. It has had practice: it once had to follow the doctrine of two natures in one person.', 'Go back and try again.' ),
		) ),
		'401' => array( 'links' => true, 'variants' => array(
			$v( 'Identify yourself', 'This part of the site wants a sign-in, and what you sent did not match.', 'We will not put it to a vote, as the bishops did when they decided who gets to be God.' ),
			$v( 'Sign-in required', 'You need to prove who you are. We ask for a password, not a baptism.', 'Please check your details and try again.' ),
			$v( 'Credentials, please', 'Only authorised persons beyond this point. Unlike the Trinity, we can tell you how many there are.', 'Check your sign-in and try again.' ),
		) ),
		'403' => array( 'links' => true, 'variants' => array(
			$v( 'No entry', 'You are not allowed in here. No blood is accepted as payment.', 'The Bible text, commentary and interlinear are open to everyone.' ),
			$v( 'Forbidden', 'Access denied, and no scapegoat is available to take the blame for you.', 'The rest of the site is open to everyone.' ),
			$v( 'Not for you', 'This door is shut. Salvation by proxy does not work on servers either.', 'The Bible text, commentary and interlinear are open to everyone.' ),
		) ),
		'404' => array( 'links' => true, 'variants' => array(
			$v( 'Not found. Like the Trinity in your Bible', 'The page you followed does not exist. You will not find the word “Trinity” in any verse either, though people have been searching for seventeen centuries.' ),
			$v( 'Lost without a trace', 'This page is missing, like the originals of every book of the New Testament. Nobody has seen those either, and still they are quoted with confidence.' ),
			$v( 'Page not found', 'Whoever wrote this page did not sign it. In that, it is exactly like the Gospels.' ),
			$v( 'No such page', 'There is no such page. “Three in one” was not in the text either, until the councils put it there.' ),
			$v( 'Not here', 'We looked in the text and could not find it. This is the same method that finds the Trinity, only honest.' ),
		) ),
		'500' => array( 'links' => false, 'variants' => array(
			$v( 'Something broke', 'A server error. Unlike original sin, it is nobody’s fault but ours, and it will be forgiven without a sacrifice.', 'Please try again in a few minutes.' ),
			$v( 'It is not you, it is us', 'The server tripped. No cosmic debt has been incurred, and nobody needs to be crucified for it.', 'Please try again in a few minutes.' ),
			$v( 'Internal error', 'Something failed on our side. It is a mystery, which is the traditional word for an explanation nobody can give.', 'Please try again in a few minutes.' ),
		) ),
		'502' => array( 'links' => false, 'variants' => array(
			$v( 'The server is not answering', 'The server behind this site has gone silent, much like the Gospel writers when asked who wrote them.', 'Please try again in a moment.' ),
			$v( 'No reply', 'We asked the server a question and it did not answer. Sound familiar, anyone who has asked God about the problem of evil?', 'Please try again in a moment.' ),
			$v( 'Bad gateway', 'The server behind this site did not reply. Like Jesus, it will be back, and sooner than promised.', 'Please try again in a moment.' ),
		) ),
		'504' => array( 'links' => false, 'variants' => array(
			$v( 'The server took too long', 'We waited for the server behind this site and it never replied, like the disciples waiting for a return within their generation.', 'Please try again in a moment.' ),
			$v( 'Gateway timeout', 'The server behind this site ran out of time. It happens; the end of the world has been running late for two thousand years.', 'Please try again in a moment.' ),
			$v( 'Still waiting', 'The server behind this site did not answer in time. Unlike the Parousia, it should not take centuries.', 'Please try again in a moment.' ),
		) ),
		'503' => array( 'links' => false, 'variants' => array(
			$v( 'Back shortly', 'The site is being updated. Unlike the Second Coming, we are giving you a date: a few minutes from now.', 'Thank you for your patience.' ),
			$v( 'Down for maintenance', 'We are fixing things. The disciples were told the end was near within their lifetime; we are more reliable than that.', 'Please try again in a few minutes.' ),
			$v( 'Be right back', 'The site will be back in minutes. A prophecy with a deadline that actually comes true: a first.', 'Thank you for your patience.' ),
		) ),
	);
}

/** Pages that share another page's wording, with a different code. */
function tbe_error_aliases(): array {
	return array(
		'maintenance' => array( 'from' => '503', 'code' => '503' ),
		'db-error'    => array( 'from' => 'db', 'code' => '503' ),
		'php-error'   => array( 'from' => 'php', 'code' => '500' ),
	);
}

/** The wording for the two failures only the drop-ins show. */
function tbe_error_extra_pages(): array {
	$v = static fn( string $title, string ...$lines ): array => array( 'title' => $title, 'lines' => $lines );
	return array(
		'db'  => array( 'links' => false, 'variants' => array(
			$v( 'We cannot reach our database', 'The site cannot reach its stored text just now. It is not lost; it just is not answering, rather like the original manuscripts.', 'Please try again in a few minutes.' ),
			$v( 'The database has gone missing', 'The site cannot find its stored text, rather like the original manuscripts: everyone talks about them and nobody can produce them.', 'Please try again in a few minutes.' ),
			$v( 'The library is shut', 'The site cannot reach its database. It is not lost; it just is not answering, unlike God.', 'Please try again in a few minutes.' ),
			$v( 'Where did the text go?', 'The stored text is unreachable for a moment. This is the closest we will come to a textual variant on this site.', 'Please try again in a few minutes.' ),
		) ),
		'php' => array( 'links' => false, 'variants' => array(
			$v( 'Something went wrong', 'A fatal error stopped this page from loading. Nobody has to die for it: reloading is the whole atonement.', 'Please try again in a few minutes.' ),
			$v( 'A fatal error', 'Something stopped this page from loading. Nobody has to die for it: reloading is the whole atonement.', 'Please try again in a few minutes.' ),
			$v( 'Fatal, but not eternal', 'The page crashed. No hell is involved; a reload will do.', 'Please try again in a few minutes.' ),
			$v( 'It fell over', 'A fatal error. If you want a theory of who is to blame, the programmer fell, and the fall is documented.', 'Please try again in a few minutes.' ),
		) ),
	);
}

/**
 * The short name of each page for its title tag, used when a variant's title is longer than the 32 characters
 * the title rule leaves beside " | The Bible Exposed".
 */
function tbe_error_short_titles(): array {
	return array(
		'400'         => 'Bad request',
		'401'         => 'Sign-in required',
		'403'         => 'Forbidden',
		'404'         => 'Page not found',
		'500'         => 'Something went wrong',
		'502'         => 'Bad gateway',
		'504'         => 'Gateway timeout',
		'503'         => 'Back shortly',
		'maintenance' => 'Back shortly',
		'db-error'    => 'We cannot reach our database',
		'php-error'   => 'Something went wrong',
	);
}

/**
 * The brand mark as the tab icon, a data address: an error page answers at any path, in a subfolder install too,
 * and while WordPress or PHP is down, so it carries the icon inside the page and asks the server for nothing. The
 * mark is written out here, the same as assets/img/brand/favicon.svg (tools/test_error_pages.php compares them),
 * so a page built while that file is still uploading has its icon too.
 */
function tbe_error_icon_link(): string {
	$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="#6e1f2e"/><g fill="none" stroke="#e0b958" stroke-width="3.2" stroke-linejoin="round"><path d="M25 27L39 23V38H50V52H39V58H25V52H14V38H25Z"/><path d="M25 13H39V23L25 27Z" transform="translate(-7 -6) rotate(-13 32 25)"/></g></svg>';
	return '<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,' . htmlspecialchars( rawurlencode( $svg ), ENT_QUOTES, 'UTF-8' ) . '">';
}

/** The title tag of a page: "<title> | The Bible Exposed", under 60 characters. */
function tbe_error_title_tag( string $key, string $title ): string {
	return ( mb_strlen( $title ) <= 32 ? $title : ( tbe_error_short_titles()[ $key ] ?? 'Error' ) ) . ' | The Bible Exposed';
}

/** One page's data, resolving aliases: array( code, links, variants ). */
function tbe_error_page( string $key ): array {
	$pages   = tbe_error_pages();
	$extra   = tbe_error_extra_pages();
	$aliases = tbe_error_aliases();
	if ( isset( $aliases[ $key ] ) ) {
		$from = $aliases[ $key ]['from'];
		$page = $pages[ $from ] ?? $extra[ $from ];
		return array( 'code' => $aliases[ $key ]['code'], 'links' => $page['links'], 'variants' => $page['variants'] );
	}
	$page = $pages[ $key ];
	return array( 'code' => $key, 'links' => $page['links'], 'variants' => $page['variants'] );
}

/** A random variant of a page's message, for pages built while a request is running. */
function tbe_error_variant( string $key ): array {
	$variants = tbe_error_page( $key )['variants'];
	return $variants[ random_int( 0, count( $variants ) - 1 ) ];
}

/**
 * The HTML of one error page.
 *
 * The first variant is written into the page, and all of them are embedded so a script swaps in a random one
 * on each load (static pages and drop-ins cannot choose at request time without PHP).
 *
 * @param string $key      A page key (400 ... 504, maintenance, db-error, php-error).
 * @param string|null $font_url Address of Angel.woff2 (the site name's font). The default is relative to errors/,
 *                              with the font's version (tbe_error_root_font_url()), since the theme folder may be
 *                              named tbe, TBE or anything else; the copies the theme writes for the site get the
 *                              real address (tbe_error_font_url()).
 * @param string $home     Root-relative address of the home page, with a trailing slash. The links start from it.
 */
function tbe_error_page_html( string $key, ?string $font_url = null, string $home = '/' ): string {
	$font_url = $font_url ?? tbe_error_root_font_url();
	$page     = tbe_error_page( $key );
	$code     = $page['code'];
	$first    = $page['variants'][0];
	$e        = static fn( string $s ): string => htmlspecialchars( $s, ENT_QUOTES, 'UTF-8' );
	$text     = '';
	foreach ( $first['lines'] as $line ) {
		$text .= '<p>' . $e( $line ) . '</p>';
	}
	$json     = json_encode( $page['variants'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE );
	$links    = $page['links']
		? '<nav class="more" aria-label="Elsewhere on the site"><a href="' . $e( $home . 'bible/' ) . '">The Books</a><a href="' . $e( $home . 'bible/interlinear/' ) . '">Interlinear</a><a href="' . $e( $home . 'commentary/' ) . '">Commentary</a><a href="' . $e( $home . 'glossary/' ) . '">Index</a></nav>'
		: '';
	$again    = $page['links'] ? '<a class="go" href="' . $e( $home ) . '">Go to the home page</a>' : '<a class="go" href="' . $e( $home ) . '">Try again</a>';
	$mark     = '<svg viewBox="0 -52 340 717" fill="none" stroke="currentColor" stroke-width="30" stroke-linejoin="round" aria-hidden="true"><path d="M135.75 210L144 216L159 202L174 217L190 204L202.25 209.5V460.75H331.25V526.25H202.25V656.25H135.75V526.25H7.75V460.75H135.75Z"/><path d="M135.75 7.75H202.25V209.5L190 204L174 217L159 202L144 216L135.75 210Z" transform="translate(-24 -40) rotate(-8 169 218)"/></svg>';
	$script   = '(function(){var S=' . json_encode( tbe_error_short_titles()[ $key ] ?? 'Error', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE ) . ',V=' . $json . ';var v=V[Math.floor(Math.random()*V.length)];var h=document.getElementById("t"),b=document.getElementById("b");if(!h||!b){return;}h.textContent=v.title;document.title=(v.title.length<=32?v.title:S)+" | The Bible Exposed";b.textContent="";v.lines.forEach(function(l){var p=document.createElement("p");p.textContent=l;b.appendChild(p);});})();';

	return '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">'
		. '<title>' . $e( tbe_error_title_tag( $key, $first['title'] ) ) . '</title>' . tbe_error_icon_link() . '<style>'
		. '@font-face{font-family:"Angel";src:url("' . $e( $font_url ) . '") format("woff2");font-display:swap}'
		. ':root{--paper:#f4eedc;--card:#fbf7ea;--ink:#231d14;--muted:#6b604f;--accent:#6e1f2e;--rubric:#9b2c2c;--gold:#a67c2e;--line:#d9cdb0}'
		. '@media (prefers-color-scheme:dark){:root{--paper:#17120f;--card:#201915;--ink:#ece3cf;--muted:#a79c88;--accent:#d4a24c;--rubric:#d9786b;--gold:#d1a64c;--line:#3a2f27}}'
		. '*{box-sizing:border-box}html,body{height:100%;margin:0}'
		. 'body{display:flex;align-items:center;justify-content:center;padding:24px;background:var(--paper);color:var(--ink);font:1.1rem/1.65 Georgia,"Times New Roman",serif}'
		. 'main{width:min(34rem,100%);padding:2.6rem 2.2rem 2.4rem;background:var(--card);border:1px solid var(--line);border-top:4px solid var(--gold);border-radius:30px;text-align:center;box-shadow:0 18px 50px -24px rgba(35,29,20,.45)}'
		. '.mark{display:inline-block;color:var(--accent);height:5.2rem}.mark svg{height:100%;width:auto}'
		. '.name{margin:.9rem 0 0;font:2rem/1.1 "Angel",Georgia,serif;letter-spacing:.02em}'
		. '.code{margin:1.6rem 0 .2rem;font:600 .78rem/1 system-ui,sans-serif;letter-spacing:.3em;text-transform:uppercase;color:var(--rubric)}'
		. 'h1{margin:.2rem 0 1rem;font-size:clamp(1.6rem,5vw,2.1rem);font-weight:400;line-height:1.2}'
		. 'p{margin:0 auto .6rem;max-width:30rem;color:var(--muted)}'
		. '.go{display:inline-block;margin-top:1.4rem;padding:.7rem 1.7rem;border-radius:999px;background:linear-gradient(135deg,var(--accent),var(--rubric));color:#fff;font:600 .95rem system-ui,sans-serif;text-decoration:none;transition:transform .2s,box-shadow .2s}'
		. '.go:hover,.go:focus-visible{transform:translateY(-2px);box-shadow:0 12px 24px -12px var(--accent)}'
		. '.more{display:flex;flex-wrap:wrap;justify-content:center;gap:.4rem;margin-top:1.4rem;font:500 .78rem system-ui,sans-serif;letter-spacing:.09em;text-transform:uppercase}'
		. '.more a{padding:.45rem .9rem;border-radius:999px;color:var(--ink);text-decoration:none;transition:background .2s,color .2s}'
		. '.more a:hover,.more a:focus-visible{background:color-mix(in srgb,var(--accent) 12%,var(--card));color:var(--accent)}'
		. '</style></head><body><main role="main"><div class="mark">' . $mark . '</div><p class="name">The Bible Exposed</p>'
		. '<p class="code">' . $e( $code ) . '</p><h1 id="t">' . $e( $first['title'] ) . '</h1><div id="b">' . $text . '</div>' . $again . $links
		. '</main><script>' . $script . '</script></body></html>';
}
