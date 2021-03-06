<?php
/**
 * Class Tribe__Events__Editor__Configuration
 *
 * @since 4.7
 */
class Tribe__Events__Editor__Configuration implements Tribe__Editor__Configuration_Interface  {

	/**
	 * Hook used to attach actions / filters
	 *
	 * @since 4.7
	 */
	public function hook() {
		add_filter( 'tribe_editor_config', array( $this, 'editor_config' ) );
	}

	/**
	 * Add custom variables to be localized
	 *
	 * @since 4.7
	 *
	 * @param array $editor_config
	 * @return array
	 */
	public function editor_config( $editor_config ) {
		$tec = empty( $editor_config['events'] ) ? array() : $editor_config['events'];
		$editor_config['events'] = array_merge( (array) $tec, $this->localize() );
		return $editor_config;
	}

	/**
	 * Return the variables to be localized
	 *
	 * @since 4.7
	 *
	 * @return array
	 */
	public function localize() {
		return array(
			'settings'      => tribe( 'events.editor.settings' )->get_options(),
			'timezoneHTML'  => tribe_events_timezone_choice( Tribe__Events__Timezones::get_event_timezone_string() ),
			'priceSettings' => array(
				'defaultCurrencySymbol'   => tribe_get_option( 'defaultCurrencySymbol', '$' ),
				'defaultCurrencyPosition' => (
					tribe_get_option( 'reverseCurrencyPosition', false ) ? 'suffix' : 'prefix'
				),
				'isNewEvent' => tribe( 'context' )->is_new_post(),
			),
			'editor'        => array(
				'isClassic' => $this->post_is_from_classic_editor( tribe_get_request_var( 'post', 0 ) ),
			),
			'googleMap'     => array(
				'zoom' => apply_filters( 'tribe_events_single_map_zoom_level', (int) tribe_get_option( 'embedGoogleMapsZoom', 8 ) ),
				'key'  => tribe_get_option( 'google_maps_js_api_key' ),
			),
		);
	}


	/**
	 * Check if post is from classic editor
	 *
	 * @since 4.7
	 *
	 * @param int|WP_Post $post
	 *
	 * @return bool
	 */
	public function post_is_from_classic_editor( $post ) {
		if ( ! $post instanceof WP_Post ) {
			$post = get_post( $post );
		}

		if ( empty( $post ) || ! ( $post instanceof WP_Post ) ) {
			return false;
		}

		/** @var Tribe__Editor $editor */
		$editor = tribe( 'editor' );
		return tribe_is_truthy( get_post_meta( $post->ID, $editor->key_flag_classic_editor, true ) );
	}
}