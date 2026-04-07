#!/usr/bin/env node

/**
 * Downloads external OG images into wordpress/fsf-product-grid/images/
 * and updates products.json to reference local filenames instead of URLs.
 *
 * Usage: node scripts/download-images.js
 */

import { readFileSync, writeFileSync, existsSync, mkdirSync, createWriteStream } from 'fs'
import { resolve, dirname } from 'path'
import { fileURLToPath } from 'url'
import { pipeline } from 'stream/promises'

const __dirname = dirname(fileURLToPath(import.meta.url))
const DATA_PATH = resolve(__dirname, '../src/data/products.json')
const IMAGES_DIR = resolve(__dirname, '../wordpress/fsf-product-grid/images/')
const DEV_IMAGES_DIR = resolve(__dirname, '../public/images/')
const DELAY_MS = 500

function sleep(ms) {
  return new Promise((r) => setTimeout(r, ms))
}

function slugify(str) {
  return str
    .toLowerCase()
    .replace(/['']/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
}

async function downloadImage(url, destPath) {
  try {
    const controller = new AbortController()
    const timeout = setTimeout(() => controller.abort(), 15000)

    const res = await fetch(url, {
      signal: controller.signal,
      headers: {
        'User-Agent':
          'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
      },
      redirect: 'follow',
    })

    clearTimeout(timeout)

    if (!res.ok) {
      console.log(`    HTTP ${res.status}`)
      return false
    }

    const contentType = res.headers.get('content-type') || ''
    if (!contentType.startsWith('image/') && !contentType.includes('svg')) {
      console.log(`    Not an image: ${contentType}`)
      return false
    }

    const fileStream = createWriteStream(destPath)
    await pipeline(res.body, fileStream)
    return true
  } catch (err) {
    console.log(`    ${err.message}`)
    return false
  }
}

function guessExtension(url) {
  const pathname = new URL(url).pathname.toLowerCase()
  if (pathname.endsWith('.svg')) return '.svg'
  if (pathname.endsWith('.png')) return '.png'
  if (pathname.endsWith('.jpg') || pathname.endsWith('.jpeg')) return '.jpg'
  if (pathname.endsWith('.webp')) return '.webp'
  if (pathname.endsWith('.gif')) return '.gif'
  return '.png'
}

async function main() {
  const products = JSON.parse(readFileSync(DATA_PATH, 'utf-8'))

  if (!existsSync(IMAGES_DIR)) mkdirSync(IMAGES_DIR, { recursive: true })
  if (!existsSync(DEV_IMAGES_DIR)) mkdirSync(DEV_IMAGES_DIR, { recursive: true })

  const withUrls = products.filter(
    (p) => p.ogImage && p.ogImage.startsWith('http')
  )
  console.log(
    `${withUrls.length} products have external image URLs to download\n`
  )

  let downloaded = 0
  let failed = 0

  for (let i = 0; i < withUrls.length; i++) {
    const p = withUrls[i]
    const slug = slugify(p.title)
    const ext = guessExtension(p.ogImage)
    const filename = `${slug}${ext}`
    const destWp = resolve(IMAGES_DIR, filename)
    const destDev = resolve(DEV_IMAGES_DIR, filename)

    console.log(`[${i + 1}/${withUrls.length}] ${p.title}`)

    if (existsSync(destWp)) {
      console.log(`    Already downloaded, skipping`)
      p.ogImage = filename
      downloaded++
      continue
    }

    console.log(`    ${p.ogImage}`)
    const ok = await downloadImage(p.ogImage, destWp)

    if (ok) {
      // Also copy to public/ for Vite dev server
      const wpData = readFileSync(destWp)
      writeFileSync(destDev, wpData)

      p.ogImage = filename
      downloaded++
      console.log(`    ✓ saved as ${filename}`)
    } else {
      console.log(`    ✗ download failed, keeping URL`)
      failed++
    }

    await sleep(DELAY_MS)
  }

  writeFileSync(DATA_PATH, JSON.stringify(products, null, 2) + '\n')

  console.log(
    `\nDone! ${downloaded} downloaded, ${failed} failed.`
  )
  console.log(`Images in: ${IMAGES_DIR}`)
  console.log(`Dev copies: ${DEV_IMAGES_DIR}`)
  console.log(`Updated: ${DATA_PATH}`)
}

main()
