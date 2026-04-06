#!/usr/bin/env node

/**
 * Second-pass logo scraper for products that are still missing images.
 *
 * Strategies tried in order:
 * 1. og:image (re-attempt with a browser-like User-Agent)
 * 2. <meta name="twitter:image"> 
 * 3. <link rel="apple-touch-icon"> (usually 180x180, good quality)
 * 4. <link rel="icon" type="image/png"> with largest size
 * 5. Skip — Clearbit fallback will handle the rest at runtime
 */

import { readFileSync, writeFileSync } from 'fs'
import { resolve, dirname } from 'path'
import { fileURLToPath } from 'url'

const __dirname = dirname(fileURLToPath(import.meta.url))
const DATA_PATH = resolve(__dirname, '../src/data/products.json')
const DELAY_MS = 1200

const UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'

function sleep(ms) {
  return new Promise((r) => setTimeout(r, ms))
}

function extractImage(html) {
  // 1. og:image (both attribute orders)
  let m = html.match(/<meta[^>]+property=["']og:image["'][^>]+content=["']([^"']+)["']/i)
  if (m) return m[1]
  m = html.match(/<meta[^>]+content=["']([^"']+)["'][^>]+property=["']og:image["']/i)
  if (m) return m[1]

  // 2. twitter:image
  m = html.match(/<meta[^>]+(?:name|property)=["']twitter:image["'][^>]+content=["']([^"']+)["']/i)
  if (m) return m[1]
  m = html.match(/<meta[^>]+content=["']([^"']+)["'][^>]+(?:name|property)=["']twitter:image["']/i)
  if (m) return m[1]

  // 3. apple-touch-icon
  m = html.match(/<link[^>]+rel=["']apple-touch-icon[^"']*["'][^>]+href=["']([^"']+)["']/i)
  if (m) return m[1]

  // 4. Large PNG favicon
  m = html.match(/<link[^>]+rel=["']icon["'][^>]+href=["']([^"']+\.png[^"']*)["']/i)
  if (m) return m[1]

  return ''
}

function resolveUrl(base, path) {
  if (!path) return ''
  if (path.startsWith('http://') || path.startsWith('https://')) return path
  try {
    return new URL(path, base).href
  } catch {
    return ''
  }
}

function cleanUrl(url) {
  return url.replace(/&amp;/g, '&')
}

async function fetchImage(url) {
  try {
    const controller = new AbortController()
    const timeout = setTimeout(() => controller.abort(), 12000)

    const res = await fetch(url, {
      signal: controller.signal,
      headers: { 'User-Agent': UA },
      redirect: 'follow',
    })

    clearTimeout(timeout)

    if (!res.ok) {
      console.log(`    HTTP ${res.status}`)
      return ''
    }

    const html = await res.text()
    const raw = extractImage(html)
    if (!raw) return ''

    const resolved = resolveUrl(url, cleanUrl(raw))

    // Filter out junk
    if (resolved.includes('Liquid error')) return ''
    if (resolved.length < 10) return ''

    return resolved
  } catch (err) {
    console.log(`    ${err.message}`)
    return ''
  }
}

async function main() {
  const products = JSON.parse(readFileSync(DATA_PATH, 'utf-8'))

  const missing = products.filter((p) => !p.ogImage && p.url)
  console.log(`${missing.length} products need images (out of ${products.length} total)\n`)

  let found = 0

  for (let i = 0; i < missing.length; i++) {
    const p = missing[i]
    console.log(`[${i + 1}/${missing.length}] ${p.title}`)
    console.log(`    ${p.url}`)

    const img = await fetchImage(p.url)

    if (img) {
      p.ogImage = img
      found++
      console.log(`    ✓ ${img}`)
    } else {
      console.log(`    ✗ no image found`)
    }

    if (i < missing.length - 1) await sleep(DELAY_MS)
  }

  writeFileSync(DATA_PATH, JSON.stringify(products, null, 2) + '\n')

  console.log(`\nDone! Found images for ${found}/${missing.length} products.`)
  console.log(`Updated ${DATA_PATH}`)
}

main()
