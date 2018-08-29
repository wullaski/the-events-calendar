<?php

/**
 * "Additional Fields" implementation.
 *
 * Allows users to create additional fields via the Events > Settings > Additional Fields
 * tab that will then become common to all events and can be set via the event editor.
 *
 * Events's dditional fields are stored in the post meta table and may be recorded
 * in two different ways. One is a historical form that also provides fast retrieval,
 * where multiple values are contained in a pipe-separated format within a single record,
 * ie:
 *
 *     meta_key       meta_value
 *     -------------  -----------
 *     _ecp_custom_x  apples|greengages|oranges
 *
 * Multiple values like the above example of several tasty fruits available
 * at a theoretical market event will additionally be stored in separate records, closer
 * to how WordPress does things organically:
 *
 *     meta_key        meta_value
 *     -------------   -----------
 *     __ecp_custom_x  apples
 *     __ecp_custom_x  greengages
 *     __ecp_custom_x  oranges
 *
 * Note the key for the second arrangement differs by a single leading underscore. This
 * facilitates easier and more flexible searching of additional fields when desired with
 * only a slight storage overhead. By default, this will only happen for field types that
 * support multiple values (such as the checkbox type).
 */
class Tribe__Events__Additional_Fields {

	/**
	 * List of field types supporting the assignment of multiple values.
	 *
	 * @since TBD
	 *
	 * @var array
	 */
	protected $multichoice_types = array(
		'checkbox'
	);

	/**
	 * Hook for Additional Fields
	 *
	 * @since TBD
	 */
	public function hook() {

		add_action( 'tribe_settings_do_tabs', array( $this, 'add_settings_tabs' ) );
		add_action( 'wp_ajax_remove_option', array( $this, 'remove_meta_field' ) );
		add_action( 'tribe_settings_after_content_tab_additional-fields', array( $this, 'event_meta_options' ) );
		add_action( 'tribe_events_details_table_bottom', array( $this, 'single_event_meta' ) );
		$this->add_save_single_meta_filter();
		add_filter( 'tribe_settings_validate_tab_additional-fields', array( $this, 'force_save_meta' ) );
		add_filter( 'tribe_events_csv_import_event_additional_fields', array( $this, 'import_additional_fields' ) );
		add_filter( 'tribe_events_importer_event_column_names', array( $this, 'importer_column_mapping' ) );

		// During EA imports the additional fields will not be modified so we suspend the class
		// filters to avoid emptying them.
		add_action( 'tribe_aggregator_before_insert_posts', array( $this, 'remove_save_single_meta_filter' ) );
		add_action( 'tribe_aggregator_after_insert_posts', array( $this, 'add_save_single_meta_filter' ) );

		add_action( 'tribe_events_single_event_meta_primary_section_end', array( $this, 'additional_fields' ) );
	}

	/**
	 * Add Additional Field Setting Tab
	 *
	 * @since TBD
	 */
	public function add_settings_tabs() {
		// The single-entry array at the end allows for the save settings button to be displayed.
		new Tribe__Settings_Tab( 'additional-fields', __( 'Additional Fields', 'tribe-events-calendar-pro' ), array(
			'priority' => 35,
			'fields'   => array( null ),
		) );
	}

	/**
	 * Given an array representing a additional field structure, or a string representing a field
	 * type, returns true if the type is considered "multichoice".
	 *
	 * @since TBD
	 *
	 * @param array|string $structure_or_type
	 *
	 * @return bool
	 */
	public function is_multichoice( $structure_or_type ) {
		$field_type = ( is_array( $structure_or_type ) && isset( $structure_or_type['type'] ) )
			? $structure_or_type['type']
			: $structure_or_type;

		$is_multichoice = in_array( $field_type, $this->get_multichoice_fields_list() );

		/**
		 * Controls whether the specified type should be considered "multichoice", which can impact
		 * whether or not individual post meta records are generated when storing the field.
		 *
		 * @var bool   $is_multichoice
		 * @var string $field_type
		 */
		return apply_filters( 'tribe_events_field_is_multichoice', $is_multichoice, $field_type );
	}

