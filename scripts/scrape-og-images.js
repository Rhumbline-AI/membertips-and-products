#!/usr/bin/env node

/**
 * Scrape OG images from product URLs and write them back into products.json.
 *
 * Usage:  node scripts/scrape-og-images.js
 *
 * - Reads src/data/products.json
 * - For each product with a URL (and no existing ogImage), fetches the page
 *   and extracts <meta property="og:image" content="...">
 * - Writes the enriched data back to products.json
 * - Rate-limited to 1 request per second to be polite
 */

import { readFileSync, writeFileSync } from 'fs'
import { resolve, dirname } from 'path'
import { fileURLToPath } from 'url'

const __dirname = dirname(fileURLToPath(import.meta.url))
const DATA_PATH = resolve(__dirname, '../src/data/products.json')
const DELAY_MS = 1000

function sleep(ms) {
  return new Promise((r) => setTimeout(r, ms))
}

function extractOgImage(html) {
  // property before content
  let match = html.match(
    /<meta[^>]+property=["']og:image["'][^>]+content=["']([^"']+)["']/i
  )
  if (match) return match[1]

  // content before property
  match = html.match(
    /<meta[^>]+content=["']([^"']+)["'][^>]+property=["']og:image["']/i
  )
  if (match) return match[1]

  return ''
}

async function fetchOgImage(url) {
  try {
    const controller = new AbortController()
    const timeout = setTimeout(() => controller.abort(), 15000)

    const res = await fetch(url, {
      signal: controller.signal,
      headers: {
        'User-Agent':
          'Mozilla/5.0 (compatible; FSF-OG-Scraper/1.0; +https://github.com/Rhumbline-AI)',
      },
      redirect: 'follow',
    })

    clearTimeout(timeout)

    if (!res.ok) {
      console.log(`  ⚠ HTTP ${res.status}`)
      return ''
    }

    const html = await res.text()
    return extractOgImage(html)
  } catch (err) {
    console.log(`  ⚠ ${err.message}`)
    return ''
  }
}

async function main() {
  const raw = readFileSync(DATA_PATH, 'utf-8')
  const products = JSON.parse(raw)

  let scraped = 0
  let skipped = 0
  let failed = 0

  for (let i = 0; i < products.length; i++) {
    const p = products[i]
    const label = `[${i + 1}/${products.length}] ${p.title}`

    if (p.ogImage) {
      console.log(`${label} — already has image, skipping`)
      skipped++
      continue
    }

    if (!p.url) {
      console.log(`${label} — no URL, skipping`)
      skipped++
      continue
    }

    console.log(`${label} — fetching ${p.url}`)
    const ogImage = await fetchOgImage(p.url)

    if (ogImage) {
      p.ogImage = ogImage
      console.log(`  ✓ ${ogImage}`)
      scraped++
    } else {
      console.log(`  ✗ no OG image found`)
      failed++
    }

    await sleep(DELAY_MS)
  }

  writeFileSync(DATA_PATH, JSON.stringify(products, null, 2) + '\n')

  console.log(`\nDone! ${scraped} scraped, ${skipped} skipped, ${failed} failed.`)
  console.log(`Updated ${DATA_PATH}`)
}

main()
