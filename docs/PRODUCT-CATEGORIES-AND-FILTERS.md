# Product Categories, Attributes, Search and Filters

## Catalogue structure

The catalogue uses a parent-and-child category structure. The included editable starter data is:

- Women
  - Pilates
  - Sportswear
  - Bathrobes
  - Streetwear

Categories may have a public image, description, SEO title, and SEO description. Only active categories can be opened on the storefront. The `Women` parent is not shown as a Collection filter; its child categories are.

## Product assignment

In **Admin → Products**, each product may have:

- one primary category;
- additional categories;
- available sizes;
- available colours;
- collection tags.

The primary category is used for concise product-card labelling and category sales reporting. Products created before this feature remain valid and are shown as **Uncategorised** until an administrator assigns categories.

Sizes and colours are availability metadata only. **Stock remains product-level**: there is one stock count for each product, rather than separate stock for each size/colour combination. No attribute IDs are sent to the cart, checkout, Stripe, DuitNow, or fulfilment flows.

## Storefront URLs and search

- All products: `/collection`
- Category landing page: `/collection/{category-slug}`
- New product-detail links: `/collection/products/{product-slug}`

The application still resolves legacy product URLs in the form `/collection/{product-slug}`. Active category slugs take precedence, so category slugs should not duplicate product slugs.

Collection search is server-side and matches active products by name, description, category, tag, and colour. The current products table has no SKU column, so SKU is deliberately not searched until a SKU field is introduced.

## Filter query parameters

Filters use query strings and remain in place while sorting and paginating:

```text
/collection?category[]=pilates&size[]=m&colour[]=black&tag[]=new-arrival&min_price=100&max_price=200&availability=in_stock&sort=price_asc
```

- Multiple values within a group use **OR** (`size[]=s&size[]=m`).
- Different groups use **AND** (Pilates **and** Black **and** S/M).
- Invalid or inactive attribute values are ignored safely.
- Valid sorts are `featured`, `newest`, `price_asc`, `price_desc`, `name_asc`, `best_selling`, and `highest_rated`.
- Best selling uses paid, non-cancelled orders only; highest rated uses approved reviews only.

## SEO

Category pages use their stored SEO title/description when provided and otherwise use their category name and description. Category URLs are included in the sitemap. Filtered Collection URLs retain a clean canonical Collection or category URL and are not added to the sitemap.

## Admin maintenance

Use these areas under **Admin**:

- Categories
- Sizes
- Colours
- Collection Tags

Deleting a category or attribute that is assigned to a product safely deactivates it instead. Deactivated categories and attributes stay attached to historical product data but do not appear in public filters or product attribute displays.

The `ProductCatalogueSeeder` is idempotent: it creates missing starter categories, sizes, colours, and tags with `firstOrCreate` and does not overwrite existing custom names or assignments.

## Commands

Run the normal, non-destructive migration and seed commands after deployment:

```powershell
php artisan migrate
php artisan db:seed --class=ProductCatalogueSeeder
php artisan optimize:clear
php artisan view:cache
php artisan test --filter=ProductCatalogueTest
```
