<?php
use WPGatsby\Admin\Preview;

// Constants
$settings_page_url = get_bloginfo('url') . '/wp-admin/options-general.php?page=gatsbyjs';
$gatsby_cloud_preview_url = 'https://www.gatsbyjs.com/preview/';

// HTML structures
function print_header() {
    ?>
    <head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta http-equiv="X-UA-Compatible" content="ie=edge">
	<title>Preview</title>
	<style>
            <?php Preview::print_file_contents('includes/style.css'); ?>
	</style>
</head>
    <?php
}

function print_footer() {
    wp_footer();
}

function print_content($message) {
    ?>
<div class="content">
	<h1>Preview not found</h1>
        <p><?php echo $message; ?></p>
</div>
    <?php
}

// Main code
print_header();
?>
<body>

<?php
$message = "Visit the <a href='$settings_page_url' target='_blank' rel='noopener, nofollow. noreferrer, noopener, external'>settings page</a> to add a valid Preview webhook URL.<br><br>If you don't have a Gatsby Preview instance, you can <a href='$gatsby_cloud_preview_url' target='_blank' rel='noopener, nofollow. noreferrer, noopener, external'>set one up now on Gatsby Cloud.</a>";
print_content($message);
?>

</body>
<?php
print_footer();
?>
