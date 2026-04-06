# FSF Product Grid

A filterable, paginated grid of member-recommended products — built as a React + Vite app that compiles into a WordPress plugin.

## Features

- **Faceted filtering** — sidebar checkboxes grouped by product category with live result counts
- **Client-side pagination** — 12 products per page, instant navigation
- **Static data** — all products bundled at build time from `src/data/products.json` (no database required)
- **OG image scraping** — Node scripts to auto-fetch brand logos/images from product URLs
- **Three-tier image fallback** — scraped OG image → Clearbit logo API → Google favicon → letter placeholder
- **Responsive layout** — CSS Grid: 3 columns → 2 → 1 across breakpoints
- **WordPress plugin wrapper** — drop-in via `[fsf_product_grid]` shortcode

## Project Structure

```
membertips-and-products/
├── src/
│   ├── main.jsx                    # Entry point — mounts to #fsf-product-grid
│   ├── App.jsx                     # Root — state, filtering, pagination logic
│   ├── index.css                   # Tailwind + global styles
│   ├── data/
│   │   └── products.json           # All products (static, bundled at build)
│   └── components/
│       ├── FilterSidebar.jsx       # Category checkboxes with counts
│       ├── ProductGrid.jsx         # CSS Grid of product cards
│       ├── ProductCard.jsx         # Card with image fallback, title, desc, URL
│       ├── Pagination.jsx          # Page navigation
│       └── styles.css              # Component CSS
│
├── scripts/
│   ├── scrape-og-images.js         # Scrape og:image from product URLs
│   └── scrape-logos.js             # Enhanced scraper (twitter:image, apple-touch-icon, etc.)
│
├── wordpress/
│   └── fsf-product-grid/
│       ├── fsf-product-grid.php    # WP plugin (shortcode + asset enqueue)
│       └── app/                    # Built JS/CSS output (Vite writes here)
│
├── vite.config.js                  # Dual-mode: dev server vs WordPress build
├── package.json
├── tailwind.config.js
└── postcss.config.js
```

## Development

```bash
# Install dependencies
npm install

# Start local dev server
npm run dev
```

Open [http://localhost:5173](http://localhost:5173) to view the app.

## Data Management

Products live in `src/data/products.json`. Each entry has:

```json
{
  "title": "Product Name",
  "url": "https://example.com",
  "description": "Short description",
  "category": "Category Name",
  "ogImage": "https://example.com/logo.png"
}
```

### Scrape OG Images

```bash
# First pass — og:image meta tags
npm run scrape-og

# Second pass — twitter:image, apple-touch-icon, favicons
node scripts/scrape-logos.js
```

## WordPress Deployment

```bash
# Build the React app into the WP plugin folder
npm run build:wp

# Zip the plugin
cd wordpress && zip -r ../fsf-product-grid.zip fsf-product-grid

# Upload via WP Admin → Plugins → Add New → Upload Plugin
# Add [fsf_product_grid] shortcode to any page
```

## Tech Stack

- **React 19** + **Vite 6**
- **Tailwind CSS 4**
- **Lucide React** (icons)
- **Vanilla JS** filtering & pagination (no jQuery)
