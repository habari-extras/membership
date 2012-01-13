<?php

class Membership extends Plugin
{
	/**
	 * Load text domain for i18n
	 **/
	public function action_init()
	{
		$this->load_text_domain( "membership" );
		$this->add_template( 'membership', dirname( __FILE__ ) . '/membership.php' );
	}

	public function configure()
	{
		$fs = new MembershipTokens(); // FormStorage
		$ui = new FormUI( "membership" );
		$tokens = $ui->append( 'textarea', 'tokens', $fs, _t( 'Tokens, one per line:', 'membership' ) );
		$tokens->rows = 4;
		$tokens->class[] = 'resizable';
		$tokens->add_validator( array( $this, "duplicate_validator" ) );
		$ui->on_success( array( $this, 'updated_config' ) );
		$ui->append( 'submit', 'save', _t( 'Save', 'membership' ) );
		return $ui;
	}

	public function duplicate_validator( $list, $control, $form )
	{
		$values = explode( "\n", $list );
		$values = array_map( 'trim', $values );
		$dupes = array_keys( array_filter( array_count_values( $values ), create_function( '$v', 'return $v > 1;' ) ) );

		if( count( $dupes ) ) {
			return array( _t( 'Tokens must be unique. There is more than one: %s', array( implode( ", ", $dupes ) ), 'membership' ) );
		}
		else {
			return array();
		}
	}

	public function updated_config( FormUI $ui )
	{
		Session::notice( _t( 'Tokens saved.' , 'membership' ) );
		$ui->save();
	}

	/**
	 * Add tokens to the publish form
	 **/
	public function action_form_publish( $form, $post )
	{

		$tokens = array();
		foreach( ACL::all_tokens() as $token) {
			if( $token->token_group == 'membership' ) {
				$tokens[ $token->id ] = $token->description;
			}
		}

		$settings = $form->publish_controls->append( 'fieldset', 'token_set', _t( 'Membership', 'membership' ) );
		$settings->append( 'checkboxes', 'tokens', 'null:null', _t( 'Membership', 'membership' ), $tokens );
		$settings->class = 'container formcontrol transparent';

		// If this is an existing post, see if it has tokens already
		if ( 0 != $post->id ) {
			$form->tokens->value = $post->has_tokens( array_keys( $tokens ) );
		}
	}

	/**
	 * Process tokens when the publish form is received
	 **/
	public function action_publish_post( $post, $form )
	{
		if( count( $form->tokens->value ) ) {
			$post->add_tokens( $form->tokens->value );
		}
	}

	/**
	 * Log this request in as a user if a key is present in the URL
	 * @param $user Comes in form Habari empty usually
	 * @return User The user to be identified as, if a key is present in the URL
	 */
	public function filter_user_identify($user)
	{
		if(isset($_GET['mkey'])) {
			list($id, $key) = explode('_', $_GET['mkey']);
			$key = $this->get_user_key($id);
			if($_GET['mkey'] == $key) {
				$user = User::get_by_id($id);
			}
		}
		return $user;
	}

	/**
	 * Get the key for a user, or make one, for the specified or current user
	 * @param null|User|integer $user Null for the current user, or the id or User object of a user
	 * @return string The key to use to pass identify
	 */
	public function get_user_key($user = null)
	{
		if($user) {
			$user = User::get($user);
		}
		else {
			$user = User::identify();
		}
		if($user->info->membership_key == '') {
			$user->info->membership_key = sha1(rand(0, 65535) . $user->id);
			$user->update();
		}

		return $user->id . '_' . $user->info->membership_key;
	}

	public function filter_block_list($block_list)
	{
		$block_list['membership'] = _t( 'Member Feed');
		return $block_list;
	}
	
	public function action_block_content_membership($block, $theme)
	{
		$content = 'zz';
		if(User::identify()->id != 0) {
			$content .= '<a href="' . URL::get('atom_feed', array('index' => 1, 'mkey' => $this->get_user_key())) . '">';
			$content .= _t('My feed', 'membership');
			$content .= '</a>';
		}
		$block->content = $content;
	}
	
}

class MembershipTokens implements FormStorage {
	/**
	 * Compare the saved Option with the new value in the form field.
	 * Create tokens that don't yet exist, and destroy those that shouldn't.
	 *
	 * @param string $key The name of the form field to store.
	 * @param mixed $value The value of the form field
	 **/
	public function field_save( $key, $value )
	{
		if( $key == 'tokens' ) {
			$old_tokens = array_values( Options::get( 'membership__tokens', array() ) ); //discard the keys
			$new_tokens = array_map( 'trim', explode( "\n", $value ) );

			// create tokens for newly added
			foreach( array_diff( $new_tokens, $old_tokens ) as $description ) {
				$name = "membership_" . ACL::normalize_token( $description );

				ACL::create_token(
					$name, // prefixed, normalized name
					$description, // description as entered
					"membership" // group = "membership"
				);
				// Deny the anonymous group access this new token
				$anon = UserGroup::get('anonymous');
				if ( false != $anon ) {
					$anon->deny( $name );
				}
			}

			// destroy tokens for newly removed
			foreach( array_diff( $old_tokens, $new_tokens) as $description ) {
				ACL::destroy_token( "membership_" . ACL::normalize_token( $description ) );
			}

			Options::set( 'membership__tokens', $new_tokens );
		}
	}

	/**
	 * Load the form value
	 *
	 * @param string $key Unused parameter, since this only ever uses one Option
	 * @return array
	 **/
	public function field_load( $key )
	{
		if( $key == 'tokens' ) {
			return implode( "\n", Options::get( 'membership__tokens', array() ) );
		}
	}

}
?>
