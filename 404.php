<?php

$parts=explode('/',$_SERVER['REQUEST_URI']);
$blogs = $wpdb->get_results( "SELECT blog_id FROM $wpdb->blogs WHERE path='/".$parts[1]."/'", OBJECT_K );

$user = wp_get_current_user();

if ( $blogs ) {
    foreach ( $blogs as $blog ) {
		$which = count( $parts ) - 2;
		switch_to_blog( $blog->blog_id );
			$post = get_page_by_path( $parts[ count( $parts ) - 2 ] );
			error_log( "CCG *** post status is " . $post->post_status );
			if ( $post && ( $post->post_status == 'inherit' || $post->post_status == 'publish' ) ) {
				if ( $user->ID ) {
					/* user already logged in, just do a 403 */
					$title = 'Forbidden';
					$lead = 'Your account does not have access to this page.';
					$description = 'The account you are currently logged into does not have access to this page. If you have a different account try logging out (see the link at the upper right of this page) then logging back in using that account.';
				} else {
					/* user not logged in, redirect to the authentication page */
					$title = 'Authentication Required';
					$lead = 'The page you requested requires authentication';
					$description = 'Please use the "Login" link in the upper right and enter your Colby credentials to view this page.';
					print <<<EOT
<script language="javascript" type="text/javascript">
jQuery(document).ready( function() {
loginCMS();
});
</script>
EOT;

				}
			} else {
				/* no post/page for this post, just do a 404 */
				$title = 'File Not Found';
				$lead = 'We can\'t find the file you requested.';
				$description = 'Please use the navigation above or <a href="/search/">search</a> to find what you\'re looking for.';
			}
    }
} else {
	/* no site for this URL, just do a 404 */
	$title = 'File Not Found';
	$lead = 'We can\'t find the file you requested.';
	$description = 'Please use the navigation above or <a href=\'/search/\'>search</a> to find what you\'re looking for.';
}
?>
