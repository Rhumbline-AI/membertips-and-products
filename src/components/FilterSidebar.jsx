export default function FilterSidebar({ categories, selected, totalResults, onChange }) {
  return (
    <aside className="fsf-sidebar">
      <div className="fsf-sidebar-header">
        <span className="fsf-sidebar-title">FILTERS</span>
        <span className="fsf-sidebar-count">{totalResults} Results</span>
      </div>

      <div className="fsf-filter-list">
        {categories.map((cat) => {
          const isChecked = selected.includes(cat.name)
          return (
            <label key={cat.name} className="fsf-filter-item">
              <input
                type="checkbox"
                checked={isChecked}
                onChange={() => onChange(cat.name)}
              />
              <span className="fsf-filter-name">{cat.name}</span>
              <span className="fsf-filter-count">{cat.count}</span>
            </label>
          )
        })}
      </div>
    </aside>
  )
}
