<?php

namespace Ink\Helpers;

if ( ! defined( 'INK_FRAMEWORK' ) ) {
	exit();
}

/**
 * Class WordPress
 * @package Ink\Helpers
 *
 * Helper class providing an abstraction layer to WordPress core and utility methods.
 */
class WordPress
{
	/**
	 * Retrieve all categories
	 * @param bool|true $forVC Whether or not this list is retrieved to be displayed in a Visual Composer dropdown
	 * @param int $childOf The ID of the parent category
	 * @param bool|true $hideEmpty Whether or not to skip empty categories
	 * @return array
	 */
	public static function getBlogCategories( $forVC = true, $childOf = 0, $hideEmpty = true )
	{
		$args = [
			'type' => 'post',
			'child_of' => $childOf,
			'orderby' => 'name',
			'order' => 'ASC',
			'hide_empty' => $hideEmpty,
			'hierarchical' => 1,
			'taxonomy' => 'category',
			'pad_counts' => false
		];
		$cats = get_categories( $args );
		$out = [];
		if ( empty( $cats ) ) {
			return $out;
		}
		foreach ( $cats as $cat ) {
			if ( $forVC ) {
				$out[$cat->name] = $cat->term_id;
			}
			else {
				$out[$cat->term_id] = $cat->name;
			}
		}
		return $out;
	}

	/**
	 * Get the specified number of posts from the given category
	 * @param int|string $categoryID
	 * @param string $postType
	 * @param int $limit
	 * @return array
	 */
	public static function getPostsFromCategory( $categoryID = 1, $postType = 'post', $limit = 3 )
	{
		if ( is_string( $categoryID ) ) {
			$categoryID = get_cat_ID( $categoryID );
		}
		return get_posts( [
			'post_type' => $postType,
			'post_status' => 'publish',
			'category' => $categoryID,
			'numberposts' => $limit,
		] );
	}

	/**
	 * Get random posts (from category if specified)
	 * @param int $limit
	 * @param int|string $categoryID
	 * @return array
	 */
	public static function getRandomPosts( $limit = 3, $categoryID = 0 )
	{
		$args = [
			'post_type' => 'post',
			'post_status' => 'publish',
			'numberposts' => $limit,
			'orderby' => 'rand'
		];
		if ( ! empty( $categoryID ) ) {
			if ( is_string( $categoryID ) ) {
				$categoryID = get_cat_ID( $categoryID );
			}
			$args['category'] = $categoryID;
		}
		return get_posts( $args );
	}

	/**
	 * Get latest published posts
	 * @param int $limit
	 * @param int|string $categoryID
	 * @return array
	 */
	public static function getLatestPosts( $limit = 3, $categoryID = 0 )
	{
		$args = [
			'post_type' => 'post',
			'post_status' => 'publish',
			'numberposts' => $limit,
		];
		if ( ! empty( $categoryID ) ) {
			if ( is_string( $categoryID ) ) {
				$categoryID = get_cat_ID( $categoryID );
			}
			$args['category'] = $categoryID;
		}
		return get_posts( $args );
	}


}
