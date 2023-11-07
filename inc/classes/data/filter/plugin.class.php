<?php
/**
 * @package The_SEO_Framework\Classes\Data\Filter\Plugin
 * @subpackage The_SEO_Framework\Data\Plugin
 */

namespace The_SEO_Framework\Data\Filter;

\defined( 'THE_SEO_FRAMEWORK_PRESENT' ) or die;

use function \The_SEO_Framework\has_run;

use The_SEO_Framework\{
	Data,
	Helper\Taxonomy,
	Helper\Post_Type,
	Meta,
};

/**
 * The SEO Framework plugin
 * Copyright (C) 2023 Sybre Waaijer, CyberWire B.V. (https://cyberwire.nl/)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 3 as published
 * by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Holds a collection of plugin option sanitization methods.
 *
 * @since 4.3.0
 * @access private
 */
class Plugin {

	/**
	 * @since 4.3.0
	 * @var array[] The option filter sanitization callbacks.
	 */
	private static $sanitizers = [];

	/**
	 * Filters the settings whenever updated.
	 * Only hooked in admin.
	 *
	 * @hook "sanitize_option . THE_SEO_FRAMEWORK_SITE_OPTIONS" 10
	 * @since 2.2.2
	 * @since 4.3.0 1. Moved from `\The_SEO_Framework\Load`.
	 *              2. Renamed from `sanitize`.
	 *
	 * @param mixed  $value          The sanitized [sic] option value.
	 * @param string $option         The option name.
	 * @param string $original_value The original value passed to the function.
	 * @return mixed The actually sanitized option value.
	 */
	public static function filter_settings_update( $value, $option, $original_value ) {

		// Revert on erroneous input.
		if ( empty( $value ) || ! \is_array( $value ) )
			return $original_value;

		static::register_sanitizers_jit();

		// Use our filterable options as a fallback instead.
		$original_value = array_merge(
			Data\Plugin\Setup::get_default_options(),
			Data\Plugin::get_options(),
		);

		/**
		 * @since 4.3.0
		 * @param array $filters A map of filters and their callbacks : {
		 *    string option_name => callable[] A list of callbacks,
		 * }
		 */
		$sanitizers = \apply_filters(
			'the_seo_framework_settings_update_sanitizers',
			static::$sanitizers,
		);

		foreach ( $value as $key => &$val ) {
			if ( isset( $sanitizers[ $key ] ) ) {
				foreach ( $sanitizers[ $key ] as $callback ) {
					$val = \call_user_func_array(
						$callback,
						[
							$val,
							$original_value[ $key ], // If this fails, the option isn't registered properly. Error is good.
							$option,
						],
					);
				}
			} else {
				unset( $value[ $key ] );
			}
		}

		return $value;
	}

	/**
	 * Add sanitization filters to suboptions.
	 * Will only set filters if they don't already exists. This allows for other
	 * developers to add their custom filters before we do --- use filter
	 * `'sanitize_option_' . \THE_SEO_FRAMEWORK_SITE_OPTIONS`.
	 *
	 * @since 4.3.0
	 *
	 * @param array $filters A map of filters and their callbacks : {
	 *    string option_name => callable|callable[] callback,
	 * }
	 */
	public static function register_sanitizers( $filters ) {

		// Remit FETCH_STATIC_PROP_R opcode calls every time we'd otherwise use static::$filters hereinafter.
		$_sanitizers = &static::$sanitizers;

		foreach ( $filters as $option => $callbacks ) {
			if ( \is_array( $callbacks[0] ) ) {
				$_sanitizers[ $option ] ??= $callbacks;
			} else {
				$_sanitizers[ $option ] ??= [ $callbacks ];
			}
		}
	}

