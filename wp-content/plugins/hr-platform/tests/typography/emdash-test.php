<?php
/**
 * House style tests: the em dash rewrite (includes/site/typography.php). Plain PHP, no WordPress needed.
 * Run: php wp-content/plugins/hr-platform/tests/typography/emdash-test.php
 *
 * "{D}" in a case stands for an em dash, "{B}" for the horizontal bar and {mdash}, {dec}, {hex} for its HTML
 * entities, so this file contains none of them.
 * The "post N" cases are real sentences from published articles (local copy of the archive, read with wp-cli),
 * with the rewrite an editor checked by hand.
 */

namespace {
    if ( PHP_SAPI !== 'cli' ) exit;
    if ( ! defined( 'ABSPATH' ) ) {
        define( 'ABSPATH', __DIR__ );
    }
    if ( ! function_exists( 'add_filter' ) ) {
        function add_filter( ...$args ) { return true; }
        function add_action( ...$args ) { return true; }
    }
    $GLOBALS['shortcode_tags'] = $GLOBALS['shortcode_tags'] ?? array( 'caption' => '__return_empty_string', 'embed' => '__return_empty_string' );
    require_once dirname( __DIR__, 2 ) . '/includes/site/typography.php';
}

namespace HR\Platform\Tests\Typography {

use function HR\Platform\Site\{replace_em_dashes, replace_em_dashes_text, has_em_dash};

$fails = 0;
$total = 0;
$d     = static fn( string $s ): string => str_replace( array( '{D}', '{B}', '{mdash}', '{dec}', '{hex}' ), array( "\u{2014}", "\u{2015}", '&' . 'mdash;', '&#' . '8212;', '&#x' . '2014;' ), $s );
$check = static function ( string $name, string $got, string $want ) use ( &$fails, &$total ): void {
    $total++;
    if ( $got === $want ) {
        echo "ok   {$name}\n";
        return;
    }
    $fails++;
    echo "FAIL {$name}\n     got:  {$got}\n     want: {$want}\n";
};

// [name, input, expected] for replace_em_dashes() (HTML).
$html_cases = array(
    // Ranges become a hyphen.
    array( 'year range', 'Sales rose 2019{D}2021 across the board.', 'Sales rose 2019-2021 across the board.' ),
    array( 'spaced year range', 'Sales rose 2019 {D} 2021 across the board.', 'Sales rose 2019-2021 across the board.' ),
    array( 'rupee range', 'Profit is seen at Rs5{D}7bn this year.', 'Profit is seen at Rs5-7bn this year.' ),
    array( 'fiscal years', 'Targets for FY24{D}FY25 were met.', 'Targets for FY24-FY25 were met.' ),
    array( 'month range', 'The July{D}September quarter was weak.', 'The July-September quarter was weak.' ),
    array( 'range in brackets', '<p>(Rs5{D}7bn)</p>', '<p>(Rs5-7bn)</p>' ),
    array( 'place pair', 'Pakistan{D}China trade talks resumed.', 'Pakistan-China trade talks resumed.' ),
    array( 'city pair', 'The Karachi{D}Lahore motorway opened.', 'The Karachi-Lahore motorway opened.' ),
    // Paired asides become commas, or brackets around a list.
    array( 'paired aside', 'He said {D} and I quote {D} that rates will fall.', 'He said, and I quote, that rates will fall.' ),
    array( 'paired aside across inline tags', '<p>Rates <em>rose</em>{D}<strong>sharply</strong>{D}in May.</p>', '<p>Rates <em>rose</em>, <strong>sharply</strong>, in May.</p>' ),
    // Single clause dashes.
    array( 'lowercase next: comma', 'The rupee fell{D}sharply.', 'The rupee fell, sharply.' ),
    array( 'capital next: colon', 'The result was clear{D}PSX closed higher.', 'The result was clear: PSX closed higher.' ),
    array( 'digit next: colon', 'The SBP held rates{D}22% for now.', 'The SBP held rates: 22% for now.' ),
    array( 'quote next: colon', 'The governor said{D}"we will act".', 'The governor said: "we will act".' ),
    array( 'percent then aside', '<p>Inflation hit 5.6% {D} the lowest in years.</p>', '<p>Inflation hit 5.6%, the lowest in years.</p>' ),
    // Every spelling of the dash.
    array( 'named entity', '<p>The index rose {mdash} and banks led.</p>', '<p>The index rose, and banks led.</p>' ),
    array( 'decimal entity', '<p>The index rose {dec} and banks led.</p>', '<p>The index rose, and banks led.</p>' ),
    array( 'hex entity', '<p>The index rose {hex} and banks led.</p>', '<p>The index rose, and banks led.</p>' ),
    array( 'upper hex entity, capital next', '<p>The index rose &#X2014; Banks led.</p>', '<p>The index rose: Banks led.</p>' ),
    array( 'horizontal bar', '<p>The index rose {B} and banks led.</p>', '<p>The index rose, and banks led.</p>' ),
    array( 'doubled dash', '<p>The index rose {D}{D} and banks led.</p>', '<p>The index rose, and banks led.</p>' ),
    array( 'no-break spaces', 'A&nbsp;{D}&nbsp;b', 'A, b' ),
    // No doubled punctuation or stray spaces.
    array( 'before a full stop', '<p>Growth slowed{D}.</p>', '<p>Growth slowed.</p>' ),
    array( 'after a comma', '<p>Growth slowed, {D} as expected.</p>', '<p>Growth slowed, as expected.</p>' ),
    array( 'end of paragraph', '<p>Growth slowed {D}</p>', '<p>Growth slowed</p>' ),
    // Attributions and datelines.
    array( 'line-leading attribution', '<p>{D} Reuters</p>', '<p>- Reuters</p>' ),
    array( 'attribution after a sentence', '<p>He left. {D} APP</p>', '<p>He left. - APP</p>' ),
    array( 'dateline in bold', '<p><strong>KARACHI</strong> {D} Pakistan&#8217;s exports rose.</p>', '<p><strong>KARACHI</strong>: Pakistan&#8217;s exports rose.</p>' ),
    array( 'dateline before a date', '<p>KARACHI {D} September 30: The SBP said.</p>', '<p>KARACHI, September 30: The SBP said.</p>' ),
    array( 'dateline with a date', '<p>KARACHI, September 30, 2026 {D} The SBP said.</p>', '<p>KARACHI, September 30, 2026: The SBP said.</p>' ),
    // Never touched: attributes, URLs, shortcodes, code, raw elements, block settings.
    array( 'attributes kept', '<p><a href="https://example.com/a{D}b" title="x{D}y">Link{D}text</a> here.</p>', '<p><a href="https://example.com/a{D}b" title="x{D}y">Link, text</a> here.</p>' ),
    array( 'image alt attribute kept', '<img alt="a{D}b" src="x.jpg"><p>a{D}b</p>', '<img alt="a{D}b" src="x.jpg"><p>a, b</p>' ),
    array( 'URL in text kept', '<p>Read https://example.com/a{mdash}b?q=1 {D} the full report.</p>', '<p>Read https://example.com/a{mdash}b?q=1, the full report.</p>' ),
    array( 'shortcode kept', '<p>[caption id="1" caption="a{D}b"]Pic[/caption] Text{D}more.</p>', '<p>[caption id="1" caption="a{D}b"]Pic[/caption] Text, more.</p>' ),
    array( 'unknown shortcode shape kept', '<p>[gallery title="a{D}b"] Text{D}more.</p>', '<p>[gallery title="a{D}b"] Text, more.</p>' ),
    array( 'inline code kept', '<p>Use <code>a{D}b</code> here{D}now.</p>', '<p>Use <code>a{D}b</code> here, now.</p>' ),
    array( 'pre kept', '<pre>x{D}y</pre><p>Text{D}more.</p>', '<pre>x{D}y</pre><p>Text, more.</p>' ),
    array( 'script kept', '<script>var a="x{D}y";</script><p>Text{D}more.</p>', '<script>var a="x{D}y";</script><p>Text, more.</p>' ),
    array( 'style kept', '<style>.a::after{content:"{D}"}</style><p>Text{D}more.</p>', '<style>.a::after{content:"{D}"}</style><p>Text, more.</p>' ),
    array( 'textarea kept', '<textarea>a{D}b</textarea>', '<textarea>a{D}b</textarea>' ),
    array( 'block comment JSON kept', '<!-- wp:paragraph {"x":"a{D}b"} --><p>Text{D}more.</p><!-- /wp:paragraph -->', '<!-- wp:paragraph {"x":"a{D}b"} --><p>Text, more.</p><!-- /wp:paragraph -->' ),
    array( '">" inside an attribute', '<p><span data-x="1 > 0{D}2">A{D}b</span></p>', '<p><span data-x="1 > 0{D}2">A, b</span></p>' ),
    array( 'literal "<" is text', '<p>If x < y{D}then z.</p>', '<p>If x < y, then z.</p>' ),
    array( 'no dash: unchanged', '<p>Plain text, no dash.</p>', '<p>Plain text, no dash.</p>' ),
    array( 'en dash is not an em dash', '<p>The 5' . "\u{2013}" . '7% band.</p>', '<p>The 5' . "\u{2013}" . '7% band.</p>' ),
);

// Real sentences from the archive.
$real_cases = array(
    array( 'post 977', 'According to him, the absence of a shared picture at the event{D}which saw Junaid Safdar marry Shanzay Ali Rohail{D}does not diminish the strength of their bond.',
        'According to him, the absence of a shared picture at the event, which saw Junaid Safdar marry Shanzay Ali Rohail, does not diminish the strength of their bond.' ),
    array( 'post 1087', 'Additionally, wheeling charges{D}the cost of transporting power{D}have been reduced to below Rs 9 per kWh, enabling industries to trade power more efficiently.',
        'Additionally, wheeling charges, the cost of transporting power, have been reduced to below Rs 9 per kWh, enabling industries to trade power more efficiently.' ),
    array( 'post 1141', 'The bodies were recovered after a grueling rescue operation{D}Sadia’s body was found late Wednesday night on Outfall Road, while her daughter’s body was retrieved nearly 17 hours later at Saggian.',
        'The bodies were recovered after a grueling rescue operation: Sadia’s body was found late Wednesday night on Outfall Road, while her daughter’s body was retrieved nearly 17 hours later at Saggian.' ),
    array( 'post 1237', 'This initiative aims to allow the public to observe the sacred night{D}marked by prayer, Quranic recitation, and visiting the graves of loved ones{D}with ease and religious devotion.',
        'This initiative aims to allow the public to observe the sacred night (marked by prayer, Quranic recitation, and visiting the graves of loved ones) with ease and religious devotion.' ),
    array( 'post 1370', 'The Donation: The sisters emptied their shared pink piggy bank and donated their entire savings{D}$61.38{D}to the Canadian Red Cross for Haiti relief.',
        'The Donation: The sisters emptied their shared pink piggy bank and donated their entire savings, $61.38, to the Canadian Red Cross for Haiti relief.' ),
    array( 'post 1442', 'Market analysts suggest that the "pillars" of recent growth{D}AI, crypto, and precious metals{D}are now being questioned.',
        'Market analysts suggest that the "pillars" of recent growth (AI, crypto, and precious metals) are now being questioned.' ),
    array( 'post 1513', 'Despite headline inflation falling to 5.6% in December{D}well within the 5–7% target range{D}the Governor emphasized that a tight monetary stance is necessary to guard against anticipated global and local developments that could reignite price hikes.',
        'Despite headline inflation falling to 5.6% in December, well within the 5–7% target range, the Governor emphasized that a tight monetary stance is necessary to guard against anticipated global and local developments that could reignite price hikes.' ),
    array( 'post 1651', 'Rahman’s Success: Tarique Rahman secured victory in both constituencies he contested{D}Dhaka-17 and Bogura-6.',
        'Rahman’s Success: Tarique Rahman secured victory in both constituencies he contested: Dhaka-17 and Bogura-6.' ),
    array( 'post 1819', 'By taking a household "failure"{D}a burnt shirt{D}and placing it in a luxury boutique, Vetements continues to challenge our perception of what makes an item "valuable." Is it the craftsmanship, or is it the irony of the brand name attached to it?',
        'By taking a household "failure", a burnt shirt, and placing it in a luxury boutique, Vetements continues to challenge our perception of what makes an item "valuable." Is it the craftsmanship, or is it the irony of the brand name attached to it?' ),
    array( 'post 2244', 'Production Collapse: The United Arab Emirates{D}OPEC’s third-largest producer{D}reported a 50% drop in production as export outlets remain blocked.',
        'Production Collapse: The United Arab Emirates, OPEC’s third-largest producer, reported a 50% drop in production as export outlets remain blocked.' ),
    array( 'post 2313', 'Energy Costs: Surging global oil prices{D}driven by the Israel-US-Iran conflict{D}have trickled down into domestic fuel and transport expenses.',
        'Energy Costs: Surging global oil prices, driven by the Israel-US-Iran conflict, have trickled down into domestic fuel and transport expenses.' ),
    array( 'post 2675', '"Employee well-being is no longer an optional corporate social responsibility checkbox{D}it is a foundational pillar of sustainable corporate infrastructure.',
        '"Employee well-being is no longer an optional corporate social responsibility checkbox; it is a foundational pillar of sustainable corporate infrastructure.' ),
    array( 'post 2753', 'This megaproject will directly protect national data sovereignty, insulate sensitive information, and save the country billions in imported computing capacity." {D} Danish Iqbal, Chairman of QGDC',
        'This megaproject will directly protect national data sovereignty, insulate sensitive information, and save the country billions in imported computing capacity." - Danish Iqbal, Chairman of QGDC' ),
    array( 'post 2840', 'Broad-Based Sectors Surge: Heavyweight blocks{D}including commercial banks, exploration &amp; production (E&amp;P) companies, power generation, and fertilizers{D}saw massive institutional accumulation.',
        'Broad-Based Sectors Surge: Heavyweight blocks, including commercial banks, exploration &amp; production (E&amp;P) companies, power generation, and fertilizers, saw massive institutional accumulation.' ),
    array( 'post 2946', 'Most Pakistanis are unaware that a regulated commodity exchange exists in the country." {D} Khurram Zafar, CEO PMEX',
        'Most Pakistanis are unaware that a regulated commodity exchange exists in the country." - Khurram Zafar, CEO PMEX' ),
    array( 'post 2977', 'We will keep credit flowing to the productive sectors {D} housing, exports, technology and, above all, the SMEs that will drive the next phase of job creation.',
        'We will keep credit flowing to the productive sectors: housing, exports, technology and, above all, the SMEs that will drive the next phase of job creation.' ),
    array( 'post 3010', 'Domestic investors are increasingly committing capital, encouraged by the improving macroeconomic stability under the Finance Minister and his team {D} and this confidence now extends beyond the stock market into the real economy.',
        'Domestic investors are increasingly committing capital, encouraged by the improving macroeconomic stability under the Finance Minister and his team, and this confidence now extends beyond the stock market into the real economy.' ),
    array( 'post 3091', 'Emirates, Qatar Airways and Etihad Airways {D} the region\'s three largest airlines {D} are operating at or close to 90% of their pre-war capacity.',
        'Emirates, Qatar Airways and Etihad Airways, the region\'s three largest airlines, are operating at or close to 90% of their pre-war capacity.' ),
    array( 'post 3435', 'The office{D}expected to be operational by the end of 2026 and led by a regional director{D}will help deepen engagement across three specific nations:',
        'The office, expected to be operational by the end of 2026 and led by a regional director, will help deepen engagement across three specific nations:' ),
    array( 'post 3443', '“Reducing circular debt through borrowing is not reform {D} it simply postpones the problem while consumers continue to pay higher electricity bills,” he remarked.',
        '“Reducing circular debt through borrowing is not reform; it simply postpones the problem while consumers continue to pay higher electricity bills,” he remarked.' ),
    array( 'post 3490', 'MANILA, PHILIPPINES (3 July 2026) {D} Asian Development Bank (ADB) President Masato Kanda brought together some of the world’s most influential business leaders to put private sector muscle behind solutions to Asia and the Pacific’s most pressing problems.',
        'MANILA, PHILIPPINES (3 July 2026): Asian Development Bank (ADB) President Masato Kanda brought together some of the world’s most influential business leaders to put private sector muscle behind solutions to Asia and the Pacific’s most pressing problems.' ),
    array( 'post 3497', 'KARACHI {D} Foreign and multinational companies operating in Pakistan have significantly scaled their social development footprint, mobilizing a total of PKR 15.33 billion toward Corporate Social Responsibility (CSR) initiatives over the past year.',
        'KARACHI: Foreign and multinational companies operating in Pakistan have significantly scaled their social development footprint, mobilizing a total of PKR 15.33 billion toward Corporate Social Responsibility (CSR) initiatives over the past year.' ),
    array( 'post 3505', 'MULTAN {D} The Excise, Taxation and Narcotics Control Department (ET&amp;NC) of the Multan division has successfully surpassed its annual revenue target for the fiscal year 2025–26, recording an overall tax recovery of 104%.',
        'MULTAN: The Excise, Taxation and Narcotics Control Department (ET&amp;NC) of the Multan division has successfully surpassed its annual revenue target for the fiscal year 2025–26, recording an overall tax recovery of 104%.' ),
    array( 'post 3633', 'Regional inflation is now forecast at 4.3% this year compared to 3% in 2025{D}an upward revision of 0.7 percentage points from April.',
        'Regional inflation is now forecast at 4.3% this year compared to 3% in 2025, an upward revision of 0.7 percentage points from April.' ),
    array( 'post 3825', 'The Commission found that Google gives preferential treatment to its own services{D}including shopping, hotels, transport, and sports results{D}over those of third parties in Google Search.',
        'The Commission found that Google gives preferential treatment to its own services (including shopping, hotels, transport, and sports results) over those of third parties in Google Search.' ),
    array( 'post 3968', 'Expanding exports is therefore no longer simply a growth objective{D}it is an economic necessity.',
        'Expanding exports is therefore no longer simply a growth objective; it is an economic necessity.' ),
    array( 'post 4067', 'Just last year, a successful serial-entrepreneur I know pumped big money into the same dream {D} beautiful space at a strategic location, well-curated menu, grand launch {D} and was forced to close within eighteen months.',
        'Just last year, a successful serial-entrepreneur I know pumped big money into the same dream (beautiful space at a strategic location, well-curated menu, grand launch) and was forced to close within eighteen months.' ),
    array( 'post 4200', 'State of Pakistan’s Economy {D} Half Yearly Report, July–December FY2026 (Finance Division)',
        'State of Pakistan’s Economy: Half Yearly Report, July–December FY2026 (Finance Division)' ),
    array( 'post 4341', '{D} Aamir Ibrahim, CEO of JazzWorld',
        '- Aamir Ibrahim, CEO of JazzWorld' ),
    array( 'post 5606', 'Kaukab Iqbal urged the government to make the principle of “First verify the land and approvals, then announce the project, then advertise and accept bookings {D} while keeping the buyer’s money protected in an Escrow Account” an integral part of the law.',
        'Kaukab Iqbal urged the government to make the principle of “First verify the land and approvals, then announce the project, then advertise and accept bookings, while keeping the buyer’s money protected in an Escrow Account” an integral part of the law.' ),
    array( 'post 5941', 'The import quotas for three other major agricultural commodities {D} rice, corn and cotton {D} will also remain unchanged, reflecting continuity in China’s import quota policy for key food and textile-related raw materials.',
        'The import quotas for three other major agricultural commodities (rice, corn and cotton) will also remain unchanged, reflecting continuity in China’s import quota policy for key food and textile-related raw materials.' ),
);

foreach ( $html_cases as [ $name, $in, $want ] ) {
    $check( $name, $d( replace_em_dashes( $d( $in ) ) ), $d( $want ) );
}
foreach ( $real_cases as [ $name, $in, $want ] ) {
    $got = replace_em_dashes( $d( $in ) );
    $check( $name, $got, $d( $want ) );
    $check( $name . ' (as HTML paragraph)', replace_em_dashes( '<p>' . $d( $in ) . '</p>' ), '<p>' . $d( $want ) . '</p>' );
}

// Plain text: "<" is a character, not markup.
$check( 'plain text keeps "<"', replace_em_dashes_text( $d( 'Rates <b> rose{D}sharply' ) ), 'Rates <b> rose, sharply' );
$check( 'plain text title', replace_em_dashes_text( $d( 'PSX gains {D} KSE-100 ends at a record' ) ), 'PSX gains: KSE-100 ends at a record' );

// Detection, safety and the preview data.
$total++;
if ( ! has_em_dash( $d( 'a{D}b' ) ) || ! has_em_dash( 'a&#x2015;b' ) || has_em_dash( 'a-b ' . "\u{2013}" ) ) {
    $fails++;
    echo "FAIL has_em_dash detection\n";
} else {
    echo "ok   has_em_dash detection\n";
}
$bad = "a\xC3{D}b";
$check( 'invalid UTF-8 left alone', replace_em_dashes( $d( $bad ) ), $d( $bad ) );
$check( 'placeholder characters left alone', replace_em_dashes( $d( "a\u{E000}{D}b" ) ), $d( "a\u{E000}{D}b" ) );
replace_em_dashes( $d( '<p>The rupee fell{D}sharply{D}on Monday.</p>' ), $rows );
$check( 'preview row', json_encode( $rows[0] ?? null, JSON_UNESCAPED_UNICODE ), json_encode( array( 'before' => 'The rupee fell', 'old' => $d( '{D}' ), 'new' => ', ', 'after' => $d( 'sharply{D}on Monday.' ), 'new_before' => 'The rupee fell', 'new_after' => 'sharply, on Monday.' ), JSON_UNESCAPED_UNICODE ) );
$check( 'output never holds an em dash', (string) (int) has_em_dash( replace_em_dashes( $d( '<p>{D}{D} a {D} b {D} c {D}.</p><p>X{D}</p>' ) ) ), '0' );
$check( 'idempotent', replace_em_dashes( replace_em_dashes( $d( '<p>He said {D} and I quote {D} that rates will fall.</p>' ) ) ), '<p>He said, and I quote, that rates will fall.</p>' );

echo "\n{$total} checks, " . ( $total - $fails ) . " passed, {$fails} failed.\n";
exit( $fails ? 1 : 0 );

}
