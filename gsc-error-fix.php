<?php
/**
 * Plugin Name: GSC Error Fix - Product Schema
 * Plugin URI: https://github.com/dratzymarcano/gscerrorfix
 * Description: Automatically adds missing schema.org markup (offers, reviews, aggregateRating) to WooCommerce products to fix Google Search Console errors
 * Version: 1.0.0
 * Author: Dratzy Marcano
 * Author URI: https://github.com/dratzymarcano
 * Requires at least: 5.0
 * Requires PHP: 7.0
 * Text Domain: gsc-error-fix
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package GSC_Error_Fix
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main GSC Error Fix Plugin Class.
 */
class GSC_Error_Fix {

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	const VERSION = '1.0.0';

	/**
	 * Single instance of the class.
	 *
	 * @var GSC_Error_Fix
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return GSC_Error_Fix
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ) );
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
	}

	/**
	 * Initialize the plugin.
	 */
	public function init() {
		// Check if WooCommerce is active.
		if ( ! $this->is_woocommerce_active() ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			return;
		}

		// Hook into WooCommerce product pages.
		add_action( 'wp_footer', array( $this, 'add_product_schema' ), 99 );
	}

	/**
	 * Check if WooCommerce is active.
	 *
	 * @return bool
	 */
	private function is_woocommerce_active() {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Display admin notice if WooCommerce is not active.
	 */
	public function woocommerce_missing_notice() {
		?>
		<div class="notice notice-error">
			<p>
				<?php
				echo wp_kses_post(
					sprintf(
						/* translators: %s: Plugin name */
						__( '<strong>%s</strong> requires WooCommerce to be installed and active.', 'gsc-error-fix' ),
						'GSC Error Fix - Product Schema'
					)
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Add product schema to single product pages.
	 */
	public function add_product_schema() {
		// Only run on single product pages.
		if ( ! is_product() ) {
			return;
		}

		global $product;

		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return;
		}

		$schema = $this->generate_product_schema( $product );

		if ( empty( $schema ) ) {
			return;
		}

		// Allow filtering of the schema data.
		$schema = apply_filters( 'gsc_error_fix_product_schema', $schema, $product );

		// Output JSON-LD.
		echo "\n" . '<script type="application/ld+json">' . "\n";
		echo wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
		echo "\n" . '</script>' . "\n";
	}

	/**
	 * Generate product schema data.
	 *
	 * @param WC_Product $product Product object.
	 * @return array Schema data.
	 */
	private function generate_product_schema( $product ) {
		$schema = array(
			'@context' => 'https://schema.org/',
			'@type'    => 'Product',
			'name'     => $product->get_name(),
			'sku'      => $product->get_sku(),
		);

		// Add description if available.
		if ( $product->get_description() ) {
			$schema['description'] = wp_strip_all_tags( $product->get_description() );
		} elseif ( $product->get_short_description() ) {
			$schema['description'] = wp_strip_all_tags( $product->get_short_description() );
		}

		// Add image if available.
		$image_id = $product->get_image_id();
		if ( $image_id ) {
			$image_url = wp_get_attachment_image_url( $image_id, 'full' );
			if ( $image_url ) {
				$schema['image'] = $image_url;
			}
		}

		// Add brand if available (from product categories).
		$categories = $product->get_category_ids();
		if ( ! empty( $categories ) ) {
			$category = get_term( $categories[0], 'product_cat' );
			if ( $category && ! is_wp_error( $category ) ) {
				$schema['brand'] = array(
					'@type' => 'Brand',
					'name'  => $category->name,
				);
			}
		}

		// Add offers schema.
		$offers = $this->generate_offers_schema( $product );
		if ( ! empty( $offers ) ) {
			$schema['offers'] = $offers;
		}

		// Add aggregate rating and reviews if product has reviews.
		$review_count = $product->get_review_count();
		if ( $review_count > 0 ) {
			$aggregate_rating = $this->generate_aggregate_rating_schema( $product );
			if ( ! empty( $aggregate_rating ) ) {
				$schema['aggregateRating'] = $aggregate_rating;
			}

			$reviews = $this->generate_reviews_schema( $product );
			if ( ! empty( $reviews ) ) {
				$schema['review'] = $reviews;
			}
		}

		return apply_filters( 'gsc_error_fix_schema_data', $schema, $product );
	}

	/**
	 * Generate offers schema for a product.
	 *
	 * @param WC_Product $product Product object.
	 * @return array Offers schema.
	 */
	private function generate_offers_schema( $product ) {
		$offers = array();

		// Handle variable products.
		if ( $product->is_type( 'variable' ) ) {
			$variations = $product->get_available_variations();
			
			foreach ( $variations as $variation_data ) {
				$variation = wc_get_product( $variation_data['variation_id'] );
				if ( ! $variation ) {
					continue;
				}

				$offer = $this->create_single_offer( $variation );
				if ( ! empty( $offer ) ) {
					$offers[] = $offer;
				}
			}

			// If we have multiple offers, return them as an array.
			if ( count( $offers ) > 1 ) {
				return $offers;
			} elseif ( count( $offers ) === 1 ) {
				return $offers[0];
			}
		}

		// Handle simple products and other types.
		return $this->create_single_offer( $product );
	}

	/**
	 * Create a single offer schema.
	 *
	 * @param WC_Product $product Product object.
	 * @return array Offer schema.
	 */
	private function create_single_offer( $product ) {
		$offer = array(
			'@type' => 'Offer',
			'url'   => get_permalink( $product->get_id() ),
		);

		// Add price and currency.
		$price = $product->get_price();
		if ( $price ) {
			$offer['price']         = $price;
			$offer['priceCurrency'] = get_woocommerce_currency();
		}

		// Add availability.
		$offer['availability'] = $this->get_availability_schema( $product );

		// Add valid dates.
		$date_on_sale_from = $product->get_date_on_sale_from();
		if ( $date_on_sale_from ) {
			$offer['priceValidFrom'] = $date_on_sale_from->date( 'c' );
		}

		$date_on_sale_to = $product->get_date_on_sale_to();
		if ( $date_on_sale_to ) {
			$offer['priceValidUntil'] = $date_on_sale_to->date( 'c' );
		}

		// Add seller information.
		$offer['seller'] = array(
			'@type' => 'Organization',
			'name'  => get_bloginfo( 'name' ),
			'url'   => home_url(),
		);

		// Add item condition (assume new for all products).
		$offer['itemCondition'] = 'https://schema.org/NewCondition';

		return apply_filters( 'gsc_error_fix_offer_schema', $offer, $product );
	}

	/**
	 * Get availability schema URL based on product stock status.
	 *
	 * @param WC_Product $product Product object.
	 * @return string Availability schema URL.
	 */
	private function get_availability_schema( $product ) {
		$stock_status = $product->get_stock_status();

		$availability_map = array(
			'instock'     => 'https://schema.org/InStock',
			'outofstock'  => 'https://schema.org/OutOfStock',
			'onbackorder' => 'https://schema.org/PreOrder',
		);

		return isset( $availability_map[ $stock_status ] )
			? $availability_map[ $stock_status ]
			: 'https://schema.org/InStock';
	}

	/**
	 * Generate aggregate rating schema.
	 *
	 * @param WC_Product $product Product object.
	 * @return array|null Aggregate rating schema or null if no rating.
	 */
	private function generate_aggregate_rating_schema( $product ) {
		$average_rating = $product->get_average_rating();
		$review_count   = $product->get_review_count();

		if ( ! $average_rating || ! $review_count ) {
			return null;
		}

		$aggregate_rating = array(
			'@type'       => 'AggregateRating',
			'ratingValue' => $average_rating,
			'reviewCount' => $review_count,
			'bestRating'  => '5',
			'worstRating' => '1',
		);

		return apply_filters( 'gsc_error_fix_aggregate_rating_schema', $aggregate_rating, $product );
	}

	/**
	 * Generate reviews schema.
	 *
	 * @param WC_Product $product Product object.
	 * @return array Reviews schema.
	 */
	private function generate_reviews_schema( $product ) {
		$reviews_data = array();

		// Get product reviews.
		$args = array(
			'post_id' => $product->get_id(),
			'status'  => 'approve',
			'type'    => 'review',
			'number'  => 10, // Limit to 10 reviews to avoid overwhelming the schema.
		);

		$comments = get_comments( $args );

		foreach ( $comments as $comment ) {
			$rating = get_comment_meta( $comment->comment_ID, 'rating', true );

			// Skip comments without ratings.
			if ( ! $rating ) {
				continue;
			}

			$review = array(
				'@type'         => 'Review',
				'reviewRating'  => array(
					'@type'       => 'Rating',
					'ratingValue' => $rating,
					'bestRating'  => '5',
					'worstRating' => '1',
				),
				'author'        => array(
					'@type' => 'Person',
					'name'  => $comment->comment_author,
				),
				'datePublished' => get_comment_date( 'c', $comment ),
			);

			// Add review body if available.
			if ( ! empty( $comment->comment_content ) ) {
				$review['reviewBody'] = wp_strip_all_tags( $comment->comment_content );
			}

			$reviews_data[] = $review;
		}

		return apply_filters( 'gsc_error_fix_reviews_schema', $reviews_data, $product );
	}

	/**
	 * Activation hook.
	 */
	public function activate() {
		// Check if WooCommerce is active on activation.
		if ( ! $this->is_woocommerce_active() ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
			wp_die(
				wp_kses_post(
					__( 'GSC Error Fix - Product Schema requires WooCommerce to be installed and active. Please install WooCommerce first.', 'gsc-error-fix' )
				),
				esc_html__( 'Plugin Activation Error', 'gsc-error-fix' ),
				array( 'back_link' => true )
			);
		}

		// Set a transient to trigger a welcome notice.
		set_transient( 'gsc_error_fix_activation_notice', true, 60 );
	}

	/**
	 * Deactivation hook.
	 */
	public function deactivate() {
		// Clean up transients.
		delete_transient( 'gsc_error_fix_activation_notice' );
	}
}

/**
 * Initialize the plugin.
 */
function gsc_error_fix_init() {
	return GSC_Error_Fix::get_instance();
}

// Initialize the plugin.
gsc_error_fix_init();
