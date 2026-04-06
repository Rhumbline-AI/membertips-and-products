import { useState, useMemo } from 'react'
import { ExternalLink } from 'lucide-react'

function extractDomain(url) {
  if (!url) return ''
  try {
    const host = new URL(url).hostname
    return host.replace(/^www\./, '')
  } catch {
    return url
  }
}

function useImageFallback(ogImage, domain) {
  const sources = useMemo(() => {
    const s = []
    if (ogImage) s.push(ogImage)
    if (domain) {
      s.push(`https://logo.clearbit.com/${domain}`)
      s.push(
        `https://t3.gstatic.com/faviconV2?client=SOCIAL&type=FAVICON&fallback_opts=TYPE,SIZE,URL&url=http://${domain}&size=128`
      )
    }
    return s
  }, [ogImage, domain])

  const [failedCount, setFailedCount] = useState(0)

  const currentSrc = failedCount < sources.length ? sources[failedCount] : null

  function handleError() {
    setFailedCount((c) => c + 1)
  }

  return { src: currentSrc, onError: handleError }
}

export default function ProductCard({ product }) {
  const { title, url, description } = product
  const domain = extractDomain(url)
  const { src: imgSrc, onError } = useImageFallback(product.ogImage, domain)

  const inner = (
    <>
      <div className="fsf-card-image">
        {imgSrc ? (
          <img src={imgSrc} alt={title} loading="lazy" onError={onError} />
        ) : (
          <div className="fsf-card-placeholder">
            <span>{title.charAt(0)}</span>
          </div>
        )}
      </div>
      <div className="fsf-card-body">
        <h3 className="fsf-card-title">{title}</h3>
        <p className="fsf-card-desc">{description}</p>
        {domain && (
          <span className="fsf-card-url">
            {domain}
            <ExternalLink size={12} style={{ marginLeft: 4, opacity: 0.6 }} />
          </span>
        )}
      </div>
    </>
  )

  if (url) {
    return (
      <a href={url} target="_blank" rel="noopener noreferrer" className="fsf-card">
        {inner}
      </a>
    )
  }

  return <div className="fsf-card fsf-card--no-link">{inner}</div>
}