	/**
	 * Registers each of the settings with a sanitization filter type.
	 *
	 * @since 2.8.0
	 * @since 3.1.0 Added caching, preventing duplicate registrations.
	 * @since 4.3.0 1. Moved from `\The_SEO_Framework\Load`.
	 *              2. Renamed from `init_sanitizer_filters`.
	 */
	public static function register_sanitizers_jit() {

		if ( has_run( __METHOD__ ) ) return;

		$filters = [
			'advanced_query_protection'    => 'checkbox',
			'alter_archive_query'          => 'checkbox',
			'alter_archive_query_type'     => 'alter_query_type',
			'alter_search_query_type'      => 'alter_query_type',
			'alter_search_query'           => 'checkbox',
			'author_noarchive'             => 'checkbox',
			'author_nofollow'              => 'checkbox',
			'author_noindex'               => 'checkbox',
			'auto_description'             => 'checkbox',
			'auto_description_html_method' => 'auto_description_method',
			'baidu_verification'           => 'verification_code',
			'bing_verification'            => 'verification_code',
			'cache_sitemap'                => 'checkbox',
			'canonical_scheme'             => 'canonical_scheme',
			'date_noarchive'               => 'checkbox',
			'date_nofollow'                => 'checkbox',
			'date_noindex'                 => 'checkbox',
			'disabled_post_types'          => [ 'disabled_post_types', 'checkbox_array' ],
			'disabled_taxonomies'          => [ 'disabled_taxonomies', 'checkbox_array' ],
			'display_character_counter'    => 'checkbox',
			'display_pixel_counter'        => 'checkbox',
			'display_seo_bar_metabox'      => 'checkbox',
			'display_seo_bar_tables'       => 'checkbox',
			'excerpt_the_feed'             => 'checkbox',
			'facebook_author'              => 'facebook_profile_link',
			'facebook_publisher'           => 'facebook_profile_link',
			'facebook_tags'                => 'checkbox',
			'google_verification'          => 'verification_code',
			'home_paged_noindex'           => 'checkbox',
			'home_title_location'          => 'title_location',
			'homepage_description'         => 'metadata_text',
			'homepage_noarchive'           => 'checkbox',
			'homepage_nofollow'            => 'checkbox',
			'homepage_noindex'             => 'checkbox',
			'homepage_og_description'      => 'metadata_text',
			'homepage_og_title'            => 'metadata_text',
			'homepage_social_image_id'     => 'absolute_integer',
			'homepage_social_image_url'    => 'fully_qualified_url',
			'homepage_tagline'             => 'checkbox',
			'homepage_title_tagline'       => 'metadata_text',
			'homepage_title'               => 'metadata_text',
			'homepage_twitter_description' => 'metadata_text',
			'homepage_twitter_title'       => 'metadata_text',
			'index_the_feed'               => 'checkbox',
			'knowledge_facebook'           => 'fully_qualified_url',
			'knowledge_instagram'          => 'fully_qualified_url',
			'knowledge_linkedin'           => 'fully_qualified_url',
			'knowledge_logo_id'            => 'absolute_integer',
			'knowledge_logo_url'           => 'fully_qualified_url',
			'knowledge_logo'               => 'checkbox',
			'knowledge_name'               => 'metadata_text',
			'knowledge_output'             => 'checkbox',
			'knowledge_pinterest'          => 'fully_qualified_url',
			'knowledge_soundcloud'         => 'fully_qualified_url',
			'knowledge_tumblr'             => 'fully_qualified_url',
			'knowledge_twitter'            => 'fully_qualified_url',
			'knowledge_type'               => 'knowledge_type',
			'knowledge_youtube'            => 'fully_qualified_url',
			'ld_json_breadcrumbs'          => 'checkbox',
			'ld_json_enabled'              => 'checkbox',
			'ld_json_searchbox'            => 'checkbox',
			'max_image_preview'            => 'copyright_image_size',
			'max_snippet_length'           => 'copyright_content_length',
			'max_video_preview'            => 'copyright_content_length',
			'multi_og_image'               => 'checkbox',
			'oembed_remove_author'         => 'checkbox',
			'oembed_scripts'               => 'checkbox',
			'oembed_use_og_title'          => 'checkbox',
			'oembed_use_social_image'      => 'checkbox',
			'og_tags'                      => 'checkbox',
			'paged_noindex'                => 'checkbox',
			'ping_bing'                    => 'checkbox',
			'ping_google'                  => 'checkbox',
			'ping_use_cron_prerender'      => 'checkbox',
			'ping_use_cron'                => 'checkbox',
			'pint_verification'            => 'verification_code',
			'post_modify_time'             => 'checkbox',
			'post_publish_time'            => 'checkbox',
			'prev_next_archives'           => 'checkbox',
			'prev_next_frontpage'          => 'checkbox',
			'prev_next_posts'              => 'checkbox',
			'pta'                          => 'pta_meta',
			'search_noarchive'             => 'checkbox',
			'search_nofollow'              => 'checkbox',
			'search_noindex'               => 'checkbox',
			'seo_bar_symbols'              => 'checkbox',
			'set_copyright_directives'     => 'checkbox',
			'shortlink_tag'                => 'checkbox',
			'site_noarchive'               => 'checkbox',
			'site_nofollow'                => 'checkbox',
			'site_noindex'                 => 'checkbox',
			'site_title'                   => 'metadata_text',
			'sitemap_color_accent'         => 'rgb_hex',
			'sitemap_color_main'           => 'rgb_hex',
			'sitemap_logo_id'              => 'absolute_integer',
			'sitemap_logo_url'             => 'fully_qualified_url',
			'sitemap_logo'                 => 'checkbox',
			'sitemap_query_limit'          => 'sitemap_query_limit',
			'sitemap_styles'               => 'checkbox',
			'sitemaps_modified'            => 'checkbox',
			'sitemaps_output'              => 'checkbox',
			'sitemaps_robots'              => 'checkbox',
			'social_image_fb_id'           => 'absolute_integer',
			'social_image_fb_url'          => 'fully_qualified_url',
			'social_title_rem_additions'   => 'checkbox',
			'source_the_feed'              => 'checkbox',
			'theme_color'                  => 'rgb_hex',
			'timestamps_format'            => 'numeric_string',
			'title_location'               => 'title_location',
			'title_rem_additions'          => 'checkbox',
			'title_rem_prefixes'           => 'checkbox',
			'title_separator'              => 'title_separator',
			'title_strip_tags'             => 'checkbox',
			'twitter_card'                 => 'twitter_card',
			'twitter_creator'              => 'twitter_profile_handle',
			'twitter_site'                 => 'twitter_profile_handle',
			'twitter_tags'                 => 'checkbox',
			'yandex_verification'          => 'verification_code',

			// phpcs:disable, WordPress.Arrays.MultipleStatementAlignment.LongIndexSpaceBeforeDoubleArrow -- it fine.
			Data\Plugin\Helper::get_robots_option_index( 'post_type', 'noarchive' ) => 'checkbox_array',
			Data\Plugin\Helper::get_robots_option_index( 'post_type', 'nofollow' )  => 'checkbox_array',
			Data\Plugin\Helper::get_robots_option_index( 'post_type', 'noindex' )   => 'checkbox_array',
			Data\Plugin\Helper::get_robots_option_index( 'taxonomy', 'noarchive' )  => 'checkbox_array',
			Data\Plugin\Helper::get_robots_option_index( 'taxonomy', 'nofollow' )   => 'checkbox_array',
			Data\Plugin\Helper::get_robots_option_index( 'taxonomy', 'noindex' )    => 'checkbox_array',
			// phpcs:enable, WordPress.Arrays.MultipleStatementAlignment.LongIndexSpaceBeforeDoubleArrow
		];

		// Remit FETCH_CLASS_NAME opcode, which performs a function call to check if it's valid.
		$sanitizer_class = static::class;

		foreach ( $filters as &$callbacks ) {
			if ( \is_array( $callbacks ) ) {
				foreach ( $callbacks as &$cb )
					$cb = [ $sanitizer_class, $cb ];
			} else {
				$callbacks = [ $sanitizer_class, $callbacks ];
			}
		}

		static::register_sanitizers( $filters );
	}

