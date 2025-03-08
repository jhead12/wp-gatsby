<?php

use WPGatsby\Admin\Preview;

global $post;
$gatsby_content_sync_url = Preview::get_gatsby_content_sync_url_for_post( $post );
?>
<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta http-equiv="X-UA-Compatible" content="ie=edge">
	<title>Gatsby Preview</title>

		<?php Preview::print_file_contents( 'includes/style.css' ); ?>
	<script>
        <?php if ( $gatsby_content_sync_url ) { ?>
            window.location.replace("<?php echo esc_js( $gatsby_content_sync_url ); ?>");
        <?php } ?>
	</script>
</head>

<body>
<?php
if ( ! $gatsby_content_sync_url ) {
    $settings_page_link = get_bloginfo( 'url' ) . '/wp-admin/options-general.php?page=gatsbyjs';
    $docs_link = 'https://www.gatsbyjs.com/cloud/docs/wordpress/getting-started/';
    $contact_support_link = 'https://www.gatsbyjs.com/contact-us/';
    $set_up_preview_link = 'https://www.gatsbyjs.com/preview/';
?>

<div class="content error">
	<h1>The Preview couldn't be loaded</h1>
    <p>Please add your Gatsby Cloud Content Sync URL to the WPGatsby plugin <a href="<?php echo esc_url( $settings_page_link ); ?>" target="_blank" rel="noopener, nofollow">settings page</a>.</p>
	<pre id="error-message-element"></pre>
	<h2>Troubleshooting</h2>
	<span id="troubleshooting-html-area">
        <p>Please ensure your URL is correct and your Preview instance is up and running. If you've set the correct URL, your Preview instance is currently running, and you're still having trouble, please refer to the docs for troubleshooting steps, ask your developer, or contact support if that doesn't solve your issue.</p>
        <a href="<?php echo esc_url( $docs_link ); ?>" target="_blank" rel="noopener, nofollow">Refer to the docs</a>.
        <a href="<?php echo esc_url( $contact_support_link ); ?>" target="_blank" rel="noopener, nofollow">Contact support</a> if you need further assistance.
	</span>
	<h2>Developer instructions</h2>
    <p>Please visit <a href="https://github.com/gatsbyjs/gatsby/blob/master/packages/gatsby-source-wordpress/docs/tutorials/configuring-wp-gatsby.md#setting-up-preview" target="_blank" rel="noopener, nofollow">the docs</a> for instructions on setting up Gatsby Preview.</p>
    <p>If you don't have a valid Gatsby Preview instance, you can <a href="<?php echo esc_url( $set_up_preview_link ); ?>" target="_blank" rel="noopener, nofollow">set one up now on Gatsby Cloud</a>.</p>
</div>

<?php } ?>
<?php wp_footer(); ?>
</body>
</html>