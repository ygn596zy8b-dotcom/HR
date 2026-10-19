<?php
/**
 * Site module settings: organisation details (structured data, footer address) and cookie consent.
 * Owner-confirmed defaults come from docs/SPEC.md ("Owner decisions"); the phone number was not provided and stays empty.
 */

namespace HR\Platform\Site;

use function HR\Platform\setting;

if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'hr_settings_schema', function ( array $schema ): array {
    $schema['organisation'] = array(
        'title'       => 'Organisation',
        'description' => 'Used in search-engine structured data (Organization / NewsMediaOrganization), the footer address and the legal pages. Leave a field empty rather than guessing — empty fields are simply left out.',
        'fields'      => array(
            'org_legal_name'     => array( 'label' => 'Legal name', 'type' => 'text', 'default' => 'Headline Recorder' ),
            'org_street_address' => array( 'label' => 'Street address', 'type' => 'text', 'default' => 'First Floor, Feroze Heights, Saadi Town' ),
            'org_locality'       => array( 'label' => 'City', 'type' => 'text', 'default' => 'Karachi' ),
            'org_region'         => array( 'label' => 'Province / region', 'type' => 'text', 'default' => 'Sindh' ),
            'org_postal_code'    => array( 'label' => 'Postal code', 'type' => 'text', 'default' => '75280' ),
            'org_country'        => array(
                'label'       => 'Country code',
                'type'        => 'text',
                'default'     => 'PK',
                'description' => 'Two-letter ISO code, e.g. PK.',
                'sanitize'    => static fn( $v ) => strtoupper( substr( preg_replace( '/[^a-z]/i', '', (string) $v ), 0, 2 ) ),
            ),
            'org_phone'          => array(
                'label'       => 'Phone',
                'type'        => 'text',
                'default'     => '',
                'description' => 'International format, e.g. +92 21 1234 5678. Optional.',
                'sanitize'    => static fn( $v ) => preg_replace( '/[^0-9+()\- ]/', '', (string) $v ),
            ),
            'org_email'          => array( 'label' => 'Public email', 'type' => 'email', 'default' => 'contact@headlinerecorder.com' ),
            'org_same_as'        => array(
                'label'       => 'Other official profiles',
                'type'        => 'textarea',
                'default'     => '',
                'description' => 'One URL per line (e.g. LinkedIn page). Added to the profiles already set in Yoast SEO → Settings → Site representation.',
                'sanitize'    => static function ( $v ) {
                    $urls = array_filter( array_map( static fn( $u ) => esc_url_raw( trim( $u ), array( 'https' ) ), preg_split( '/\R/', (string) $v ) ) );
                    return implode( "\n", array_unique( $urls ) );
                },
            ),
        ),
    );
    $schema['consent'] = array(
        'title'       => 'Cookies & analytics',
        'description' => 'The cookie banner asks readers before any analytics cookie is set. Google Analytics itself is configured in Site Kit.',
        'fields'      => array(
            'consent_banner'         => array( 'label' => 'Show the cookie banner', 'type' => 'checkbox', 'default' => 1 ),
            'consent_analytics_mode' => array(
                'label'       => 'Google tag before consent',
                'type'        => 'select',
                'default'     => 'basic',
                'options'     => array(
                    'basic'    => 'Do not load Google’s tag until the reader accepts analytics (recommended)',
                    'advanced' => 'Load the tag in Consent Mode (no cookies, cookieless pings until the reader accepts)',
                ),
                'description' => 'Both use Google Consent Mode v2 with everything denied by default. The privacy policy wording follows this choice.',
            ),
        ),
    );
    return $schema;
} );

/** Organisation details with empty values removed. */
function org(): array {
    $keys = array( 'legal_name', 'street_address', 'locality', 'region', 'postal_code', 'country', 'phone', 'email', 'same_as' );
    $out  = array();
    foreach ( $keys as $key ) {
        $value = trim( (string) setting( 'org_' . $key ) );
        if ( '' !== $value ) {
            $out[ $key ] = $value;
        }
    }
    return $out;
}

/** Human-readable country name for the codes we are likely to need; otherwise the code itself. */
function country_name( string $code ): string {
    $names = array( 'PK' => 'Pakistan', 'AE' => 'United Arab Emirates', 'GB' => 'United Kingdom', 'US' => 'United States', 'SA' => 'Saudi Arabia' );
    return $names[ strtoupper( $code ) ] ?? strtoupper( $code );
}

/** True when Google's tag must wait for the reader's consent before loading. */
function analytics_waits_for_consent(): bool {
    return 'advanced' !== setting( 'consent_analytics_mode' );
}