	/**
	 * @since 4.3.0
	 *
	 * @param mixed $value An unsanitized value.
	 * @return int A boolean as a string (1 or 0) option value.
	 */
	public static function checkbox( $value ) {
		return Sanitize::boolean_integer( $value );
	}

	/**
	 * @since 4.3.0
	 *
	 * @param mixed $value     An unsanitized value.
	 * @param mixed $old_value The last known value.
	 * @return string A valid query type alteration option value.
	 */
	public static function alter_query_type( $value, $old_value ) {

		switch ( $value ) {
			case 'in_query':
			case 'post_query':
				return $value;
		}

		return $old_value;
	}

	/**
	 * @since 4.3.0
	 *
	 * @param mixed $value     An unsanitized value.
	 * @param mixed $old_value The last known value.
	 * @return string A sanitized verification code value.
	 */
	public static function auto_description_method( $value, $old_value ) {

		switch ( $value ) {
			case 'fast':
			case 'accurate':
			case 'thorough':
				return $value;
		}

		return $old_value;
	}

	/**
	 * @since 4.3.0
	 *
	 * @param mixed $value An unsanitized value.
	 * @return string A sanitized verification code value.
	 */
	public static function verification_code( $value ) {

		// Extract the content if it hasn't already in JS.
		if ( str_contains( '<', $value ) ) {
			$value = preg_match(
				'/\bcontent=(?:([\'"])([^$]*?)\g{-2}|([^\s\/>]+))/i',
				'$2',
				$matches,
			);

			// 3 = unquoted content, 2 = quoted content.
			$value = $matches[3] ?? $matches[2] ?? '';
		}

		return preg_replace( '/[^a-z\d_-]+/i', '', $value );
	}

