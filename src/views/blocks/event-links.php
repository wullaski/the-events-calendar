<?php
/**
 * Renders the event links block
 *
 * @version 0.3.0-alpha
 *
 */
// don't show on password protected posts
if ( post_password_required() ) {
	return;
}

$has_google_cal = $this->attr( 'hasGoogleCalendar' );
$has_ical       = $this->attr( 'hasiCal' );

$should_render = $has_google_cal || $has_ical;

remove_filter( 'the_content', 'do_blocks', 9 );
?>
<?php if ( $should_render ) : ?>
    <div class="tribe-block tribe-block__events-link">
		<?php if ( $has_google_cal ) : ?>
            <div class="tribe-block__btn--link tribe-block__events-gcal">
                <a
                    href="<?php echo Tribe__Events__Main::instance()->esc_gcal_url( tribe_get_gcal_link() ); ?>"
                    title="<?php esc_attr_e( 'Add to Google Calendar', 'events-gutenberg' ); ?>"
                >
                    <img src="<?php echo tribe( 'gutenberg' )->extension_url . 'src/modules/icons/link.svg'; ?>" />
                    <?php echo esc_html( $this->attr( 'googleCalendarLabel' ) ) ?>
                </a>
            </div>
		<?php endif; ?>
		<?php if ( $has_ical ) : ?>
            <div class="tribe-block__btn--link tribe-block__-events-ical">
                <a
                    href="<?php echo esc_url( tribe_get_single_ical_link() ); ?>"
                    title="<?php esc_attr_e( 'Download .ics file', 'events-gutenberg' ); ?>"
                >
                    <img src="<?php echo tribe( 'gutenberg' )->extension_url . 'src/modules/icons/link.svg'; ?>" />
                    <?php echo esc_html( $this->attr( 'iCalLabel' ) ) ?>
                </a>
            </div>
		<?php endif; ?>
    </div>
<?php endif; ?>

<?php add_filter( 'the_content', 'do_blocks', 9 );
