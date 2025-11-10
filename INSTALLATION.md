# Installation Guide

## Prerequisites

Before installing the GSC Error Fix plugin, ensure you have:

1. WordPress 5.0 or higher
2. PHP 7.0 or higher
3. WooCommerce plugin installed and active

## Installation Methods

### Method 1: Direct File Upload (Recommended for Single File)

1. Download the `gsc-error-fix.php` file
2. Connect to your WordPress site via FTP or File Manager
3. Navigate to `/wp-content/plugins/`
4. Create a new folder called `gsc-error-fix`
5. Upload `gsc-error-fix.php` into the `gsc-error-fix` folder
6. Go to WordPress Admin → Plugins
7. Find "GSC Error Fix - Product Schema" in the list
8. Click "Activate"

### Method 2: WordPress Admin Upload (ZIP File)

1. Create a ZIP file containing `gsc-error-fix.php`
2. In WordPress Admin, go to Plugins → Add New
3. Click "Upload Plugin" button at the top
4. Choose your ZIP file and click "Install Now"
5. After installation, click "Activate Plugin"

### Method 3: Manual Installation via Command Line

```bash
# Navigate to your WordPress plugins directory
cd /path/to/wordpress/wp-content/plugins/

# Create plugin directory
mkdir gsc-error-fix

# Copy the plugin file
cp /path/to/gsc-error-fix.php gsc-error-fix/

# Set proper permissions
chmod 644 gsc-error-fix/gsc-error-fix.php
```

Then activate via WordPress Admin → Plugins.

## Post-Installation Steps

### 1. Verify WooCommerce is Active

After activation, the plugin will check if WooCommerce is active. If not, you'll see an error notice:

> **GSC Error Fix - Product Schema** requires WooCommerce to be installed and active.

**Solution**: Install and activate WooCommerce first, then activate this plugin.

### 2. Test the Schema Output

1. Visit any WooCommerce product page on your site
2. View the page source (Right-click → View Page Source)
3. Search for `application/ld+json`
4. You should see the schema markup in JSON-LD format

Example:
```html
<script type="application/ld+json">
{
  "@context": "https://schema.org/",
  "@type": "Product",
  "name": "Your Product Name",
  ...
}
</script>
```

### 3. Validate the Schema

Use Google's Rich Results Test to validate:

1. Go to [Google Rich Results Test](https://search.google.com/test/rich-results)
2. Enter your product page URL
3. Click "Test URL"
4. Check for any errors or warnings

### 4. Monitor Google Search Console

1. Open [Google Search Console](https://search.google.com/search-console)
2. Go to "Enhancements" → "Products"
3. Wait for Google to re-crawl your pages (can take days to weeks)
4. Check for reduced errors in the "Product" enhancement report

## Verification Checklist

- [ ] WordPress and PHP meet minimum version requirements
- [ ] WooCommerce is installed and active
- [ ] Plugin is activated successfully
- [ ] No error notices appear in WordPress Admin
- [ ] Schema markup appears in product page source
- [ ] Schema passes Google Rich Results Test
- [ ] Products have at least one of: offers, review, or aggregateRating

## Troubleshooting

### Plugin Won't Activate

**Issue**: "Plugin could not be activated because it triggered a fatal error"

**Solution**: 
- Check that PHP version is 7.0 or higher
- Ensure WooCommerce is installed and active
- Check error logs at `/wp-content/debug.log` (if debug mode is enabled)

### Schema Not Appearing

**Issue**: No JSON-LD schema in page source

**Solution**:
- Verify you're viewing a single product page (not shop page or archive)
- Clear all caching (WordPress cache, browser cache, CDN cache)
- Temporarily deactivate other schema/SEO plugins to check for conflicts
- Check if your theme overrides the `wp_footer` hook

### WooCommerce Missing Error

**Issue**: Error notice about WooCommerce not being active

**Solution**:
1. Go to Plugins → Installed Plugins
2. Find WooCommerce and click "Activate"
3. Refresh the page

### Conflict with Other Plugins

**Issue**: Duplicate schema or conflicts with other SEO plugins

**Solution**:
- If using Yoast SEO, Rank Math, or similar: They may already add schema
- You can use this plugin to supplement missing fields
- Or use the provided filters to customize which schema to output
- Consider deactivating other schema plugins if duplication occurs

## Advanced Configuration

### Using Filters

You can customize the schema output using WordPress filters. Add this to your theme's `functions.php`:

```php
// Customize the entire product schema
add_filter( 'gsc_error_fix_product_schema', function( $schema, $product ) {
    // Add custom fields
    $schema['customField'] = 'custom value';
    return $schema;
}, 10, 2 );

// Customize offers schema
add_filter( 'gsc_error_fix_offer_schema', function( $offer, $product ) {
    // Modify offer details
    return $offer;
}, 10, 2 );

// Customize aggregate rating
add_filter( 'gsc_error_fix_aggregate_rating_schema', function( $rating, $product ) {
    // Modify rating details
    return $rating;
}, 10, 2 );
```

## Uninstallation

To remove the plugin:

1. Go to Plugins → Installed Plugins
2. Find "GSC Error Fix - Product Schema"
3. Click "Deactivate"
4. Click "Delete"
5. Confirm deletion

The plugin will clean up after itself (removes transients).

## Support

For issues, questions, or contributions:
- GitHub: [https://github.com/dratzymarcano/gscerrorfix](https://github.com/dratzymarcano/gscerrorfix)
- Report bugs via GitHub Issues

## Next Steps

After successful installation:

1. ✅ Request re-indexing of your product pages in Google Search Console
2. ✅ Monitor the "Products" enhancement report for improvements
3. ✅ Use Google Rich Results Test to validate your schema
4. ✅ Wait for Google to re-crawl (typically 1-4 weeks)
5. ✅ Check search results for rich snippets (star ratings, prices)