	/**
	 * Returns a list of additional field types deemed "multichoice" in nature.
	 *
	 * @since TBD
	 *
	 * @return array
	 */
	public function get_multichoice_fields_list() {
		static $field_list;

		// If we have already built our list of multichoice field types, return it directly!
		if ( isset( $field_list ) ) {
			return $field_list;
		}

		/**
		 * The list of additional field types to be considered "multichoice" (ie, where admins can
		 * assign multiple possible values to the same post).
		 *
		 * @var array $multichoice_types
		 */
		$field_list = (array) apply_filters( 'tribe_events_multichoice_field_types', $this->multichoice_types );
		return $field_list;
	}

	/**
	 * Removes an additional field from the database and from any events that may be using that field.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function remove_meta_field() {
		global $wpdb, $tribe_ecp;
		if ( ! isset( $tribe_ecp ) ) {
			$tribe_ecp = Tribe__Events__Main::instance();
		}

		if ( ! current_user_can( 'edit_tribe_events' ) ) {
			exit;
		}

		$options = Tribe__Settings_Manager::get_options();
		array_splice( $options['custom-fields'], $_POST['field'] - 1, 1 );
		Tribe__Settings_Manager::set_options( $options, false );

		$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->postmeta WHERE meta_key=%s", '_ecp_custom_' . $_POST['field'] ) );
		die();
	}

	/**
	 * Loads the additional field options screen
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function event_meta_options() {

		$core = Tribe__Events__Main::instance();

		// Grab the additional fields and append an extra blank row at the end
		$additional_fields   = tribe_get_option( 'custom-fields' );
		$additional_fields   = is_scalar( $additional_fields ) ? array() : (array) $additional_fields;
		$additional_fields[] = array();

		// Counts used to decide whether the "remove field" or "add another" should appear
		$total        = count( $additional_fields );
		$count        = 0;
		$add_another  = esc_html( __( 'Add another', 'tribe-events-calendar-pro' ) );
		$remove_field = esc_html( __( 'Remove', 'tribe-events-calendar-pro' ) );

		include $core->pluginPath . 'src/admin-views/event-additional-field-options.php';
	}

	/**
	 * Loads the additional field meta box on the event editor screen
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function single_event_meta() {
		$tribe_ecp    = Tribe__Events__Main::instance();
		$additional_fields = tribe_get_option( 'custom-fields' );

		$events_event_meta_template = Tribe__Events__Main::instance()->pluginPath . 'src/admin-views/event-additional-fields.php';
		$events_event_meta_template = apply_filters( 'tribe_events_event_meta_template', $events_event_meta_template );
		if ( ! empty( $events_event_meta_template ) ) {
			include( $events_event_meta_template );
		}
	}

	/**
	 * Saves the additional fields for a single event.
	 *
	 * In the case of fields where mutiple values have been assigned (or even if only
	 * a single value was assigned - but the field type itself _supports_ multiple
	 * values, such as a checkbox field) an additional set of records will be created
	 * storing each value in a separate row of the postmeta table.
	 *
	 * @since TBD
	 *
	 * @param $post_id
	 * @param $data
	 *
	 * @return void
	 * @see 'tribe_events_update_meta'
	 */
	public function save_single_event_meta( $post_id, $data = array() ) {
		$additional_fields = (array) tribe_get_option( 'custom-fields' );

		foreach ( $additional_fields as $additional_field ) {
			// If the field name (ie, "_ecp_custom_x") has not been set then we cannot store it
			if ( ! isset( $additional_field['name'] ) ) {
				continue;
			}

			$ordinary_field_name   = wp_kses_data( $additional_field['name'] );
			$searchable_field_name = '_' . $ordinary_field_name;

			// Grab the new value and reset the searchable records container
			$value              = $this->get_value_to_save( $additional_field['name'], $data );
			$searchable_records = array();

			// If multiple values have been assigned (ie, if this is a checkbox field or similar) then
			// build a single pipe-separated field and a list of individual records
			if ( is_array( $value ) ) {
				$ordinary_record    = esc_attr( implode( '|', str_replace( '|', '', $value ) ) );
				$searchable_records = $value;
			}
			// If we have only a single value we may still need to record an extra entry if the type
			// of field is multichoice in nature
			else {

				$searchable_records[] = $ordinary_record = wp_kses(
					$value,
					array(
						'a' => array(
							'href'   => array(),
							'title'  => array(),
							'target' => array(),
						),
						'b'      => array(),
						'i'      => array(),
						'strong' => array(),
						'em'     => array(),
					)
				);
			}

			// Store the combined field
			update_post_meta( $post_id, $ordinary_field_name, $ordinary_record );

			// If this is not a multichoice field *and* there is only a single value we can move to the
			// next record, otherwise we should continue and store each value individually
			if ( ! $this->is_multichoice( $additional_field ) && count( $searchable_records ) === 1 ) {
				continue;
			}

			// Kill all existing searchable additional fields first of all
			delete_post_meta( $post_id, $searchable_field_name );

			// Rebuild with the new values
			foreach ( $searchable_records as $single_value ) {
				add_post_meta( $post_id, $searchable_field_name, $single_value );
			}
		}
	}

