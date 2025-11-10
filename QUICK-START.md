# Quick Start Guide

## 🚀 Get Started in 3 Steps

### 1️⃣ Install
- Upload `gsc-error-fix.php` to `/wp-content/plugins/gsc-error-fix/`
- Or create a ZIP and upload via WordPress Admin

### 2️⃣ Activate
- Go to WordPress Admin → Plugins
- Find "GSC Error Fix - Product Schema"
- Click "Activate"

### 3️⃣ Verify
- Visit any product page
- View source and search for `application/ld+json`
- Test with [Google Rich Results Test](https://search.google.com/test/rich-results)

## ✅ What This Plugin Does

Automatically adds **structured data** to all WooCommerce products:

| Schema Type | What It Includes | When Added |
|-------------|------------------|------------|
| **Offers** | Price, currency, availability, seller info | All products |
| **Review** | Reviewer name, rating, comment, date | Products with reviews |
| **AggregateRating** | Average rating, review count | Products with reviews |

## 🎯 Benefits

- ✅ Fixes Google Search Console schema errors
- ✅ Enables Google Rich Results (star ratings in search)
- ✅ Improves click-through rates from Google
- ✅ Makes products eligible for rich snippets
- ✅ Zero configuration needed

## 📋 Requirements

- WordPress 5.0+
- PHP 7.0+
- WooCommerce (active)

## 🔍 Common Questions

**Q: Will this create duplicate schema if I use Yoast/Rank Math?**  
A: It may. Consider deactivating other schema plugins or use filters to customize output.

**Q: How long until Google shows rich results?**  
A: Google needs to re-crawl your pages (typically 1-4 weeks). Request re-indexing in Search Console for faster results.

**Q: Does it work with variable products?**  
A: Yes! It creates multiple offers for each variation.

**Q: Can I customize the schema?**  
A: Yes! 5 WordPress filters available (see README.md).

## 🛠️ Troubleshooting

| Problem | Solution |
|---------|----------|
| "WooCommerce required" error | Install and activate WooCommerce first |
| Schema not appearing | Clear cache, check you're on a product page |
| Plugin won't activate | Check PHP version (7.0+ required) |

## 📚 More Information

- **Full Documentation**: See [README.md](README.md)
- **Installation Guide**: See [INSTALLATION.md](INSTALLATION.md)
- **Schema Examples**: See [SCHEMA-EXAMPLES.md](SCHEMA-EXAMPLES.md)

## 🔗 Useful Links

- [Google Rich Results Test](https://search.google.com/test/rich-results)
- [Google Search Console](https://search.google.com/search-console)
- [Schema.org Validator](https://validator.schema.org/)
- [GitHub Repository](https://github.com/dratzymarcano/gscerrorfix)

## 📊 What Gets Fixed

This plugin ensures all products have **at least one** required property for Google Rich Results:

```
Before Plugin:
❌ Product missing offers
❌ Product missing review
❌ Product missing aggregateRating
⚠️  Not eligible for rich results

After Plugin:
✅ Offers schema added
✅ Reviews schema added (if reviews exist)
✅ AggregateRating added (if reviews exist)
✅ Eligible for Google Rich Results
```

## 💡 Pro Tips

1. **Test Immediately**: Use Google Rich Results Test right after activation
2. **Monitor GSC**: Check Google Search Console → Enhancements → Products
3. **Request Re-indexing**: Speed up Google's re-crawl in Search Console
4. **Keep Updated**: Ensure WooCommerce and WordPress are current
5. **Check Mobile**: Rich results appear on mobile search too

## 📞 Need Help?

- Check [INSTALLATION.md](INSTALLATION.md) for detailed troubleshooting
- Review [SCHEMA-EXAMPLES.md](SCHEMA-EXAMPLES.md) to see expected output
- Open an issue on [GitHub](https://github.com/dratzymarcano/gscerrorfix)

---

**Version**: 1.0.0  
**Author**: Dratzy Marcano  
**License**: GPL v2 or later
