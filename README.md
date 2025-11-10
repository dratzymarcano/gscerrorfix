# GSC Error Fix - Product Schema

A WordPress plugin that automatically fixes Google Search Console schema errors for WooCommerce products by adding proper structured data (schema.org markup).

## Description

This plugin automatically adds missing schema.org markup (offers, reviews, aggregateRating) to WooCommerce products to fix Google Search Console errors and make your products eligible for Google's rich results.

## Features

- **Auto-detects WooCommerce products** and adds missing schema markup
- **Offers Schema**: Includes price, currency, availability status, valid from/to dates, and seller information
- **Review Schema**: Adds proper Review schema with reviewer name, rating value, review body, and review date
- **AggregateRating Schema**: Displays average rating, review count, and rating scales
- **Variable Products Support**: Handles variable products with multiple offers
- **JSON-LD Format**: Outputs schema in JSON-LD format (recommended by Google)
- **WordPress Standards**: Follows WordPress coding standards with proper escaping and sanitization
- **Customizable**: Includes filters for customization
- **Compatible**: Works alongside WooCommerce's existing schema

## Requirements

- WordPress 5.0 or higher
- PHP 7.0 or higher
- WooCommerce (active)

## Installation

1. Download the plugin files
2. Upload the `gsc-error-fix.php` file to your `/wp-content/plugins/gsc-error-fix/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Ensure WooCommerce is installed and active

## Usage

Once activated, the plugin automatically adds structured data to all WooCommerce product pages. No configuration needed!

The plugin will:
- Add Offers schema to all products with pricing information
- Add Review and AggregateRating schema to products that have customer reviews
- Handle variable products by creating multiple offer entries

## Schema Output

The plugin generates JSON-LD structured data that includes:

### Product Schema
- Product name, SKU, description, and image
- Brand information (from product categories)

### Offers Schema
- Price and currency
- Availability status (In Stock, Out of Stock, Pre-Order)
- Valid from/to dates for sale prices
- Seller information
- Item condition (New)

### Review Schema (if reviews exist)
- Review rating (1-5 scale)
- Reviewer name
- Review date
- Review body/content

### AggregateRating Schema (if reviews exist)
- Average rating value
- Total review count
- Best rating (5)
- Worst rating (1)

## Filters

The plugin provides several filters for customization:

### `gsc_error_fix_product_schema`
Filter the complete product schema before output.

```php
add_filter( 'gsc_error_fix_product_schema', function( $schema, $product ) {
    // Modify schema here
    return $schema;
}, 10, 2 );
```

### `gsc_error_fix_schema_data`
Filter the schema data during generation.

```php
add_filter( 'gsc_error_fix_schema_data', function( $schema, $product ) {
    // Modify schema data here
    return $schema;
}, 10, 2 );
```

### `gsc_error_fix_offer_schema`
Filter individual offer schema.

```php
add_filter( 'gsc_error_fix_offer_schema', function( $offer, $product ) {
    // Modify offer here
    return $offer;
}, 10, 2 );
```

### `gsc_error_fix_aggregate_rating_schema`
Filter aggregate rating schema.

```php
add_filter( 'gsc_error_fix_aggregate_rating_schema', function( $aggregate_rating, $product ) {
    // Modify aggregate rating here
    return $aggregate_rating;
}, 10, 2 );
```

### `gsc_error_fix_reviews_schema`
Filter reviews schema.

```php
add_filter( 'gsc_error_fix_reviews_schema', function( $reviews, $product ) {
    // Modify reviews here
    return $reviews;
}, 10, 2 );
```

## Validation

After activating the plugin, you can validate your structured data using:
- [Google Rich Results Test](https://search.google.com/test/rich-results)
- [Schema.org Validator](https://validator.schema.org/)

## Troubleshooting

### WooCommerce Not Active Error
If you see an admin notice about WooCommerce not being active:
1. Install and activate WooCommerce
2. Refresh your WordPress admin page

### Schema Not Appearing
1. Ensure you're viewing a single product page
2. Check your theme's source code for the JSON-LD script tag
3. Clear any caching plugins

### Google Search Console Errors
After activating the plugin:
1. Wait for Google to re-crawl your pages (can take days to weeks)
2. Request re-indexing in Google Search Console for faster results
3. Use Google's Rich Results Test to verify the schema immediately

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This plugin is licensed under the GPL v2 or later.

## Author

Dratzy Marcano - [GitHub](https://github.com/dratzymarcano)

## Changelog

### 1.0.0
- Initial release
- Product schema with offers, reviews, and aggregateRating
- Support for variable products
- WordPress coding standards compliance
- Filters for customization