	/**
	 * @since 4.3.0
	 *
	 * @param mixed $value     An unsanitized value.
	 * @param mixed $old_value The last known value.
	 * @return string A valid canonical scheme option value.
	 */
	public static function canonical_scheme( $value, $old_value ) {

		switch ( $value ) {
			case 'automatic':
			case 'https':
			case 'http':
				return $value;
		}

		return $old_value;
	}

	/**
	 * @since 4.3.0
	 *
	 * @param mixed $value     An unsanitized value.
	 * @return array A valid disabled post type setting.
	 */
	public static function disabled_post_types( $value ) {

		if ( empty( $value ) || ! \is_array( $value ) ) return [];

		foreach ( Post_Type::get_all_forced_supported() as $forced )
			unset( $value[ $forced ] );

		return $value;
	}

	/**
	 * @since 4.3.0
	 *
	 * @param mixed $value An unsanitized value.
	 * @return array A valid disabled post type setting.
	 */
	public static function disabled_taxonomies( $value ) {

		if ( empty( $value ) || ! \is_array( $value ) ) return [];

		foreach ( Taxonomy::get_all_forced_supported() as $forced )
			unset( $value[ $forced ] );

		return $value;
	}

	/**
	 * @since 4.3.0
	 *
	 * @param mixed $value An unsanitized value.
	 * @return int[] An array of boolean as a string (1 or 0).
	 */
	public static function checkbox_array( $value ) {

		if ( empty( $value ) || ! \is_array( $value ) ) return [];

		foreach ( $value as &$val )
			$val = Sanitize::boolean_integer( $val );

		return $value;
	}

	/**
	 * @since 4.3.0
	 *
	 * @param mixed $value An unsanitized value.
	 * @return string The sanitized Facebook profile URL.
	 */
	public static function facebook_profile_link( $value ) {
		return Sanitize::facebook_profile_link( $value );
	}

	/**
	 * @since 4.3.0
	 *
	 * @param mixed $value     An unsanitized value.
	 * @param mixed $old_value The last known value.
	 * @return string Either left or right.
	 */
	public static function title_location( $value, $old_value ) {

		switch ( $value ) {
			case 'left':
			case 'right':
				return $value;
		}

		return $old_value;
	}

	/**
	 * @since 4.3.0
	 *
	 * @param mixed $value An unsanitized value.
	 * @return string A sanitized single line of metadata text.
	 */
	public static function metadata_text( $value ) {
		return Sanitize::metadata_content( $value );
	}

	/**
	 * @since 4.3.0
	 *
	 * @param mixed $value An unsanitized value.
	 * @return string Either left or right.
	 */
	public static function absolute_integer( $value ) {
		return \absint( $value );
	}

	/**
	 * @since 4.3.0
	 *
	 * @param mixed $value An unsanitized value.
	 * @return string A fully qualified sanitized URL that matches the current scheme if current domain.
	 */
	public static function fully_qualified_url( $value ) {

		if ( empty( $value ) ) return '';

		return \sanitize_url(
			Meta\URI\Utils::make_absolute_current_scheme_url( $value ),
			[ 'https', 'http' ],
		);
	}

	/**
	 * @since 4.3.0
	 *
	 * @param mixed $value     An unsanitized value.
	 * @param mixed $old_value The last known value.
	 * @return string A valid knowledge type option value.
	 */
	public static function knowledge_type( $value, $old_value ) {

		switch ( $value ) {
			case 'person':
			case 'organization':
				return $value;
		}

		return $old_value;
	}

