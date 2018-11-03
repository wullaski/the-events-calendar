/**
 * Wordpress dependenciess
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { getSetting, getPriceSettings } from '@moderntribe/events/editor/settings';
import { string } from '@moderntribe/common/utils';
import * as types from './types';

const position = string.isTruthy( getSetting( 'reverseCurrencyPosition', 0 ) )
	? 'suffix'
	: 'prefix';

export const DEFAULT_STATE = {
	position: getPriceSettings().default_currency_position || position,
	symbol: getPriceSettings().default_currency_symbol || __( '$', 'events-gutenberg' ),
	cost: '',
	description: '',
};

export default ( state = DEFAULT_STATE, action ) => {
	switch ( action.type ) {
		case types.SET_PRICE_COST:
			return {
				...state,
				cost: action.payload.cost,
			};
		case types.SET_PRICE_POSITION:
			return {
				...state,
				position: action.payload.position,
			};
		case types.SET_PRICE_SYMBOL:
			return {
				...state,
				symbol: action.payload.symbol,
			};
		case types.SET_PRICE_DESCRIPTION:
			return {
				...state,
				description: action.payload.description,
			};
		default:
			return state;
	}
};
