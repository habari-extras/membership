<?php

class Membership extends Plugin
{
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

}

class MembershipTokens implements FormStorage {
	/**
	 * Compare the saved Option with the new value in the form field.
	 * Create tokens that don't yet exist, and destroy those that shouldn't.
	 *
	 * @param string $key The name of the form field to store.
	 * @param mixed $value The value of the form field
	 */
	public function field_save( $key, $value )
	{
		if( $key == 'tokens' ) {
			$old_tokens = array_values( Options::get( 'membership__tokens', array() ) ); //discard the keys
			$new_tokens = array_map( 'trim', explode( "\n", $value ) );

			// create tokens for newly added
			foreach( array_diff( $new_tokens, $old_tokens ) as $description ) {
				ACL::create_token(
					"membership_" . ACL::normalize_token( $description ), // prefixed, normalized name
					$description, // description as entered
					"membership" // group = "membership"
				);
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
	 */
	public function field_load( $key )
	{
		if( $key == 'tokens' ) {
			return implode( "\n", Options::get( 'membership__tokens', array() ) );
		}
	}

}
?>
