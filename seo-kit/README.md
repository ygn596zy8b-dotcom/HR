# Headline Recorder: Google Search Console kit

Everything you need to submit headlinerecorder.com to Google (and Bing) for SEO.

## 1. Verify the site (once)
1. Open https://search.google.com/search-console and click **Add property**.
2. Choose **Domain** and type `headlinerecorder.com`.
3. Google shows a TXT record. Add it in your DNS (Cloudflare: DNS > Records > Add > Type TXT, Name `@`, paste the value). Click **Verify**.
   (Alternative: choose **URL prefix** `https://headlinerecorder.com/` and verify with Yoast SEO > Settings > Site connections > Google, paste the meta code.)

## 2. Submit the sitemaps
Search Console > **Sitemaps** > "Add a new sitemap", submit each of these (one at a time):

| Sitemap | What it lists |
|---|---|
| `sitemap.xml` | Every article, page, category and podcast episode (main index) |
| `sitemap-news.xml` | Google News: articles from the last 2 days |

Both are generated live by the site, so they are always current. Do not upload XML files: just submit the addresses.

## 3. Google News (optional, recommended)
Open https://publishercenter.google.com, add the publication "Headline Recorder", URL `https://headlinerecorder.com/`, category Business, country Pakistan, language English, and add the RSS feed `https://headlinerecorder.com/feed/`.

## 4. Check the important pages
Search Console > **URL inspection**: paste the home page, one article and `/podcast/`, then click **Request indexing**.

## 5. Bing and AI search (5 minutes)
1. https://www.bing.com/webmasters > **Import from Google Search Console** (one click).
2. Bing also feeds ChatGPT search and Copilot. `robots.txt` already allows the AI search crawlers, and `/llms.txt` gives AI engines a clean summary of the site.

## 6. What to watch weekly
- **Pages** report: anything under "Not indexed" with reason "Crawled, currently not indexed" or "Duplicate".
- **Core Web Vitals**: should stay green (LCP < 2.5 s, INP < 200 ms, CLS < 0.1).
- **Enhancements**: Breadcrumbs, Articles: zero errors.

## Files in this folder
- `robots.txt`: the robots file the site serves (the live copy is at https://headlinerecorder.com/robots.txt).
- `urls-to-inspect.txt`: key URLs to request indexing for after launch.
