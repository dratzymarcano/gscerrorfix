# Example Schema Output

This file demonstrates the JSON-LD schema output that the plugin generates for WooCommerce products.

## Example 1: Simple Product with Reviews

```json
{
  "@context": "https://schema.org/",
  "@type": "Product",
  "name": "Premium Coffee Beans",
  "sku": "COFFEE-001",
  "description": "High-quality arabica coffee beans from Colombia",
  "image": "https://example.com/wp-content/uploads/coffee.jpg",
  "brand": {
    "@type": "Brand",
    "name": "Beverages"
  },
  "offers": {
    "@type": "Offer",
    "url": "https://example.com/product/premium-coffee-beans/",
    "price": "24.99",
    "priceCurrency": "USD",
    "availability": "https://schema.org/InStock",
    "seller": {
      "@type": "Organization",
      "name": "My WooCommerce Store",
      "url": "https://example.com"
    },
    "itemCondition": "https://schema.org/NewCondition"
  },
  "aggregateRating": {
    "@type": "AggregateRating",
    "ratingValue": "4.5",
    "reviewCount": "12",
    "bestRating": "5",
    "worstRating": "1"
  },
  "review": [
    {
      "@type": "Review",
      "reviewRating": {
        "@type": "Rating",
        "ratingValue": "5",
        "bestRating": "5",
        "worstRating": "1"
      },
      "author": {
        "@type": "Person",
        "name": "John Doe"
      },
      "datePublished": "2024-01-15T10:30:00+00:00",
      "reviewBody": "Great coffee! The flavor is amazing and the beans are fresh."
    }
  ]
}
```

## Example 2: Variable Product with Multiple Offers

```json
{
  "@context": "https://schema.org/",
  "@type": "Product",
  "name": "Cotton T-Shirt",
  "sku": "TSHIRT-VAR",
  "description": "Comfortable cotton t-shirt available in multiple colors and sizes",
  "image": "https://example.com/wp-content/uploads/tshirt.jpg",
  "brand": {
    "@type": "Brand",
    "name": "Clothing"
  },
  "offers": [
    {
      "@type": "Offer",
      "url": "https://example.com/product/cotton-tshirt/",
      "price": "19.99",
      "priceCurrency": "USD",
      "availability": "https://schema.org/InStock",
      "seller": {
        "@type": "Organization",
        "name": "My WooCommerce Store",
        "url": "https://example.com"
      },
      "itemCondition": "https://schema.org/NewCondition"
    },
    {
      "@type": "Offer",
      "url": "https://example.com/product/cotton-tshirt/",
      "price": "19.99",
      "priceCurrency": "USD",
      "availability": "https://schema.org/InStock",
      "seller": {
        "@type": "Organization",
        "name": "My WooCommerce Store",
        "url": "https://example.com"
      },
      "itemCondition": "https://schema.org/NewCondition"
    }
  ]
}
```

## Example 3: Product with Sale Price

```json
{
  "@context": "https://schema.org/",
  "@type": "Product",
  "name": "Smart Watch",
  "sku": "WATCH-001",
  "description": "Advanced fitness tracking smart watch",
  "image": "https://example.com/wp-content/uploads/watch.jpg",
  "offers": {
    "@type": "Offer",
    "url": "https://example.com/product/smart-watch/",
    "price": "149.99",
    "priceCurrency": "USD",
    "availability": "https://schema.org/InStock",
    "priceValidFrom": "2024-01-01T00:00:00+00:00",
    "priceValidUntil": "2024-01-31T23:59:59+00:00",
    "seller": {
      "@type": "Organization",
      "name": "My WooCommerce Store",
      "url": "https://example.com"
    },
    "itemCondition": "https://schema.org/NewCondition"
  }
}
```

## Example 4: Out of Stock Product

```json
{
  "@context": "https://schema.org/",
  "@type": "Product",
  "name": "Limited Edition Sneakers",
  "sku": "SHOES-LIMITED",
  "description": "Rare collector's edition sneakers",
  "offers": {
    "@type": "Offer",
    "url": "https://example.com/product/limited-edition-sneakers/",
    "price": "299.99",
    "priceCurrency": "USD",
    "availability": "https://schema.org/OutOfStock",
    "seller": {
      "@type": "Organization",
      "name": "My WooCommerce Store",
      "url": "https://example.com"
    },
    "itemCondition": "https://schema.org/NewCondition"
  }
}
```

## Validation

You can validate the schema output using:
- [Google Rich Results Test](https://search.google.com/test/rich-results)
- [Schema.org Validator](https://validator.schema.org/)

Simply copy the JSON-LD from your product page source and paste it into these tools.

## Benefits

This schema markup:
1. ✅ Fixes Google Search Console errors for missing offers, review, and aggregateRating
2. ✅ Makes products eligible for Google Rich Results (Product Rich Snippets)
3. ✅ Improves click-through rates from search results
4. ✅ Displays star ratings, prices, and availability in search results
5. ✅ Follows Google's structured data guidelines
