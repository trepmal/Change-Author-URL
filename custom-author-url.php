<?php
//Plugin Name: Change the author url

/*
	Normally: yoursite.com/author/user_nicename
	Now: yoursite.com/author/user_meta
*/

$change_author_url = new Change_Author_URL();
class Change_Author_URL {

	function __construct() {
		// add the user/profile field
		add_action( 'personal_options_update', array( &$this, 'save_custom_profile_fields' ) );
		add_action( 'edit_user_profile_update', array( &$this, 'save_custom_profile_fields' ) );
		add_action( 'personal_options', array( &$this, 'add_profile_options') );
		add_filter( 'get_the_author_url', array( &$this, 'get_the_author_url'), 10, 2 );
		add_filter( 'author_link', '__return_false' );


		// hack the rewrite rule
		add_action( 'wp_loaded', array( &$this, 'wp_loaded' ) );
		add_filter( 'rewrite_rules_array', array( &$this, 'rewrite_rules_array' ) );
		add_action( 'pre_get_posts', array( &$this, 'pre_get_posts' ), 2 );
	}

	function save_custom_profile_fields( $user_id ) {
		$slug = esc_attr( $_POST['user_slug'] );
		$user = get_user_by( 'login', $slug );
		if ( $user && $user->ID != $user_id ) {
			return;
		} elseif ( 0 > count( get_users( array( 'meta_key' => 'slug', 'meta_value' => $slug ) ) ) ) {
			return;
		}
		update_user_meta( $user_id, 'slug', $slug );
	}
	function add_profile_options( $profileuser ) {
		if ( '' == ( $slug = get_user_meta( $profileuser->ID, 'slug', true ) ) )
			$slug = $profileuser->user_nicename;

		?><tr>
		<th scope="row">User Slug</th>
		<td><input type="text" name="user_slug" value="<?php echo $slug; ?>" /> <span class="description">Be URL-friendly. And no slashes.</span></td>
		</tr><?php
	}
	function get_the_author_url( $value, $user_id ) {
		if ( '' == ( $slug = get_user_meta( $user_id, 'slug', true ) ) )
			$slug = get_user_by( 'id', $user_id )->user_nicename;
		return site_url( "author/$slug" );
	}

	// flush_rules() if our rules are not yet included
	function wp_loaded(){
		$rules = get_option( 'rewrite_rules' );

		if ( ! isset( $rules['author/([^/]+)/?$'] ) ) {
			global $wp_rewrite;
			$wp_rewrite->flush_rules();
		}
	}

	// Adding a new rule
	function rewrite_rules_array( $rules ) {
		$newrules = array();
		$newrules['author/([^/]+)/?$'] = 'index.php?author_name=$matches[1]';
		return $newrules + $rules;
	}

	// Adding the id var so that WP recognizes it
	function query_vars( $vars ) {
		array_push( $vars, 'author_slug' );
		return $vars;
	}

	function pre_get_posts( $query ) {
		if ( is_admin() ) return;

		if ( ! isset( $query->query_vars['author_name'] ) ) return;

		$slug = $query->query_vars['author_name'];
		//check if custom slug
		$users = get_users( array( 'meta_key' => 'slug', 'meta_value' => $slug ) );
		if ( ! empty( $users ) ) {
			$query->set( 'author_name', $users[0]->user_nicename );
		} elseif ( $user = get_user_by( 'login', $slug ) ) {
			// try and correct if
			$meta = get_the_author_meta( 'slug', $user->ID );
			if ( ! empty( $meta ) ) {
				wp_redirect( site_url("author/$meta") );
				exit;
			}
		}
		// echo $slug;
	}
}