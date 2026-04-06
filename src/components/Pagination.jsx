import { ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight } from 'lucide-react'

export default function Pagination({ currentPage, totalPages, onPageChange }) {
  function pageNumbers() {
    const pages = []
    const maxVisible = 5
    let start = Math.max(1, currentPage - Math.floor(maxVisible / 2))
    let end = Math.min(totalPages, start + maxVisible - 1)

    if (end - start + 1 < maxVisible) {
      start = Math.max(1, end - maxVisible + 1)
    }

    if (start > 1) {
      pages.push(1)
      if (start > 2) pages.push('...')
    }

    for (let i = start; i <= end; i++) {
      pages.push(i)
    }

    if (end < totalPages) {
      if (end < totalPages - 1) pages.push('...')
      pages.push(totalPages)
    }

    return pages
  }

  return (
    <nav className="fsf-pagination" aria-label="Product pagination">
      <button
        onClick={() => onPageChange(1)}
        disabled={currentPage === 1}
        className="fsf-page-btn"
        aria-label="First page"
      >
        <ChevronsLeft size={16} />
      </button>
      <button
        onClick={() => onPageChange(currentPage - 1)}
        disabled={currentPage === 1}
        className="fsf-page-btn"
        aria-label="Previous page"
      >
        <ChevronLeft size={16} />
      </button>

      {pageNumbers().map((page, i) =>
        page === '...' ? (
          <span key={`ellipsis-${i}`} className="fsf-page-ellipsis">
            &hellip;
          </span>
        ) : (
          <button
            key={page}
            onClick={() => onPageChange(page)}
            className={`fsf-page-btn ${page === currentPage ? 'fsf-page-active' : ''}`}
            aria-current={page === currentPage ? 'page' : undefined}
          >
            {page}
          </button>
        )
      )}

      <button
        onClick={() => onPageChange(currentPage + 1)}
        disabled={currentPage === totalPages}
        className="fsf-page-btn"
        aria-label="Next page"
      >
        <ChevronRight size={16} />
      </button>
      <button
        onClick={() => onPageChange(totalPages)}
        disabled={currentPage === totalPages}
        className="fsf-page-btn"
        aria-label="Last page"
      >
        <ChevronsRight size={16} />
      </button>
    </nav>
  )
}
