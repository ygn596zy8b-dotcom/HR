<?xml version="1.0" encoding="UTF-8"?>
<!--
  Headline Recorder: browser view of the RSS feeds (/feed/, category feeds and the podcast feed /podcast/feed/).
  Feed readers and podcast apps ignore this stylesheet and read the RSS itself; a person who opens a feed link in a
  browser sees a readable page that explains what the link is for and how to use it.
  The stylesheet's own address (from the xml-stylesheet instruction) gives the plugin's asset folder, so the CSS and
  the copy-button script load from the same origin without inline code. Author: MSBJ.
-->
<xsl:stylesheet version="1.0"
  xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
  xmlns:atom="http://www.w3.org/2005/Atom"
  xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd"
  xmlns:media="http://search.yahoo.com/mrss/"
  xmlns:dc="http://purl.org/dc/elements/1.1/"
  exclude-result-prefixes="atom itunes media dc">

  <xsl:output method="html" encoding="UTF-8" indent="yes" doctype-system="about:legacy-compat"/>

  <xsl:variable name="pi" select="/processing-instruction('xml-stylesheet')[contains(., 'feed.xsl')][1]"/>
  <xsl:variable name="xsl-href" select="substring-before(substring-after($pi, 'href=&quot;'), '&quot;')"/>
  <xsl:variable name="assets" select="substring-before($xsl-href, 'xsl/feed.xsl')"/>
  <xsl:variable name="ver">
    <xsl:if test="contains($xsl-href, '?')">?<xsl:value-of select="substring-after($xsl-href, '?')"/></xsl:if>
  </xsl:variable>

  <xsl:variable name="channel" select="/rss/channel"/>
  <xsl:variable name="is-podcast" select="boolean($channel/itunes:author or $channel/itunes:image or $channel/itunes:category)"/>
  <xsl:variable name="self">
    <xsl:choose>
      <xsl:when test="$channel/atom:link[@rel='self']/@href"><xsl:value-of select="$channel/atom:link[@rel='self']/@href"/></xsl:when>
      <xsl:otherwise><xsl:value-of select="$channel/link"/></xsl:otherwise>
    </xsl:choose>
  </xsl:variable>
  <xsl:variable name="self-bare" select="substring-after($self, '://')"/>
  <xsl:variable name="cover">
    <xsl:choose>
      <xsl:when test="$channel/itunes:image/@href"><xsl:value-of select="$channel/itunes:image/@href"/></xsl:when>
      <xsl:otherwise><xsl:value-of select="$channel/image/url"/></xsl:otherwise>
    </xsl:choose>
  </xsl:variable>

  <xsl:template match="/">
    <html>
      <xsl:attribute name="lang">
        <xsl:choose>
          <xsl:when test="$channel/language"><xsl:value-of select="$channel/language"/></xsl:when>
          <xsl:otherwise>en</xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>
      <head>
        <meta charset="utf-8"/>
        <meta name="viewport" content="width=device-width, initial-scale=1"/>
        <meta name="robots" content="noindex, follow"/>
        <title>
          <xsl:choose>
            <xsl:when test="$is-podcast">Podcast feed: </xsl:when>
            <xsl:otherwise>RSS feed: </xsl:otherwise>
          </xsl:choose>
          <xsl:value-of select="$channel/title"/>
        </title>
        <link rel="stylesheet" href="{$assets}css/feed.css{$ver}"/>
        <script src="{$assets}js/feed.js{$ver}" defer="defer"></script>
      </head>
      <body>
        <a class="skip" href="#latest">Skip to the latest <xsl:choose><xsl:when test="$is-podcast">episodes</xsl:when><xsl:otherwise>stories</xsl:otherwise></xsl:choose></a>
        <header class="bar">
          <div class="wrap bar__inner">
            <a class="brand" href="{$channel/link}"><span class="brand__mark" aria-hidden="true">HR</span><span>Headline Recorder</span></a>
            <a class="bar__back" href="{$channel/link}">
              <xsl:choose>
                <xsl:when test="$is-podcast">Go to the Podcast page</xsl:when>
                <xsl:otherwise>Go to the website</xsl:otherwise>
              </xsl:choose>
            </a>
          </div>
        </header>

        <main>
          <section class="intro">
            <div class="wrap intro__inner">
              <xsl:if test="$is-podcast and string($cover)">
                <img class="intro__cover" src="{$cover}" alt="" width="160" height="160"/>
              </xsl:if>
              <div class="intro__copy">
                <p class="eyebrow">
                  <xsl:choose>
                    <xsl:when test="$is-podcast">Podcast feed</xsl:when>
                    <xsl:otherwise>RSS news feed</xsl:otherwise>
                  </xsl:choose>
                </p>
                <h1><xsl:value-of select="$channel/title"/></h1>
                <p class="lede">
                  <xsl:choose>
                    <xsl:when test="$is-podcast">This is the Headline Recorder Podcast feed. Paste this link into your podcast app to get every new episode automatically.</xsl:when>
                    <xsl:otherwise>This is a Headline Recorder news feed. Paste this link into a news reader app to get every new story automatically, without visiting the website.</xsl:otherwise>
                  </xsl:choose>
                </p>
                <xsl:if test="string($channel/description)">
                  <p class="desc"><xsl:value-of select="$channel/description"/></p>
                </xsl:if>
              </div>
            </div>
          </section>

          <section class="wrap panel" aria-labelledby="link-title">
            <h2 id="link-title" class="panel__title">
              <xsl:choose>
                <xsl:when test="$is-podcast">1. Copy the podcast link</xsl:when>
                <xsl:otherwise>1. Copy the feed link</xsl:otherwise>
              </xsl:choose>
            </h2>
            <div class="copy">
              <label class="sr" for="feed-url">Feed link</label>
              <input id="feed-url" class="copy__input" type="text" readonly="readonly" value="{$self}" spellcheck="false" data-feed-url=""/>
              <button type="button" class="btn" data-copy="feed-url" hidden="hidden">Copy link</button>
            </div>
            <p class="hint" aria-live="polite" data-copy-status="">Select the link and copy it, then paste it into your app.</p>

            <h2 class="panel__title">2. Paste it into your app</h2>
            <xsl:choose>
              <xsl:when test="$is-podcast">
                <ul class="apps">
                  <li class="app">
                    <h3>Apple Podcasts</h3>
                    <p>On iPhone or iPad: open the Library tab, tap the More button (three dots), choose <strong>Follow a Show by URL</strong> and paste the link. On a Mac: choose File, then Follow a Show by URL.</p>
                    <a class="btn btn--ghost" href="podcast://{$self-bare}">Open in Apple Podcasts</a>
                  </li>
                  <li class="app">
                    <h3>Pocket Casts</h3>
                    <p>Open the Discover tab, paste the link into the search box and tap <strong>Subscribe</strong>.</p>
                    <a class="btn btn--ghost" href="pktc://subscribe/{$self-bare}">Open in Pocket Casts</a>
                  </li>
                  <li class="app">
                    <h3>Spotify</h3>
                    <p>Spotify does not let listeners add a show by link. Search for <strong>Headline Recorder Podcast</strong> in Spotify; if it is not listed there, use any of the other apps on this page.</p>
                  </li>
                  <li class="app">
                    <h3>Any other podcast app</h3>
                    <p>Overcast, Castbox, AntennaPod, Podcast Addict and most others have an option called <strong>Add podcast by URL</strong> or <strong>Add RSS feed</strong>. Paste the link there.</p>
                  </li>
                </ul>
              </xsl:when>
              <xsl:otherwise>
                <ul class="apps">
                  <li class="app">
                    <h3>Feedly or Inoreader</h3>
                    <p>Choose <strong>Follow</strong> or <strong>Add content</strong>, paste the link and confirm.</p>
                  </li>
                  <li class="app">
                    <h3>NetNewsWire, Reeder or Feeder</h3>
                    <p>Use the <strong>+</strong> button or <strong>New Feed</strong>, then paste the link.</p>
                  </li>
                  <li class="app">
                    <h3>Outlook or Thunderbird</h3>
                    <p>Outlook: right-click <strong>RSS Subscriptions</strong> and choose Add a New RSS Feed. Thunderbird: create a Feeds account and add the link.</p>
                  </li>
                  <li class="app">
                    <h3>Any other news reader</h3>
                    <p>Look for <strong>Add feed</strong> or <strong>Subscribe by URL</strong> and paste the link. New stories then arrive in the app as soon as they are published.</p>
                  </li>
                </ul>
              </xsl:otherwise>
            </xsl:choose>
          </section>

          <section id="latest" class="wrap latest" aria-labelledby="latest-title">
            <h2 id="latest-title" class="latest__title">
              <xsl:choose>
                <xsl:when test="$is-podcast">Latest episodes</xsl:when>
                <xsl:otherwise>Latest stories</xsl:otherwise>
              </xsl:choose>
            </h2>
            <xsl:choose>
              <xsl:when test="$channel/item">
                <ol class="items">
                  <xsl:for-each select="$channel/item[position() &lt;= 20]">
                    <li class="item">
                      <xsl:if test="media:content[@medium='image']/@url">
                        <img class="item__img" src="{media:content[@medium='image'][1]/@url}" alt="" loading="lazy" width="160" height="90"/>
                      </xsl:if>
                      <div class="item__body">
                        <h3 class="item__title"><a href="{link}"><xsl:value-of select="title"/></a></h3>
                        <p class="item__meta">
                          <xsl:if test="pubDate"><time><xsl:value-of select="substring(pubDate, 6, 11)"/></time></xsl:if>
                          <xsl:if test="itunes:duration and number(itunes:duration) &gt; 0">
                            <span><xsl:value-of select="round(number(itunes:duration) div 60) + number(number(itunes:duration) &lt; 60)"/> min</span>
                          </xsl:if>
                          <xsl:if test="category[1] and not($is-podcast)"><span><xsl:value-of select="category[1]"/></span></xsl:if>
                        </p>
                        <xsl:variable name="plain">
                          <xsl:call-template name="strip-tags"><xsl:with-param name="text" select="description"/></xsl:call-template>
                        </xsl:variable>
                        <xsl:if test="string-length(normalize-space($plain)) &gt; 0">
                          <p class="item__desc" data-decode="">
                            <xsl:choose>
                              <xsl:when test="string-length(normalize-space($plain)) &gt; 900"><xsl:value-of select="substring(normalize-space($plain), 1, 897)"/>...</xsl:when>
                              <xsl:otherwise><xsl:value-of select="normalize-space($plain)"/></xsl:otherwise>
                            </xsl:choose>
                          </p>
                        </xsl:if>
                        <xsl:if test="$is-podcast">
                          <a class="item__more" href="{link}">Listen on the website</a>
                        </xsl:if>
                      </div>
                    </li>
                  </xsl:for-each>
                </ol>
              </xsl:when>
              <xsl:otherwise>
                <p class="empty">Nothing has been published in this feed yet. Follow it now and new items will arrive in your app automatically.</p>
              </xsl:otherwise>
            </xsl:choose>
          </section>
        </main>

        <footer class="foot">
          <div class="wrap">
            <p>Headline Recorder: Pakistan business news, economy, markets and policy. <a href="{$channel/link}">Visit the website</a>.</p>
            <p class="foot__small">You are seeing this page because the feed link was opened in a web browser. Apps read the same link as a machine-readable RSS feed.</p>
          </div>
        </footer>
      </body>
    </html>
  </xsl:template>

  <!-- Removes HTML tags from an item description (the feed carries it as escaped HTML). -->
  <xsl:template name="strip-tags">
    <xsl:param name="text"/>
    <xsl:choose>
      <xsl:when test="contains($text, '&lt;')">
        <xsl:value-of select="substring-before($text, '&lt;')"/>
        <xsl:text> </xsl:text>
        <xsl:call-template name="strip-tags">
          <xsl:with-param name="text" select="substring-after(substring-after($text, '&lt;'), '&gt;')"/>
        </xsl:call-template>
      </xsl:when>
      <xsl:otherwise><xsl:value-of select="$text"/></xsl:otherwise>
    </xsl:choose>
  </xsl:template>
</xsl:stylesheet>