	/**
	 * Checks passed metadata array for an additional field, returns its value
	 * If the value is not found in the passed array, checks the $_POST for the value
	 *
	 * @since TBD
	 *
	 * @param $name
	 * @param $data
	 *
	 * @return string
	 */
	private function get_value_to_save( $name, $data ) {
		$value = '';

		// $data takes precedence over $_POST but we want to check both
		if ( isset( $_POST ) ) {
			$data = array_merge( $data, $_POST );
		}

		// Is the field set and non-empty? Note that we make an exception for (string) '0'
		// which in this case we don't want to treat as being empty
		if ( isset( $data[ $name ] ) && ( $data[ $name ] === '0' || ! empty( $data[ $name ] ) ) ) {
			$value = $data[ $name ];
		}

		return $value;
	}

	/**
	 * Enforce saving on additional fields tab
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function force_save_meta() {
		$options = Tribe__Settings_Manager::get_options();
		$options = $this->save_meta_options( $options );
		Tribe__Settings_Manager::set_options( $options );
	}

	/**
	 * Add additional fields to the event array passed thru the importer
	 *
	 * @since TBD
	 */
	public function import_additional_fields( $import_fields ) {
		$additional_fields = (array) tribe_get_option( 'custom-fields' );
		foreach ( $additional_fields as $additional_field ) {
			if ( empty( $additional_field['name'] ) || empty( $additional_field['label'] ) ) {
				continue;
			}
			$import_fields[ $additional_field['name'] ] = $additional_field['label'];
		}
		return $import_fields;
	}

	/**
	 * Add additional fields to the column mapping passed to the importer
	 *
	 * @since TBD
	 */
	public function importer_column_mapping( $column_mapping ) {
		$additional_fields = (array) tribe_get_option( 'custom-fields' );
		foreach ( $additional_fields as $additional_field ) {
			if (
				! is_array( $additional_field )
				|| empty( $additional_field['name'] )
				|| ! isset( $additional_field['label'] )
			) {
				continue;
			}

			$column_mapping[ $additional_field['name'] ] = $additional_field['label'];
		}
		return $column_mapping;
	}

	/**
	 * Save/update the additional field structure.
	 *
	 * @since TBD
	 *
	 * @param $tribe_options
	 *
	 * @return array
	 */
	public function save_meta_options( $tribe_options ) {
		// The custom-fields key may not exist if not fields have been defined
		$tribe_options['custom-fields'] = isset( $tribe_options['custom-fields'] ) ? $tribe_options['custom-fields'] : array();

		// Maintain a record of the highest assigned custom field index
		$max_index = isset( $tribe_options['custom-fields-max-index'] )
			? $tribe_options['custom-fields-max-index']
			: count( $tribe_options['custom-fields'] ) + 1;

		// Clear the existing list of custom fields
		$tribe_options['custom-fields'] = array();

		foreach ( $_POST['custom-field'] as $index => $field ) {
			$name   = wp_kses( stripslashes( $_POST['custom-field'][ $index ] ), array() );
			$type   = 'text';
			$values = '';

			// For new fields, it's possible the type/value hasn't been defined (fallback to defaults if so)
			if ( isset( $_POST['custom-field-type'][ $index ] ) ) {
				$type   = wp_kses( stripslashes( $_POST['custom-field-type'][ $index ] ), array() );
				$values = wp_kses( stripslashes( $_POST['custom-field-options'][ $index ] ), array() );
			}

			// Remove empty lines
			$values = preg_replace( "/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\r\n", $values );
			$values = rtrim( $values );
			//Remove Vertical Bar for Checkbox Field
			$values = $type == 'checkbox' ? str_replace( '|', '', $values ) : $values;

			// The indicies of pre-existing custom fields begin with an underscore - so if
			// the index does not have an underscore we need to assign a new one
			if ( 0 === strpos( $index, '_' ) ) {
				$assigned_index = substr( $index, 1 );
			} else {
				$assigned_index = ++ $max_index;
			}

			if ( $name ) {
				$tribe_options['custom-fields'][ $assigned_index ] = array(
					'name'   => '_ecp_custom_' . $assigned_index,
					'label'  => $name,
					'type'   => $type,
					'values' => $values,
				);
			}
		}

		// Update the max index and return the updated options array
		$tribe_options['custom-fields-max-index'] = $max_index;

		return $tribe_options;
	}

