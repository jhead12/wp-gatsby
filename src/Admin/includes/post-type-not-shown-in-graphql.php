<?php use WPGatsby\Admin\Preview; ?>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta http-equiv="X-UA-Compatible" content="ie=edge">
	<title><?php echo esc_html( Preview::ERROR_TITLE ); ?></title>
	<style>
		<?php Preview::print_file_contents( 'includes/style.css' ); ?>
	</style>
</head>

<body>
<div class="content">
	<h1><?php echo esc_html( Preview::ERROR_HEADING ); ?></h1>
	<p>
		The post type <b>"<?php echo esc_html( get_post_type() ); ?>"</b> is not set up properly for Gatsby Preview.
		<br />
		Post types must have <b>"show_in_graphql"</b> set to work with Preview.
		<br />
		<br />

		Visit the
		<a
			href="https://docs.wpgraphql.com/getting-started/custom-post-types/" target="_blank"
			rel="noopener, nofollow. noreferrer, noopener, external">
				WPGraphQL Docs
		</a>
		to learn how to configure this post type.
	</p>
</div>
<?php wp_footer(); ?>
</body>

</html>
