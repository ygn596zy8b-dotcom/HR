<?php
/**
 * Branded transactional email (table layout, inline styles for every mail client). All content is plain text,
 * escaped here. @var string $subject  @var array $c preheader, heading, paragraphs, button{label,url}, after, note
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$hra_font = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";
$hra_addr = 'First Floor, Feroze Heights, Saadi Town, Karachi, Sindh 75280, Pakistan';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light only">
<title><?php echo esc_html( $subject ); ?></title>
</head>
<body style="margin:0;padding:0;background:#f2f2f7;-webkit-text-size-adjust:100%;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:#f2f2f7;"><?php echo esc_html( (string) $c['preheader'] ); ?></div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f2f2f7;">
<tr><td align="center" style="padding:24px 12px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;background:#ffffff;border-collapse:collapse;">
        <tr><td style="background:#050505;padding:22px 32px;border-bottom:4px solid #ff001c;">
            <span style="font-family:<?php echo esc_attr( $hra_font ); ?>;font-size:18px;font-weight:800;letter-spacing:3px;color:#ffffff;">HEADLINE</span>
            <span style="font-family:<?php echo esc_attr( $hra_font ); ?>;font-size:18px;font-weight:800;letter-spacing:3px;color:#ff001c;">RECORDER</span>
        </td></tr>
        <tr><td style="padding:36px 32px 8px;font-family:<?php echo esc_attr( $hra_font ); ?>;">
            <h1 style="margin:0 0 18px;font-size:26px;line-height:1.2;font-weight:800;color:#111111;"><?php echo esc_html( (string) $c['heading'] ); ?></h1>
            <?php foreach ( (array) $c['paragraphs'] as $hra_p ) : ?>
                <p style="margin:0 0 16px;font-size:16px;line-height:1.6;color:#333333;"><?php echo esc_html( (string) $hra_p ); ?></p>
            <?php endforeach; ?>
        </td></tr>
        <?php if ( ! empty( $c['button']['url'] ) ) : ?>
        <tr><td style="padding:8px 32px 24px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
                <td style="background:#ff001c;">
                    <a href="<?php echo esc_url( $c['button']['url'] ); ?>" style="display:inline-block;padding:15px 28px;font-family:<?php echo esc_attr( $hra_font ); ?>;font-size:15px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#ffffff;text-decoration:none;"><?php echo esc_html( (string) $c['button']['label'] ); ?></a>
                </td>
            </tr></table>
            <p style="margin:16px 0 0;font-family:<?php echo esc_attr( $hra_font ); ?>;font-size:13px;line-height:1.5;color:#5f5f66;">Button not working? Copy this link into your browser:<br><a href="<?php echo esc_url( $c['button']['url'] ); ?>" style="color:#c0001a;word-break:break-all;"><?php echo esc_html( $c['button']['url'] ); ?></a></p>
        </td></tr>
        <?php endif; ?>
        <?php if ( ! empty( $c['after'] ) || '' !== (string) $c['note'] ) : ?>
        <tr><td style="padding:0 32px 28px;font-family:<?php echo esc_attr( $hra_font ); ?>;">
            <?php foreach ( (array) $c['after'] as $hra_p ) : ?>
                <p style="margin:0 0 12px;font-size:15px;line-height:1.6;color:#333333;"><?php echo esc_html( (string) $hra_p ); ?></p>
            <?php endforeach; ?>
            <?php if ( '' !== (string) $c['note'] ) : ?>
                <p style="margin:16px 0 0;padding:12px 14px;background:#f2f2f7;border-left:3px solid #111111;font-size:13px;line-height:1.5;color:#5f5f66;"><?php echo esc_html( (string) $c['note'] ); ?></p>
            <?php endif; ?>
        </td></tr>
        <?php endif; ?>
        <tr><td style="padding:20px 32px 28px;border-top:1px solid #e5e5ea;font-family:<?php echo esc_attr( $hra_font ); ?>;font-size:12px;line-height:1.6;color:#8e8e93;">
            We will never ask for your password by email. This message was sent because of activity on your Headline Recorder account.<br>
            Headline Recorder · <?php echo esc_html( $hra_addr ); ?><br>
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>" style="color:#5f5f66;"><?php echo esc_html( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ); ?></a> · <a href="<?php echo esc_url( home_url( '/privacy-policy/' ) ); ?>" style="color:#5f5f66;">Privacy Policy</a>
        </td></tr>
    </table>
</td></tr>
</table>
</body>
</html>
