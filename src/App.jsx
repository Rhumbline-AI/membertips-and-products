import { useState, useMemo } from 'react'
import products from './data/products.json'
import FilterSidebar from './components/FilterSidebar'
import ProductGrid from './components/ProductGrid'
import Pagination from './components/Pagination'

const ITEMS_PER_PAGE = 12

function buildCategoryMap(products) {
  const map = {}
  for (const p of products) {
    const cat = p.category
    if (!map[cat]) map[cat] = 0
    map[cat]++
  }
  return Object.entries(map)
    .sort(([a], [b]) => a.localeCompare(b))
    .map(([name, count]) => ({ name, count }))
}

export default function App() {
  const [selectedCategories, setSelectedCategories] = useState([])
  const [currentPage, setCurrentPage] = useState(1)

  const categories = useMemo(() => buildCategoryMap(products), [])

  const filtered = useMemo(() => {
    if (selectedCategories.length === 0) return products
    return products.filter((p) => selectedCategories.includes(p.category))
  }, [selectedCategories])

  const totalResults = filtered.length
  const totalPages = Math.max(1, Math.ceil(totalResults / ITEMS_PER_PAGE))
  const safePage = Math.min(currentPage, totalPages)
  const pageProducts = filtered.slice(
    (safePage - 1) * ITEMS_PER_PAGE,
    safePage * ITEMS_PER_PAGE
  )

  function handleFilterChange(categoryName) {
    setSelectedCategories((prev) => {
      if (prev.includes(categoryName)) {
        return prev.filter((c) => c !== categoryName)
      }
      return [...prev, categoryName]
    })
    setCurrentPage(1)
  }

  return (
    <>
      <h1 className="fsf-heading">Member Recommended Products</h1>
      <div className="fsf-layout">
        <FilterSidebar
          categories={categories}
          selected={selectedCategories}
          totalResults={totalResults}
          onChange={handleFilterChange}
        />
        <div className="fsf-main">
          <ProductGrid products={pageProducts} />
          {totalPages > 1 && (
            <Pagination
              currentPage={safePage}
              totalPages={totalPages}
              onPageChange={setCurrentPage}
            />
          )}
        </div>
      </div>
    </>
  )
}
