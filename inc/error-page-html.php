<?php
/**
 * The HTML of the themed error pages. Plain PHP with no WordPress calls: the drop-ins run when WordPress cannot,
 * and tools/build_error_pages.php uses it to write the static pages in errors/.
 *
 * @package tbe-child
 */

defined( 'ABSPATH' ) || exit;

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
			$v( 'The database has gone missing', 'The site cannot find its stored text, rather like the original manuscripts: everyone talks about them and nobody can produce them.', 'Please try again in a few minutes.' ),
			$v( 'The library is shut', 'The site cannot reach its database. It is not lost; it just is not answering, unlike God.', 'Please try again in a few minutes.' ),
			$v( 'Where did the text go?', 'The stored text is unreachable for a moment. This is the closest we will come to a textual variant on this site.', 'Please try again in a few minutes.' ),
		) ),
		'php' => array( 'links' => false, 'variants' => array(
			$v( 'A fatal error', 'Something stopped this page from loading. Nobody has to die for it: reloading is the whole atonement.', 'Please try again in a few minutes.' ),
			$v( 'Fatal, but not eternal', 'The page crashed. No hell is involved; a reload will do.', 'Please try again in a few minutes.' ),
			$v( 'It fell over', 'A fatal error. If you want a theory of who is to blame, the programmer fell, and the fall is documented.', 'Please try again in a few minutes.' ),
		) ),
	);
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
 * @param string $key      A page key (400 ... 503, maintenance, db-error, php-error).
 * @param string $font_url Root-relative address of Angel.woff2 (the site name's font).
 */
function tbe_error_page_html( string $key, string $font_url = '/wp-content/themes/TBE/assets/fonts/Angel.woff2' ): string {
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
		? '<nav class="more" aria-label="Elsewhere on the site"><a href="/bible/">The Books</a><a href="/bible/interlinear/">Interlinear</a><a href="/commentary/">Commentary</a><a href="/glossary/">Index</a></nav>'
		: '';
	$again    = $page['links'] ? '<a class="go" href="/">Go to the home page</a>' : '<a class="go" href="/">Try again</a>';
	$mark     = '<svg viewBox="0 -52 340 717" fill="none" stroke="currentColor" stroke-width="30" stroke-linejoin="round" aria-hidden="true"><path d="M135.75 210L144 216L159 202L174 217L190 204L202.25 209.5V460.75H331.25V526.25H202.25V656.25H135.75V526.25H7.75V460.75H135.75Z"/><path d="M135.75 7.75H202.25V209.5L190 204L174 217L159 202L144 216L135.75 210Z" transform="translate(-24 -40) rotate(-8 169 218)"/></svg>';
	$script   = '(function(){var V=' . $json . ';var v=V[Math.floor(Math.random()*V.length)];var h=document.getElementById("t"),b=document.getElementById("b");if(!h||!b){return;}h.textContent=v.title;document.title=v.title+" · The Bible Exposed";b.textContent="";v.lines.forEach(function(l){var p=document.createElement("p");p.textContent=l;b.appendChild(p);});})();';

	return '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">'
		. '<title>' . $e( $first['title'] ) . ' · The Bible Exposed</title><style>'
		. '@font-face{font-family:"Angel";src:url("' . $e( $font_url ) . '") format("woff2");font-display:swap}'
		. ':root{--paper:#f4eedc;--card:#fbf7ea;--ink:#231d14;--muted:#6b604f;--accent:#6e1f2e;--rubric:#9b2c2c;--gold:#a67c2e;--line:#d9cdb0}'
		. '@media (prefers-color-scheme:dark){:root{--paper:#17120f;--card:#201915;--ink:#ece3cf;--muted:#a79c88;--accent:#d4a24c;--rubric:#d9786b;--gold:#d1a64c;--line:#3a2f27}}'
		. '*{box-sizing:border-box}html,body{height:100%;margin:0}'
		. 'body{display:flex;align-items:center;justify-content:center;padding:24px;background:var(--paper);color:var(--ink);font:1.1rem/1.65 Georgia,"Times New Roman",serif}'
		. 'main{width:min(34rem,100%);padding:2.6rem 2.2rem 2.4rem;background:var(--card);border:1px solid var(--line);border-top:4px solid var(--gold);border-radius:30px;text-align:center;box-shadow:0 18px 50px -24px rgba(35,29,20,.45)}'
		. '.mark{display:inline-block;color:var(--accent);height:5.2rem}.mark svg{height:100%;width:auto}'
		. '.name{margin:.9rem 0 0;font:2rem/1.1 "Angel",Georgia,serif;letter-spacing:.02em}'
		. '.code{margin:1.6rem 0 .2rem;font:600 .78rem/1 system-ui,sans-serif;letter-spacing:.3em;text-transform:uppercase;color:var(--gold)}'
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