	/**
	 * @since 4.3.0
	 *
	 * @param mixed $value     An unsanitized value.
	 * @param mixed $old_value The last known value.
	 * @return string A valid copyright image size option value.
	 */
	public static function copyright_image_size( $value, $old_value ) {

		switch ( $value ) {
			case 'none':
			case 'standard':
			case 'large':
				return $value;
		}

		return $old_value;
	}

	/**
	 * @since 4.3.0
	 *
	 * @param mixed $value An unsanitized value.
	 * @return int A valid copyright content length option value.
	 */
	public static function copyright_content_length( $value ) {
		// At least -1, at most 600.
		return max( -1, min( 600, (int) $value ) );
	}

	/**
	 * @since 4.3.0
	 *
	 * @param mixed $value An unsanitized value.
	 * @return string A valid copyright content length option value.
	 */
	public static function rgb_hex( $value ) {
		return Sanitize::rgb_hex( $value );
	}

	/**
	 * @since 4.3.0
	 *
	 * @param mixed $value     An unsanitized value.
	 * @param mixed $old_value The last known value.
	 * @return int A valid sitemap query limit.
	 */
	public static function sitemap_query_limit( $value, $old_value ) {
		return max(
			1,
			min(
				50000,
				\absint( $value ) ?: $old_value,
			)
		);
	}

	/**
	 * @since 4.3.0
	 *
	 * @param mixed $value An unsanitized value.
	 * @return string An integer as string.
	 */
	public static function numeric_string( $value ) {
		return Sanitize::numeric_string( $value );
	}

	/**
	 * @since 4.3.0
	 *
	 * @param mixed $value     An unsanitized value.
	 * @param mixed $old_value The last known value.
	 * @return string A valid title separator.
	 */
	public static function title_separator( $value, $old_value ) {

		if ( \array_key_exists( $value, Meta\Title\Utils::get_separator_list() ) )
			return $value;

		return $old_value;
	}

	/**
	 * @since 4.3.0
	 *
	 * @param mixed $value     An unsanitized value.
	 * @param mixed $old_value The last known value.
	 * @return string A valid Twitter card type.
	 */
	public static function twitter_card( $value, $old_value ) {

		if ( 'auto' === $value ) // var_Dump() is this implemented?
			return $value;

		if ( \in_array( $value, Meta\Twitter::get_supported_cards(), true ) )
			return $value;

		return $old_value;
	}

	/**
	 * @since 4.3.0
	 * @link <https://help.twitter.com/en/managing-your-account/change-x-handle>
	 *
	 * @param mixed $value An unsanitized value.
	 * @return string A valid title separator.
	 */
	public static function twitter_profile_handle( $value ) {
		return Sanitize::twitter_profile_handle( $value );
	}

	/**
	 * @since 4.3.0
	 *
	 * @param mixed $value An unsanitized value.
	 * @return array[] The sanitized post type archive meta.
	 */
	public static function pta_meta( $value ) {

		if ( empty( $value ) )
			return [];

		// Do NOT test for post type's existence -- it might be registered too late (incorrectly).
		// If the metadata yields empty -- do not unset key! It'll override "defaults" that way.
		foreach ( $value as &$meta ) {
			foreach ( $meta as $key => &$val ) {
				switch ( $key ) {
					case 'doctitle':
					case 'og_title':
					case 'tw_title':
					case 'description':
					case 'og_description':
					case 'tw_description':
						$val = Sanitize::metadata_content( $val );
						continue 2;

					case 'canonical':
					case 'social_image_url':
						$val = \sanitize_url( $val, [ 'https', 'http' ] );
						continue 2;

					case 'social_image_id':
						// Bound to social_image_url.
						$val = $meta['social_image_url'] ? \absint( $val ) : 0;
						continue 2;

					case 'noindex':
					case 'nofollow':
					case 'noarchive':
						$val = Sanitize::qubit( $val );
						continue 2;

					case 'redirect':
						// Allow all protocols also allowed by WP:
						$val = \sanitize_url( $val );
						continue 2;

					case 'title_no_blog_name':
						$val = Sanitize::boolean_integer( $val );
						continue 2;

					default:
						unset( $meta[ $key ] );
				}
			}
		}

		return $value;
	}
}