	/**
	 * Retrieve an additional field's value by searching its label
	 * instead of its (more obscure) ID
	 *
	 * @since TBD
	 *
	 * @param  (string) $label, the label to search for
	 * @param  (int) $eventID (optional), the event to look for, defaults to global $post
	 *
	 * @return (string) value of the field
	 */
	public function get_additional_fields_by_label( $label, $eventID = null ) {
		$eventID           = Tribe__Events__Main::postIdHelper( $eventID );
		$additional_fields = tribe_get_option( 'custom-fields', false );
		if ( is_array( $additional_fields ) ) {
			foreach ( $additional_fields as $field ) {
				if ( $field['label'] == $label ) {
					return get_post_meta( $eventID, $field['name'], true );
				}
			}
		}
	}

	/**
	 * Render additional field data within the single event view meta section.
	 *
	 * @since TBD
	 */
	public function additional_fields() {
		tribe_get_template_part( 'modules/meta/additional-fields', null, array(
			'fields' => $this->tribe_get_additional_fields(),
		) );
	}

	/**
	 * Adds the filter that will save the event additional meta.
	 *
	 * @since TBD
	 */
	public function add_save_single_meta_filter() {
		add_action( 'tribe_events_update_meta', array( $this, 'save_single_event_meta' ), 10, 2 );
	}

	/**
	 * Removes the filter that will save the event additional meta.
	 *
	 * @since TBD
	 */
	public function remove_save_single_meta_filter() {
		remove_action( 'tribe_events_update_meta', array( $this, 'save_single_event_meta' ), 10 );
	}

	/**
	 * Determines whether or not to show the WordPress custom fields metabox for events.
	 *
	 * @since TBD
	 *
	 * @return bool
	 */
	public function display_custom_fields_metabox() {
		$show_box = tribe_get_option( 'disable_metabox_custom_fields' );
		if ( ! tribe_is_truthy( $show_box ) ) {
			remove_post_type_support( Tribe__Events__Main::POSTTYPE, 'custom-fields' );

			return false;
		}

		return true;
	}

	/**
	 * Get an array of additional fields
	 *
	 * @since TBD
	 *
	 * @param int $postId (optional)
	 *
	 * @return array $data of additional fields
	 *
	 */
	public function tribe_get_additional_fields( $postId = null ) {
		$postId       = Tribe__Events__Main::postIdHelper( $postId );
		$data         = array();
		$additional_fields = tribe_get_option( 'custom-fields', false );
		if ( is_array( $additional_fields ) ) {
			foreach ( $additional_fields as $field ) {
				$meta = str_replace( '|', ', ', get_post_meta( $postId, $field['name'], true ) );
				if ( $field['type'] == 'url' && ! empty( $meta ) ) {
					$url_label = $meta;
					$parseUrl  = parse_url( $meta );
					if ( empty( $parseUrl['scheme'] ) ) {
						$meta = "http://$meta";
					}
					$meta = sprintf( '<a href="%s" target="%s">%s</a>',
						esc_url( $meta ),
						apply_filters( 'tribe_get_event_website_link_target', '_self' ),
						apply_filters( 'tribe_get_event_website_link_label', $url_label )
						);
				}

				// Display $meta if not empty - making a special exception for (string) '0'
				// which in this context should be considered a valid, non-empty value
				if ( $meta || '0' === $meta ) {
					$data[ esc_html( $field['label'] ) ] = $meta; // $meta has been through wp_kses - links are allowed
				}
			}
		}

		/**
		 * Filter an Events Additional Fields
		 *
		 * @since TBD
		 *
		 * @param $data array an array of additional fields
		 */
		return apply_filters( 'tribe_get_additional_fields', $data );
	}
}
