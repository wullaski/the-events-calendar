import { registerBlockType } from '@wordpress/blocks';

import classicEventDetails from '@moderntribe/events/blocks/classic-event-details';

import eventDateTime from '@moderntribe/events/blocks/event-datetime';
import eventVenue from '@moderntribe/events/blocks/event-venue';
import eventOrganizer from '@moderntribe/events/blocks/event-organizer';
import eventLinks from '@moderntribe/events/blocks/event-links';
import eventPrice from '@moderntribe/events/blocks/event-price';
import eventCategory from '@moderntribe/events/blocks/event-category';
import eventTags from '@moderntribe/events/blocks/event-tags';
import eventWebsite from '@moderntribe/events/blocks/event-website';
import FeaturedImage from '@moderntribe/events/blocks/featured-image';
import { initStore } from '@moderntribe/events/data';

import './style.pcss';

initStore();

const blocks = [
	classicEventDetails,
	eventDateTime,
	eventVenue,
	eventOrganizer,
	eventLinks,
	eventPrice,
	eventCategory,
	eventTags,
	eventWebsite,
	FeaturedImage,
];

blocks.forEach( block => {
	const blockName = `tribe/${ block.id }`;
	registerBlockType( blockName, block );
} );

export default blocks;

