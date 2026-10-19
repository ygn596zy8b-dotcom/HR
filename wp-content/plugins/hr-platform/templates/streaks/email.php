<?php
/**
 * Streak reminder email (table layout, inline styles). Theme override: headlinerec-child/hr/streaks/email.php
 *
 * @var string $subject  @var string $lead  @var array $reads (title, url, minutes, oped)  @var string $unsub
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$hrs_font = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";
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
<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:#f2f2f7;"><?php echo esc_html( $lead ); ?></div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f2f2f7;">
<tr><td align="center" style="padding:24px 12px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;background:#ffffff;border-collapse:collapse;font-family:<?php echo esc_attr( $hrs_font ); ?>;">
        <tr><td style="background:#050505;padding:22px 32px;border-bottom:4px solid #ff001c;">
            <span style="font-size:18px;font-weight:800;letter-spacing:3px;color:#ffffff;">HEADLINE</span>
            <span style="font-size:18px;font-weight:800;letter-spacing:3px;color:#ff001c;">RECORDER</span>
        </td></tr>
        <tr><td style="padding:36px 32px 8px;">
            <p style="margin:0 0 10px;font-size:12px;font-weight:700;letter-spacing:3px;text-transform:uppercase;color:#ff001c;">Reading streak</p>
            <h1 style="margin:0 0 16px;font-size:26px;line-height:1.2;font-weight:800;color:#111111;"><?php echo esc_html( $subject ); ?></h1>
            <p style="margin:0 0 20px;font-size:16px;line-height:1.6;color:#333333;"><?php echo esc_html( $lead ); ?></p>
        </td></tr>
        <?php foreach ( $reads as $hrs_r ) : ?>
        <tr><td style="padding:0 32px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-top:1px solid #e5e5ea;">
                <tr><td style="padding:16px 0;<?php echo $hrs_r['oped'] ? 'border-left:3px solid #ff001c;padding-left:14px;' : ''; ?>">
                    <p style="margin:0 0 4px;font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:<?php echo $hrs_r['oped'] ? '#c0001a' : '#5f5f66'; ?>;"><?php echo esc_html( ( $hrs_r['oped'] ? 'Op-ed' : 'Article' ) . ' · ' . $hrs_r['minutes'] . ' min read' ); ?></p>
                    <a href="<?php echo esc_url( $hrs_r['url'] ); ?>" style="font-size:18px;line-height:1.35;font-weight:700;color:#111111;text-decoration:none;"><?php echo esc_html( $hrs_r['title'] ); ?></a>
                </td></tr>
            </table>
        </td></tr>
        <?php endforeach; ?>
        <tr><td style="padding:20px 32px 32px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
                <td style="background:#ff001c;">
                    <a href="<?php echo esc_url( $reads ? $reads[0]['url'] : home_url( '/' ) ); ?>" style="display:inline-block;padding:15px 28px;font-size:15px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#ffffff;text-decoration:none;">Keep my streak</a>
                </td>
            </tr></table>
        </td></tr>
        <tr><td style="padding:20px 32px 28px;border-top:1px solid #e5e5ea;font-size:12px;line-height:1.6;color:#8e8e93;">
            You get this email because you turned on streak reminders. <a href="<?php echo esc_url( $unsub ); ?>" style="color:#5f5f66;">Turn off reminders</a><br>
            Headline Recorder · First Floor, Feroze Heights, Saadi Town, Karachi, Sindh 75280, Pakistan<br>
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>" style="color:#5f5f66;"><?php echo esc_html( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ); ?></a> · <a href="<?php echo esc_url( home_url( '/privacy-policy/' ) ); ?>" style="color:#5f5f66;">Privacy Policy</a>
        </td></tr>
    </table>
</td></tr>
</table>
</body>
</html>
