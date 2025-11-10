# GSC Schema Fix WordPress Plugin

A WordPress plugin that automatically fixes Google Search Console errors related to missing structured data properties (`offers`, `review`, or `aggregateRating`). This plugin helps make your content eligible for Google Search rich results.

## Features

- **Automatic Schema Detection**: Detects existing schema markup and only adds missing properties
- **Multiple Schema Types**: Supports Articles, Products, and WebPages
- **Configurable Options**: Customizable default values for ratings, reviews, and offers
- **E-commerce Integration**: Works with WooCommerce and Easy Digital Downloads
- **Admin Interface**: Easy-to-use settings page in WordPress admin
- **Clean Uninstall**: Properly removes all plugin data when uninstalled

## Installation

1. Upload the plugin files to `/wp-content/plugins/gsc-schema-fix/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings > GSC Schema Fix to configure the plugin

## Configuration

### Settings Page

Navigate to **Settings > GSC Schema Fix** in your WordPress admin to configure:

- **Enable Auto Rating**: Automatically add `aggregateRating` when missing
- **Default Rating Value**: Set the default rating (1-5 scale)
- **Default Rating Count**: Set the number of ratings
- **Enable Auto Offers**: Automatically add `offers` for products
- **Default Currency**: Set the currency for offers (USD, EUR, GBP, etc.)
- **Enable Auto Review**: Automatically add `review` when missing
- **Default Reviewer Name**: Set the default reviewer name

## How It Works

The plugin automatically:

1. **Detects** existing schema markup on your pages
2. **Identifies** missing required properties (`offers`, `review`, `aggregateRating`)
3. **Injects** the missing schema data using JSON-LD format
4. **Maintains** existing schema without conflicts

## Schema Types Supported

- **Article**: For blog posts and articles
- **Product**: For e-commerce products (with offers support)
- **WebPage**: For static pages

## E-commerce Integration

The plugin automatically detects prices from:
- WooCommerce products
- Easy Digital Downloads
- Custom price meta fields

## Google Search Console Errors Fixed

This plugin specifically addresses these GSC errors:
- "Either 'offers', 'review', or 'aggregateRating' should be specified"
- "Items with this issue are invalid"
- "Invalid items are not eligible for Google Search's rich results"

## Technical Details

- **JSON-LD Format**: Uses Google's recommended JSON-LD structured data format
- **Schema.org Compliant**: Follows official schema.org specifications
- **Performance Optimized**: Only loads on singular pages (posts, pages, products)
- **Conflict Prevention**: Detects existing markup to avoid duplicates

## Files Structure

```
gsc-schema-fix/
├── gsc-schema-fix.php      # Main plugin file
├── uninstall.php           # Clean uninstall handler
├── assets/
│   ├── admin.css          # Admin styling
│   └── admin.js           # Admin JavaScript
└── README.md              # This file
```

## Requirements

- WordPress 4.7 or higher
- PHP 7.0 or higher

## License

GPL v2 or later

## Support

For issues or feature requests, please visit the [GitHub repository](https://github.com/dratzymarcano/gscerrorfix).

## Changelog

### Version 1.0.0
- Initial release
- Automatic schema markup injection
- Admin configuration interface
- E-commerce integration support
