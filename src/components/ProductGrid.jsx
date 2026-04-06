import ProductCard from './ProductCard'

export default function ProductGrid({ products }) {
  if (products.length === 0) {
    return (
      <div className="fsf-empty">
        <p>No products match the selected filters.</p>
      </div>
    )
  }

  return (
    <div className="fsf-card-grid">
      {products.map((product, i) => (
        <ProductCard key={`${product.title}-${i}`} product={product} />
      ))}
    </div>
  )
}
